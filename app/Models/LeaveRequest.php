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
use App\Traits\HasApprovals;

class LeaveRequest extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes, HasApprovals;

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
      $processApproval = function ($leaveRequest) {
          $user        = $leaveRequest->user;
          $isPaidType  = $leaveRequest->leaveType->is_paid;
          $leaveTypeCode = $leaveRequest->leaveType->code;
          $plType      = \App\Models\LeaveType::where('code', 'ALP')->first();
          $legacyPaidType = \App\Models\LeaveType::where('code', 'PL')->first();
          // WFH never draws from any balance pool — it's always fully paid
          // via PayrollService directly, never via a balance deduction.
          $excludedPoolCodes = ['ML', 'MAT', 'PL_PAT', 'PAT', 'SHL', 'WFH'];

          // Determine which balance record to deduct from
          $deductLeaveTypeId = $leaveRequest->leave_type_id;
          $pooledIntoAlp = $isPaidType && $plType && !in_array($leaveTypeCode, $excludedPoolCodes);
          if ($pooledIntoAlp) {
              $deductLeaveTypeId = $plType->id;
          }

          $balance = LeaveBalance::firstOrCreate([
              'user_id'       => $user->id,
              'leave_type_id' => $deductLeaveTypeId,
              'tenant_id'     => $leaveRequest->tenant_id,
          ]);

          // Legacy "Paid Leave" (PL) balances left over from before the ALP
          // consolidation still hold real unused days for some employees
          // (the type is deactivated but old accrued balance wasn't drained).
          // Fall back to that pool when ALP itself is insufficient, instead
          // of marking the leave unpaid while earned leave sits stranded.
          $legacyBalance = ($pooledIntoAlp && $legacyPaidType)
              ? LeaveBalance::firstOrCreate([
                    'user_id'       => $user->id,
                    'leave_type_id' => $legacyPaidType->id,
                    'tenant_id'     => $leaveRequest->tenant_id,
                ])
              : null;

          $tempDate  = $leaveRequest->from_date->copy();
          $toDate    = $leaveRequest->to_date;
          $lwpDays   = 0.0;
          $paidDays  = 0.0;
          $balanceChecked = false;
          $decrement = $leaveRequest->is_half_day ? 0.5 : 1.0;

          while ($tempDate->lte($toDate)) {
              if (\App\Services\LeavePolicyService::isWorkingDay($user, $tempDate)) {

                  $availableBalance = (float)$balance->balance - (float)$balance->used;

                  if ($leaveRequest->is_short_leave) {
                      $attendanceStatus = 'present';
                      // Short Leave's paid/unpaid status used to be whatever was
                      // set at submission time and was never rechecked here at
                      // approval, so a short leave submitted when the balance was
                      // low stayed unpaid forever even after balance freed up.
                      if ($isPaidType) {
                          $balanceChecked = true;
                          if ($availableBalance >= $decrement) {
                              $balance->used += $decrement;
                              $paidDays += $decrement;
                          } else {
                              $lwpDays += $decrement;
                          }
                      }
                  } elseif ($leaveRequest->is_half_day) {
                      // Same 0.5-day mechanics as any other half-day request —
                      // only the attendance label differs, so WFH renders as
                      // "WFH" everywhere instead of "Half Day" (owner spec).
                      $attendanceStatus = (strtoupper($leaveTypeCode) === 'WFH') ? 'work_from_home' : 'half-Day';
                  } else {
                      if ($isPaidType && !in_array($leaveTypeCode, $excludedPoolCodes)) {
                          $balanceChecked = true;
                          $availableLegacy = $legacyBalance ? ((float)$legacyBalance->balance - (float)$legacyBalance->used) : 0.0;

                          if ($availableBalance >= $decrement) {
                              $balance->used += $decrement;
                              $paidDays += $decrement;
                              $attendanceStatus = 'leave';
                          } elseif ($legacyBalance && $availableLegacy >= $decrement) {
                              $legacyBalance->used += $decrement;
                              $paidDays += $decrement;
                              $attendanceStatus = 'leave';
                          } else {
                              $lwpDays += $decrement;
                              $attendanceStatus = 'unpaid_leave';
                          }
                      } else {
                          $attendanceStatus = 'leave';
                      }
                  }

                  $attendance = Attendance::where('user_id', $leaveRequest->user_id)
                      ->whereDate('check_in_time', $tempDate)
                      ->first();

                  if ($attendance) {
                      $attendance->update([
                          'leave_request_id' => $leaveRequest->id,
                          'status'           => ($attendance->check_in_time && $attendance->check_in_time->format('H:i') !== '00:00') ? $attendance->status : $attendanceStatus,
                      ]);
                  } else {
                      Attendance::create([
                          'user_id'          => $leaveRequest->user_id,
                          'tenant_id'        => $leaveRequest->tenant_id,
                          'check_in_time'    => $tempDate->copy()->startOfDay(),
                          'status'           => $attendanceStatus,
                          'shift_id'         => $user->shift_id,
                          'leave_request_id' => $leaveRequest->id,
                          'created_by_id'    => auth()->id() ?? $user->id,
                      ]);
                  }
              }
              $tempDate->addDay();
          }

          $balance->save();
          if ($legacyBalance) {
              $legacyBalance->save();
          }

          if ($balanceChecked) {
              $leaveRequest->withoutEvents(function () use ($leaveRequest, $lwpDays) {
                  $leaveRequest->updateQuietly([
                      'is_lwp'   => $lwpDays > 0,
                      'lwp_days' => $lwpDays,
                  ]);
              });
          }
      };

      static::created(function ($leaveRequest) use ($processApproval) {
          if ($leaveRequest->status === LeaveRequestStatus::APPROVED) {
              $processApproval($leaveRequest);
          }
      });

      static::updated(function ($leaveRequest) use ($processApproval) {
          // ---------------------------------------------------------------
          // ON APPROVAL: deduct balance + create attendance records
          // ---------------------------------------------------------------
          if ($leaveRequest->wasChanged('status') && $leaveRequest->status === LeaveRequestStatus::APPROVED) {
              $processApproval($leaveRequest);
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
              $plType        = \App\Models\LeaveType::where('code', 'ALP')->first();
              // WFH excluded here too: approval above never deducts it from
              // ALP, so refunding it into ALP on cancellation would credit
              // days that were never taken out — inflating the shared pool.
              $excludedPoolCodes = ['ML', 'MAT', 'PL_PAT', 'PAT', 'SHL', 'WFH'];

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
                  ->where(function ($q) use ($leaveRequest) {
                      $q->whereIn('status', ['leave', 'paid_leave', 'unpaid_leave', 'on_leave', 'half-Day', 'work_from_home'])
                        ->orWhere('leave_request_id', $leaveRequest->id);
                  })
                  ->delete();
          }
      });
  }
}
