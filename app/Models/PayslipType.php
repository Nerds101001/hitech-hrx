<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class PayslipType extends Model implements AuditableContract
{
    use UserActionsTrait, TenantTrait, Auditable;

    protected $fillable = [
        'name',
        'tenant_id',
        'created_by_id',
    ];
}
