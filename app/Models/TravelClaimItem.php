<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravelClaimItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'penalty_applied' => 'boolean',
    ];

    public function claim()
    {
        return $this->belongsTo(TravelClaim::class, 'travel_claim_id');
    }
}
