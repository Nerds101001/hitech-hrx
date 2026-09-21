<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One goal's status for one Monday. Pending (status null) until the person submits it. */
class NinetyDayGoalCheckin extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'due_on'       => 'date',
        'submitted_at' => 'datetime',
        'is_late'      => 'boolean',
    ];

    public function goal()
    {
        return $this->belongsTo(NinetyDayGoal::class, 'goal_id');
    }

    public function isSubmitted(): bool
    {
        return $this->status !== null && $this->status !== '';
    }
}
