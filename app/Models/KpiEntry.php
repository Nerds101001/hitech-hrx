<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * One weekly submission against a KPI, awaiting or having had HR review.
 *
 * The monthly KpiCheck holds the score; these hold what the employee actually
 * reported each week and who signed it off.
 */
class KpiEntry extends Model implements AuditableContract
{
    use TenantTrait, Auditable;

    public const SUBMITTED = 'submitted';
    public const APPROVED  = 'approved';
    public const REJECTED  = 'rejected';

    protected $fillable = [
        'tenant_id', 'kpi_check_id', 'kpi_assignment_id', 'user_id',
        'period_start', 'period_end', 'value', 'note',
        'status', 'reviewed_by_id', 'reviewed_at', 'review_note',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end'   => 'date',
        'reviewed_at'  => 'datetime',
        'value'        => 'float',
    ];

    public function assignment()
    {
        return $this->belongsTo(KpiAssignment::class, 'kpi_assignment_id');
    }

    public function check()
    {
        return $this->belongsTo(KpiCheck::class, 'kpi_check_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    public function scopeAwaitingReview($q)
    {
        return $q->where('status', self::SUBMITTED);
    }
}
