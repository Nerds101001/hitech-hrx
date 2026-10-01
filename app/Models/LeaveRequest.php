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
    'half_day_session',
    // Sent by Api\LeaveController::createLeaveRequest(); without them a
    // short leave from the app lost its flag and times on the way in.
    'is_short_leave',
    'duration_hours',
    'start_time',
    'end_time'
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

  /**
   * The approved half-day leave for the OTHER half of this same single date
   * (this is the first half, that the second - or the reverse). Someone may
   * take one half and later the other; together the two make a full day, and
   * attendance, payroll and CCare must read them as one day off, not two
   * separate half days each half-worked.
   */
  public function otherHalf(array $statuses = [LeaveRequestStatus::APPROVED]): ?self
  {
    if (!$this->is_half_day || !in_array($this->half_day_session, ['first_half', 'second_half'], true)
        || !$this->from_date || !$this->to_date || !$this->from_date->isSameDay($this->to_date)) {
      return null;
    }

    return static::where('user_id', $this->user_id)
      ->where('id', '!=', $this->id)
      ->whereIn('status', $statuses)
      ->where('is_half_day', true)
      ->where('half_day_session', $this->half_day_session === 'first_half' ? 'second_half' : 'first_half')
      ->whereDate('from_date', $this->from_date->toDateString())
      ->whereDate('to_date', $this->to_date->toDateString())
      ->with('leaveType')
      ->first();
  }

  /** Whether this half-day is paid: WFH is a worked (paid) half; otherwise a paid type not turned into LWP. */
  public function halfIsPaid(): bool
  {
    if (strtoupper((string) ($this->leaveType->code ?? '')) === 'WFH') {
      return true;
    }

    return (bool) ($this->leaveType->is_paid ?? false) && !($this->is_lwp && (float) $this->lwp_days >= 0.5);
  }

  /** The attendance label for a day both halves of which are on leave, from whether each half is paid. */
  public static function bothHalvesStatus(bool $aPaid, bool $bPaid): string
  {
    $paid = (int) $aPaid + (int) $bPaid;

    return $paid === 2 ? 'leave' : ($paid === 0 ? 'unpaid_leave' : 'half-Day');
  }

  /** The attendance label a single approved half-day leave writes (as processApproval does). */
  public function ownHalfStatus(): string
  {
    if (strtoupper((string) ($this->leaveType->code ?? '')) === 'WFH') {
      return 'work_from_home';
    }

    return $this->halfIsPaid() || !($this->leaveType->is_paid ?? false) ? 'half-Day' : 'unpaid_leave';
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

                  // CCare's register already took this day from the balance (an
                  // absence it auto-adjusted before this leave was approved):
                  // the day is paid, but must not be taken a second time - it
                  // was, for 5 days across 4 people by 30 Sep 2026. Cancelling
                  // still refunds it, which undoes CCare's deduction along with
                  // the row the cancellation deletes.
                  $takenByRegister = !$leaveRequest->is_short_leave && str_contains(
                      (string) Attendance::where('user_id', $leaveRequest->user_id)->whereDate('check_in_time', $tempDate)->value('admin_reason'),
                      'Auto-adjusted against Earned Leave'
                  );

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
                      // Was missing the balance-deduction half of what the
                      // short-leave and full-day branches on either side of
                      // this one already do — a half-day leave picked its
                      // attendance label and stopped there, never touching
                      // $balance or $balanceChecked. So it never actually
                      // deducted anything, and is_lwp (set below, only when
                      // $balanceChecked) stayed frozen at whatever it was at
                      // submission time forever, even for a leave type that
                      // is genuinely paid. Same 0.5-day mechanics as any
                      // other half-day request now — only the attendance
                      // label differs, so WFH still renders as "WFH"
                      // everywhere instead of "Half Day" (owner spec), and
                      // never draws from a balance pool same as full-day WFH.
                      if (strtoupper($leaveTypeCode) === 'WFH') {
                          $attendanceStatus = 'work_from_home';
                      } elseif ($isPaidType && !in_array($leaveTypeCode, $excludedPoolCodes)) {
                          $balanceChecked = true;
                          $availableLegacy = $legacyBalance ? ((float)$legacyBalance->balance - (float)$legacyBalance->used) : 0.0;

                          if ($takenByRegister) {
                              $paidDays += $decrement;
                              $attendanceStatus = 'half-Day';
                          } elseif ($availableBalance >= $decrement) {
                              $balance->used += $decrement;
                              $paidDays += $decrement;
                              $attendanceStatus = 'half-Day';
                          } elseif ($legacyBalance && $availableLegacy >= $decrement) {
                              $legacyBalance->used += $decrement;
                              $paidDays += $decrement;
                              $attendanceStatus = 'half-Day';
                          } else {
                              $lwpDays += $decrement;
                              $attendanceStatus = 'unpaid_leave';
                          }
                      } else {
                          $attendanceStatus = 'half-Day';
                      }
                  } else {
                      if ($isPaidType && !in_array($leaveTypeCode, $excludedPoolCodes)) {
                          $balanceChecked = true;
                          $availableLegacy = $legacyBalance ? ((float)$legacyBalance->balance - (float)$legacyBalance->used) : 0.0;

                          if ($takenByRegister) {
                              $paidDays += $decrement;
                              $attendanceStatus = 'leave';
                          } elseif ($availableBalance >= $decrement) {
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
                      // Keep a real punch's own status: someone who actually worked
                      // must not have that erased by a leave label. But 'absent' is
                      // the one status an approved leave exists to explain, and an
                      // admin-marked absence carries a clock time too (placeholder
                      // shift hours, working_hours 0), so this test preserved it --
                      // leaving the employee unpaid for the day AND carrying an
                      // absence penalty on a day HR had approved. An approved leave
                      // always outranks an absence.
                      $existingStatus = strtolower((string) $attendance->status);
                      $isRealPunch = $attendance->check_in_time
                          && $attendance->check_in_time->format('H:i') !== '00:00';
                      $absenceLike = in_array($existingStatus, ['absent', 'miss-punch', 'miss_punch'], true);

                      // The other half of this date already on leave: the day is
                      // now wholly off, so label it for both halves instead of
                      // overwriting the first half's label with this one's.
                      $otherHalf = $leaveRequest->is_half_day ? $leaveRequest->otherHalf() : null;
                      if ($otherHalf && !($isRealPunch && !$absenceLike)) {
                          // This half's own lwp flag is only saved after this loop,
                          // so its paid-ness is read off the label just worked out.
                          $thisPaid = $attendanceStatus === 'work_from_home'
                              || ($isPaidType && $attendanceStatus !== 'unpaid_leave');
                          $attendanceStatus = self::bothHalvesStatus($thisPaid, $otherHalf->halfIsPaid());
                      }

                      $attendance->update([
                          'leave_request_id' => $leaveRequest->id,
                          'status'           => ($isRealPunch && !$absenceLike) ? $attendance->status : $attendanceStatus,
                      ]);
                  } elseif (($leaveRequest->is_short_leave || $leaveRequest->leaveType->is_short_leave)
                      && \App\Models\CcSalespersonMap::where('role', 'ccare')->where('sales_user_id', $leaveRequest->user_id)->exists()) {
                      // A salesperson on the CCare register does not punch, so
                      // this placeholder was the only row for the day -- and its
                      // 'present' made an approved short leave a full Present
                      // day on its own, however long it ran. Their day is left
                      // for CCare, who sees the short leave on the register and
                      // reports whether the rest of the day was worked.
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

              // The other half of this date still approved: the day is still half
              // on leave. Hand its row back to that half (with that half's own
              // label) instead of deleting it, which dropped the surviving half.
              $otherHalf = $leaveRequest->is_half_day ? $leaveRequest->otherHalf() : null;
              if ($otherHalf) {
                  foreach (Attendance::where('user_id', $leaveRequest->user_id)
                      ->whereDate('check_in_time', $leaveRequest->from_date->toDateString())->get() as $row) {
                      $isPlaceholder = !$row->check_in_time || $row->check_in_time->format('H:i') === '00:00';
                      $row->update([
                          'leave_request_id' => $otherHalf->id,
                          'status'           => $isPlaceholder ? $otherHalf->ownHalfStatus() : $row->status,
                      ]);
                  }
                  return;
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
