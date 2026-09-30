<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * A department-level KPI/KRA definition. Stamped onto each matching employee as
 * a KpiAssignment, which is what the scoring engine reads.
 */
class KpiTemplate extends Model implements AuditableContract
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = [
        'bands'             => 'array',
        'is_active'         => 'boolean',
        'max_marks'         => 'decimal:2',
        'target_value'      => 'decimal:2',
        'threshold_percent' => 'decimal:2',
        'late_score'        => 'decimal:2',
        'late_percent'      => 'decimal:2',
        'per_day_penalty'   => 'decimal:2',
        'result_cap_percent' => 'decimal:2',
        'designation_ids'   => 'array',
    ];

    public const SCORING_TYPES = ['timeline', 'target', 'threshold', 'banded'];
    public const LATE_POLICIES = ['none', 'fixed', 'percent', 'per_day'];

    /** The fields a generated KpiAssignment inherits verbatim. */
    public const INHERITED = [
        'title', 'category', 'frequency', 'scoring_type', 'max_marks', 'target_value',
        'threshold_percent', 'bands', 'late_policy', 'late_score', 'late_percent',
        'grace_days', 'per_day_penalty', 'data_source', 'approver_id',
        'check_day', 'check_month', 'trend', 'result_cap_percent',
    ];

    /**
     * Inherited when the assignment is first created, then owned by the person.
     *
     * A target is individual — Archana's revenue target is 13 lakh and Dipika's
     * is 202 — so re-saving the template must not drag everyone back to one
     * shared number. Everything else (weights, scoring rules, late policy) is
     * department policy and does keep following the template.
     */
    public const SEEDED_ONCE = [
        'target_value',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /** The KRA this KPI sits under, if any. */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_template_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_template_id');
    }

    public function assignments()
    {
        return $this->hasMany(KpiAssignment::class, 'kpi_template_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * Does this template reach someone holding $designationId? A template with
     * no designation list covers everyone in its department, as it always did.
     */
    public function coversDesignation($designationId): bool
    {
        $ids = $this->designation_ids;
        if (empty($ids)) {
            return true;
        }
        return $designationId !== null && in_array((int) $designationId, array_map('intval', $ids), true);
    }

    /** null department = every department in the tenant. */
    public function appliesToWholeCompany(): bool
    {
        return $this->department_id === null;
    }
}
