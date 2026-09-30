<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Chat extends Model implements AuditableContract
{
  use UserActionsTrait, TenantTrait, SoftDeletes, Auditable;

  protected $table = 'chats';

  protected $fillable = [
    'is_group_chat',
    'name',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
  ];

  public function participants()
  {
    return $this->hasMany(ChatParticipant::class, 'chat_id');
  }

  public function messages()
  {
    return $this->hasMany(ChatMessage::class, 'chat_id');
  }

  protected $casts = [
    'created_at' => 'datetime:Y-m-d h:i:s A',
    'is_group_chat' => 'boolean',
  ];

}
