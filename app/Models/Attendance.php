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
   * Off-day (Sunday/holiday/OFF-Saturday) comp-off credit standing for this
   * person and day, from the balance's own audit trail: sum of "Earned via
   * … Working … on d-m-Y" amounts less "… Working Credit Reversed … on
   * d-m-Y" amounts. The amount is the number in parentheses in the reason
   * text (e.g. "Earned via Sunday Working (0.5) on 29-10-2026"); an entry
   * written before half-day credit existed carries no parenthetical and
   * always meant a full day, so it defaults to 1.0.
   */
  public static function offDayCreditStanding(int $userId, int $leaveTypeId, $day): float
  {
    $balanceId = \App\Models\LeaveBalance::where('user_id', $userId)->where('leave_type_id', $leaveTypeId)->value('id');
    if (!$balanceId) {
      return 0.0;
    }
    $on = ' on ' . \Carbon\Carbon::parse($day)->format('d-m-Y');
    $trail = \OwenIt\Auditing\Models\Audit::where('auditable_type', \App\Models\LeaveBalance::class)->where('auditable_id', $balanceId);

    $sum = function (\Illuminate\Support\Collection $rows): float {
      $total = 0.0;
      foreach ($rows as $text) {
        $total += preg_match('/\(([\d.]+)\)/', (string) $text, $m) ? (float) $m[1] : 1.0;
      }
      return $total;
    };

    $earned = (clone $trail)->where('user_agent', 'like', 'Earned via % Working%' . $on)->pluck('user_agent');
    $reversed = (clone $trail)->where('user_agent', 'like', '% Working Credit Reversed%' . $on)->pluck('user_agent');

    return $sum($earned) - $sum($reversed);
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

          // Full day (OFFDAY_CREDIT_MINUTES, 7h50m) earns 1.0; at least half
          // (OFFDAY_HALF_CREDIT_MINUTES, 4h) but short of a full day earns
          // 0.5; below that, nothing. Working an off-day used to earn
          // nothing at all unless it cleared the full bar - a partial shift
          // there is now credited the same way a partial shift on an
          // ordinary weekday earns a half day.
          $newAmount = 0.0;
          $newLabel = null;
          if ($checkOut) {
              $mins = \App\Services\AttendanceDayStatus::workedMinutes($checkIn, $checkOut);   // punches may be stored the other way round
              if ($mins >= \App\Services\AttendanceDayStatus::OFFDAY_CREDIT_MINUTES) {
                  $newAmount = 1.0;
                  $newLabel = 'Full';
              } elseif ($mins >= \App\Services\AttendanceDayStatus::OFFDAY_HALF_CREDIT_MINUTES) {
                  $newAmount = 0.5;
                  $newLabel = 'Half';
              }
          }

          $dayName = $isSunday ? "Sunday" : ($isHoliday ? "Holiday" : "Saturday");
          $hasStamp = $attendance->admin_reason && (
              str_contains($attendance->admin_reason, "Sunday Working Credit") ||
              str_contains($attendance->admin_reason, "Holiday Working Credit") ||
              str_contains($attendance->admin_reason, "Saturday Working Credit")
          );
          // The stamp alone is not proof: CCare's register rewrites
          // admin_reason on every save, wiping it, and the next save credited
          // the day again (7 days across 6 people by 30 Sep 2026). The
          // balance's own trail says what amount, if any, actually stands.
          $priorAmount = self::offDayCreditStanding($user->id, $compOffType->id, $checkIn);

          $setStamp = function (?string $label) use ($attendance, $dayName) {
              $attendance->withoutEvents(function () use ($attendance, $dayName, $label) {
                  $currentReason = (string) $attendance->admin_reason;
                  $cleanReason = trim(preg_replace('/\s+/', ' ', str_replace(
                      ["Sunday Working Credit (Full) (Auto-credited)", "Sunday Working Credit (Half) (Auto-credited)", "Sunday Working Credit (Auto-credited)",
                       "Holiday Working Credit (Full) (Auto-credited)", "Holiday Working Credit (Half) (Auto-credited)", "Holiday Working Credit (Auto-credited)",
                       "Saturday Working Credit (Full) (Auto-credited)", "Saturday Working Credit (Half) (Auto-credited)", "Saturday Working Credit (Auto-credited)",
                       " | "],
                      ["", "", "", "", "", "", "", "", "", " "],
                      $currentReason
                  )));
                  $newReason = $label ? $dayName . " Working Credit (" . $label . ") (Auto-credited)" : null;
                  $finalReason = $newReason ? ($cleanReason ? $cleanReason . " | " . $newReason : $newReason) : ($cleanReason ?: null);
                  $attendance->updateQuietly(['admin_reason' => $finalReason]);
              });
          };

          if (abs($newAmount - $priorAmount) < 0.001) {
              // Already correctly credited (or correctly nothing); only the
              // cosmetic stamp might need restoring if CCare's register wiped it.
              if ($newAmount > 0 && !$hasStamp) {
                  $setStamp($newLabel);
              }
              return;
          }

          $balance = \App\Models\LeaveBalance::firstOrCreate(
              ['user_id' => $user->id, 'leave_type_id' => $compOffType->id],
              ['tenant_id' => $user->tenant_id ?? $attendance->tenant_id, 'balance' => 0, 'used' => 0, 'accrued_this_year' => 0]
          );

          if ($newAmount > $priorAmount) {
              $diff = $newAmount - $priorAmount;
              \App\Models\LeaveBalance::$auditReason = "Earned via " . $dayName . " Working (" . $diff . ") on " . $checkIn->format('d-m-Y');
              $balance->increment('balance', $diff);
              $balance->increment('accrued_this_year', $diff);
          } else {
              $diff = $priorAmount - $newAmount;
              \App\Models\LeaveBalance::$auditReason = $dayName . " Working Credit Reversed (" . $diff . ") on " . $checkIn->format('d-m-Y');
              $balance->decrement('balance', $diff);
              $balance->decrement('accrued_this_year', $diff);
          }

          $setStamp($newLabel);
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
          // The balance's own trail is the authoritative amount standing for
          // this day (0, 0.5 or 1.0) - the stamp text alone can't tell full
          // from half apart reliably enough to reverse the right amount.
          $standing = self::offDayCreditStanding($user->id, $compOffType->id, $checkIn);

          if ($standing > 0) {
              $balance = \App\Models\LeaveBalance::where('user_id', $user->id)
                  ->where('leave_type_id', $compOffType->id)
                  ->first();

              if ($balance) {
                  \App\Models\LeaveBalance::$auditReason = $dayName . " Working Credit Reversed (" . $standing . ") (Attendance Deleted) on " . $checkIn->format('d-m-Y');
                  $balance->decrement('balance', $standing);
                  $balance->decrement('accrued_this_year', $standing);
              }
          }
      });
  }
}
