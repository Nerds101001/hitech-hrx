<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentCollection extends Model
{
  use  UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'payment_collections';

  protected $fillable = [
    'user_id',
    'client_id',
    'payment_mode',
    'amount',
    'remarks',
    'proof_url',
    'created_by_id',
    'updated_by_id',
    'tenant_id'
  ];

  public function client()
  {
    return $this->belongsTo(Client::class);
  }

  public function user()
  {
    return $this->belongsTo(User::class);
  }
}
