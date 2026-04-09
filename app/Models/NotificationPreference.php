<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
  use UserActionsTrait, TenantTrait;

  protected $fillable = ['user_id', 'preferences'];

  protected $casts = [
    'preferences' => 'array',
  ];
}
