<?php

namespace App\Models\Telephony;

use Illuminate\Database\Eloquent\Model;

class TelephonyCallEvent extends Model
{
  protected $table = 'telephony_call_events';
  protected $guarded = ['id'];

  protected $casts = [
    'occurred_at' => 'datetime',
    'payload' => 'array',
  ];
}
