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
        'bingo_target',
        // Entered by CCare / New Biz on the Target Achievement popup. Null means
        // nobody has filled it in yet, which is not the same as a recorded 0.
        'tally_sale',
        'tally_sale_by_id',
        'tally_sale_at',
    ];

    protected $casts = [
        'tally_sale'    => 'decimal:2',
        'tally_sale_at' => 'datetime',
    ];

    public function tallySaleBy()
    {
        return $this->belongsTo(User::class, 'tally_sale_by_id');
    }

    public function salesperson()
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }
}
