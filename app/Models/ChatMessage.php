<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatMessage extends Model
{
  use UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'chat_messages';

  protected $fillable = [
    'chat_id',
    'user_id',
    'content',
    'message_type',
    'is_forwarded',
    'is_edited',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
  ];
  protected $casts = [
    'content' => 'string',
    'message_type' => 'string',
    'created_at' => 'datetime:Y-m-d h:i:s A',
  ];

  public function chat()
  {
    return $this->belongsTo(Chat::class, 'chat_id');
  }

  public function user()
  {
    return $this->belongsTo(User::class, 'user_id');
  }

  public function reactions()
  {
    return $this->hasMany(ChatMessageReaction::class, 'chat_message_id');
  }

  public function readReceipts()
  {
    return $this->hasMany(ChatMessageReadReceipt::class, 'chat_message_id');
  }

  public function chatFile()
  {
    return $this->hasOne(ChatFile::class, 'chat_message_id');
  }
}
