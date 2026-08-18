<?php

namespace App\Services;

use App\Enums\CallEndedBy;
use App\Enums\CallOrigin;
use App\Enums\TelephonyCallStatus;
use App\Models\TelephonyCall;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Mock implementation of HRX's canonical call contract. No real vendor is
 * wired in — outcomes are derived deterministically from the destination
 * number's last digit, so the full contract (busy / no-answer / invalid /
 * connected) can be exercised locally before any adapter exists:
 *
 *   ...7  -> busy
 *   ...8  -> no_answer
 *   ...9  -> invalid_number
 *   else  -> connects
 *
 * A real vendor adapter (Convoque, the current dialer, or anything else)
 * replaces the body of these methods only. TelephonyCall::toContract() —
 * every field, every status value — never changes underneath the caller.
 */
class TelephonyCallService
{
  public function initiate(User $agent, string $to, ?string $campaignId, ?string $idempotencyKey): TelephonyCall
  {
    if ($idempotencyKey) {
      $existing = TelephonyCall::where('idempotency_key', $idempotencyKey)->first();
      if ($existing) {
        return $existing;
      }
    }

    $call = TelephonyCall::create([
      'call_uuid'       => (string) Str::uuid(),
      'vendor'          => 'mock',
      'origin'          => CallOrigin::HRX,
      'agent_id'        => $agent->id,
      'campaign_id'     => $campaignId,
      'from_number'     => $agent->phone ?: 'agent-extension',
      'to_number'       => $to,
      'status'          => TelephonyCallStatus::RINGING,
      'queued_at'       => now(),
      'ringing_at'      => now(),
      'idempotency_key' => $idempotencyKey,
      'created_by_id'   => $agent->id,
    ]);

    $outcome = $this->mockOutcomeFor($to);

    if ($outcome === TelephonyCallStatus::CONNECTED) {
      $call->update([
        'status'       => TelephonyCallStatus::CONNECTED,
        'connected_at' => now(),
      ]);
    } else {
      $call->update([
        'status'   => $outcome,
        'ended_at' => now(),
      ]);
    }

    return $call->fresh();
  }

  private function mockOutcomeFor(string $to): TelephonyCallStatus
  {
    return match (substr($to, -1)) {
      '7'     => TelephonyCallStatus::BUSY,
      '8'     => TelephonyCallStatus::NO_ANSWER,
      '9'     => TelephonyCallStatus::INVALID_NUMBER,
      default => TelephonyCallStatus::CONNECTED,
    };
  }

  public function find(string $callUuid): ?TelephonyCall
  {
    return TelephonyCall::where('call_uuid', $callUuid)->first();
  }

  public function hangup(TelephonyCall $call, CallEndedBy $endedBy = CallEndedBy::AGENT, ?string $reason = null): TelephonyCall
  {
    if ($call->status === TelephonyCallStatus::ENDED) {
      return $call;
    }

    $duration = $call->connected_at ? now()->diffInSeconds($call->connected_at) : 0;

    $call->update([
      'status'     => TelephonyCallStatus::ENDED,
      'ended_by'   => $endedBy,
      'end_reason' => $reason,
      'ended_at'   => now(),
      'duration'   => $duration,
    ]);

    return $call->fresh();
  }

  /**
   * Mock-only convenience: simulate the customer disconnecting first — the
   * exact dead-zone scenario this whole contract exists to close. Not part
   * of the real vendor-facing spec; a real vendor pushes this as a webhook
   * instead of us calling it ourselves.
   */
  public function simulateCustomerHangup(TelephonyCall $call): TelephonyCall
  {
    return $this->hangup($call, CallEndedBy::CUSTOMER, 'simulated: customer disconnected');
  }

  public function disposition(TelephonyCall $call, string $code, ?string $notes, User $agent): TelephonyCall
  {
    $call->update([
      'disposition_code'  => $code,
      'disposition_notes' => $notes,
      'updated_by_id'      => $agent->id,
    ]);

    return $call->fresh();
  }

  public function recording(TelephonyCall $call): ?string
  {
    if ($call->status !== TelephonyCallStatus::ENDED || !$call->connected_at) {
      return null;
    }

    if (!$call->recording_url) {
      $call->update([
        'recording_url' => "https://mock-recordings.internal.test/{$call->call_uuid}.mp3?expires=" . now()->addHour()->timestamp,
      ]);
    }

    return $call->recording_url;
  }

  public function redial(TelephonyCall $original, User $agent): TelephonyCall
  {
    $call = $this->initiate($agent, $original->to_number, $original->campaign_id, null);
    $call->update(['redial_of_call_uuid' => $original->call_uuid]);

    return $call->fresh();
  }
}
