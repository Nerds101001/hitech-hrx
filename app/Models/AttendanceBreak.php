<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class AttendanceBreak extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'attendance_breaks';

  protected $fillable = [
    'attendance_log_id',
    'start_time',
    'end_time',
    'duration',
    'reason',
  ];
  protected $casts = [
    'start_time' => 'datetime',
    'end_time' => 'datetime',
  ];

  public function attendanceLog()
  {
    return $this->belongsTo(AttendanceLog::class);
  }
}
