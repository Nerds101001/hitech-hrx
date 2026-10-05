<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Task extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'tasks';

  /** Statuses that mean the task is no longer awaiting action. */
  public const CLOSED_STATUSES = ['completed', 'cancelled', 'resolved', 'closed'];

  public const PRIORITIES = ['low', 'medium', 'high'];

  /**
   * The three states an assignee actually chooses between when updating
   * their own progress. 'in_progress' already existed on the status enum
   * unused elsewhere in the app, so "Partially Done" reuses it rather than
   * widening the enum for a new value. Every other enum value (hold,
   * rejected, reassigned, reopened, objection, ...) stays admin/internal,
   * not offered in this picker.
   */
  public const PROGRESS_STATUSES = [
    'new'         => 'Pending',
    'in_progress' => 'Partially Done',
    'completed'   => 'Completed',
  ];

  protected $fillable = [
    'batch_id',
    'task_schedule_id',
    'title',
    'description',
    'type',
    'assigned_by_id',
    'user_id',
    'client_id',
    'site_id',
    'latitude',
    'longitude',
    'max_radius',
    'start_date_time',
    'end_date_time',
    'for_date',
    'due_date',
    'start_form_id',
    'end_form_id',
    'is_geo_fence_enabled',
    'status',
    'priority',
    'completed_at',
    'objection_notes',
    'attachment_path',
    'objected_by_id',
    'objected_at',
    'delay_notified_at',
    'due_soon_notified_at',
    'escalated_at',
    'completion_expected_date',
    'expected_date_notified_at',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
  ];

  public function assignedBy()
  {
    return $this->belongsTo(User::class, 'assigned_by_id');
  }

  public function objectedBy()
  {
    return $this->belongsTo(User::class, 'objected_by_id');
  }

  public function user()
  {
    return $this->belongsTo(User::class);
  }

  public function client()
  {
    return $this->belongsTo(Client::class);
  }

  public function site()
  {
    return $this->belongsTo(Site::class);
  }

  public function startForm()
  {
    return $this->belongsTo(Form::class, 'start_form_id');
  }

  public function endForm()
  {
    return $this->belongsTo(Form::class, 'end_form_id');
  }

  public function taskUpdates()
  {
    return $this->hasMany(TaskUpdate::class);
  }

  public function schedule()
  {
    return $this->belongsTo(TaskSchedule::class, 'task_schedule_id');
  }

  /** The pivot rows themselves — carries who added the CC and when. */
  public function taskCcs()
  {
    return $this->hasMany(TaskCc::class);
  }

  /** The people CC'd on this task — co-owners who can watch status and reply, but aren't the assignee. */
  public function ccUsers()
  {
    return $this->belongsToMany(User::class, 'task_ccs')->withPivot('created_by_id')->withTimestamps();
  }

  /** Only this person's own tasks — the one place "for a user" is decided. */
  public function scopeForUser(Builder $query, int $userId): Builder
  {
    return $query->where('user_id', $userId);
  }

  /** Tasks this person is CC'd on — watching, not assigned. */
  public function scopeCcTo(Builder $query, int $userId): Builder
  {
    return $query->whereHas('taskCcs', fn ($q) => $q->where('user_id', $userId));
  }

  /** True if this person can see the task's full update thread and add a reply: assignee, assigner, or CC'd. */
  public function isVisibleTo(int $userId): bool
  {
    return (int) $this->user_id === $userId
      || (int) $this->assigned_by_id === $userId
      || $this->taskCcs()->where('user_id', $userId)->exists();
  }

  /**
   * isVisibleTo(), plus whoever may manage the task on Task Management
   * (admin/HR/tasks.manage, a manager for their own reports). Those people
   * can already edit or delete it, so its remarks must not be hidden from
   * them - the View popup used to say "No updates yet." to an admin.
   */
  public function canBeViewedBy(?User $user): bool
  {
    return $user && ($this->isVisibleTo((int) $user->id) || \App\Support\TaskAccess::canManage($user, $this));
  }

  /**
   * A task due on a DAY (no time picked) stays due until 6pm IST, not
   * midnight - after that it's overdue the same day. A task given an exact
   * time is overdue at that time. The time used to be ignored: 18 hours were
   * added to it as well, so a task due at 3 PM only turned overdue at 9 AM
   * the next morning.
   */
  public const DUE_CUTOFF_HOUR = 18;

  /** When a task with this due_date becomes overdue, in SQL (see DUE_CUTOFF_HOUR). */
  public const DEADLINE_SQL = "(CASE WHEN TIME(tasks.due_date) = '00:00:00' THEN tasks.due_date + INTERVAL 18 HOUR ELSE tasks.due_date END)";

  /** Same rows the daily overdue check targets, for the badge/list to agree with it. */
  public function scopeOverdue(Builder $query): Builder
  {
    return $query->whereNotNull('tasks.due_date')
      ->whereRaw(self::DEADLINE_SQL . ' < ?', [now()])
      ->whereNotIn('tasks.status', self::CLOSED_STATUSES);
  }

  /** Due today and not overdue yet - a task is never in both. */
  public function scopeDueToday(Builder $query): Builder
  {
    return $query->whereDate('tasks.due_date', now()->toDateString())
      ->whereRaw(self::DEADLINE_SQL . ' >= ?', [now()])
      ->whereNotIn('tasks.status', self::CLOSED_STATUSES);
  }

  public function getIsOverdueAttribute(): bool
  {
    return $this->due_date !== null
      && now()->gt($this->due_deadline)
      && !in_array($this->status, self::CLOSED_STATUSES, true);
  }

  /** True when the assigner picked an exact time, not just a day. */
  public function getHasDueTimeAttribute(): bool
  {
    return $this->due_date !== null && $this->due_date->format('H:i:s') !== '00:00:00';
  }

  /** The moment this task becomes overdue: its exact time, or 6pm on its day. */
  public function getDueDeadlineAttribute(): ?\Carbon\Carbon
  {
    if ($this->due_date === null) {
      return null;
    }

    return $this->has_due_time ? $this->due_date->copy() : $this->due_date->copy()->addHours(self::DUE_CUTOFF_HOUR);
  }

  /** "05 Oct 2026", or "05 Oct 2026, 03:00 PM" when a time was picked - never a fake 12:00 AM. */
  public function dueLabel(string $dateFormat = 'd M Y'): ?string
  {
    if ($this->due_date === null) {
      return null;
    }

    return $this->due_date->format($dateFormat) . ($this->has_due_time ? ', ' . $this->due_date->format('h:i A') : '');
  }

  public function getIsCompletedAttribute(): bool
  {
    return in_array($this->status, self::CLOSED_STATUSES, true);
  }

  /**
   * How completing this task landed against its deadline: 'early', 'on_time'
   * or 'late'. A day-only task compares DAYS - finished any time on the due
   * day is on time (a task done at 11pm on its due date still counts). A task
   * given an exact time is late once that time has passed; it used to count
   * as on time for the rest of that day. Null when there is nothing to judge
   * it against (not completed, or no due date set).
   */
  public function getCompletionTimingAttribute(): ?string
  {
    if (!$this->is_completed || !$this->completed_at || !$this->due_date) {
      return null;
    }

    $completedDay = $this->completed_at->copy()->startOfDay();
    $dueDay = $this->due_date->copy()->startOfDay();

    return match (true) {
      $completedDay->lt($dueDay) => 'early',
      $this->has_due_time => $this->completed_at->lte($this->due_date) ? 'on_time' : 'late',
      $completedDay->eq($dueDay) => 'on_time',
      default => 'late',
    };
  }

  protected $casts = [
    'is_geo_fence_enabled' => 'boolean',
    'latitude' => 'float',
    'longitude' => 'float',
    'max_radius' => 'integer',
    'start_date_time' => 'datetime',
    'end_date_time' => 'datetime',
    'due_date' => 'datetime',
    'completed_at' => 'datetime',
    'objected_at' => 'datetime',
    'delay_notified_at' => 'datetime',
    'due_soon_notified_at' => 'datetime',
    'escalated_at' => 'datetime',
    'completion_expected_date' => 'date',
    'expected_date_notified_at' => 'datetime',
  ];


}
