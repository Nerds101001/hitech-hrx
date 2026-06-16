<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NinetyDayGoal extends Model
{
    use TenantTrait, UserActionsTrait, SoftDeletes;

    protected $fillable = [
        'user_id',
        'appraisal_cycle_id',
        'client_name',
        'product',
        'goal_type',
        'target_value',
        'w0', 'w1', 'w2', 'w3', 'w4', 'w5', 'w6', 'w7', 'w8', 'w9', 'w10', 'w11', 'w12',
        'is_active',
        'created_by_id',
        'tenant_id',
    ];

    protected $casts = [
        'target_value' => 'float',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cycle()
    {
        return $this->belongsTo(AppraisalCycle::class, 'appraisal_cycle_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
