<?php

namespace App\Services\Telephony;

use App\Models\Telephony\TelephonyAgentMap;
use App\Models\Telephony\TelephonyAgentSession;
use App\Models\Telephony\TelephonyAgentStateChange;
use App\Models\Telephony\TelephonyCall;
use App\Models\Telephony\TelephonyCallEvent;
use App\Models\Telephony\TelephonyCallLeg;
use App\Models\Telephony\TelephonyRecording;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Turns one webhook payload into rows.
 *
 * Two rules run through everything here:
 *
 * 1. Late events must never clobber newer ones. Each call keeps the highest
 *    `sequence` it has seen and older arrivals only fill blanks.
 * 2. A call is never dropped. If no agent-map row matches, the call is stored
 *    with is_unassigned = true and shows on the exceptions screen. A dropped
 *    call is invisible; an unassigned one gets fixed.
 */
class CallEventNormalizer
{
  /**
   * @param  array<string,mixed>  $payload
   */
  public function handle(array $payload): void
  {
    $event = (string) ($payload['event'] ?? '');

    match (true) {
      str_starts_with($event, 'agent.') => $this->agentEvent($event, $payload),
      $event === 'recording.ready' => $this->recordingReady($payload),
      default => $this->callEvent($event, $payload),
    };
  }

  // ───────────────────────────────────────────────────────────── call events

  /**
   * @param  array<string,mixed>  $p
   */
  private function callEvent(string $event, array $p): void
  {
    $callUid = (string) ($p['call_uid'] ?? '');
    if ($callUid === '') {
      return;
    }

    $agent = (array) ($p['agent'] ?? []);
    $map = TelephonyAgentMap::resolve($agent);
    $tenantId = $map?->tenant_id ?? config('telephony.inbound.default_tenant_id');
    $sequence = (int) ($p['sequence'] ?? 0);

    DB::transaction(function () use ($event, $p, $callUid, $agent, $map, $tenantId, $sequence) {
      // Append-only log first. The unique index on (tenant_id, event_id) is the
      // real idempotency guard, so a duplicate that races past the controller
      // check still cannot be written twice.
      TelephonyCallEvent::firstOrCreate(
        ['tenant_id' => $tenantId, 'event_id' => $p['event_id']],
        [
          'call_uid' => $callUid,
          'event_type' => $event,
          'sequence' => $sequence,
          // A CDR replayed by telephony:reconcile carries no occurred_at of its
          // own, so fall back to when the call actually ended before giving up
          // and stamping now(). The column is NOT NULL.
          'occurred_at' => $this->time($p['occurred_at'] ?? null)
            ?? $this->time($p['timestamps']['ended_at'] ?? null)
            ?? now(),
          'payload' => $p,
        ]
      );

      $call = TelephonyCall::firstOrNew([
        'tenant_id' => $tenantId,
        'call_uid' => $callUid,
      ]);

      $isNewer = $sequence >= (int) ($call->last_sequence ?? 0);

      $attrs = array_filter([
        'ref_id' => $p['ref_id'] ?? null,
        'parent_call_uid' => $p['parent_call_uid'] ?? null,
        'user_id' => $map?->user_id,
        'agent_id' => $agent['agent_id'] ?? null,
        'agent_code' => $agent['agent_code'] ?? null,
        'agent_name' => $agent['agent_name'] ?? null,
        'extension' => $agent['extension'] ?? null,
        'agent_number' => $agent['agent_number'] ?? null,
        'origin' => $p['origin'] ?? null,
        'direction' => $p['direction'] ?? null,
        'mode' => $p['mode'] ?? null,
        'caller_number' => $p['caller_number'] ?? null,
        'callee_number' => $p['callee_number'] ?? null,
        'did_number' => $p['did_number'] ?? null,
        'campaign_id' => $p['campaign']['campaign_id'] ?? null,
        'campaign_name' => $p['campaign']['campaign_name'] ?? null,
        'queue' => $p['campaign']['queue'] ?? null,
        'skill' => $p['campaign']['skill'] ?? null,
        'lead_source' => $p['campaign']['lead_source'] ?? null,
        'lead_id' => $p['customer']['lead_id'] ?? null,
        'customer_name' => $p['customer']['name'] ?? null,
        'gateway_id' => $p['gateway']['gateway_id'] ?? null,
        'channel' => $p['gateway']['channel'] ?? null,
        'sim_msisdn' => $p['gateway']['sim_msisdn'] ?? null,
        'operator' => $p['gateway']['operator'] ?? null,
      ], fn ($v) => $v !== null && $v !== '');

      foreach ($attrs as $field => $value) {
        // A newer event may correct a value; an older one may only fill a hole.
        if ($isNewer || $call->{$field} === null || $call->{$field} === '') {
          $call->{$field} = $value;
        }
      }

      // Only an event that actually names an agent may decide this. Plenty of
      // legitimate events carry no agent block at all (call.forwarded,
      // queue.enqueued, conference legs); recomputing on those flipped an
      // already-resolved call back to unassigned and pushed it onto the
      // exceptions screen for no reason.
      if ($agent !== []) {
        $call->is_unassigned = $map === null;
      } elseif ($call->user_id === null) {
        $call->is_unassigned = true;
      }

      if ($event === 'call.completed') {
        $this->applyCompletion($call, $p);
      } elseif ($isNewer) {
        $this->applyLifecycleTimestamps($call, $event, $p);
      }

      if ($sequence > (int) ($call->last_sequence ?? 0)) {
        $call->last_sequence = $sequence;
      }

      $call->save();

      $map?->forceFill(['last_seen_at' => now()])->saveQuietly();

      $this->applyLegs($event, $p, $call, $tenantId);
    });
  }

  /**
   * @param  array<string,mixed>  $p
   */
  private function applyLifecycleTimestamps(TelephonyCall $call, string $event, array $p): void
  {
    $at = $this->time($p['occurred_at'] ?? null);

    match ($event) {
      'call.originated' => $call->initiated_at ??= $at,
      'call.agent_ringing' => $call->agent_ring_at ??= $at,
      'call.agent_answered' => $call->agent_answer_at ??= $at,
      'call.customer_ringing' => $call->customer_ring_at ??= $at,
      'call.answered' => $call->answered_at ??= $this->time($p['answered_at'] ?? $p['occurred_at'] ?? null),
      'queue.enqueued' => $call->queue_enter_at ??= $at,
      'queue.dequeued' => $call->queue_exit_at ??= $at,
      'queue.abandoned', 'ivr.abandoned' => $this->applyAbandon($call, $p, $at),
      default => null,
    };

    if (in_array($event, ['call.transfer.completed', 'call.transfer.initiated'], true)) {
      $call->was_transferred = true;
    }
    if (str_starts_with($event, 'call.conference.')) {
      $call->was_conferenced = true;
    }
    if ($event === 'call.forwarded') {
      $call->was_forwarded = true;
    }
  }

  /**
   * @param  array<string,mixed>  $p
   */
  private function applyAbandon(TelephonyCall $call, array $p, ?Carbon $at): void
  {
    $call->status = 'abandoned';
    $call->queue_exit_at ??= $at;
    $call->ended_at ??= $at;
    // telephony:reconcile windows the day on initiated_at. An abandoned call
    // that never gets one is invisible to every local count, so the nightly
    // run reports a gap that backfilling can never close. Fall back to when
    // the caller entered the queue, or to the abandon itself.
    $call->initiated_at ??= $call->queue_enter_at ?? $at;
    $call->queue_wait_seconds = (int) ($p['wait_seconds'] ?? $call->queue_wait_seconds);
    $call->queue = $p['queue_name'] ?? $call->queue;
    $call->is_complete = true;
  }

  /**
   * The full CDR. This always wins over anything set by a lifecycle event.
   *
   * @param  array<string,mixed>  $p
   */
  private function applyCompletion(TelephonyCall $call, array $p): void
  {
    $t = (array) ($p['timestamps'] ?? []);
    $d = (array) ($p['durations'] ?? []);
    $disp = (array) ($p['disposition'] ?? []);
    $rec = (array) ($p['recording'] ?? []);

    $call->status = $p['status'] ?? $call->status;
    $call->hangup_by = $p['hangup_by'] ?? null;
    $call->hangup_cause = $p['hangup_cause'] ?? null;
    $call->hangup_cause_code = $p['hangup_cause_code'] ?? null;

    foreach ([
      'initiated_at', 'agent_ring_at', 'agent_answer_at', 'customer_ring_at',
      'answered_at', 'ended_at', 'wrapup_start_at', 'wrapup_end_at',
      'queue_enter_at', 'queue_exit_at',
    ] as $field) {
      if (!empty($t[$field])) {
        $call->{$field} = $this->time($t[$field]);
      }
    }

    foreach ([
      'agent_ring_seconds', 'customer_ring_seconds', 'pdd_seconds',
      'queue_wait_seconds', 'talk_seconds', 'hold_seconds', 'wrap_seconds',
      'total_seconds',
    ] as $field) {
      $call->{$field} = (int) ($d[$field] ?? 0);
    }
    $call->billed_seconds = isset($d['billed_seconds']) ? (int) $d['billed_seconds'] : null;

    $call->disposition_code = $disp['code'] ?? null;
    $call->disposition_name = $disp['name'] ?? null;
    $call->sub_disposition_code = $disp['sub_code'] ?? null;
    $call->sub_disposition_name = $disp['sub_name'] ?? null;
    $call->remarks = $disp['remarks'] ?? null;
    $call->callback_at = !empty($disp['callback_at']) ? $this->time($disp['callback_at']) : null;

    $call->was_transferred = (bool) ($p['was_transferred'] ?? $call->was_transferred);
    $call->was_conferenced = (bool) ($p['was_conferenced'] ?? $call->was_conferenced);
    $call->was_forwarded = (bool) ($p['was_forwarded'] ?? $call->was_forwarded);
    $call->has_recording = (bool) ($rec['available'] ?? $call->has_recording);

    $call->hold_events = $p['hold_events'] ?? null;
    $call->quality = $p['quality'] ?? null;
    $call->cost = $p['cost'] ?? null;

    $call->via_reconciliation = (bool) ($p['via_reconciliation'] ?? false);
    $call->is_complete = true;

    // The CDR and the audio in one event.
    //
    // recording.ready as a separate call is still the documented route and
    // still works, but a gateway that already knows the recording location at
    // hangup should not have to make a second request to say so -- and every
    // event a vendor is asked to send separately is an event that arrives late
    // or not at all. If the completion carries a recording_uid, treat the
    // block as a recording.ready and store it.
    if (!empty($rec['recording_uid'])) {
      $this->recordingReady($rec + [
        'call_uid' => $call->call_uid,
        'occurred_at' => $rec['occurred_at'] ?? ($t['ended_at'] ?? null),
      ]);

      // recordingReady() sets has_recording on its own instance of this call,
      // which the caller's save() would then overwrite with whatever
      // `recording.available` happened to say. A recording_uid arriving here
      // is itself the proof, so set it on the instance being saved.
      if (($rec['recording_type'] ?? 'call') === 'call') {
        $call->has_recording = true;
      }
    }
  }

  /**
   * @param  array<string,mixed>  $p
   */
  private function applyLegs(string $event, array $p, TelephonyCall $call, ?string $tenantId): void
  {
    if (!empty($p['transfer'])) {
      $tr = (array) $p['transfer'];
      TelephonyCallLeg::updateOrCreate(
        [
          'tenant_id' => $tenantId,
          'call_uid' => $call->call_uid,
          'leg_type' => 'transfer',
          'child_call_uid' => $tr['child_call_uid'] ?? null,
        ],
        [
          'sub_type' => $tr['type'] ?? null,
          'from_agent_id' => $tr['from_agent_id'] ?? null,
          'to_agent_id' => $tr['to_agent_id'] ?? null,
          'to_extension' => $tr['to_extension'] ?? null,
          'to_number' => $tr['to_external_number'] ?? null,
          'to_queue' => $tr['to_queue'] ?? null,
          'started_at' => $this->time($tr['transferred_at'] ?? null),
          'consult_seconds' => $tr['consult_seconds'] ?? null,
          'recording_uid' => $tr['recording_uid'] ?? null,
        ]
      );
    }

    if (!empty($p['conference'])) {
      $cf = (array) $p['conference'];
      TelephonyCallLeg::updateOrCreate(
        [
          'tenant_id' => $tenantId,
          'call_uid' => $call->call_uid,
          'leg_type' => 'conference',
          'conference_id' => $cf['conference_id'] ?? null,
        ],
        [
          'participants' => $cf['participants'] ?? null,
          'started_at' => $this->time($cf['started_at'] ?? null),
          'ended_at' => $this->time($cf['ended_at'] ?? null),
          'recording_uid' => $cf['recording_uid'] ?? null,
        ]
      );
    }

    if (!empty($p['forwarding'])) {
      $fw = (array) $p['forwarding'];
      TelephonyCallLeg::updateOrCreate(
        [
          'tenant_id' => $tenantId,
          'call_uid' => $call->call_uid,
          'leg_type' => 'forward',
          'child_call_uid' => $fw['child_call_uid'] ?? null,
        ],
        [
          'sub_type' => $fw['type'] ?? null,
          'from_number' => $fw['from_number'] ?? null,
          'to_number' => $fw['to_number'] ?? null,
          'started_at' => $this->time($fw['forwarded_at'] ?? null),
        ]
      );
    }
  }

  // ────────────────────────────────────────────────────────────── recordings

  /**
   * @param  array<string,mixed>  $p
   */
  private function recordingReady(array $p): void
  {
    $call = TelephonyCall::where('call_uid', $p['call_uid'])->first();
    $tenantId = $call?->tenant_id ?? config('telephony.inbound.default_tenant_id');

    // A vendor that cannot expose a signed-URL endpoint pushes the location
    // here instead. Accept either spelling: url is what most gateways call it,
    // recording_url is what our own documentation asks for, and a vendor that
    // sends the wrong one should not silently lose their audio.
    $url = $p['recording_url'] ?? $p['url'] ?? null;

    // Optional fields are dropped when absent rather than written as null, so
    // a later partial event -- a refreshed URL, say -- cannot blank out
    // metadata the first one already established.
    $optional = array_filter([
      'recording_url' => $url,
      'url_expires_at' => $this->time($p['url_expires_at'] ?? null),
      'duration_seconds' => $p['duration_seconds'] ?? null,
      'format' => $p['format'] ?? null,
      'size_bytes' => $p['size_bytes'] ?? null,
      'channels' => $p['channels'] ?? null,
      'checksum_sha256' => $p['checksum_sha256'] ?? null,
      'retention_until' => $p['retention_until'] ?? null,
      'ready_at' => $this->time($p['occurred_at'] ?? null),
    ], fn ($v) => $v !== null);

    TelephonyRecording::updateOrCreate(
      ['tenant_id' => $tenantId, 'recording_uid' => $p['recording_uid']],
      array_merge($optional, [
        'call_uid' => $p['call_uid'],
        'recording_type' => $p['recording_type'] ?? 'call',
      ])
    );

    // Only the main call recording flips the flag; a transfer-leg or conference
    // recording belongs to its leg, not to the parent call's play button.
    if ($call && ($p['recording_type'] ?? 'call') === 'call') {
      $call->forceFill(['has_recording' => true])->save();
    }
  }

  // ─────────────────────────────────────────────────────────── agent events

  /**
   * @param  array<string,mixed>  $p
   */
  private function agentEvent(string $event, array $p): void
  {
    $agent = (array) ($p['agent'] ?? []);
    $map = TelephonyAgentMap::resolve($agent);
    $tenantId = $map?->tenant_id ?? config('telephony.inbound.default_tenant_id');
    $sessionId = $p['session_id'] ?? null;

    if ($event === 'agent.state' || str_starts_with($event, 'agent.break.')) {
      $state = $p['state'] ?? (str_ends_with($event, 'start') ? 'break' : 'ready');

      TelephonyAgentStateChange::create([
        'tenant_id' => $tenantId,
        'session_id' => $sessionId,
        'user_id' => $map?->user_id,
        'agent_id' => $agent['agent_id'] ?? null,
        'state' => $state,
        'previous_state' => $p['previous_state'] ?? null,
        'reason' => $p['reason'] ?? null,
        'occurred_at' => $this->time($p['occurred_at'] ?? null),
      ]);

      $this->closePreviousState($tenantId, $agent['agent_id'] ?? null, $this->time($p['occurred_at'] ?? null));

      return;
    }

    if (!$sessionId) {
      return;
    }

    $totals = (array) ($p['totals'] ?? []);

    $session = TelephonyAgentSession::firstOrNew([
      'tenant_id' => $tenantId,
      'session_id' => $sessionId,
    ]);

    $session->fill(array_filter([
      'user_id' => $map?->user_id,
      'agent_id' => $agent['agent_id'] ?? null,
      'agent_code' => $agent['agent_code'] ?? null,
      'extension' => $agent['extension'] ?? null,
      'agent_number' => $agent['agent_number'] ?? null,
      'mode' => $p['mode'] ?? null,
      'campaign_id' => $p['campaign']['campaign_id'] ?? null,
      'campaign_name' => $p['campaign']['campaign_name'] ?? null,
      'device' => $p['device'] ?? null,
    ], fn ($v) => $v !== null));

    if ($event === 'agent.login') {
      $session->login_at = $this->time($p['login_at'] ?? $p['occurred_at'] ?? null);
      $session->is_open = true;
    }

    if ($event === 'agent.logout') {
      $session->logout_at = $this->time($p['logout_at'] ?? $p['occurred_at'] ?? null);
      $session->is_open = false;

      // Take the vendor's own totals rather than deriving them, so HRX and the
      // dialer User Session report cannot disagree.
      foreach ([
        'login_seconds', 'ready_seconds', 'talk_seconds', 'hold_seconds',
        'wrap_seconds', 'idle_seconds', 'break_seconds', 'calls_handled',
        'calls_missed',
      ] as $field) {
        $session->{$field} = (int) ($totals[$field] ?? 0);
      }
      $session->occupancy_percent = $totals['occupancy_percent'] ?? null;
      $session->breaks = $p['breaks'] ?? null;
    }

    $session->save();
  }

  private function closePreviousState(?string $tenantId, ?string $agentId, ?Carbon $at): void
  {
    if (!$agentId || !$at) {
      return;
    }

    $previous = TelephonyAgentStateChange::where('tenant_id', $tenantId)
      ->where('agent_id', $agentId)
      ->whereNull('duration_seconds')
      ->where('occurred_at', '<', $at)
      ->orderByDesc('occurred_at')
      ->first();

    if ($previous) {
      $previous->forceFill([
        'duration_seconds' => max(0, $at->diffInSeconds($previous->occurred_at)),
      ])->save();
    }
  }

  /**
   * Payloads carry an explicit offset, which Carbon honours; Laravel then
   * stores the value in app.timezone (Asia/Kolkata here). A naive string falls
   * back to the gateway's configured timezone rather than to whatever the PHP
   * process happens to be set to.
   */
  private function time(mixed $value): ?Carbon
  {
    if (empty($value)) {
      return null;
    }

    $hasOffset = (bool) preg_match('/(Z|[+-]\d{2}:?\d{2})$/', (string) $value);

    $parsed = $hasOffset
      ? Carbon::parse((string) $value)
      : Carbon::parse((string) $value, config('telephony.vendor_timezone'));

    // Laravel formats a datetime in whatever timezone the Carbon instance
    // carries - it does not convert to app.timezone on save. Without this line
    // a payload sent as 09:28:22Z would be written as the literal string
    // 09:28:22 and later read back as 09:28:22 IST, shifting every call by
    // five and a half hours with nothing to indicate anything went wrong.
    return $parsed->setTimezone(config('app.timezone'));
  }
}
