<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class SalesTargetLog extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'sales_target_logs';

  protected $fillable = [
    'sales_target_id',
    'date',
    'achieved_amount',
    'remaining_amount',
    'status',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
  ];

  protected $casts = [
    'date' => 'date',
    'achieved_amount' => 'float',
    'remaining_amount' => 'float',
  ];

  public function salesTarget()
  {
    return $this->belongsTo(SalesTarget::class);
  }
}
