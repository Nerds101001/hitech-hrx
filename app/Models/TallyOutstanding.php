<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TallyOutstanding extends Model
{
    public $timestamps = false;
    protected $table = 'tally_outstanding';
    protected $guarded = ['id'];
    protected $casts = ['bill_date' => 'date', 'amount' => 'float'];
}
