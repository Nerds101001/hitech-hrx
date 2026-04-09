<?php

namespace App\Models;

use App\Enums\Status;
use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class LeaveType extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'leave_types';

  protected $fillable = [
    'name',
    'is_paid',
    'code',
    'notes',
    'is_proof_required',
    'is_short_leave',
    'is_carry_forward',
    'is_split_entitlement',
    'is_consecutive_allowed',
    'is_strict_rules',
    'status',
    'tenant_id',
    'site_id',
    'created_by_id',
    'updated_by_id',
  ];

  protected $casts = [
    'status' => Status::class,
    'is_paid' => 'boolean',
    'is_proof_required' => 'boolean',
    'is_short_leave' => 'boolean',
    'is_carry_forward' => 'boolean',
    'is_split_entitlement' => 'boolean',
    'is_consecutive_allowed' => 'boolean',
    'is_strict_rules' => 'boolean',
  ];

  public function site()
  {
    return $this->belongsTo(Site::class, 'site_id');
  }

  public function unitLeavePolicies()
  {
    return $this->hasMany(UnitLeavePolicy::class, 'leave_type_id');
  }
}
