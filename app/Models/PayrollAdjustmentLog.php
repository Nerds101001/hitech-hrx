<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class PayrollAdjustmentLog extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'payroll_adjustment_logs';

  protected $fillable = [
    'payroll_record_id',
    'payroll_adjustment_id',
    'name',
    'code',
    'type',
    'applicability',
    'amount',
    'percentage',
    'user_id',
    'log_message',
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

  public function payrollAdjustment()
  {
    return $this->belongsTo(PayrollAdjustment::class, 'payroll_adjustment_id');
  }

  public function user()
  {
    return $this->belongsTo(User::class, 'user_id');
  }
}
