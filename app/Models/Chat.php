<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Chat extends Model
{
  use UserActionsTrait, TenantTrait, SoftDeletes;

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
