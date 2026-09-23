<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class KpiCheck extends Model implements AuditableContract
{
    use TenantTrait, Auditable;

    protected $fillable = [
        'tenant_id',
        'kpi_assignment_id',
        'user_id',
        'period_start',
        'period_end',
        'due_date',
        'status',
        'completed_at',
        'achieved_value',
        'score',
        'remarks',
        'evaluated_by_id',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'achieved_value' => 'float',
        'score' => 'float',
    ];

    public function assignment()
    {
        return $this->belongsTo(KpiAssignment::class, 'kpi_assignment_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
