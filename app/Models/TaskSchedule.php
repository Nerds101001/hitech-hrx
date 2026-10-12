<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * A recurring task assignment. See the migration for the target_user_ids
 * snapshot design — it is what makes removing one person from an active
 * schedule a simple array edit rather than needing a separate exclusion
 * list layered on top of live department membership.
 */
class TaskSchedule extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'task_schedules';

  protected $fillable = [
    'title',
    'description',
    'priority',
    'frequency',
    'weekday',
    'day_of_month',
    'ends_on',
    'target_user_ids',
    'cc_user_ids',
    'assigned_by_id',
    'is_active',
    'last_run_on',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
  ];

  protected $casts = [
    'target_user_ids' => 'array',
    'cc_user_ids' => 'array',
    'is_active' => 'boolean',
    'last_run_on' => 'date',
    'ends_on' => 'date',
    'weekday' => 'integer',
    'day_of_month' => 'integer',
  ];

  public function assignedBy()
  {
    return $this->belongsTo(User::class, 'assigned_by_id');
  }

  public function tasks()
  {
    return $this->hasMany(Task::class);
  }

  public function targetUsers()
  {
    return User::whereIn('id', $this->target_user_ids ?? []);
  }

  private const WEEKDAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

  /** How this reads on screen — "Every Tuesday", "Every 7th of the month", "Every day". */
  public function recurrenceLabel(): string
  {
    $base = match ($this->frequency) {
      'weekly'  => 'Every ' . (self::WEEKDAY_NAMES[$this->weekday] ?? '—'),
      'monthly' => 'Every ' . self::ordinal((int) $this->day_of_month) . ' of the month',
      default   => 'Every day',
    };

    return $this->ends_on ? $base . ' until ' . $this->ends_on->format('d M Y') : $base;
  }

  /** True once the schedule is past its end date — nothing more should be created. */
  public function hasEnded(?\Carbon\Carbon $day = null): bool
  {
    return $this->ends_on && ($day ?: \Carbon\Carbon::today())->startOfDay()->gt($this->ends_on->copy()->startOfDay());
  }

  private static function ordinal(int $n): string
  {
    if (in_array($n % 100, [11, 12, 13], true)) {
      return $n . 'th';
    }
    return $n . match ($n % 10) {
      1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th',
    };
  }

  /**
   * Does today match this schedule's recurrence? Monthly clamps to the
   * month's real last day, so a day_of_month of 31 still fires on 30
   * September rather than never firing at all that month.
   */
  public function matchesToday(\Carbon\Carbon $day): bool
  {
    return match ($this->frequency) {
      'daily'   => true,
      'weekly'  => $day->dayOfWeek === $this->weekday,
      'monthly' => $day->day === min($this->day_of_month, $day->daysInMonth),
      default   => false,
    };
  }

  /**
   * Sunday or a company holiday — no task ever fires on either, whatever
   * the schedule's own frequency says. Shared by TaskScheduleService (the
   * actual fire/skip decision) and nextOccurrence() below (so a dashboard
   * preview never shows a date that was never actually going to fire).
   */
  public static function isNonWorkingDay(\Carbon\Carbon $day): bool
  {
    $key = $day->toDateString();

    // Remembered for this request only: the "moved to the next working day"
    // look-back asks about the same few dates for every schedule.
    return $day->isSunday()
      || \Illuminate\Support\Facades\Cache::store('array')->rememberForever('task-holiday:' . $key,
        fn () => \App\Models\Holiday::where('date', $key)->where('status', 1)->exists());
  }

  /**
   * Is $day a day this schedule fires? Its own day, or - when that fell on
   * a Sunday or holiday - the first working day after it. Skipping used to
   * drop the occurrence: a weekly Friday task skipped on a Friday holiday
   * simply didn't happen that week, and a monthly one lost the whole month
   * (three schedules missed 2 Oct 2026 that way). Days before the schedule
   * existed never count.
   */
  public function isDueOn(\Carbon\Carbon $day): bool
  {
    if (self::isNonWorkingDay($day)) {
      return false;
    }
    if ($this->matchesToday($day)) {
      return true;
    }
    $since = $this->created_at ? $this->created_at->copy()->startOfDay() : null;
    for ($d = $day->copy()->subDay(), $i = 0; $i < 7 && self::isNonWorkingDay($d); $d->subDay(), $i++) {
      if ($this->matchesToday($d) && (!$since || $d->gte($since))) {
        return true;
      }
    }

    return false;
  }

  /**
   * The next date this schedule will fire, starting today. Today counts
   * only if it is due and hasn't already fired (last_run_on isn't today) -
   * this is what lets a dashboard preview a recurring assignment before its
   * Task row exists, since fireIfDue() only ever creates that row on the
   * day it's actually due, never in advance. 62 days covers the longest
   * possible monthly gap.
   */
  public function nextOccurrence(?\Carbon\Carbon $from = null): ?\Carbon\Carbon
  {
    if (!$this->is_active) {
      return null;
    }
    $from = $from ? $from->copy() : \Carbon\Carbon::today();
    for ($i = 0; $i <= 62; $i++) {
      $day = $from->copy()->addDays($i);
      if (!$this->isDueOn($day)) {
        continue;
      }
      if ($i === 0 && $this->last_run_on && $this->last_run_on->isSameDay($day)) {
        continue;
      }
      return $day;
    }
    return null;
  }
}
