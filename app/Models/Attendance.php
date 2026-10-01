<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Attendance extends Model implements AuditableContract
{
  use UserActionsTrait, TenantTrait, SoftDeletes, Auditable;

  public const STATUS_PRESENT  = 'Present';
  public const STATUS_HALF_DAY = 'Half-Day';
  public const STATUS_ABSENT   = 'Absent';
  public const STATUS_ON_LEAVE = 'On-Leave';

  protected $table = 'attendances';

  protected $fillable = [
    'user_id',
    'check_in_time',
    'check_out_time',
    'late_reason',
    'shift_id',
    'early_checkout_reason',
    'status',
    'site_id',
    'approved_by_id',
    'approved_at',
    'created_by_id',
    'updated_by_id',
    'admin_reason',
    'notes',
    'attachment',
    'is_policy_late',
    'leave_request_id',
    'is_outdoor_duty',
    'outdoor_duty_id',
    'tenant_id',
  ];

  protected $casts = [
    'check_in_time' => 'datetime',
    'check_out_time' => 'datetime',
    'approved_at' => 'datetime',
    'is_policy_late'  => 'boolean',
    'is_outdoor_duty' => 'boolean',
    'created_at'      => 'datetime',
  ];

  public function user()
  {
    return $this->belongsTo(User::class);
  }

  public function leaveRequest()
  {
    return $this->belongsTo(LeaveRequest::class);
  }

  public function outdoorDuty()
  {
    return $this->belongsTo(\App\Models\OutdoorDuty::class);
  }

  public function shift()
  {
    return $this->belongsTo(Shift::class);
  }

  public function activities()
  {
    return $this->hasMany(Activity::class);
  }

  public function visits()
  {
    return $this->hasMany(Visit::class);
  }

  public function latestAttendanceLog()
  {
    return $this->attendanceLogs()->latest()->first();
  }

  public function attendanceLogs()
  {
    return $this->hasMany(AttendanceLog::class);
  }

  public function todaysLatestAttendanceLog()
  {
    return $this->attendanceLogs()
      ->whereDate('created_at', now()->toDateString())
      ->latest()
      ->first();
  }

  public function isCheckedOut(): bool
  {
    $checkOut = $this->attendanceLogs()
      ->orderBy('created_at', 'desc')
      ->first();

    return $checkOut && $checkOut->type === 'check_out';
  }

  public function attendanceBreaks()
  {
    return $this->hasMany(AttendanceBreak::class);
  }

  public function updatedBy()
  {
    return $this->belongsTo(User::class, 'updated_by_id');
  }

  public function createdBy()
  {
    return $this->belongsTo(User::class, 'created_by_id');
  }

  /**
   * Off-day (Sunday/holiday/OFF-Saturday) comp-off credits standing for this
   * person and day, from the balance's own audit trail: "Earned via … Working
   * on d-m-Y" less "… Working Credit Reversed … on d-m-Y".
   */
  public static function offDayCreditStanding(int $userId, int $leaveTypeId, $day): int
  {
    $balanceId = \App\Models\LeaveBalance::where('user_id', $userId)->where('leave_type_id', $leaveTypeId)->value('id');
    if (!$balanceId) {
      return 0;
    }
    $on = ' on ' . \Carbon\Carbon::parse($day)->format('d-m-Y');
    $trail = \OwenIt\Auditing\Models\Audit::where('auditable_type', \App\Models\LeaveBalance::class)->where('auditable_id', $balanceId);

    return (clone $trail)->where('user_agent', 'like', 'Earned via % Working' . $on)->count()
      - (clone $trail)->where('user_agent', 'like', '% Working Credit Reversed%' . $on)->count();
  }

  protected static function booted()
  {
      static::saved(function ($attendance) {
          $checkIn = $attendance->check_in_time;
          if (!$checkIn) {
              return;
          }
          $user = $attendance->user;
          if (!$user) return;

          $isSunday = $checkIn->isSunday();
          // Site-aware: a holiday tagged to another location is a normal
          // working day for this employee, so it must not earn a comp-off.
          $isHoliday = \App\Models\Holiday::appliesTo($user, $checkIn);
          // An OFF Saturday (per the employee's leave policy profile, or their
          // site as fallback) earns a comp-off exactly like a Sunday. Working
          // Saturdays are normal days and earn nothing.
          $isOffSaturday = $checkIn->isSaturday()
              && \App\Services\LeavePolicyService::saturdayOffState($user, $checkIn) === true;
          if (!$isSunday && !$isHoliday && !$isOffSaturday) {
              return;
          }

          // Prefer the ACTIVE canonical ALP pool. The old orWhere chain had no
          // ordering, so first() returned the lowest id — the *inactive* legacy
          // 'PL' type — and every Sunday/holiday credit was banked into a pool
          // employees cannot spend.
          $compOffType = \App\Models\LeaveType::where('code', 'ALP')->first()
              ?: \App\Models\LeaveType::where('name', 'like', '%All Purpose%')->first()
              ?: \App\Models\LeaveType::where('code', 'PL')->first();

          if (!$compOffType) return;

          $checkOut = $attendance->check_out_time;
          $hasFourHours = false;

          if ($checkOut) {
              $mins = \App\Services\AttendanceDayStatus::workedMinutes($checkIn, $checkOut);   // punches may be stored the other way round
              // 3h55m, not a strict 4h: see AttendanceDayStatus::OFFDAY_CREDIT_MINUTES.
              // (The flag keeps its old name; it now means "worked enough for the credit".)
              if ($mins >= \App\Services\AttendanceDayStatus::OFFDAY_CREDIT_MINUTES) {
                  $hasFourHours = true;
              }
          }

          $dayName = $isSunday ? "Sunday" : ($isHoliday ? "Holiday" : "Saturday");
          $reasonStr = $dayName . " Working Credit (Auto-credited)";
          
          // Check if either Sunday or Holiday credit has already been applied
          $hasStamp = $attendance->admin_reason && (
              str_contains($attendance->admin_reason, "Sunday Working Credit") ||
              str_contains($attendance->admin_reason, "Holiday Working Credit") ||
              str_contains($attendance->admin_reason, "Saturday Working Credit")
          );
          // The stamp alone is not proof: CCare's register rewrites
          // admin_reason on every save, wiping it, and the next save credited
          // the day again (7 days across 6 people by 30 Sep 2026). The
          // balance's own trail says whether a credit for the day stands.
          $standing = self::offDayCreditStanding($user->id, $compOffType->id, $checkIn);
          $hasAlreadyCredited = $hasStamp || $standing > 0;

          if ($hasFourHours && $standing > 0 && !$hasStamp) {
              // Credited already, stamp wiped: put it back, credit nothing.
              $attendance->withoutEvents(function () use ($attendance, $reasonStr) {
                  $attendance->updateQuietly(['admin_reason' => $attendance->admin_reason ? $attendance->admin_reason . " | " . $reasonStr : $reasonStr]);
              });
          }

          if ($hasFourHours) {
              if (!$hasAlreadyCredited) {
                  // Credit 1.0 day to balance
                  $balance = \App\Models\LeaveBalance::firstOrCreate(
                      ['user_id' => $user->id, 'leave_type_id' => $compOffType->id],
                      ['tenant_id' => $user->tenant_id ?? $attendance->tenant_id, 'balance' => 0, 'used' => 0, 'accrued_this_year' => 0]
                  );

                  \App\Models\LeaveBalance::$auditReason = "Earned via " . $dayName . " Working on " . $checkIn->format('d-m-Y');
                  $balance->increment('balance', 1);
                  $balance->increment('accrued_this_year', 1);

                  // Update attendance record without triggering events again
                  $attendance->withoutEvents(function () use ($attendance, $reasonStr) {
                      $currentReason = $attendance->admin_reason;
                      $newReason = $currentReason ? $currentReason . " | " . $reasonStr : $reasonStr;
                      $attendance->updateQuietly(['admin_reason' => $newReason]);
                  });
              }
          } else {
              if ($hasAlreadyCredited) {
                  // Reverse/debit 1.0 day
                  $balance = \App\Models\LeaveBalance::where('user_id', $user->id)
                      ->where('leave_type_id', $compOffType->id)
                      ->first();

                  if ($balance) {
                      \App\Models\LeaveBalance::$auditReason = $dayName . " Working Credit Reversed on " . $checkIn->format('d-m-Y');
                      $balance->decrement('balance', 1);
                      $balance->decrement('accrued_this_year', 1);
                  }

                  $attendance->withoutEvents(function () use ($attendance) {
                      $currentReason = $attendance->admin_reason;
                      // Remove both possible credit strings
                      $cleanReason = str_replace(
                          ["Sunday Working Credit (Auto-credited)", "Holiday Working Credit (Auto-credited)", "Saturday Working Credit (Auto-credited)", " | "],
                          ["", "", "", " "],
                          $currentReason
                      );
                      $cleanReason = trim(preg_replace('/\s+/', ' ', $cleanReason));
                      $attendance->updateQuietly(['admin_reason' => $cleanReason ?: null]);
                  });
              }
          }
      });

      static::deleted(function ($attendance) {
          $checkIn = $attendance->check_in_time;
          if (!$checkIn) {
              return;
          }
          $user = $attendance->user;
          if (!$user) return;

          $isSunday = $checkIn->isSunday();
          // Site-aware: a holiday tagged to another location is a normal
          // working day for this employee, so it must not earn a comp-off.
          $isHoliday = \App\Models\Holiday::appliesTo($user, $checkIn);
          // An OFF Saturday (per the employee's leave policy profile, or their
          // site as fallback) earns a comp-off exactly like a Sunday. Working
          // Saturdays are normal days and earn nothing.
          $isOffSaturday = $checkIn->isSaturday()
              && \App\Services\LeavePolicyService::saturdayOffState($user, $checkIn) === true;
          if (!$isSunday && !$isHoliday && !$isOffSaturday) {
              return;
          }

          // Prefer the ACTIVE canonical ALP pool. The old orWhere chain had no
          // ordering, so first() returned the lowest id — the *inactive* legacy
          // 'PL' type — and every Sunday/holiday credit was banked into a pool
          // employees cannot spend.
          $compOffType = \App\Models\LeaveType::where('code', 'ALP')->first()
              ?: \App\Models\LeaveType::where('name', 'like', '%All Purpose%')->first()
              ?: \App\Models\LeaveType::where('code', 'PL')->first();

          if (!$compOffType) return;

          $dayName = $isSunday ? "Sunday" : ($isHoliday ? "Holiday" : "Saturday");
          $hasAlreadyCredited = ($attendance->admin_reason && (
              str_contains($attendance->admin_reason, "Sunday Working Credit") ||
              str_contains($attendance->admin_reason, "Holiday Working Credit") ||
              str_contains($attendance->admin_reason, "Saturday Working Credit")
          )) || self::offDayCreditStanding($user->id, $compOffType->id, $checkIn) > 0;   // stamp may have been wiped

          if ($hasAlreadyCredited) {
              $balance = \App\Models\LeaveBalance::where('user_id', $user->id)
                  ->where('leave_type_id', $compOffType->id)
                  ->first();

              if ($balance) {
                  \App\Models\LeaveBalance::$auditReason = $dayName . " Working Credit Reversed (Attendance Deleted) on " . $checkIn->format('d-m-Y');
                  $balance->decrement('balance', 1);
                  $balance->decrement('accrued_this_year', 1);
              }
          }
      });
  }
}
