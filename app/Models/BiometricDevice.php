<?php

namespace App\Models;

use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BiometricDevice extends Model
{
    use HasFactory, SoftDeletes, TenantTrait, UserActionsTrait;

    protected $fillable = [
        'name',
        'ip_address',
        'port',
        'site_id',
        'is_active',
        'last_sync_at',
        'status',
        'last_error',
        'created_by_id',
        'updated_by_id',
        'tenant_id',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }
}
