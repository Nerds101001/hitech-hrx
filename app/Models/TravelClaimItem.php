<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravelClaimItem extends Model
{
    protected $fillable = [];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'penalty_applied' => 'boolean',
    ];

    public function claim()
    {
        return $this->belongsTo(TravelClaim::class, 'travel_claim_id');
    }
}
