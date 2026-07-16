<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class KpiAssignment extends Model implements AuditableContract
{
    use Auditable, TenantTrait;

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
    ];

    protected $casts = [
        'max_marks' => 'float',
        'late_score' => 'float',
        'target_value' => 'float',
        'is_active' => 'boolean',
    ];

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
