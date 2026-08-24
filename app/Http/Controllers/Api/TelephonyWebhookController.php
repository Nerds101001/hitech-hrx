<?php

namespace App\Http\Controllers\Api;

use App\Enums\CallEndedBy;
use App\Enums\CallOrigin;
use App\Enums\TelephonyCallStatus;
use App\Http\Controllers\Controller;
use App\Models\CrmClientActivity;
use App\Models\TelephonyCall;
use App\Models\User;
use App\Support\CrmPhoneMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Receives call-event pushes from a telephony vendor and folds them into
 * HRX's own canonical call record (see TelephonyCall::toContract()).
 *
 * A call HRX itself placed is matched by the call_id/refID we handed the
 * vendor. A call the vendor reports that HRX never placed -- someone
 * dialing manually, or any path outside the click-to-call button -- gets
 * a new record instead of being dropped, so "how many calls were made"
 * isn't limited to only the ones that went through our own button.
 * Authenticated by a shared secret, not a session — the caller is a
 * vendor's server, not a logged-in user.
 */
class TelephonyWebhookController extends Controller
{
  public function handle(Request $request): JsonResponse
  {
    $secret = $request->header('X-HRX-Webhook-Secret') ?? $request->input('secret');
    $expected = (string) config('services.telephony_webhook.secret');

    if ($expected === '' || !$secret || !hash_equals($expected, (string) $secret)) {
      Log::warning('Telephony webhook: rejected, bad/missing secret', ['ip' => $request->ip()]);

      return response()->json(['error' => 'unauthorized'], 401);
    }

    $payload = $request->all();
    $callId = $payload['call_id'] ?? $payload['ref_id'] ?? null;
    $event = $payload['event'] ?? null;

    if (!$callId || !$event) {
      return response()->json([
        'error'   => 'invalid_payload',
        'message' => "'call_id' (or 'ref_id') and 'event' are required.",
      ], 422);
    }

    // call_uuid first (a call HRX itself placed, correlated by the id we
    // generated) — vendor_ref second (a second+ event for an externally
    // discovered call, correlated by the vendor's own raw id instead).
    $call = TelephonyCall::where('call_uuid', $callId)->first()
      ?? TelephonyCall::where('vendor_ref', $callId)->first()
      ?? $this->createExternalCall($callId, $payload);

    $call->update(['raw_vendor_response' => $payload]);
    $this->applyEvent($call, $event, $payload);

    return response()->json(['received' => true, 'call_id' => $call->call_uuid]);
  }

  /**
   * A call the vendor knows about that HRX never initiated. call_uuid is
   * always our own freshly generated id, so the public contract stays a
   * clean UUID regardless of origin; vendor_ref holds the vendor's own raw
   * id so the *next* event for this same call finds this row instead of
   * creating a duplicate. agent_id is left null unless the 'from' number
   * matches a known user, since manual dialing carries no guarantee we can
   * identify who placed it.
   */
  private function createExternalCall(string $callId, array $payload): TelephonyCall
  {
    Log::info('Telephony webhook: no matching call, recording as external', ['vendor_ref' => $callId]);

    $fromNumber = $payload['from'] ?? null;
    $toNumber = $payload['to'] ?? null;
    $agent = $fromNumber ? User::where('phone', $fromNumber)->first() : null;

    return TelephonyCall::create([
      'call_uuid'             => (string) Str::uuid(),
      'vendor_ref'            => $callId,
      'vendor'                => $payload['vendor'] ?? 'unknown',
      'origin'                => CallOrigin::EXTERNAL,
      'agent_id'              => $agent?->id,
      'from_number'           => $fromNumber ?? 'unknown',
      'to_number'             => $toNumber ?? 'unknown',
      'crm_client_mapping_id' => CrmPhoneMatcher::resolve($fromNumber, $toNumber),
      'status'                => TelephonyCallStatus::RINGING,
      'queued_at'             => now(),
    ]);
  }

  private function applyEvent(TelephonyCall $call, string $event, array $payload): void
  {
    switch ($event) {
      case 'call.ringing':
        $call->update([
          'status'     => TelephonyCallStatus::RINGING,
          'ringing_at' => $call->ringing_at ?? now(),
        ]);
        break;

      case 'call.connected':
        $call->update([
          'status'       => TelephonyCallStatus::CONNECTED,
          'connected_at' => $call->connected_at ?? now(),
        ]);
        break;

      case 'call.busy':
      case 'call.no_answer':
      case 'call.invalid_number':
      case 'call.failed':
        $call->update([
          'status'             => match ($event) {
            'call.busy'           => TelephonyCallStatus::BUSY,
            'call.no_answer'      => TelephonyCallStatus::NO_ANSWER,
            'call.invalid_number' => TelephonyCallStatus::INVALID_NUMBER,
            'call.failed'         => TelephonyCallStatus::FAILED,
          },
          'ended_at'           => now(),
          'ring_seconds'       => isset($payload['ring_seconds']) ? (int) $payload['ring_seconds'] : $call->ring_seconds,
          'vendor_disposition' => $payload['vendor_disposition'] ?? $call->vendor_disposition,
        ]);
        break;

      case 'call.ended':
        // Carbon 3's diffInSeconds() is signed (direction-aware, not
        // absolute) and can return a fractional value — abs()+round()
        // guards against both, since duration is an unsigned integer column.
        $duration = $call->connected_at
          ? (int) round(abs($call->connected_at->diffInSeconds(now())))
          : (isset($payload['duration']) ? (int) $payload['duration'] : null);

        $call->update([
          'status'             => TelephonyCallStatus::ENDED,
          'ended_by'           => $this->mapEndedBy($payload['ended_by'] ?? null),
          'end_reason'         => $payload['end_reason'] ?? null,
          'ended_at'           => now(),
          'duration'           => $duration,
          'vendor_disposition' => $payload['vendor_disposition'] ?? $call->vendor_disposition,
        ]);
        $this->logToCustomerFeed($call);
        break;

      case 'call.completed':
        $call->update([
          'status'             => TelephonyCallStatus::ENDED,
          'duration'           => $payload['duration'] ?? $call->duration,
          'ring_seconds'       => isset($payload['ring_seconds']) ? (int) $payload['ring_seconds'] : $call->ring_seconds,
          'vendor_disposition' => $payload['vendor_disposition'] ?? $call->vendor_disposition,
          'recording_url'      => $payload['recording_url'] ?? $call->recording_url,
        ]);
        $this->logToCustomerFeed($call);
        break;

      default:
        Log::info('Telephony webhook: unrecognised event', ['event' => $event, 'call_id' => $call->call_uuid]);
    }
  }

  /**
   * The telephony half of the Customer 360 feed for calls that never went
   * through TelephonyCallController::disposition() at all -- there's no
   * agent-in-HRX to wrap this call up, so it's logged with whatever the
   * vendor itself reported. One last best-effort phone match first, in case
   * 'to'/'from' weren't both known yet when the row was first created.
   */
  private function logToCustomerFeed(TelephonyCall $call): void
  {
    if (!$call->crm_client_mapping_id) {
      $call->crm_client_mapping_id = CrmPhoneMatcher::resolve($call->from_number, $call->to_number);
      if ($call->crm_client_mapping_id) {
        $call->save();
      }
    }

    CrmClientActivity::logForCall($call, $call->agent);
  }

  private function mapEndedBy(?string $raw): ?CallEndedBy
  {
    return match ($raw) {
      'agent'    => CallEndedBy::AGENT,
      'customer' => CallEndedBy::CUSTOMER,
      'system'   => CallEndedBy::SYSTEM,
      default    => null,
    };
  }
}
