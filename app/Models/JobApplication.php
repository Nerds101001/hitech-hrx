<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class JobApplication extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = [
        'job',
        'name',
        'email',
        'phone',
        'profile',
        'resume',
        'cover_letter',
        'dob',
        'gender',
        'address',
        'country',
        'state',
        'city',
        'zip_code',
        'stage',
        'order',
        'skill',
        'rating',
        'is_archive',
        'custom_question',
        'created_by',
    ];

    public function jobs()
    {
        return $this->hasOne('App\Models\Job', 'id', 'job');
    }

    public function stage()
    {
        return $this->hasOne('App\Models\JobStage', 'id', 'stage');
    }
}
