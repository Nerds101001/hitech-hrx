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
    'tenant_id',
    'created_by_id',
    'updated_by_id',
  ];

  protected $casts = [
    'total_leaves' => 'float',
  ];

  public function user()
  {
    return $this->belongsTo(User::class);
  }

  public function leaveType()
  {
    return $this->belongsTo(LeaveType::class);
  }

  public function getUsedLeavesAttribute()
  {
      return \App\Models\LeaveRequest::where('user_id', $this->user_id)
          ->where('leave_type_id', $this->leave_type_id)
          ->where('status', 'approved')
          ->get()
          ->sum(function ($req) {
              return $req->is_half_day ? 0.5 : (\Carbon\Carbon::parse($req->from_date)->diffInDays(\Carbon\Carbon::parse($req->to_date)) + 1);
          });
  }

  public function getRemainingLeavesAttribute()
  {
      return max(0, $this->total_leaves - $this->used_leaves);
  }
}
