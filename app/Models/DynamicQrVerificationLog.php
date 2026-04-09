<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class DynamicQrVerificationLog extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'dynamic_qr_verification_logs';

  protected $fillable = [
    'user_id',
    'qr_code',
    'is_verified',
    'verified_at',
    'reason',
    'site_id',
    'dynamic_qr_device_id',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
    'deleted_at',
  ];

  protected $casts = [
    'is_verified' => 'boolean',
    'verified_at' => 'datetime',
  ];

  public function user()
  {
    return $this->belongsTo(User::class, 'user_id');
  }
}
