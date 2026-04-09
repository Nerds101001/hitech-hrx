<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

class LeaveBalance extends Model
{
    use TenantTrait;

    protected $fillable = [
        'user_id',
        'leave_type_id',
        'balance',
        'used',
        'tenant_id',
    ];

    protected $casts = [
        'balance' => 'float',
        'used'    => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }
}
