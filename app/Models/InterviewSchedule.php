<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class InterviewSchedule extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = [
        'candidate',
        'employee',
        'date',
        'time',
        'comment',
        'employee_response',
        'created_by',
    ];

    public function applications()
    {
       return $this->hasOne('App\Models\JobApplication','id','candidate');
    }

    public function users()
    {
        return $this->hasOne('App\Models\User', 'id', 'employee');
    }
}
