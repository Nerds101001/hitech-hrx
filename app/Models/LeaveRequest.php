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
    'is_lwp',
    'lwp_days',
    'approval_notes',
    'notes',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
    'cancel_reason',
    'cancelled_at',
    'is_half_day',
    'half_day_session'
  ];

  protected $casts = [
    'status'       => LeaveRequestStatus::class,
    'is_lwp'       => 'boolean',
    'is_half_day'  => 'boolean',
    'lwp_days'     => 'float',
    'from_date'    => 'date:d-m-Y',
    'to_date'      => 'date:d-m-Y',
    'approved_at'  => 'datetime',
    'rejected_at'  => 'datetime',
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

          // ---------------------------------------------------------------
          // ON APPROVAL: deduct balance + create attendance records
          // ---------------------------------------------------------------
          if ($leaveRequest->wasChanged('status') && $leaveRequest->status === LeaveRequestStatus::APPROVED) {
              $user        = $leaveRequest->user;
              $isPaidType  = $leaveRequest->leaveType->is_paid;
              $leaveTypeCode = $leaveRequest->leaveType->code;
              $plType      = \App\Models\LeaveType::where('code', 'PL')->first();
              $excludedPoolCodes = ['ML', 'MAT', 'PL_PAT', 'PAT', 'SHL'];

              // Determine which balance record to deduct from
              $deductLeaveTypeId = $leaveRequest->leave_type_id;
              if ($isPaidType && $plType && !in_array($leaveTypeCode, $excludedPoolCodes)) {
                  $deductLeaveTypeId = $plType->id;
              }

              $balance = LeaveBalance::firstOrCreate([
                  'user_id'       => $user->id,
                  'leave_type_id' => $deductLeaveTypeId,
                  'tenant_id'     => $leaveRequest->tenant_id,
              ]);

              $tempDate  = $leaveRequest->from_date->copy();
              $toDate    = $leaveRequest->to_date;
              $lwpDays   = 0.0;
              $paidDays  = 0.0;
              $decrement = $leaveRequest->is_half_day ? 0.5 : 1.0;

              while ($tempDate->lte($toDate)) {
                  if (\App\Services\LeavePolicyService::isWorkingDay($user, $tempDate)) {

                      $availableBalance = (float)$balance->balance - (float)$balance->used;

                      if ($isPaidType && !in_array($leaveTypeCode, $excludedPoolCodes)) {
                          if ($availableBalance >= $decrement) {
                              // Paid day — deduct from balance
                              $balance->used += $decrement;
                              $paidDays += $decrement;
                              $attendanceStatus = 'leave'; // paid leave
                          } else {
                              // No balance left — LWP day
                              $lwpDays += $decrement;
                              $attendanceStatus = 'unpaid_leave';
                          }
                      } else {
                          // Non-paid type (SL, CL etc.) or excluded — just mark leave
                          $attendanceStatus = 'leave';
                      }

                      Attendance::updateOrCreate(
                          [
                              'user_id'        => $leaveRequest->user_id,
                              'tenant_id'      => $leaveRequest->tenant_id,
                              'check_in_time'  => $tempDate->copy()->startOfDay(),
                          ],
                          [
                              'status'           => $attendanceStatus,
                              'shift_id'         => $user->shift_id,
                              'leave_request_id' => $leaveRequest->id,
                              'created_by_id'    => auth()->id() ?? $user->id,
                          ]
                      );
                  }
                  $tempDate->addDay();
              }

              $balance->save();

              // Update LWP flag on the leave request itself
              if ($lwpDays > 0) {
                  $leaveRequest->withoutEvents(function () use ($leaveRequest, $lwpDays) {
                      $leaveRequest->updateQuietly([
                          'is_lwp'   => true,
                          'lwp_days' => $lwpDays,
                      ]);
                  });
              }
          }

          // ---------------------------------------------------------------
          // ON CANCELLATION (post-approval): reverse balance + delete attendance
          // ---------------------------------------------------------------
          if ($leaveRequest->wasChanged('status') &&
              $leaveRequest->getOriginal('status') === LeaveRequestStatus::APPROVED &&
              ($leaveRequest->status === LeaveRequestStatus::CANCELLED || $leaveRequest->status === LeaveRequestStatus::REJECTED)) {

              $user          = $leaveRequest->user;
              $isPaidType    = $leaveRequest->leaveType->is_paid;
              $leaveTypeCode = $leaveRequest->leaveType->code;
              $plType        = \App\Models\LeaveType::where('code', 'PL')->first();
              $excludedPoolCodes = ['ML', 'MAT', 'PL_PAT', 'PAT', 'SHL'];

              $refundLeaveTypeId = $leaveRequest->leave_type_id;
              if ($isPaidType && $plType && !in_array($leaveTypeCode, $excludedPoolCodes)) {
                  $refundLeaveTypeId = $plType->id;
              }

              // Only refund the PAID days (not LWP days)
              $paidDaysToRefund = \App\Services\LeavePolicyService::calculateWorkingDays(
                  $user,
                  $leaveRequest->leave_type_id,
                  $leaveRequest->from_date->toDateString(),
                  $leaveRequest->to_date->toDateString(),
                  (bool)$leaveRequest->is_half_day
              ) - (float)($leaveRequest->lwp_days ?? 0);

              if ($paidDaysToRefund > 0) {
                  $balance = LeaveBalance::where('user_id', $leaveRequest->user_id)
                      ->where('leave_type_id', $refundLeaveTypeId)
                      ->first();

                  if ($balance) {
                      $balance->used = max(0, $balance->used - $paidDaysToRefund);
                      $balance->save();
                  }
              }

              // Delete auto-created attendance records for those dates
              Attendance::where('user_id', $leaveRequest->user_id)
                  ->whereBetween('check_in_time', [
                      $leaveRequest->from_date->startOfDay(),
                      $leaveRequest->to_date->endOfDay()
                  ])
                  ->whereIn('status', ['leave', 'paid_leave', 'unpaid_leave', 'on_leave'])
                  ->delete();
          }
      });
  }
}
