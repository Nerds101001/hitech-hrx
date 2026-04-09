<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Payslip extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'payslips';

  protected $fillable = [
    'user_id',
    'payroll_record_id',
    'code',
    'basic_salary',
    'total_deductions',
    'total_benefits',
    'net_salary',
    'status',
    'notes',
    'total_worked_days',
    'total_absent_days',
    'total_leave_days',
    'total_late_days',
    'total_early_checkout_days',
    'total_overtime_days',
    'total_holidays',
    'total_weekends',
    'total_working_days',
    'tenant_id',
    'created_by_id',
    'updated_by_id',
  ];

  protected $casts = [
    'basic_salary' => 'float',
    'total_deductions' => 'float',
    'total_benefits' => 'float',
    'net_salary' => 'float',
    'total_worked_days' => 'integer',
    'total_absent_days' => 'integer',
    'total_leave_days' => 'integer',
    'total_late_days' => 'integer',
    'total_early_checkout_days' => 'integer',
    'total_overtime_days' => 'integer',
    'total_holidays' => 'integer',
    'total_weekends' => 'integer',
    'total_working_days' => 'integer',
  ];

  public function user()
  {
    return $this->belongsTo(User::class, 'user_id');
  }

  public function payrollRecord()
  {
    return $this->belongsTo(PayrollRecord::class, 'payroll_record_id');
  }
}
