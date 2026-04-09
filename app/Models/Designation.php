<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Designation extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'designations';

  protected $fillable = [
    'name',
    'code',
    'notes',
    'status',
    'department_id',
    'parent_id',
    'tenant_id',
    'level',
    'is_leave_approver',
    'is_expense_approver',
    'is_loan_approver',
    'is_document_approver',
    'is_advance_approver',
    'is_resignation_approver',
    'is_transfer_approver',
    'is_promotion_approver',
    'is_increment_approver',
    'is_training_approver',
    'is_recruitment_approver',
    'is_performance_approver',
    'is_disciplinary_approver',
    'is_complaint_approver',
    'is_warning_approver',
    'is_termination_approver',
    'is_confirmation_approver',
    'created_by_id',
    'updated_by_id',
  ];

  protected $casts = [
    'is_leave_approver' => 'boolean',
    'is_expense_approver' => 'boolean',
    'is_loan_approver' => 'boolean',
    'is_document_approver' => 'boolean',
    'is_advance_approver' => 'boolean',
    'is_resignation_approver' => 'boolean',
    'is_transfer_approver' => 'boolean',
    'is_promotion_approver' => 'boolean',
    'is_increment_approver' => 'boolean',
    'is_training_approver' => 'boolean',
    'is_recruitment_approver' => 'boolean',
    'is_performance_approver' => 'boolean',
    'is_disciplinary_approver' => 'boolean',
    'is_complaint_approver' => 'boolean',
    'is_warning_approver' => 'boolean',
    'is_termination_approver' => 'boolean',
    'is_confirmation_approver' => 'boolean',
  ];

  public function department()
  {
    return $this->belongsTo(Department::class, 'department_id');
  }

  public function parent()
  {
    return $this->belongsTo(Designation::class, 'parent_id');
  }

  public function children()
  {
    return $this->hasMany(Designation::class, 'parent_id');
  }

  public function users()
  {
    return $this->hasMany(User::class, 'designation_id');
  }

}
