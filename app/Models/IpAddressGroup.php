<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class IpAddressGroup extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'ip_address_groups';

  protected $fillable = [
    'name',
    'code',
    'description',
    'status',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
  ];

  public function ipAddresses()
  {
    return $this->hasMany(IpAddress::class, 'ip_address_group_id');
  }
}
