<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One inbound hit on the Tally push endpoint -- accepted or rejected. A thin
 * record, written by VerifyTallyPushSignature (refusals) and TallyPushController
 * (accepted calls). Mirrors TelephonyWebhookDelivery.
 */
class TallyPushRequest extends Model
{
    protected $table = 'tally_push_requests';
    protected $guarded = ['id'];

    protected $casts = [
        'headers' => 'array',
        'signature_valid' => 'boolean',
        'received_at' => 'datetime',
    ];
}
