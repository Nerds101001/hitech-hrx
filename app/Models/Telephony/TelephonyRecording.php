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
  ];

  public function isExpired(): bool
  {
    return $this->retention_until !== null && $this->retention_until->isPast();
  }
}
