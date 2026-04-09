<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;

class PayslipType extends Model
{
    use UserActionsTrait, TenantTrait;

    protected $fillable = [
        'name',
        'tenant_id',
        'created_by_id',
    ];
}
