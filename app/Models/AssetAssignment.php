<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;

class AssetAssignment extends Model
{
    use TenantTrait;

    protected $fillable = [
        'asset_id',
        'user_id',
        'assigned_by',
        'assigned_at',
        'returned_at',
        'condition_on_assignment',
        'condition_on_return',
        'notes',
        'tenant_id',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
