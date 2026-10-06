<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/** One person's 13-week goal period. Closes by itself; the next starts the following Monday. */
class NinetyDayRound extends Model implements AuditableContract
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = [
        'starts_on'  => 'date',
        'ends_on'    => 'date',
        'closed_at'  => 'datetime',
        'customer_window_until' => 'datetime',
        'completion_reminder_sent_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Null for a round that started on its own the ordinary way; set when an admin created it as part of a named drive. */
    public function program()
    {
        return $this->belongsTo(NinetyDayProgram::class, 'program_id');
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
