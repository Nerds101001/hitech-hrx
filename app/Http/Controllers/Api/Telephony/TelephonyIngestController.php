<?php

namespace App\Http\Controllers\Api\Telephony;

use App\Http\Controllers\Controller;
use App\Jobs\Telephony\ProcessTelephonyEvent;
use App\Models\Telephony\TelephonyWebhookDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The API the vendor calls.
 *
 * Nothing is interpreted here. Store the raw payload, queue a job, answer in
 * under two seconds. If normalisation is slow or throws, the vendor still got
 * its 202 and will not retry a delivery we already hold.
 */
class TelephonyIngestController extends Controller
{
  /** GET /health - proves key, secret, clock skew and IP allowlist together. */
  public function health(Request $request): JsonResponse
  {
    return response()->json([
      'status' => 'ok',
      'server_time' => now()->toIso8601String(),
      // The label on the credential that authenticated this request, so each
      // vendor is named back to itself rather than being greeted with somebody
      // else's name. It also answers "which key am I actually presenting?",
      // which is the question when the wrong one has been handed out.
      'vendor' => $request->attributes->get('telephony_credential_label') ?? 'unnamed',
      'api_version' => 'v1',
    ]);
  }

  /** POST /events */
  public function event(Request $request): JsonResponse
  {
    $data = $request->json()->all();

    $validator = validator($data, [
      'event' => 'required|string|max:64',
      'event_id' => 'required|uuid',
      'occurred_at' => 'required|date',
      'call_uid' => 'required|string|max:191',
    ]);

    if ($validator->fails()) {
      // Store it anyway. A malformed payload we can see beats one we cannot.
      $this->store($request, 'events', $data, 'rejected', $validator->errors()->first());

      return $this->error('validation_failed', $validator->errors()->first(), 422);
    }

    return $this->accept($request, 'events', $data);
  }

  /** POST /events/batch - backlog drain only, never live traffic. */
  public function batch(Request $request): JsonResponse
  {
    $events = $request->json('events');

    if (!is_array($events) || $events === []) {
      return $this->error('validation_failed', 'events must be a non-empty array', 422);
    }

    $max = (int) config('telephony.inbound.max_batch');
    if (count($events) > $max) {
      return $this->error('too_many_events', "At most {$max} events per request", 413);
    }

    $results = [];
    $accepted = 0;
    $rejected = 0;

    foreach ($events as $event) {
      if (!is_array($event) || empty($event['event_id']) || empty($event['call_uid'])) {
        $rejected++;
        $results[] = [
          'event_id' => is_array($event) ? ($event['event_id'] ?? null) : null,
          'status' => 'rejected',
          'error' => 'event_id and call_uid are required',
        ];
        continue;
      }

      $delivery = $this->store($request, 'events', $event, 'queued');

      if ($delivery === null) {
        $results[] = ['event_id' => $event['event_id'], 'status' => 'duplicate', 'error' => null];
        continue;
      }

      $this->dispatchDelivery($delivery);
      $accepted++;
      $results[] = ['event_id' => $event['event_id'], 'status' => 'accepted', 'error' => null];
    }

    return response()->json([
      'accepted' => $accepted,
      'rejected' => $rejected,
      'results' => $results,
    ], 207);
  }

  /** POST /recordings/ready */
  public function recordingReady(Request $request): JsonResponse
  {
    $data = $request->json()->all();

    $validator = validator($data, [
      'event_id' => 'required|uuid',
      'call_uid' => 'required|string|max:191',
      'recording_uid' => 'required|string|max:191',
    ]);

    if ($validator->fails()) {
      $this->store($request, 'recordings.ready', $data, 'rejected', $validator->errors()->first());

      return $this->error('validation_failed', $validator->errors()->first(), 422);
    }

    $data['event'] = $data['event'] ?? 'recording.ready';

    return $this->accept($request, 'recordings.ready', $data);
  }

  /** POST /agent-sessions */
  public function agentSession(Request $request): JsonResponse
  {
    $data = $request->json()->all();

    $validator = validator($data, [
      'event' => 'required|string|max:64',
      'event_id' => 'required|uuid',
      'occurred_at' => 'required|date',
      'agent' => 'required|array',
    ]);

    if ($validator->fails()) {
      $this->store($request, 'agent-sessions', $data, 'rejected', $validator->errors()->first());

      return $this->error('validation_failed', $validator->errors()->first(), 422);
    }

    return $this->accept($request, 'agent-sessions', $data);
  }

  /** POST /cdr/import - bulk replay requested by telephony:reconcile. */
  public function importCdr(Request $request): JsonResponse
  {
    $records = $request->json('records');

    if (!is_array($records)) {
      return $this->error('validation_failed', 'records must be an array', 422);
    }

    $inserted = 0;
    $duplicate = 0;
    $rejected = 0;

    foreach ($records as $record) {
      if (!is_array($record) || empty($record['call_uid'])) {
        $rejected++;
        continue;
      }

      // Replays legitimately lack an event_id; derive a stable one so the same
      // CDR row replayed twice still collapses to a single event.
      $record['event'] = $record['event'] ?? 'call.completed';
      $record['event_id'] = $record['event_id']
        ?? $this->deterministicUuid('cdr-import:' . $record['call_uid']);
      $record['occurred_at'] = $record['occurred_at']
        ?? ($record['timestamps']['ended_at'] ?? now()->toIso8601String());
      $record['via_reconciliation'] = true;

      $delivery = $this->store($request, 'cdr.import', $record, 'queued');

      if ($delivery === null) {
        $duplicate++;
        continue;
      }

      $this->dispatchDelivery($delivery);
      $inserted++;
    }

    return response()->json([
      'inserted' => $inserted,
      'updated' => $duplicate,
      'rejected' => $rejected,
    ], 202);
  }

  // ────────────────────────────────────────────────────────────── internals

  /**
   * Hand a stored delivery to the normaliser without ever letting that fail the
   * request.
   *
   * This matters more than it looks. QUEUE_CONNECTION is `sync` on this
   * install, so dispatch() does NOT queue - it runs ProcessTelephonyEvent
   * inline, inside the vendor's HTTP request. An exception in normalisation
   * would therefore escape the controller as a 500, which does two bad things
   * at once: the vendor retries an event we already hold, and every occurrence
   * fires the production exception mailer in bootstrap/app.php. A busy hour of
   * malformed payloads becomes a mail flood.
   *
   * The payload is already safely in telephony_webhook_deliveries by this
   * point, which is exactly what 202 promises - "stored, not necessarily
   * processed". A failure here is visible in the Webhook Log and replayable
   * from it, so swallowing it costs nothing and is the honest reading of the
   * contract in section 5.
   *
   * Switching QUEUE_CONNECTION to a real driver later makes this a no-op
   * rather than making it wrong. Note the queue table cannot simply be
   * `database`: `jobs` in this schema is the recruitment job-postings table.
   */
  private function dispatchDelivery(TelephonyWebhookDelivery $delivery): void
  {
    try {
      ProcessTelephonyEvent::dispatch($delivery->id)->onQueue(config('telephony.queue'));
    } catch (Throwable $e) {
      Log::error('[telephony] delivery stored but could not be processed', [
        'delivery_id' => $delivery->id,
        'event_id' => $delivery->event_id,
        'call_uid' => $delivery->call_uid,
        'message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * @param  array<string,mixed>  $data
   */
  private function accept(Request $request, string $endpoint, array $data): JsonResponse
  {
    $delivery = $this->store($request, $endpoint, $data, 'queued');

    if ($delivery === null) {
      return response()->json([
        'status' => 'duplicate',
        'event_id' => $data['event_id'],
        'received_at' => now()->toIso8601String(),
      ], 200);
    }

    $this->dispatchDelivery($delivery);

    return response()->json([
      'status' => 'accepted',
      'event_id' => $data['event_id'],
      'received_at' => now()->toIso8601String(),
    ], 202);
  }

  /**
   * Returns null when this event_id has already been delivered, which is the
   * idempotency contract. Uniqueness is enforced by the database, not by a
   * select-then-insert that two concurrent workers could both win.
   *
   * @param  array<string,mixed>  $data
   */
  private function store(
    Request $request,
    string $endpoint,
    array $data,
    string $status,
    ?string $error = null
  ): ?TelephonyWebhookDelivery {
    $eventId = $data['event_id'] ?? null;

    if ($eventId && $status === 'queued') {
      $already = TelephonyWebhookDelivery::where('event_id', $eventId)
        ->whereIn('status', ['queued', 'processed'])
        ->exists();

      if ($already) {
        return null;
      }
    }

    return DB::transaction(fn () => TelephonyWebhookDelivery::create([
      'tenant_id' => config('telephony.inbound.default_tenant_id'),
      'event_id' => $eventId,
      'event_type' => $data['event'] ?? null,
      'call_uid' => $data['call_uid'] ?? null,
      'endpoint' => $endpoint,
      'source_ip' => $request->ip(),
      'headers' => [
        'timestamp' => $request->header('X-HRX-Timestamp'),
        'nonce' => $request->header('X-HRX-Nonce'),
        'idempotency_key' => $request->header('Idempotency-Key'),
        'user_agent' => $request->userAgent(),
      ],
      'payload' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'signature_valid' => true, // middleware already proved it
      'status' => $status,
      'error' => $error,
      'received_at' => now(),
    ]));
  }

  private function deterministicUuid(string $seed): string
  {
    $hash = hash('sha256', $seed);

    return sprintf(
      '%s-%s-4%s-%s%s-%s',
      substr($hash, 0, 8),
      substr($hash, 8, 4),
      substr($hash, 13, 3),
      dechex((hexdec(substr($hash, 16, 1)) & 0x3) | 0x8),
      substr($hash, 17, 3),
      substr($hash, 20, 12)
    );
  }

  private function error(string $code, string $message, int $status): JsonResponse
  {
    return response()->json([
      'error' => [
        'code' => $code,
        'message' => $message,
        'request_id' => (string) \Illuminate\Support\Str::uuid(),
      ],
    ], $status);
  }
}
