<?php

namespace App\Models\Telephony;

use Illuminate\Database\Eloquent\Model;

class TelephonyAgentStateChange extends Model
{
  protected $table = 'telephony_agent_state_changes';
  protected $guarded = ['id'];

  protected $casts = ['occurred_at' => 'datetime'];
}
