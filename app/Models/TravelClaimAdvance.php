<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravelClaimAdvance extends Model
{
    protected $fillable = [];

    protected $casts = [
        'date' => 'date',
    ];

    public function claim()
    {
        return $this->belongsTo(TravelClaim::class, 'travel_claim_id');
    }
}
