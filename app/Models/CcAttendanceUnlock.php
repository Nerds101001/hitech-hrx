<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * An admin-opened window in which back-dated salesperson attendance may still
 * be filled, overriding the normal deadline (12:00 PM on the 2nd working day
 * after the date - CcAttendanceController::lockDeadline()).
 *
 * @see \App\Http\Controllers\tenant\CcAttendanceController::isLockedFor()
 */
class CcAttendanceUnlock extends Model implements AuditableContract
{
    use Auditable;

    /** A single window may not exceed this many days — a guard against someone
     *  typing 2020 and silently reopening years of closed attendance. */
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

    /** Does this window let $userId fill attendance dated $date? */
    public function covers(string $date, ?int $userId): bool
    {
        if (!$this->is_active) {
            return false;
        }
        if ($date < $this->from_date->toDateString() || $date > $this->to_date->toDateString()) {
            return false;
        }
        if ($this->scope === 'all') {
            return true;
        }
        if ($userId === null) {
            return false;
        }
        // Cast both sides to int before comparing: user_ids can hold strings
        // for windows saved before storeUnlock() started normalizing them, and
        // a strict in_array() against $user->id (int) silently never matched,
        // leaving a "selected" window that never actually unlocked anything.
        return in_array((int) $userId, array_map('intval', $this->user_ids ?? []), true);
    }

    /** Inclusive length in days, as the UI reports it. */
    public function getDayCountAttribute(): int
    {
        return $this->from_date->diffInDays($this->to_date) + 1;
    }

    /**
     * Badge state. NOTE this is not "has today passed the end date" — a
     * back-fill window's dates are always in the past by design, so treating a
     * past to_date as expired would show "finished" on a window that is very
     * much still unlocking those dates. An active window is working, full stop;
     * only a window whose dates have not been reached yet is merely scheduled.
     */
    public function getPhaseAttribute(): string
    {
        if (!$this->is_active) return 'disabled';
        return now()->toDateString() < $this->from_date->toDateString() ? 'scheduled' : 'unlocking';
    }
}
