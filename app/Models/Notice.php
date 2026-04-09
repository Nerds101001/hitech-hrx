<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Notice extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'notices';

  protected $fillable = [
    'title',
    'description',
    'expiry_date',
    'notice_for',
    'status',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
  ];

  protected $casts = [
    'expiry_date' => 'datetime',
  ];

}
