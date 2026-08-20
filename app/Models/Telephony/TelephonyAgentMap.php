<?php

namespace App\Models\Telephony;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * HRX employee <-> dialer agent.
 *
 * resolve() is deliberately forgiving: a manual-dial event may carry only an
 * extension, or only the SIM number, so every identifier is tried in order of
 * how much we trust it.
 */
class TelephonyAgentMap extends Model
{
  use SoftDeletes;

  protected $table = 'telephony_agent_map';
  protected $guarded = ['id'];

  protected $casts = [
    'is_active' => 'boolean',
    'last_seen_at' => 'datetime',
  ];

  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }

  /**
   * @param  array<string,mixed>  $agent  The `agent` block from a webhook.
   */
  public static function resolve(array $agent): ?self
  {
    // The vendor stamping our own employee code is the most reliable signal,
    // so try it before anything the dialer manages on its own.
    if (!empty($agent['hrms_employee_id'])) {
      $user = User::where('code', $agent['hrms_employee_id'])->first();
      if ($user) {
        $row = static::where('user_id', $user->id)->where('is_active', true)->first();
        if ($row) {
          return $row;
        }
      }
    }

    foreach (['agent_id', 'agent_code', 'extension', 'agent_number'] as $field) {
      if (empty($agent[$field])) {
        continue;
      }
      $row = static::where($field, $agent[$field])->where('is_active', true)->first();
      if ($row) {
        return $row;
      }
    }

    return null;
  }
}
