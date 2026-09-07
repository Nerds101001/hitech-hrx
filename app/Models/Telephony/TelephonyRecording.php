<?php

namespace App\Models\Telephony;

use Illuminate\Database\Eloquent\Model;

class TelephonyRecording extends Model
{
  protected $table = 'telephony_recordings';
  protected $guarded = ['id'];

  protected $casts = [
    'ready_at' => 'datetime',
    'retention_until' => 'date',
    'url_expires_at' => 'datetime',
  ];

  public function isExpired(): bool
  {
    return $this->retention_until !== null && $this->retention_until->isPast();
  }

  /**
   * A pushed link HRX can still fetch.
   *
   * A vendor that cannot expose a signed-URL endpoint pushes the location
   * instead. Most such links are time-limited, so an expiry that has passed
   * counts as no link at all and playback falls back to asking the gateway --
   * which is the behaviour that turns "the player is broken" into a recording
   * that simply plays.
   */
  public function hasUsableUrl(): bool
  {
    return !empty($this->recording_url)
      && ($this->url_expires_at === null || $this->url_expires_at->isFuture());
  }
}
