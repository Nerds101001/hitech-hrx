<?php

namespace App\Models;

use App\Enums\Status;
use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class ExpenseType extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'expense_types';

  protected $fillable = [
    'name',
    'code',
    'notes',
    'default_amount',
    'max_amount',
    'is_proof_required',
    'status',
    'tenant_id',
    'created_by_id',
    'updated_by_id',
  ];
  protected $casts = [
    'status' => Status::class,
    'default_amount' => 'float',
    'max_amount' => 'float',
    'is_proof_required' => 'boolean',
  ];

  public function expenseRequests()
  {
    return $this->hasMany(ExpenseRequest::class, 'expense_type_id');
  }


}
