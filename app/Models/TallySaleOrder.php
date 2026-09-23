<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TallySaleOrder extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['order_date' => 'date', 'quantity' => 'float', 'amount' => 'float'];
}
