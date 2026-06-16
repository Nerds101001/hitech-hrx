<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OutdoorDuty extends Model
{
    use UserActionsTrait, TenantTrait, SoftDeletes;

    protected $table = 'outdoor_duties';

    protected $fillable = [
        'user_id',
        'date',
        'purpose',
        'location',
        'expected_return_time',
        'status',
        'approved_by_id',
        'rejected_by_id',
        'approved_at',
        'rejected_at',
        'approval_notes',
        'cancel_reason',
        'cancelled_at',
        'created_by_id',
        'updated_by_id',
        'tenant_id',
    ];

    protected $casts = [
        'date'         => 'date',
        'approved_at'  => 'datetime',
        'rejected_at'  => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by_id');
    }

    public function attendance()
    {
        return $this->hasOne(Attendance::class, 'outdoor_duty_id');
    }
}
