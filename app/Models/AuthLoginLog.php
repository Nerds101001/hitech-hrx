<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per authentication attempt. Append-only; deliberately NOT Auditable
 * (auditing the log would double every write and recurse). Has no updated_at.
 */
class AuthLoginLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'auth_login_logs';

    protected $fillable = [
        'user_id', 'email_input', 'guard', 'event',
        'successful', 'ip_address', 'user_agent', 'meta', 'created_at',
    ];

    protected $casts = [
        'successful' => 'boolean',
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
