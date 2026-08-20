<?php

namespace App\Models\Telephony;

use Illuminate\Database\Eloquent\Model;

class TelephonyWebhookDelivery extends Model
{
  protected $table = 'telephony_webhook_deliveries';
  protected $guarded = ['id'];

  protected $casts = [
    'headers' => 'array',
    'signature_valid' => 'boolean',
    'received_at' => 'datetime',
    'processed_at' => 'datetime',
  ];
}
