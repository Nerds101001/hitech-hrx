<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class ExpenseRequest extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'expense_requests';

  protected $fillable = [
    'user_id',
    'expense_type_id',
    'for_date',
    'amount',
    'remarks',
    'document_url',
    'status',
    'approved_at',
    'approved_by_id',
    'admin_remarks',
    'approved_amount',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
  ];

  protected $casts = [
    'for_date' => 'datetime',
    'approved_at' => 'datetime',
    'created_at' => 'datetime',
    'rejected_at' => 'datetime',
    'processed_at' => 'datetime',
    'amount' => 'float',
    'approved_amount' => 'float',
  ];

  public function expenseType()
  {
    return $this->belongsTo(ExpenseType::class, 'expense_type_id');
  }

  public function user()
  {
    return $this->belongsTo(User::class);
  }

  public function approvedBy()
  {
    return $this->belongsTo(User::class, 'approved_by_id');
  }

  public function rejectedBy()
  {
    return $this->belongsTo(User::class, 'rejected_by_id');
  }

  public function items()
  {
    return $this->hasMany(ExpenseRequestItem::class);
  }

  /**
   * Get the secure URL for the expense document.
   */
  public function getSecureUrl()
  {
      if (!$this->document_url) {
          return null;
      }
      return \App\Helpers\FileSecurityHelper::generateSecureUrl($this->document_url);
  }
}
