<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Client extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'clients';

  protected $fillable = [
    'name',
    'email',
    'address',
    'phone',
    'latitude',
    'longitude',
    'contact_person_name',
    'radius',
    'city',
    'state',
    'remarks',
    'image_url',
    'status',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
  ];

  protected $casts = [
    'latitude' => 'float',
    'longitude' => 'float',
    'radius' => 'integer',
  ];
}
