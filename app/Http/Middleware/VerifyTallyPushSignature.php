<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards POST /api/tally-push/sync -- the one endpoint the local Tally puller (hrx:tally-push) calls.
 *
 * Deliberately a smaller cousin of VerifyTelephonySignature: same five checks in the same cheapest-first order
 * (size, bearer key, source IP, timestamp+nonce freshness, HMAC), but the credential is a single key+secret
 * pair from config/tally_push.php rather than a database-backed, multi-vendor, rotating credentials table --
 * there is exactly one caller here, so that machinery would be pure overhead. If a second puller (e.g. a
 * second office) is ever needed, lift this to the telephony pattern rather than growing this file ad hoc.
 *
 * The signature is computed over the RAW body bytes, exactly as sent -- do not json_decode and re-encode
 * before verifying, key order and whitespace will differ and every signature will fail.
 */
class VerifyTallyPushSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $cfg = config('tally_push.inbound');

        // 1. Size, before anything else is parsed or hashed.
        $max = (int) ($cfg['max_body_bytes'] ?? 0);
        if ($max > 0 && strlen($request->getContent()) > $max) {
            return $this->deny($request, 413, 'payload_too_large', 'Request body exceeds ' . $max . ' bytes. Push a smaller date range.');
        }

        // 2. Bearer key.
        $configuredKey = (string) ($cfg['api_key'] ?? '');
        $presented = (string) $request->bearerToken();
        if ($configuredKey === '' || $presented === '' || !hash_equals($configuredKey, $presented)) {
            return $this->deny($request, 401, 'invalid_api_key', 'API key missing or incorrect');
        }

        // 3. Source IP. Empty allowlist = disabled (see config/tally_push.php for why that's the current state).
        $allowed = $cfg['allowed_ips'] ?? [];
        if (!empty($allowed) && !IpUtils::checkIp($request->ip(), $allowed)) {
            return $this->deny($request, 403, 'ip_not_allowed', 'Source IP is not on the allowlist');
        }

        // 4. Freshness: rejects a captured request replayed later.
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
        $nonceKey = 'tally_push:nonce:' . hash('sha256', $nonce);
        if (!Cache::add($nonceKey, 1, (int) $cfg['nonce_ttl_seconds'])) {
            return $this->deny($request, 401, 'replayed_nonce', 'This nonce has already been used');
        }

        // 5. HMAC over timestamp . nonce . raw body. Accepts any secret in the list, so a rotation can list both
        // the old and new secret until the puller (a single .env value) is updated.
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

    private function deny(Request $request, int $status, string $code, string $message): Response
    {
        $requestId = (string) \Illuminate\Support\Str::uuid();

        Log::channel(config('logging.default'))->warning('[tally_push] rejected request', [
            'request_id' => $requestId,
            'code' => $code,
            'ip' => $request->ip(),
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
