<?php

namespace App\Jobs\Telephony;

use App\Models\Telephony\TelephonyWebhookDelivery;
use App\Services\Telephony\CallEventNormalizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Normalises one stored delivery.
 *
 * Only the delivery id is carried, never the payload - the row is the single
 * source of truth, so a job replayed after a code change re-reads the original
 * bytes rather than a stale serialised copy.
 */
class ProcessTelephonyEvent implements ShouldQueue
{
  use Dispatchable;
  use InteractsWithQueue;
  use Queueable;
  use SerializesModels;

  public int $tries = 5;
  public int $timeout = 60;

  /** @return array<int,int> */
  public function backoff(): array
  {
    return [5, 30, 120, 600];
  }

  public function __construct(public int $deliveryId)
  {
  }

  public function handle(CallEventNormalizer $normalizer): void
  {
    $delivery = TelephonyWebhookDelivery::find($this->deliveryId);

    if (!$delivery || $delivery->status === 'processed') {
      return;
    }

    $delivery->increment('attempts');

    try {
      $payload = json_decode((string) $delivery->payload, true, 512, JSON_THROW_ON_ERROR);

      $normalizer->handle($payload);

      $delivery->forceFill([
        'status' => 'processed',
        'processed_at' => now(),
        'error' => null,
      ])->save();
    } catch (Throwable $e) {
      $delivery->forceFill([
        'status' => 'failed',
        'error' => substr($e->getMessage(), 0, 2000),
      ])->save();

      Log::error('[telephony] failed to normalise delivery', [
        'delivery_id' => $delivery->id,
        'event_id' => $delivery->event_id,
        'call_uid' => $delivery->call_uid,
        'message' => $e->getMessage(),
      ]);

      throw $e;
    }
  }

  public function failed(Throwable $e): void
  {
    // The delivery row keeps the raw payload, so a permanently failed event can
    // be replayed from the admin Webhook Log once the cause is fixed.
    Log::critical('[telephony] delivery exhausted all retries', [
      'delivery_id' => $this->deliveryId,
      'message' => $e->getMessage(),
    ]);
  }
}
