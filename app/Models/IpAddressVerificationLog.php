<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class IpAddressVerificationLog extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'ip_address_verification_logs';

  protected $fillable = [
    'ip',
    'is_verified',
    'user_id',
    'ip_address_group_id',
    'created_at',
    'updated_at',
  ];
  protected $casts = [
    'is_verified' => 'boolean',
  ];

  public function user()
  {
    return $this->belongsTo(User::class);
  }

  public function ipAddressGroup()
  {
    return $this->belongsTo(IpAddressGroup::class);
  }
}
