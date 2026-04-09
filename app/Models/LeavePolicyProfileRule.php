<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

class LeavePolicyProfileRule extends Model
{
    use TenantTrait;

    protected $fillable = [
        'profile_id',
        'leave_type_id',
        'is_applicable',
        'max_per_month',
        'max_per_year',
        'max_consecutive_days',
        'short_leave_hours',
        'short_leave_per_month',
        'is_carry_forward',
        'expiry_months',
        'redeem_in_same_month',
        'tenure_required_months',
        'tenure_tiers',
        'is_married_only',
        'carry_forward_max_days',
        'wfh_days_entitlement',
        'off_days_entitlement',
        'applicable_gender',
        'applicable_marital_status',
        'tenure_consecutive_allowed',
        'tenant_id',
    ];

    protected $casts = [
        'is_applicable'        => 'boolean',
        'is_carry_forward'     => 'boolean',
        'is_married_only'      => 'boolean',
        'redeem_in_same_month' => 'boolean',
        'tenure_tiers'         => 'array',
        'short_leave_hours'    => 'float',
    ];

    public function profile()
    {
        return $this->belongsTo(LeavePolicyProfile::class, 'profile_id');
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }
}
