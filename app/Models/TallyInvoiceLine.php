<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TallyInvoiceLine extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['invoice_date' => 'date', 'quantity' => 'float'];
}
