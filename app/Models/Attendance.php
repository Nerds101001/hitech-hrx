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
    'attachment',
    'is_policy_late',
    'leave_request_id',
    'tenant_id',
  ];

  protected $casts = [
    'check_in_time' => 'datetime',
    'check_out_time' => 'datetime',
    'approved_at' => 'datetime',
    'is_policy_late' => 'boolean',
    'created_at' => 'datetime',
  ];

  public function user()
  {
    return $this->belongsTo(User::class);
  }

  public function leaveRequest()
  {
    return $this->belongsTo(LeaveRequest::class);
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
}
