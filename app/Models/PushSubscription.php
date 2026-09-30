<?php

namespace App\Models;

use App\Traits\TenantTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * A browser's Web Push subscription (standard VAPID push, not Firebase).
 * One row per endpoint -- the same user can hold several across devices.
 */
class PushSubscription extends Model implements AuditableContract
{
    use TenantTrait, Auditable;

    protected $fillable = [
        'tenant_id', 'user_id', 'endpoint', 'endpoint_hash',
        'p256dh', 'auth', 'user_agent', 'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
