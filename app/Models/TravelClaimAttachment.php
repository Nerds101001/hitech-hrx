<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class TravelClaimAttachment extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = [];

    protected $casts = [
        'size' => 'integer',
    ];

    /**
     * The monthly bill categories a file can belong to, mapped to the storage
     * folder it lands in. Keys match the legacy single-path columns on
     * travel_claims so the two can be read together during the changeover.
     */
    public const CATEGORIES = [
        'food_bill_proof'         => 'travel_claims_food',
        'conveyance_petrol_proof' => 'travel_claims_conveyance',
        'toll_parking_proof'      => 'travel_claims_tolls',
        'customer_courier_proof'  => 'travel_claims_customer',
        'hotel_bill_proof'        => 'travel_claims_hotel',
    ];

    /** Most a single category will accept for one claim. */
    public const MAX_PER_CATEGORY = 30;

    public function claim()
    {
        return $this->belongsTo(TravelClaim::class, 'travel_claim_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->path);
    }

    public function getIsImageAttribute(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }
}
