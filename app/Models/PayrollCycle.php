<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class PayrollCycle extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'payroll_cycles';

  protected $fillable = [
    'name',
    'code',
    'frequency',
    'pay_period_start',
    'pay_period_end',
    'pay_date',
    'status',
    'tenant_id',
    'created_by_id',
    'updated_by_id',
  ];

  protected $casts = [
    'pay_period_start' => 'date',
    'pay_period_end' => 'date',
    'pay_date' => 'date',
    'frequency' => 'integer',
  ];

  public function payrollRecords()
  {
    return $this->hasMany(PayrollRecord::class, 'payroll_cycle_id');
  }
}
