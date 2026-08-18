<?php

namespace App\Http\Controllers\Api;

use App\Enums\CallEndedBy;
use App\Enums\TelephonyCallStatus;
use App\Http\Controllers\Controller;
use App\Models\TelephonyCall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives call-event pushes from a telephony vendor and folds them into
 * HRX's own canonical call record (see TelephonyCall::toContract()).
 *
 * The vendor is expected to echo back the call_id we handed them when the
 * call was placed (as their own 'refID'/reference field) so an event can be
 * matched back to the call it belongs to. Authenticated by a shared secret,
 * not a session — the caller is a vendor's server, not a logged-in user.
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

    $call = TelephonyCall::where('call_uuid', $callId)->first();

    if (!$call) {
      Log::warning('Telephony webhook: no matching call', ['call_id' => $callId, 'event' => $event]);

      return response()->json(['error' => 'call_not_found'], 404);
    }

    $call->update(['raw_vendor_response' => $payload]);
    $this->applyEvent($call, $event, $payload);

    return response()->json(['received' => true, 'call_id' => $call->call_uuid]);
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
          'status'   => match ($event) {
            'call.busy'           => TelephonyCallStatus::BUSY,
            'call.no_answer'      => TelephonyCallStatus::NO_ANSWER,
            'call.invalid_number' => TelephonyCallStatus::INVALID_NUMBER,
            'call.failed'         => TelephonyCallStatus::FAILED,
          },
          'ended_at' => now(),
        ]);
        break;

      case 'call.ended':
        $call->update([
          'status'     => TelephonyCallStatus::ENDED,
          'ended_by'   => $this->mapEndedBy($payload['ended_by'] ?? null),
          'end_reason' => $payload['end_reason'] ?? null,
          'ended_at'   => now(),
          'duration'   => $call->connected_at
            ? now()->diffInSeconds($call->connected_at)
            : ($payload['duration'] ?? null),
        ]);
        break;

      case 'call.completed':
        $call->update([
          'status'        => TelephonyCallStatus::ENDED,
          'duration'      => $payload['duration'] ?? $call->duration,
          'recording_url' => $payload['recording_url'] ?? $call->recording_url,
        ]);
        break;

      default:
        Log::info('Telephony webhook: unrecognised event', ['event' => $event, 'call_id' => $call->call_uuid]);
    }
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
