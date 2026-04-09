<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class GeofenceGroup extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'geofence_groups';

  protected $fillable = [
    'name',
    'code',
    'description',
    'status',
    'created_by_id',
    'updated_by_id',
    'tenant_id'
  ];

  public function geofenceLocations()
  {
    return $this->hasMany(GeofenceLocation::class, 'geofence_group_id');
  }
}
