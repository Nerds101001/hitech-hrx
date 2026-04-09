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
              $duration = 0;
              if ($leaveRequest->is_short_leave && $leaveRequest->duration_hours) {
                  $duration = $leaveRequest->duration_hours / 8; // Assuming 8-hour workday for balance deduction
              } else {
                  $duration = \App\Services\LeavePolicyService::calculateWorkingDays($leaveRequest->user, $leaveRequest->leave_type_id, $leaveRequest->from_date->toDateString(), $leaveRequest->to_date->toDateString());
              }
              
              $balance = LeaveBalance::firstOrCreate([
                  'user_id'       => $leaveRequest->user_id,
                  'leave_type_id' => $leaveRequest->leave_type_id,
                  'tenant_id'     => $leaveRequest->tenant_id,
              ]);
              
              $balance->used += $duration;
              $balance->save();

              // Create Attendance records for the leave period
              $tempDate = $leaveRequest->from_date->copy();
              $toDate = $leaveRequest->to_date;
              $workingDayCounter = 0;
              $code = $leaveRequest->leaveType->code;

              while ($tempDate->lte($toDate)) {
                  $isHoliday = \App\Models\Holiday::where(function($q) use ($leaveRequest) {
                      $q->whereNull('site_id')
                        ->orWhere('site_id', $leaveRequest->user->site_id);
                  })->where('date', $tempDate->toDateString())->exists();

                  // Check if it's weekend (assuming Saturday config is similar to calculateWorkingDays)
                  // For simplicity, let's just use the counter for actual applied days.
                  $status = 'on_leave';
                  
                  if (!$isHoliday) {
                      $isOffDay = false;
                      if ($tempDate->isSaturday()) {
                          // Standardize Check
                          $profile = $leaveRequest->user->leavePolicyProfile;
                          $satConfig = $profile ? ($profile->saturday_off_config ?? []) : ($leaveRequest->user->site->saturday_off_config ?? []);
                          if (in_array('all', (array)$satConfig)) {
                              $isOffDay = true;
                          } else {
                              $occurrence = ceil($tempDate->day / 7);
                              $isLast = ($tempDate->copy()->addWeek()->month != $tempDate->month);
                              if (in_array((string)$occurrence, (array)$satConfig) || ($isLast && in_array('last', (array)$satConfig))) {
                                  $isOffDay = true;
                              }
                          }
                      } else if ($tempDate->isSunday()) {
                          $isOffDay = true;
                      }

                      if (!$isOffDay) {
                          $workingDayCounter++;

                          if ($leaveRequest->leaveType->is_split_entitlement) {
                              // Fetch the rule to determine the off_days_entitlement limit
                              $rule = \App\Models\LeavePolicyProfileRule::where('profile_id', $leaveRequest->user->leave_policy_profile_id)
                                  ->where('leave_type_id', $leaveRequest->leaveType->id)
                                  ->first();
                                  
                              $offDaysLimit = $rule ? ($rule->off_days_entitlement ?? 0) : 0;

                              if ($workingDayCounter > $offDaysLimit) {
                                  $status = 'work_from_home';
                              }
                          }
                      } else {
                          // Keep as 'on_leave' or Mark as 'Holiday/Weekend'?
                          // Typically on_leave covers weekends as well if it's continuous.
                      }
                  } else {
                      // Holiday - let's stay 'on_leave' for continuous coverage or mark as holiday
                      // For now, on_leave is fine.
                  }

                  Attendance::updateOrCreate(
                      [
                          'user_id' => $leaveRequest->user_id,
                          'tenant_id' => $leaveRequest->tenant_id,
                          'check_in_time' => $tempDate->startOfDay(),
                      ],
                      [
                          'status' => $status,
                          'shift_id' => $leaveRequest->user->shift_id,
                          'created_by_id' => auth()->id() ?? $leaveRequest->user_id,
                      ]
                  );
                  $tempDate->addDay();
              }
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
