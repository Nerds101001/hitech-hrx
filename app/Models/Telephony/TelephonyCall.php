<?php

namespace App\Models\Telephony;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelephonyCall extends Model
{
  use HasFactory;

  protected $table = 'telephony_call_records';
  protected $guarded = ['id'];

  protected $casts = [
    'initiated_at' => 'datetime',
    'agent_ring_at' => 'datetime',
    'agent_answer_at' => 'datetime',
    'customer_ring_at' => 'datetime',
    'answered_at' => 'datetime',
    'ended_at' => 'datetime',
    'wrapup_start_at' => 'datetime',
    'wrapup_end_at' => 'datetime',
    'queue_enter_at' => 'datetime',
    'queue_exit_at' => 'datetime',
    'callback_at' => 'datetime',
    'hold_events' => 'array',
    'quality' => 'array',
    'cost' => 'array',
    'was_transferred' => 'boolean',
    'was_conferenced' => 'boolean',
    'was_forwarded' => 'boolean',
    'has_recording' => 'boolean',
    'is_unassigned' => 'boolean',
    'is_complete' => 'boolean',
    'via_reconciliation' => 'boolean',
  ];

  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }

  public function events(): HasMany
  {
    return $this->hasMany(TelephonyCallEvent::class, 'call_uid', 'call_uid');
  }

  public function legs(): HasMany
  {
    return $this->hasMany(TelephonyCallLeg::class, 'call_uid', 'call_uid');
  }

  public function recordings(): HasMany
  {
    return $this->hasMany(TelephonyRecording::class, 'call_uid', 'call_uid');
  }

  /**
   * Scope a query to what the given user is allowed to see.
   *
   * Deliberately does NOT filter on tenant_id. Every user row on this install
   * carries tenant_id NULL, and `where('tenant_id', null)` compiles to
   * `tenant_id = NULL`, which is never true in SQL - so a tenant filter here
   * would return zero rows for every user and render every call report as a
   * silent, error-free "0". App\Traits\TenantTrait documents the same finding
   * and has had its global scope disabled since 2026-08-01 for this reason.
   *
   * Rows are still WRITTEN with a tenant_id (see config/telephony.php), so the
   * unique indexes keep working and real multi-tenancy can be reinstated here
   * and in TenantTrait together.
   */
  public function scopeVisibleTo($query, User $user)
  {
    if ($user->hasAnyRole(['admin', 'hr'])) {
      return $query;
    }

    if ($user->hasRole('manager')) {
      // managerIdentityIds() covers delegated vacant positions too, which is
      // how the rest of HRX scopes manager views.
      $teamIds = User::whereIn('reporting_to_id', $user->managerIdentityIds())
        ->pluck('id')
        ->push($user->id)
        ->unique();

      return $query->whereIn('user_id', $teamIds);
    }

    return $query->where('user_id', $user->id);
  }

  /** Mask all but the last four digits for roles that may not see full numbers. */
  public static function maskNumber(?string $number): string
  {
    if (!$number) {
      return '';
    }
    $len = strlen($number);
    if ($len <= 4) {
      return str_repeat('*', $len);
    }

    return substr($number, 0, max(0, $len - 8)) . str_repeat('*', min(4, $len - 4)) . substr($number, -4);
  }
}
