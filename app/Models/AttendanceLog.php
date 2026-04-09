<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceLog extends Model
{
  use UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'attendance_logs';

  protected $fillable = [
    'attendance_id',
    'type',
    'shift_id',
    'latitude',
    'longitude',
    'altitude',
    'speed',
    'speedAccuracy',
    'horizontalAccuracy',
    'verticalAccuracy',
    'course',
    'courseAccuracy',
    'address',
    'notes',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
  ];
  protected $casts = [
    'latitude' => 'float',
    'longitude' => 'float',
    'altitude' => 'float',
    'speed' => 'float',
    'speedAccuracy' => 'float',
    'horizontalAccuracy' => 'float',
    'verticalAccuracy' => 'float',
    'course' => 'float',
    'courseAccuracy' => 'float',
  ];

  public function attendance()
  {
    return $this->belongsTo(Attendance::class, 'attendance_id');
  }

  public function shift()
  {
    return $this->belongsTo(Shift::class, 'shift_id');
  }

  public function activities()
  {
    return $this->hasMany(Activity::class);
  }

  public function deviceStatusLogs()
  {
    return $this->hasMany(DeviceStatusLog::class);
  }
}
