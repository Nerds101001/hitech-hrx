<?php

namespace App\Http\Controllers\tenant;

use App\Enums\CallEndedBy;
use App\Http\Controllers\Controller;
use App\Models\TelephonyCall;
use App\Services\TelephonyCallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * HRX's own canonical call-control contract. Every response is the same
 * shape (TelephonyCall::toContract()) no matter which vendor eventually
 * sits behind TelephonyCallService — right now that's a local mock, so this
 * can be built and tested against before any real vendor adapter exists.
 */
class TelephonyCallController extends Controller
{
  public function __construct(private TelephonyCallService $calls)
  {
  }

  private function ownedBy(Request $request, TelephonyCall $call): bool
  {
    return $call->agent_id === $request->user()->id
      || $request->user()->hasRole(['admin', 'hr']);
  }

  private function notFound(): JsonResponse
  {
    return response()->json(['error' => 'call_not_found', 'message' => 'No call with that ID.'], 404);
  }

  public function initiate(Request $request): JsonResponse
  {
    $to = trim((string) $request->input('to'));

    if (!preg_match('/^\d{10,15}$/', $to)) {
      return response()->json(['error' => 'invalid_to', 'message' => "'to' must be 10 to 15 digits."], 422);
    }

    $call = $this->calls->initiate(
      $request->user(),
      $to,
      $request->input('campaign_id') ?: null,
      $request->input('idempotency_key') ?: null
    );

    return response()->json($call->toContract(), 201);
  }

  public function show(Request $request, string $callId): JsonResponse
  {
    $call = $this->calls->find($callId);

    if (!$call || !$this->ownedBy($request, $call)) {
      return $this->notFound();
    }

    return response()->json($call->toContract());
  }

  public function hangup(Request $request, string $callId): JsonResponse
  {
    $call = $this->calls->find($callId);

    if (!$call || !$this->ownedBy($request, $call)) {
      return $this->notFound();
    }

    return response()->json($this->calls->hangup($call, CallEndedBy::AGENT)->toContract());
  }

  /** Mock-only: simulate the customer hanging up first. Not part of the real vendor contract. */
  public function simulateCustomerHangup(Request $request, string $callId): JsonResponse
  {
    $call = $this->calls->find($callId);

    if (!$call || !$this->ownedBy($request, $call)) {
      return $this->notFound();
    }

    return response()->json($this->calls->simulateCustomerHangup($call)->toContract());
  }

  public function disposition(Request $request, string $callId): JsonResponse
  {
    $call = $this->calls->find($callId);

    if (!$call || !$this->ownedBy($request, $call)) {
      return $this->notFound();
    }

    $code = trim((string) $request->input('code'));

    if ($code === '') {
      return response()->json(['error' => 'invalid_code', 'message' => "'code' is required."], 422);
    }

    return response()->json(
      $this->calls->disposition($call, $code, $request->input('notes') ?: null, $request->user())->toContract()
    );
  }

  public function recording(Request $request, string $callId): JsonResponse
  {
    $call = $this->calls->find($callId);

    if (!$call || !$this->ownedBy($request, $call)) {
      return $this->notFound();
    }

    $url = $this->calls->recording($call);

    if (!$url) {
      return response()->json(['error' => 'no_recording', 'message' => 'No recording available for this call.'], 404);
    }

    return response()->json(['call_id' => $call->call_uuid, 'recording_url' => $url]);
  }

  public function redial(Request $request, string $callId): JsonResponse
  {
    $original = $this->calls->find($callId);

    if (!$original || !$this->ownedBy($request, $original)) {
      return $this->notFound();
    }

    return response()->json($this->calls->redial($original, $request->user())->toContract(), 201);
  }
}
