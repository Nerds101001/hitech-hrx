<?php

namespace App\Models;

use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Traits\TenantTrait;

class UserDevice extends Model
{
  use TenantTrait, UserActionsTrait, SoftDeletes;

  protected $table = 'user_devices';

  protected $fillable = [
    'user_id',
    'device_type',
    'device_id',
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

  protected $casts = [
    'is_online' => 'boolean',
    'is_gps_on' => 'boolean',
    'is_wifi_on' => 'boolean',
    'is_mock' => 'boolean',
    'battery_percentage' => 'integer',
    'signal_strength' => 'integer',
  ];

  public function user()
  {
    return $this->belongsTo(User::class);
  }
}
