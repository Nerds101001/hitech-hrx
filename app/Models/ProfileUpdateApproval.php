<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProfileUpdateApproval extends Model
{
    use HasFactory, SoftDeletes, TenantTrait, UserActionsTrait;

    protected $fillable = [
        'user_id',
        'type',
        'requested_data',
        'status',
        'actioned_by_id',
        'remarks',
        'actioned_at',
        'tenant_id',
    ];

    protected $casts = [
        'requested_data' => 'array',
        'actioned_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function actionedBy()
    {
        return $this->belongsTo(User::class, 'actioned_by_id');
    }
}
