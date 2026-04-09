<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatFile extends Model
{
  use UserActionsTrait, TenantTrait, SoftDeletes;

  protected $table = 'chat_files';

  protected $fillable = [
    'chat_id',
    'chat_message_id',
    'uploaded_by_id',
    'file_path',
    'file_type',
    'file_name',
    'file_extension',
    'file_size',
    'created_by_id',
    'updated_by_id',
    'tenant_id',
  ];

  public function chat()
  {
    return $this->belongsTo(Chat::class, 'chat_id');
  }

  public function message()
  {
    return $this->belongsTo(ChatMessage::class, 'chat_message_id');
  }

  public function uploadedBy()
  {
    return $this->belongsTo(User::class, 'uploaded_by_id');
  }


}
