<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class AppraisalCycle extends Model implements AuditableContract
{
    use TenantTrait, SoftDeletes, Auditable;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'status',
        'tenant_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function goals()
    {
        return $this->hasMany(NinetyDayGoal::class);
    }

    public function scorecards()
    {
        return $this->hasMany(AppraisalScorecard::class);
    }
}
