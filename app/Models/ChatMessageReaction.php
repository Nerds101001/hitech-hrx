<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatMessageReaction extends Model
{
  use UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'chat_message_reactions';

  protected $fillable = [
    'chat_message_id',
    'user_id',
    'reaction',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
  ];

  public function message()
  {
    return $this->belongsTo(ChatMessage::class, 'chat_message_id');
  }

  public function user()
  {
    return $this->belongsTo(User::class, 'user_id');
  }
}
