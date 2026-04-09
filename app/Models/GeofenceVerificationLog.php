<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class GeofenceVerificationLog extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'geofence_verification_logs';

  protected $fillable = [
    'user_id',
    'latitude',
    'longitude',
    'is_verified',
    'verified_at',
    'reason',
    'site_id',
    'geofence_group_id',
    'created_by_id',
    'updated_by_id',
    'tenant_id'
  ];
  protected $casts = [
    'is_verified' => 'boolean',
    'verified_at' => 'datetime',
    'latitude' => 'decimal:10',
    'longitude' => 'decimal:10'
  ];

  public function user()
  {
    return $this->belongsTo(User::class);
  }

  public function site()
  {
    return $this->belongsTo(Site::class);
  }

  public function geofenceGroup()
  {
    return $this->belongsTo(GeofenceGroup::class);
  }
}
