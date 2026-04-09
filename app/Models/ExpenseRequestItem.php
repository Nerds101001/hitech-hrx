<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class ExpenseRequestItem extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'expense_request_items';

  protected $fillable = [
    'expense_request_id',
    'expense_type_id',
    'document',
    'user_notes',
    'default_amount',
    'amount',
    'approved_amount',
    'hold_amount',
    'notes',
    'status',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
  ];

  protected $casts = [
    'default_amount' => 'float',
    'amount' => 'float',
    'approved_amount' => 'float',
    'hold_amount' => 'float',
    'status' => 'string',
  ];

  public function expenseRequest()
  {
    return $this->belongsTo(ExpenseRequest::class);
  }

  public function expenseType()
  {
    return $this->belongsTo(ExpenseType::class);
  }
  
}
