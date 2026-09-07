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
    return $this->user_id === $userId
      || $this->assigned_by_id === $userId
      || $this->taskCcs()->where('user_id', $userId)->exists();
  }

  /**
   * A task due "today" stays due until 6pm IST, not midnight - after that
   * it's overdue the same day, it doesn't wait for tomorrow.
   */
  public const DUE_CUTOFF_HOUR = 18;

  /** Same rows the daily overdue check targets, for the badge/list to agree with it. */
  public function scopeOverdue(Builder $query): Builder
  {
    return $query->whereNotNull('due_date')
      ->whereRaw('due_date + INTERVAL ' . self::DUE_CUTOFF_HOUR . ' HOUR < ?', [now()])
      ->whereNotIn('status', self::CLOSED_STATUSES);
  }

  public function getIsOverdueAttribute(): bool
  {
    return $this->due_date !== null
      && now()->gt($this->due_deadline)
      && !in_array($this->status, self::CLOSED_STATUSES, true);
  }

  /** The actual moment this task becomes overdue - due_date's day at the 6pm cutoff, not midnight. */
  public function getDueDeadlineAttribute(): ?\Carbon\Carbon
  {
    return $this->due_date?->copy()->addHours(self::DUE_CUTOFF_HOUR);
  }

  public function getIsCompletedAttribute(): bool
  {
    return in_array($this->status, self::CLOSED_STATUSES, true);
  }

  /**
   * How completing this task landed against its deadline: 'early', 'on_time'
   * or 'late' — comparing the DAY completed_at falls on against the day
   * due_date falls on, not the exact timestamp, since a task finished at
   * 11pm on the due date is still on time. Null when there is nothing to
   * judge it against (not completed, or no due date was ever set).
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
    'completion_expected_date' => 'date',
    'expected_date_notified_at' => 'datetime',
  ];


}
