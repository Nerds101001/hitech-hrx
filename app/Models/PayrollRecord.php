<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Payslip;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class PayrollRecord extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'payroll_records';

  protected $fillable = [
    'user_id',
    'payroll_cycle_id',
    'period',
    'basic_salary',
    'gross_salary',
    'net_salary',
    'tax_amount',
    'status',
    'tenant_id',
    'created_by_id',
    'updated_by_id',
    'hra',
    'ca',
    'medical',
    'edu',
    'special_allowance',
    'pt',
    'epf',
    'esic',
    'month_calculation_mode',
  ];


  protected $casts = [
    'basic_salary' => 'float',
    'gross_salary' => 'float',
    'net_salary' => 'float',
    'tax_amount' => 'float',
    'hra' => 'float',
    'ca' => 'float',
    'medical' => 'float',
    'edu' => 'float',
    'special_allowance' => 'float',
    'pt' => 'float',
    'epf' => 'float',
    'esic' => 'float',
  ];

  public function payrollCycle()
  {
    return $this->belongsTo(PayrollCycle::class, 'payroll_cycle_id');
  }

  public function user()
  {
    return $this->belongsTo(User::class, 'user_id');
  }

  public function payslips()
  {
    return $this->hasMany(Payslip::class, 'payroll_record_id');
  }

  public function payrollAdjustments()
  {
    return $this->hasMany(PayrollAdjustment::class, 'payroll_record_id');
  }

  public function payrollAdjustmentLogs()
  {
    return $this->hasMany(PayrollAdjustmentLog::class, 'payroll_record_id');
  }
}
