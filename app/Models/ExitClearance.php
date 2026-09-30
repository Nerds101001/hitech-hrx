<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * One department's No-Objection Certificate for a leaver.
 */
class ExitClearance extends Model implements AuditableContract
{
    use Auditable;

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

    /**
     * May this user sign this particular clearance?
     *
     * The single source of truth: the tracker page, the save endpoint and the
     * My Tasks queue all ask this, so what a user is offered is exactly what
     * they are allowed to do.
     *
     * Note this is deliberately independent of roles beyond HR/admin — a
     * leaver's reporting manager signs their step whatever role they hold,
     * which is usually just 'employee'.
     */
    public function isSignableBy($user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->hasRole(['admin', 'Admin', 'hr', 'HR'])) {
            return true;
        }

        $step = config('hr_notifications.exit_clearances.' . $this->step_key);
        if (!$step) {
            return false;
        }

        if (($step['resolve'] ?? null) === 'reporting_manager') {
            return $this->user && (int) $this->user->reporting_to_id === (int) $user->id;
        }

        return !empty($step['department_id'])
            && (int) $user->department_id === (int) $step['department_id'];
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
