<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class GeofenceLocation extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'geofence_locations';

  protected $fillable = [
    'name',
    'description',
    'latitude',
    'longitude',
    'radius',
    'is_enabled',
    'geofence_group_id',
    'created_by_id',
    'updated_by_id',
    'tenant_id'
  ];

  public function geofenceGroup()
  {
    return $this->belongsTo(GeofenceGroup::class);
  }
}
