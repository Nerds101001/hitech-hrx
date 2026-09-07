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
    return match ($this->frequency) {
      'weekly'  => 'Every ' . (self::WEEKDAY_NAMES[$this->weekday] ?? '—'),
      'monthly' => 'Every ' . self::ordinal((int) $this->day_of_month) . ' of the month',
      default   => 'Every day',
    };
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
   * The next date this schedule will fire, starting today. Today counts
   * only if it matches and hasn't already fired (last_run_on isn't today) -
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
      if (!$this->matchesToday($day)) {
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
