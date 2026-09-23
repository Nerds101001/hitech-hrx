<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class ChatParticipant extends Model implements AuditableContract
{
  use UserActionsTrait, TenantTrait, SoftDeletes, Auditable;

  protected $table = 'chat_participants';

  protected $fillable = [
    'chat_id',
    'user_id',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
  ];

  public function chat()
  {
    return $this->belongsTo(Chat::class, 'chat_id');
  }

  public function user()
  {
    return $this->belongsTo(User::class, 'user_id');
  }
}
