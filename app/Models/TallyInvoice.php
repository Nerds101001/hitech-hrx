<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class TallyInvoice extends Model implements AuditableContract
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = [
        'invoice_date' => 'date',
        'amount' => 'float',
        'is_branch_transfer' => 'boolean',
        'extra' => 'array',
    ];

    /** Real customer sales only: not a branch transfer, and tied to a party. */
    public function scopeCustomerSales($query)
    {
        return $query->where('is_branch_transfer', false)->whereNotNull('party_code');
    }
}
