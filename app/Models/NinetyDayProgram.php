<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/** A named, admin-created 90-day drive: its own start/end date, its own invited people. */
class NinetyDayProgram extends Model implements AuditableContract
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on'   => 'date',
    ];

    public function rounds()
    {
        return $this->hasMany(NinetyDayRound::class, 'program_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
