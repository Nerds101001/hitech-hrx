<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitLeavePolicy extends Model
{
    use TenantTrait, SoftDeletes;

    protected $table = 'unit_leave_policies';

    protected $fillable = [
        'site_id',
        'leave_type_id',
        'is_applicable',
        'max_per_month',
        'max_per_year',
        'max_consecutive_days',
        'short_leave_hours',
        'short_leave_per_month',
        'tenure_required_months',
        'tenure_tiers',
        'tenant_id',
    ];

    protected $casts = [
        'is_applicable'          => 'boolean',
        'short_leave_hours'      => 'float',
        'tenure_tiers'           => 'array',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }
}
