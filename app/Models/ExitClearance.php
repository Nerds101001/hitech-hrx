<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One department's No-Objection Certificate for a leaver.
 */
class ExitClearance extends Model
{
    protected $fillable = [
        'exit_type', 'exit_id', 'user_id', 'step_key', 'label',
        'status', 'remarks', 'cleared_by_id', 'cleared_at', 'tenant_id',
    ];

    protected $casts = ['cleared_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clearedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleared_by_id');
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    public function scopeForExit($q, string $type, int $id)
    {
        return $q->where('exit_type', $type)->where('exit_id', $id);
    }

    /** True when every clearance for this exit has been signed off. */
    public static function allCleared(string $type, int $id): bool
    {
        $rows = static::forExit($type, $id)->get();

        return $rows->isNotEmpty() && $rows->every(fn($r) => $r->status === 'cleared');
    }

    /** "2 of 4 cleared" — what HR sees on the tracker. */
    public static function progress(string $type, int $id): array
    {
        $rows    = static::forExit($type, $id)->get();
        $cleared = $rows->where('status', 'cleared')->count();

        return [
            'cleared' => $cleared,
            'total'   => $rows->count(),
            'blocked' => $rows->where('status', 'blocked')->count(),
            'label'   => $rows->count() ? "{$cleared} of {$rows->count()} cleared" : 'No clearances raised',
            'done'    => $rows->isNotEmpty() && $cleared === $rows->count(),
        ];
    }
}
