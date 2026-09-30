<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * An Admin/HR-opened window in which leave may be applied for dates older than
 * the one-week back-dating limit. The leave twin of CcAttendanceUnlock.
 *
 * @see \App\Support\LeaveBackdate::error()
 */
class LeaveUnlock extends Model implements AuditableContract
{
    use Auditable;

    /** A single window may not exceed this many days - a guard against someone
     *  typing 2020 and silently reopening years of closed leave. */
    public const MAX_DAYS = 30;

    protected $fillable = [
        'from_date', 'to_date', 'scope', 'user_ids', 'is_active', 'reason', 'created_by',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date'   => 'date',
        'user_ids'  => 'array',
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Is this window open to $userId at all (ignoring dates)? */
    public function appliesTo(?int $userId): bool
    {
        if (!$this->is_active) {
            return false;
        }
        if ($this->scope === 'all') {
            return true;
        }

        return $userId !== null && in_array((int) $userId, array_map('intval', $this->user_ids ?? []), true);
    }

    /** Does this window let $userId apply for leave on $date (Y-m-d)? */
    public function covers(string $date, ?int $userId): bool
    {
        return $this->appliesTo($userId)
            && $date >= $this->from_date->toDateString()
            && $date <= $this->to_date->toDateString();
    }

    /** Inclusive length in days, as the UI reports it. */
    public function getDayCountAttribute(): int
    {
        return $this->from_date->diffInDays($this->to_date) + 1;
    }

    /** Badge state - an active window is unlocking, unless its dates are still ahead. */
    public function getPhaseAttribute(): string
    {
        if (!$this->is_active) return 'disabled';
        return now()->toDateString() < $this->from_date->toDateString() ? 'scheduled' : 'unlocking';
    }
}
