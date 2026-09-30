<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class JobCategory extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = [
        'title',
        'created_by',
    ];
}
