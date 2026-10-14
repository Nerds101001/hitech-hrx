<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * One target customer inside a person's 13-week round: the customer (from their
 * Sales Pipeline), the product and brand, the goal in words and the monthly
 * potential in lakh. Its week-by-week status lives in ninety_day_goal_checkins;
 * w0..w12 are still written alongside so the older scorecard code keeps working.
 */
class NinetyDayGoal extends Model implements AuditableContract
{
    use TenantTrait, UserActionsTrait, SoftDeletes, Auditable;

    protected $fillable = [
        'user_id',
        'appraisal_cycle_id',
        'round_id',
        'sales_pipeline_id',
        'client_name',
        'product',
        'brand',
        'goal',
        'goal_type',
        'monthly_potential',
        'expected_order_date',
        'target_value',
        'w0', 'w1', 'w2', 'w3', 'w4', 'w5', 'w6', 'w7', 'w8', 'w9', 'w10', 'w11', 'w12',
        'is_active',
        'closed_at',
        'created_by_id',
        'tenant_id',
    ];

    protected $casts = [
        'target_value'      => 'float',
        'monthly_potential' => 'float',
        'expected_order_date' => 'date',
        'is_active'         => 'boolean',
        'closed_at'         => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cycle()
    {
        return $this->belongsTo(AppraisalCycle::class, 'appraisal_cycle_id');
    }

    public function round()
    {
        return $this->belongsTo(NinetyDayRound::class, 'round_id');
    }

    public function checkins()
    {
        return $this->hasMany(NinetyDayGoalCheckin::class, 'goal_id')->orderBy('week_no');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function deleteRequests()
    {
        return $this->hasMany(NinetyDayGoalDeleteRequest::class, 'goal_id')->orderByDesc('id');
    }

    public function pendingDeleteRequest()
    {
        return $this->hasOne(NinetyDayGoalDeleteRequest::class, 'goal_id')->where('status', 'pending')->latestOfMany();
    }
}
