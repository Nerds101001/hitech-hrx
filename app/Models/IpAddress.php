<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class IpAddress extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'ip_addresses';

  protected $fillable = [
    'name',
    'description',
    'ip_address',
    'is_enabled',
    'ip_address_group_id',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
  ];

  protected $casts = [
    'is_enabled' => 'boolean',
  ];

  public function ipAddress()
  {
    return $this->belongsTo(IpAddressGroup::class, 'ip_address_group_id');
  }
}
