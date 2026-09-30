<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class SalespersonTarget extends Model implements AuditableContract
{
    use HasFactory, TenantTrait, Auditable;

    protected $fillable = [
        'tenant_id',
        'salesperson_id',
        'month_year',
        'super_kingo_target',
        'kingo_target',
        'bingo_target'
    ];

    public function salesperson()
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }
}
