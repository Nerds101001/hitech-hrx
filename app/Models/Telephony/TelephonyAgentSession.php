<?php

namespace App\Models\Telephony;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelephonyAgentSession extends Model
{
  protected $table = 'telephony_agent_sessions';
  protected $guarded = ['id'];

  protected $casts = [
    'login_at' => 'datetime',
    'logout_at' => 'datetime',
    'breaks' => 'array',
    'device' => 'array',
    'is_open' => 'boolean',
    'occupancy_percent' => 'float',
  ];

  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }

  /** Login time minus break time - what "active" means on the report. */
  public function activeSeconds(): int
  {
    return max(0, (int) $this->login_seconds - (int) $this->break_seconds);
  }
}
