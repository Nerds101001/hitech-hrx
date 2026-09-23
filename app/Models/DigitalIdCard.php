<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class DigitalIdCard extends Model implements AuditableContract
{
  use UserActionsTrait, TenantTrait, SoftDeletes, Auditable;

  protected $table = 'digital_id_cards';

  protected $fillable = [
    'user_id',
    'code',
    'data',
    'tenant_id',
    'created_by_id',
    'updated_by_id',
  ];
  protected $casts = [
    'data' => 'json',
  ];

  public function user()
  {
    return $this->belongsTo(User::class);
  }
}
