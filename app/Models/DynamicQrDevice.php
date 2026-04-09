<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class DynamicQrDevice extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'dynamic_qr_devices';

  protected $fillable = [
    'name',
    'description',
    'unique_id',
    'pin',
    'qr_value',
    'token',
    'qr_last_updated_at',
    'qr_update_interval',
    'qr_expiry_date',
    'status',
    'device_type',
    'site_id',
    'user_id',
    'tenant_id',
    'created_by_id',
    'updated_by_id',
  ];

  protected $casts = [
    'qr_last_updated_at' => 'datetime',
    'qr_expiry_date' => 'datetime',
  ];

  public function site()
  {
    return $this->belongsTo(Site::class, 'site_id', 'id');
  }

  public function user()
  {
    return $this->belongsTo(User::class, 'user_id', 'id');
  }
}
