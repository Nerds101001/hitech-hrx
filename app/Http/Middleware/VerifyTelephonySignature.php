<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards every /api/telephony/v1 route.
 *
 * Five independent checks, cheapest first: source IP, body size, bearer key,
 * clock skew and nonce, then the HMAC itself. A caller holding the API key but not the
 * shared secret still gets a 401.
 *
 * The signature is computed over the RAW body bytes. Do not json_decode and
 * re-encode before verifying - key order and whitespace will differ and every
 * signature will fail.
 */
class VerifyTelephonySignature
{
  public function handle(Request $request, Closure $next): Response
  {
    $cfg = config('telephony.inbound');

    // 1. Source IP. An empty allowlist disables the check (local dev only).
    $allowed = $cfg['allowed_ips'] ?? [];
    if (!empty($allowed) && !IpUtils::checkIp($request->ip(), $allowed)) {
      return $this->deny($request, 403, 'ip_not_allowed', 'Source IP is not on the allowlist');
    }

    // 2. Size. Checked before the HMAC so an oversized body is refused
    // without hashing megabytes first, and before the controller so it is
    // never written to the payload column.
    $max = (int) ($cfg['max_body_bytes'] ?? 0);
    if ($max > 0 && strlen($request->getContent()) > $max) {
      return $this->deny(
        $request,
        413,
        'payload_too_large',
        'Request body exceeds ' . $max . ' bytes. Split a large backlog across several /events/batch calls.'
      );
    }

    // 3. Bearer key.
    $key = (string) $cfg['api_key'];
    $presented = (string) $request->bearerToken();
    if ($key === '' || !hash_equals($key, $presented)) {
      return $this->deny($request, 401, 'invalid_api_key', 'API key missing or incorrect');
    }

    // 4. Freshness. Rejects a captured request replayed later.
    $timestamp = (string) $request->header('X-HRX-Timestamp', '');
    if ($timestamp === '' || !ctype_digit($timestamp)) {
      return $this->deny($request, 401, 'missing_timestamp', 'X-HRX-Timestamp missing or not epoch seconds');
    }
    $skew = abs(time() - (int) $timestamp);
    if ($skew > (int) $cfg['max_skew_seconds']) {
      return $this->deny($request, 401, 'stale_timestamp', "Timestamp is {$skew}s from server time");
    }

    $nonce = (string) $request->header('X-HRX-Nonce', '');
    if ($nonce === '') {
      return $this->deny($request, 401, 'missing_nonce', 'X-HRX-Nonce is required');
    }
    $nonceKey = 'telephony:nonce:' . hash('sha256', $nonce);
    if (!Cache::add($nonceKey, 1, (int) $cfg['nonce_ttl_seconds'])) {
      return $this->deny($request, 401, 'replayed_nonce', 'This nonce has already been used');
    }

    // 5. HMAC over timestamp . nonce . raw body.
    $header = (string) $request->header('X-HRX-Signature', '');
    if (!str_starts_with($header, 'sha256=')) {
      return $this->deny($request, 401, 'missing_signature', 'X-HRX-Signature must be sha256=<hex>');
    }
    $presentedSig = substr($header, 7);
    $signable = $timestamp . '.' . $nonce . '.' . $request->getContent();

    $matched = false;
    foreach (($cfg['secrets'] ?? []) as $secret) {
      if ($secret !== '' && hash_equals(hash_hmac('sha256', $signable, $secret), $presentedSig)) {
        $matched = true;
        break;
      }
    }
    if (!$matched) {
      return $this->deny($request, 401, 'invalid_signature', 'HMAC signature did not match');
    }

    return $next($request);
  }

  /**
   * Rejections are logged with the request id so a vendor asking "why did you
   * 401 us at 14:32" can be answered without guesswork, AND recorded in
   * telephony_webhook_deliveries so the answer is visible in the admin UI
   * rather than only to whoever can read the log file on the server.
   *
   * That second part matters most when the IP allowlist is wrong. Every
   * request is then refused here, before the controller, so without this the
   * Webhook Log would show nothing at all -- identical to the vendor never
   * having started, and the single most expensive misdiagnosis available in
   * this integration.
   */
  private function deny(Request $request, int $status, string $code, string $message): Response
  {
    $requestId = (string) \Illuminate\Support\Str::uuid();

    Log::channel(config('logging.default'))->warning('[telephony] rejected webhook', [
      'request_id' => $requestId,
      'code' => $code,
      'ip' => $request->ip(),
      'path' => $request->path(),
      'event_id' => $request->input('event_id'),
    ]);

    $this->record($request, $code, $message, $requestId);

    return response()->json([
      'error' => [
        'code' => $code,
        'message' => $message,
        'request_id' => $requestId,
      ],
    ], $status);
  }

  /**
   * Store a refused request, without trusting a word of it.
   *
   * Only a bounded excerpt of the body is kept: an unauthenticated caller
   * controls this content entirely, and the whole point of refusing them is
   * that they do not get to write megabytes into the database. The
   * throttle:telephony limiter caps how often this can happen at all.
   *
   * Wrapped in a catch because a logging failure must never turn a clean 401
   * into a 500 -- that would hand a caller a way to make refusals look like
   * server errors, which the vendor's retry policy treats very differently.
   */
  private function record(Request $request, string $code, string $message, string $requestId): void
  {
    try {
      $body = $request->getContent();

      \App\Models\Telephony\TelephonyWebhookDelivery::create([
        'tenant_id' => config('telephony.inbound.default_tenant_id'),
        // Never trust a refused caller's event_id as an identity: writing it
        // to the unique-indexed column would let an unauthenticated request
        // block a later genuine delivery of the same event.
        'event_id' => null,
        'event_type' => null,
        'call_uid' => null,
        'endpoint' => \Illuminate\Support\Str::limit($request->path(), 60, ''),
        'source_ip' => $request->ip(),
        'headers' => [
          'timestamp' => \Illuminate\Support\Str::limit((string) $request->header('X-HRX-Timestamp'), 40, ''),
          'nonce' => \Illuminate\Support\Str::limit((string) $request->header('X-HRX-Nonce'), 60, ''),
          'user_agent' => \Illuminate\Support\Str::limit((string) $request->userAgent(), 120, ''),
          'request_id' => $requestId,
        ],
        'payload' => \Illuminate\Support\Str::limit($body, 2000, ' ...[truncated]'),
        'signature_valid' => false,
        'status' => 'rejected',
        'error' => $code . ': ' . $message,
        'received_at' => now(),
      ]);
    } catch (\Throwable $e) {
      Log::warning('[telephony] could not record a rejected webhook', ['message' => $e->getMessage()]);
    }
  }
}
