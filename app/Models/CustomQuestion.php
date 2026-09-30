<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class CustomQuestion extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = [
        'question',
        'type',
        'options',
        'is_required',
        'created_by',
    ];

    public static $is_required = [
        'yes' => 'Yes',
        'no' => 'No',
    ];
}
