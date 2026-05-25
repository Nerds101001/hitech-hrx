<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class SalaryPolicy extends Model implements AuditableContract
{
    use Auditable, UserActionsTrait, TenantTrait, SoftDeletes;

    protected $table = 'salary_policies';

    protected $fillable = [
        'tenant_id',
        'name',
        'site_id',
        'month_calculation_mode',
        'basic_percentage',
        'hra_percentage',
        'ca_fixed',
        'medical_fixed',
        'edu_fixed',
        'pt_threshold',
        'pt_amount',
        'is_pt_applicable',
        'is_epf_applicable',
        'epf_rate',
        'is_esic_applicable',
        'esic_rate',
        'esic_threshold',
        'created_by_id',
        'updated_by_id',
    ];

    protected $casts = [
        'basic_percentage' => 'float',
        'hra_percentage' => 'float',
        'ca_fixed' => 'float',
        'medical_fixed' => 'float',
        'edu_fixed' => 'float',
        'pt_threshold' => 'float',
        'pt_amount' => 'float',
        'is_pt_applicable' => 'boolean',
        'is_epf_applicable' => 'boolean',
        'epf_rate' => 'float',
        'is_esic_applicable' => 'boolean',
        'esic_rate' => 'float',
        'esic_threshold' => 'float',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'salary_policy_id');
    }
}
