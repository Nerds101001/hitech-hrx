<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class QrCodeModel extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'qr_codes';

  protected $fillable = [
    'qr_group_id',
    'qr_code',
    'code',
    'status',
    'created_by_id',
    'updated_by_id',
    'tenant_id'
  ];

  public function qrGroup()
  {
    return $this->belongsTo(QrGroup::class, 'qr_group_id');
  }
}
