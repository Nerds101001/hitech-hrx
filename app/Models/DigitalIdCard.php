<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DigitalIdCard extends Model
{
  use UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'digital_id_cards';

  protected $fillable = [
    'user_id',
    'code',
    'data',
    'tenant_id',
    'created_by_id',
    'updated_by_id',
  ];
  protected $casts = [
    'data' => 'json',
  ];

  public function user()
  {
    return $this->belongsTo(User::class);
  }
}
