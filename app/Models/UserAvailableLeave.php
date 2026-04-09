<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class UserAvailableLeave extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'users_available_leaves';

  protected $fillable = [
    'user_id',
    'leave_type_id',
    'total_leaves',
    'used_leaves',
    'remaining_leaves',
    'tenant_id',
    'created_by_id',
    'updated_by_id',
  ];

  protected $casts = [
    'total_leaves' => 'float',
    'used_leaves' => 'float',
    'remaining_leaves' => 'float',
  ];

  public function user()
  {
    return $this->belongsTo(User::class);
  }

  public function leaveType()
  {
    return $this->belongsTo(LeaveType::class);
  }
}
