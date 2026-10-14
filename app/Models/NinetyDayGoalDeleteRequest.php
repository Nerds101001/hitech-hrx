<?php

namespace App\Models;

use App\Traits\HasApprovals;
use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/** A salesperson's ask to remove a customer from their 90-Day program; admin/HR decides. */
class NinetyDayGoalDeleteRequest extends Model implements AuditableContract
{
    use Auditable, TenantTrait, HasApprovals;

    protected $guarded = ['id'];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public function goal()
    {
        return $this->belongsTo(NinetyDayGoal::class, 'goal_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function decidedBy()
    {
        return $this->belongsTo(User::class, 'decided_by_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
