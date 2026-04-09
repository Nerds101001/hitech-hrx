<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class SOSLog extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'sos_logs';

  protected $fillable = [
    'user_id',
    'latitude',
    'longitude',
    'address',
    'notes',
    'img_url',
    'status',
    'resolved_at',
    'resolved_by_id',
    'admin_notes',
    'created_by_id',
    'updated_by_id',
    'tenant_id'
  ];

  public function user()
  {
    return $this->belongsTo(User::class, 'user_id');
  }

  public function resolvedBy()
  {
    return $this->belongsTo(User::class, 'resolved_by_id');
  }

  protected $casts = [
    'resolved_at' => 'datetime',
    'latitude' => 'float',
    'longitude' => 'float',
  ];
}
