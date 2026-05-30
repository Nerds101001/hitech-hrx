<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravelClaim extends Model
{
    protected $guarded = [];

    protected $casts = [
        'verified_at' => 'datetime',
        'paid_at' => 'datetime',
        'split_85_paid_on' => 'date',
        'split_15_paid_on' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verifier_id');
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    public function items()
    {
        return $this->hasMany(TravelClaimItem::class);
    }

    public function advances()
    {
        return $this->hasMany(TravelClaimAdvance::class);
    }
}
