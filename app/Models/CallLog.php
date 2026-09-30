<?php

namespace App\Models;

use App\Enums\CallLogType;
use App\Enums\CallStatus;
use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class CallLog extends Model implements AuditableContract
{
  use UserActionsTrait, TenantTrait, SoftDeletes, Auditable;

  protected $table = 'call_logs';

  protected $fillable = [
    'channel_id',
    'call_type',
    'initiated_by_id',
    'start_time',
    'end_time',
    'status',
    'duration',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
  ];

  public function initiatedBy()
  {
    return $this->belongsTo(User::class, 'initiated_by_id');
  }

  protected $casts = [
    'call_type' => CallLogType::class,
    'status' => CallStatus::class,
    'start_time' => 'datetime',
    'end_time' => 'datetime',
    'duration' => 'integer',
  ];


}
