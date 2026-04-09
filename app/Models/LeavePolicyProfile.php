<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeavePolicyProfile extends Model
{
    use TenantTrait, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'saturday_off_config',
        'deduction_config',
        'tenant_id',
        'status',
    ];

    protected $casts = [
        'saturday_off_config' => 'array',
        'deduction_config' => 'array',
    ];

    public function rules()
    {
        return $this->hasMany(LeavePolicyProfileRule::class, 'profile_id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'leave_policy_profile_id');
    }
}
