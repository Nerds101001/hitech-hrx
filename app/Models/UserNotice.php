<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class UserNotice extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'user_notices';

  protected $fillable = [
    'user_id',
    'notice_id',
    'tenant_id',
    'created_by_id',
    'updated_by_id',
  ];

  public function user()
  {
    return $this->belongsTo(User::class);
  }

  public function notice()
  {
    return $this->belongsTo(Notice::class);
  }
}
