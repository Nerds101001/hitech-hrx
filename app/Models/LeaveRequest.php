<?php

namespace App\Models;

use App\Enums\LeaveRequestStatus;
use App\Models\LeaveBalance;
use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class LeaveRequest extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'leave_requests';

  protected $fillable = [
    'from_date',
    'to_date',
    'user_id',
    'leave_type_id',
    'document',
    'user_notes',
    'approved_by_id',
    'rejected_by_id',
    'approved_at',
    'rejected_at',
    'status',
    'approval_notes',
    'notes',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
    'cancel_reason',
    'cancelled_at'
  ];

  protected $casts = [
    'status' => LeaveRequestStatus::class,
    'from_date' => 'date:d-m-Y',
    'to_date' => 'date:d-m-Y',
    'approved_at' => 'datetime',
    'rejected_at' => 'datetime',
    'cancelled_at' => 'datetime'
  ];

  public function user()
  {
    return $this->belongsTo(User::class, 'user_id');
  }

  public function approvedBy()
  {
    return $this->belongsTo(User::class, 'approved_by_id');
  }

  public function leaveType()
  {
    return $this->belongsTo(LeaveType::class, 'leave_type_id');
  }

  protected static function booted()
  {
      static::updated(function ($leaveRequest) {
          // If the status changed to APPROVED
          if ($leaveRequest->wasChanged('status') && $leaveRequest->status === LeaveRequestStatus::APPROVED) {
              $user = $leaveRequest->user;
              $isPaidType = $leaveRequest->leaveType->is_paid;
              $plType = \App\Models\LeaveType::where('code', 'PL')->first();
              
              // Find the balance record to use (defaults to PL for all paid types)
              $deductLeaveTypeId = $leaveRequest->leave_type_id;
              if ($isPaidType && $plType) {
                  $deductLeaveTypeId = $plType->id;
              }

              $balance = LeaveBalance::firstOrCreate([
                  'user_id'       => $user->id,
                  'leave_type_id' => $deductLeaveTypeId,
                  'tenant_id'     => $leaveRequest->tenant_id,
              ]);

              $tempDate = $leaveRequest->from_date->copy();
              $toDate = $leaveRequest->to_date;
              $workingDayCounter = 0;

              while ($tempDate->lte($toDate)) {
                  // Only process working days for balance deduction
                  if (\App\Services\LeavePolicyService::isWorkingDay($user, $tempDate)) {
                      $status = 'on_leave'; // Default fallback
                      
                      // Handle Paid vs Unpaid split
                      if ($isPaidType) {
                          $availableBalance = $balance->balance - $balance->used;
                          if ($availableBalance >= 1) {
                              $balance->used += 1;
                              $status = 'paid_leave';
                          } else {
                              $status = 'unpaid_leave';
                          }
                      } else {
                          // For explicitly non-paid types, just mark as on_leave/unpaid
                          $status = 'unpaid_leave';
                      }

                      Attendance::updateOrCreate(
                          [
                              'user_id' => $leaveRequest->user_id,
                              'tenant_id' => $leaveRequest->tenant_id,
                              'check_in_time' => $tempDate->startOfDay(),
                          ],
                          [
                              'status' => $status,
                              'shift_id' => $user->shift_id,
                              'leave_request_id' => $leaveRequest->id,
                              'created_by_id' => auth()->id() ?? $user->id,
                          ]
                      );
                  }
                  $tempDate->addDay();
              }
              $balance->save();
          }
          
          // If status was approved but now is CANCELLED or REJECTED
          if ($leaveRequest->wasChanged('status') && 
              $leaveRequest->getOriginal('status') === LeaveRequestStatus::APPROVED &&
              ($leaveRequest->status === LeaveRequestStatus::CANCELLED || $leaveRequest->status === LeaveRequestStatus::REJECTED)) {
              
              $duration = 0;
              if ($leaveRequest->is_short_leave && $leaveRequest->duration_hours) {
                  $duration = $leaveRequest->duration_hours / 8;
              } else {
                  $duration = \App\Services\LeavePolicyService::calculateWorkingDays($leaveRequest->user, $leaveRequest->leave_type_id, $leaveRequest->from_date->toDateString(), $leaveRequest->to_date->toDateString());
              }
              
              $balance = LeaveBalance::where('user_id', $leaveRequest->user_id)
                  ->where('leave_type_id', $leaveRequest->leave_type_id)
                  ->first();
              
              if ($balance) {
                  $balance->used = max(0, $balance->used - $duration);
                  $balance->save();
              }

              // Delete auto-created Attendance records
              Attendance::where('user_id', $leaveRequest->user_id)
                  ->whereBetween('check_in_time', [
                      $leaveRequest->from_date->startOfDay(), 
                      $leaveRequest->to_date->endOfDay()
                  ])
                  ->whereIn('status', ['on_leave', 'work_from_home'])
                  ->delete();
          }
      });
  }
}
