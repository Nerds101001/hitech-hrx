<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/** One goal's status for one Monday. Pending (status null) until the person submits it. */
class NinetyDayGoalCheckin extends Model implements AuditableContract
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = [
        'due_on'       => 'date',
        'next_follow_up_date' => 'date',
        'submitted_at' => 'datetime',
        'edited_at'    => 'datetime',
        'is_late'      => 'boolean',
        'auto_filled'          => 'boolean',
        'needs_upsell_confirm' => 'boolean',
        'upsell_confirmed'     => 'boolean',
    ];

    public function goal()
    {
        return $this->belongsTo(NinetyDayGoal::class, 'goal_id');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'edited_by_id');
    }

    public function notes()
    {
        return $this->hasMany(NinetyDayGoalNote::class, 'checkin_id')->orderBy('id');
    }

    public function isSubmitted(): bool
    {
        return $this->status !== null && $this->status !== '';
    }
}
