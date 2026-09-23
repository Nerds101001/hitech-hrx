<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class TallyInvoiceLine extends Model implements AuditableContract
{
    use Auditable;

    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['invoice_date' => 'date', 'quantity' => 'float'];
}
