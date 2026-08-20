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
 * Four independent checks, cheapest first: source IP, bearer key, clock skew
 * and nonce, then the HMAC itself. A caller holding the API key but not the
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

    // 2. Bearer key.
    $key = (string) $cfg['api_key'];
    $presented = (string) $request->bearerToken();
    if ($key === '' || !hash_equals($key, $presented)) {
      return $this->deny($request, 401, 'invalid_api_key', 'API key missing or incorrect');
    }

    // 3. Freshness. Rejects a captured request replayed later.
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

    // 4. HMAC over timestamp . nonce . raw body.
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
   * 401 us at 14:32" can be answered without guesswork.
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

    return response()->json([
      'error' => [
        'code' => $code,
        'message' => $message,
        'request_id' => $requestId,
      ],
    ], $status);
  }
}
