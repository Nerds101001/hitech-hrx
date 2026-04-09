<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class UserStatusModel extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, SoftDeletes;

  protected $table = 'user_statuses';

  protected $fillable = [
    'user_id',
    'status',
    'message',
    'expires_at',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
  ];
  protected $casts = [
    'status' => UserStatus::class,
    'expires_at' => 'datetime',
  ];

  public function user()
  {
    return $this->belongsTo(User::class);
  }
}
