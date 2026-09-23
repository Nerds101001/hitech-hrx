<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/** A comment on one week of a goal, from the salesperson or from an admin/manager. */
class NinetyDayGoalNote extends Model implements AuditableContract
{
    use Auditable;

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
