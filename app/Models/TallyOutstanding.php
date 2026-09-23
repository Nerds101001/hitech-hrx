<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class TallyOutstanding extends Model implements AuditableContract
{
    use Auditable;

    public $timestamps = false;
    protected $table = 'tally_outstanding';
    protected $guarded = ['id'];
    protected $casts = ['bill_date' => 'date', 'amount' => 'float'];
}
