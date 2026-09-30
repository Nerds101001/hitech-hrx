<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class KpiAssignment extends Model implements AuditableContract
{
    use Auditable, TenantTrait;

    /**
     * data_source of a figure that is shown, never scored - e.g. "Total Leads
     * Allocated" for Sales and New Biz. Entered monthly in Bulk KPI Entry,
     * carries no marks, and appears as a card on the scorecard instead of a row.
     */
    public const INFO = 'info';

    public function isInfo(): bool
    {
        return $this->data_source === self::INFO;
    }

    protected $fillable = [
        'tenant_id',
        'user_id',
        'work_control_routine_id',
        'title',
        'category',
        'frequency',
        'scoring_type',
        'max_marks',
        'late_score',
        'target_value',
        'check_day',
        'check_month',
        'is_active',
        'created_by_id',
        // Stamped from a KpiTemplate (null when hand-created for one person)
        'kpi_template_id',
        'kra_group',
        'threshold_percent',
        'bands',
        'late_policy',
        'late_percent',
        'grace_days',
        'per_day_penalty',
        'data_source',
        'approver_id',
        'trend',
        'result_cap_percent',
    ];

    protected $casts = [
        'max_marks' => 'float',
        'late_score' => 'float',
        'target_value' => 'float',
        'is_active' => 'boolean',
        'threshold_percent' => 'float',
        'late_percent' => 'float',
        'per_day_penalty' => 'float',
        'result_cap_percent' => 'float',
        'grace_days' => 'integer',
        // Scoring reads this as an array of {min, marks}
        'bands' => 'array',
    ];

    public function template()
    {
        return $this->belongsTo(KpiTemplate::class, 'kpi_template_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function routine()
    {
        return $this->belongsTo(WorkControlRoutine::class, 'work_control_routine_id');
    }

    public function checks()
    {
        return $this->hasMany(KpiCheck::class);
    }
}
