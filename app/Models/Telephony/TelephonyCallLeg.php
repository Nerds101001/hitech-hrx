<?php

namespace App\Models\Telephony;

use Illuminate\Database\Eloquent\Model;

class TelephonyCallLeg extends Model
{
  protected $table = 'telephony_call_legs';
  protected $guarded = ['id'];

  protected $casts = [
    'participants' => 'array',
    'started_at' => 'datetime',
    'ended_at' => 'datetime',
  ];
}
