<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class QrCodeVerificationLog extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'qr_code_verification_logs';

  protected $fillable = [
    'user_id',
    'qr_code',
    'is_verified',
    'verified_at',
    'reason',
    'site_id',
    'qr_group_id',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
  ];
  protected $casts = [
    'verified_at' => 'datetime',
    'is_verified' => 'boolean',
  ];

  public function user()
  {
    return $this->belongsTo(User::class, 'user_id');
  }

  public function site()
  {
    return $this->belongsTo(Site::class, 'site_id');
  }

  public function qrGroup()
  {
    return $this->belongsTo(QrGroup::class, 'qr_group_id');
  }
}
