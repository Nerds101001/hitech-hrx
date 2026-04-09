<?php

namespace App\Models;

use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class UserNotification extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, SoftDeletes;

  protected $fillable = [
    'user_id',
    'notification_id',
    'is_read',
    'is_deleted',
    'read_at',
    'deleted_at',
    'created_by_id',
    'updated_by_id',
  ];
  protected $casts = [
    'is_read' => 'boolean'
  ];

  public function user()
  {
    return $this->belongsTo(User::class);
  }

  public function notification()
  {
    return $this->belongsTo(Notification::class);
  }

}
