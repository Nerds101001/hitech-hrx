<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeviceStatusLog extends Model
{
  use UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'device_status_logs';

  protected $fillable = [
    'uid',
    'attendance_log_id',
    'device_id',
    'user_id',
    'device_type',
    'brand',
    'board',
    'sdk_version',
    'model',
    'token',
    'app_version',
    'battery_percentage',
    'is_charging',
    'is_online',
    'is_gps_on',
    'is_wifi_on',
    'is_mock',
    'signal_strength',
    'latitude',
    'longitude',
    'bearing',
    'horizontalAccuracy',
    'altitude',
    'verticalAccuracy',
    'course',
    'courseAccuracy',
    'speed',
    'speedAccuracy',
    'ip_address',
    'address',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
  ];

  public function user()
  {
    return $this->belongsTo(User::class, 'user_id');
  }

  public function device()
  {
    return $this->belongsTo(UserDevice::class, 'device_id');
  }

  public function attendanceLog()
  {
    return $this->belongsTo(AttendanceLog::class, 'attendance_log_id');
  }

}
