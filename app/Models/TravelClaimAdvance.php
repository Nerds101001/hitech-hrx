<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class TravelClaimAdvance extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = [];

    protected $casts = [
        'date' => 'date',
    ];

    public function claim()
    {
        return $this->belongsTo(TravelClaim::class, 'travel_claim_id');
    }
}
