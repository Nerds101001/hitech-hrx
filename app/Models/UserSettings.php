<?php

namespace App\Models;

use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class UserSettings extends Model implements AuditableContract
{
  use Auditable, UserActionsTrait;

  protected $table = 'user_settings';

  protected $fillable = [
    'user_id',
    'key',
    'value',
    'tenant_id',
    'created_by_id',
    'updated_by_id',
  ];

  public function user()
  {
    return $this->belongsTo(User::class);
  }
}
