<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
  use UserActionsTrait, TenantTrait, SoftDeletes;

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
              $mins = $checkIn->diffInMinutes($checkOut);
              // 3h55m, not a strict 4h: see AttendanceDayStatus::OFFDAY_CREDIT_MINUTES.
              // (The flag keeps its old name; it now means "worked enough for the credit".)
              if ($mins >= \App\Services\AttendanceDayStatus::OFFDAY_CREDIT_MINUTES) {
                  $hasFourHours = true;
              }
          }

          $dayName = $isSunday ? "Sunday" : ($isHoliday ? "Holiday" : "Saturday");
          $reasonStr = $dayName . " Working Credit (Auto-credited)";
          
          // Check if either Sunday or Holiday credit has already been applied
          $hasAlreadyCredited = $attendance->admin_reason && (
              str_contains($attendance->admin_reason, "Sunday Working Credit") ||
              str_contains($attendance->admin_reason, "Holiday Working Credit") ||
              str_contains($attendance->admin_reason, "Saturday Working Credit")
          );

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
          $hasAlreadyCredited = $attendance->admin_reason && (
              str_contains($attendance->admin_reason, "Sunday Working Credit") ||
              str_contains($attendance->admin_reason, "Holiday Working Credit") ||
              str_contains($attendance->admin_reason, "Saturday Working Credit")
          );

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
