<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

class RequestApproval extends Model
{
    use TenantTrait;

    protected $table = 'request_approvals';

    protected $fillable = [
        'approvable_id',
        'approvable_type',
        'status',
        'user_id',
        'remarks',
        'tenant_id',
    ];

    public function approvable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
