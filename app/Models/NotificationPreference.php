<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class NotificationPreference extends Model implements AuditableContract
{
  use UserActionsTrait, TenantTrait, Auditable;

  protected $fillable = ['user_id', 'preferences'];

  protected $casts = [
    'preferences' => 'array',
  ];
}
