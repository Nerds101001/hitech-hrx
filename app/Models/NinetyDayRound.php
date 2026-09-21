<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One person's 13-week goal period. Closes by itself; the next starts the following Monday. */
class NinetyDayRound extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'starts_on'  => 'date',
        'ends_on'    => 'date',
        'closed_at'  => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function goals()
    {
        return $this->hasMany(NinetyDayGoal::class, 'round_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
