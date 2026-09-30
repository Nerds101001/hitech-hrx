<?php

namespace App\Models;

use App\Enums\CrmActivityType;
use App\Traits\TenantTrait;
use App\Traits\UserActionsTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * No Auditable here (unlike CrmClientContact) -- this table already IS the
 * change/activity log; wrapping it in the generic field-change auditor
 * would just be a redundant second log of the same events.
 */
class CrmClientActivity extends Model implements AuditableContract
{
    use UserActionsTrait, TenantTrait, SoftDeletes, Auditable;

    protected $table = 'crm_client_activities';

    protected $fillable = [
        'crm_client_mapping_id',
        'type',
        'body',
        'telephony_call_id',
        'customer_contact_id',
        'our_user_id',
        'needs_follow_up',
        'follow_up_due_at',
        'follow_up_assigned_to_id',
        'follow_up_completed_at',
        'created_by_id',
        'tenant_id',
    ];

    protected $casts = [
        'type' => CrmActivityType::class,
        'needs_follow_up' => 'boolean',
        'follow_up_due_at' => 'datetime',
        'follow_up_completed_at' => 'datetime',
    ];

    public function crmClientMapping()
    {
        return $this->belongsTo(CrmClientMapping::class);
    }

    public function telephonyCall()
    {
        return $this->belongsTo(TelephonyCall::class);
    }

    public function followUpAssignedTo()
    {
        return $this->belongsTo(User::class, 'follow_up_assigned_to_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** The customer contact a hand-logged call was with (kept even if that contact is later deleted, so history still names them). */
    public function customerContact()
    {
        return $this->belongsTo(CrmClientContact::class, 'customer_contact_id')->withTrashed();
    }

    /** The person from our team who made or took a hand-logged call. */
    public function ourUser()
    {
        return $this->belongsTo(User::class, 'our_user_id');
    }

    /**
     * Auto-log a dispositioned call to its linked customer's feed -- shared
     * by TelephonyCallService::disposition() (an agent wrapping up a call
     * they placed through HRX) and TelephonyWebhookController (a call
     * reported by the vendor that HRX never placed, so there is no agent to
     * attribute notes to). Does nothing and returns null when the call
     * isn't linked to a customer -- most calls to unknown numbers never
     * will be, and that's fine, not an error.
     */
    public static function logForCall(
        TelephonyCall $call,
        ?User $actor,
        ?string $code = null,
        ?string $notes = null,
        bool $needsFollowUp = false,
        ?string $followUpDueAt = null
    ): ?self {
        if (!$call->crm_client_mapping_id) {
            return null;
        }

        // A vendor can report more than one terminal event for the same
        // call (call.ended then call.completed, or a retried disposition
        // request) -- never log the same call to the feed twice.
        $existing = self::where('telephony_call_id', $call->id)->where('type', CrmActivityType::CALL)->first();
        if ($existing) {
            return $existing;
        }

        $duration = $call->duration ? gmdate('i:s', $call->duration) : '0:00';
        $summary = sprintf(
            '%s — %s%s, duration %s',
            $actor ? $actor->getFullName() : 'System (vendor webhook)',
            $call->vendor_disposition ?: ($code ?: 'call ended'),
            $code && $call->vendor_disposition ? " ({$code})" : '',
            $duration
        );

        return self::create([
            'crm_client_mapping_id'    => $call->crm_client_mapping_id,
            'type'                     => CrmActivityType::CALL,
            'body'                     => $notes ? "{$summary}\n\n{$notes}" : $summary,
            'telephony_call_id'        => $call->id,
            'needs_follow_up'          => $needsFollowUp,
            'follow_up_due_at'         => $needsFollowUp ? $followUpDueAt : null,
            'follow_up_assigned_to_id' => $needsFollowUp && $actor ? $actor->id : null,
            'created_by_id'            => $actor?->id,
            'tenant_id'                => $call->tenant_id,
        ]);
    }
}
