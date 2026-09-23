<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One item line from Tally's SaleReportCRM export (HSN, GST %, rate, taxable value). */
class TallySaleLine extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'gst_rate' => 'float',
        'quantity' => 'float',
        'rate' => 'float',
        'taxable_amount' => 'float',
        'tax_amount' => 'float',
        'is_branch_transfer' => 'boolean',
    ];
}
