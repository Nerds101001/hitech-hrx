<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Activity extends Model
{
  use UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'activities';

  protected $fillable = [
    'uid',
    'attendance_log_id',
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
    'ip',
    'address',
    'is_mock',
    'is_gps_on',
    'is_wifi_on',
    'battery_percentage',
    'accuracy',
    'signal_strength',
    'activity',
    'image_url',
    'is_offline',
    'type',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
  ];
  protected $casts = [
    'latitude' => 'float',
    'longitude' => 'float',
    'bearing' => 'float',
    'horizontalAccuracy' => 'float',
    'altitude' => 'float',
    'verticalAccuracy' => 'float',
    'course' => 'float',
    'courseAccuracy' => 'float',
    'speed' => 'float',
    'speedAccuracy' => 'float',
    'is_mock' => 'boolean',
    'is_gps_on' => 'boolean',
    'is_wifi_on' => 'boolean',
    'is_offline' => 'boolean',
    'battery_percentage' => 'integer',
  ];

  public function attendanceLog()
  {
    return $this->belongsTo(AttendanceLog::class);
  }

}
