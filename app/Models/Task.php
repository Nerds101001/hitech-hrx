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

  /** Only this person's own tasks — the one place "for a user" is decided. */
  public function scopeForUser(Builder $query, int $userId): Builder
  {
    return $query->where('user_id', $userId);
  }

  /** Same rows the daily overdue check targets, for the badge/list to agree with it. */
  public function scopeOverdue(Builder $query): Builder
  {
    // Compared against the START of today, not the exact current time: a
    // task due_date is stored at midnight of its due day, so comparing
    // against now() made every same-day task "overdue" the instant the
    // clock passed 00:00 - it never got a chance to be "due today" at all.
    return $query->whereNotNull('due_date')
      ->where('due_date', '<', now()->startOfDay())
      ->whereNotIn('status', self::CLOSED_STATUSES);
  }

  public function getIsOverdueAttribute(): bool
  {
    return $this->due_date !== null
      && $this->due_date->lt(now()->startOfDay())
      && !in_array($this->status, self::CLOSED_STATUSES, true);
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
  ];


}
