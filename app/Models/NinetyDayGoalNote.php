<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A comment on one week of a goal, from the salesperson or from an admin/manager. */
class NinetyDayGoalNote extends Model
{
    protected $guarded = ['id'];

    public function checkin()
    {
        return $this->belongsTo(NinetyDayGoalCheckin::class, 'checkin_id');
    }

    public function goal()
    {
        return $this->belongsTo(NinetyDayGoal::class, 'goal_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
