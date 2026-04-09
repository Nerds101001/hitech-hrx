<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class QrGroup extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'qr_groups';

  protected $fillable = [
    'name',
    'code',
    'description',
    'status',
    'created_by_id',
    'updated_by_id',
    'tenant_id'
  ];

  public function qrCodes()
  {
    return $this->hasMany(QrCodeModel::class, 'qr_group_id');
  }
}
