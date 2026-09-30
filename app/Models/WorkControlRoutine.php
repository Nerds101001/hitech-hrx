<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class WorkControlRoutine extends Model implements AuditableContract
{
    use HasFactory, Auditable;

    protected $fillable = [
        'user_id',
        'title',
        'frequency',
        'duration_minutes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getDailyEquivalentMinutesAttribute()
    {
        $mins = $this->duration_minutes;
        switch ($this->frequency) {
            case 'daily':
                return $mins;
            case 'weekly':
                return $mins / 6;
            case 'fortnightly':
                return $mins / 13;
            case 'monthly':
                return $mins / 26;
            case 'quarterly':
                return $mins / 78;
            case 'yearly':
                return $mins / 312;
            default:
                return $mins;
        }
    }
}
