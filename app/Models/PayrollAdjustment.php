<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class PayrollAdjustment extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'payroll_adjustments';

  protected $fillable = [
    'name',
    'code',
    'type',
    'applicability',
    'amount',
    'percentage',
    'user_id',
    'payroll_record_id',
    'tenant_id',
    'created_by_id',
    'updated_by_id',
  ];


  protected $casts = [
    'amount' => 'float',
    'percentage' => 'float',
  ];

  public function payrollRecord()
  {
    return $this->belongsTo(PayrollRecord::class, 'payroll_record_id');
  }

  public function user()
  {
    return $this->belongsTo(User::class, 'user_id');
  }
}
