<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HRPolicyAcknowledgment extends Model
{
    protected $table = 'hr_policy_acknowledgments';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'hr_policy_id',
        'acknowledged_at',
        'ip_address',
        'user_agent',
        'signature_data',
        'receipt_path'
    ];

    protected $casts = [
        'acknowledged_at' => 'datetime',
        'signature_data' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function policy()
    {
        return $this->belongsTo(HRPolicy::class, 'hr_policy_id');
    }
}
