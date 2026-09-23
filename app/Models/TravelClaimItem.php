<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class TravelClaimItem extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = [];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'penalty_applied' => 'boolean',
        'is_flagged' => 'boolean',
    ];

    public function claim()
    {
        return $this->belongsTo(TravelClaim::class, 'travel_claim_id');
    }
}
