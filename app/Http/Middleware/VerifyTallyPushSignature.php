<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Guards POST /api/tally-push/sync and /sync-parties -- the endpoints the local Tally puller (hrx:tally-push)
 * calls.
 *
 * A smaller cousin of VerifyTelephonySignature: the credential is a single key+secret pair from
 * config/tally_push.php rather than a database-backed, multi-vendor, rotating credentials table -- there is
 * exactly one caller here. If a second puller (e.g. a second office) is ever needed, lift this to the telephony
 * pattern rather than growing this file ad hoc.
 *
 * Checks, cheapest and least informative first:
 *   0. Kill switch  -- TALLY_PUSH_ENABLED off: 404, as if the route did not exist.
 *   1. Source IP    -- only the allowlisted addresses may even reach the rest; anyone else gets the same plain 404
 *                      a non-existent URL gets, so a scanner learns nothing. An empty allowlist where one is
 *                      required means "nobody" (fail closed), never "everybody".
 *   2. Lockout      -- an allowlisted address that keeps presenting wrong credentials is locked out for a while.
 *   3. Size, 4. bearer key, 5. timestamp freshness + single-use nonce, 6. HMAC over timestamp.nonce.body.
 *
 * The signature is computed over the RAW body bytes, exactly as sent -- do not json_decode and re-encode
 * before verifying, key order and whitespace will differ and every signature will fail.
 */
class VerifyTallyPushSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $cfg = config('tally_push.inbound');

        // 0. Kill switch.
        if (empty($cfg['enabled'])) {
            $this->notFound($request);
        }

        // 1. Source IP.
        $ip = $this->clientIp($request, $cfg);
        $allowed = $cfg['allowed_ips'] ?? [];
        if (empty($allowed)) {
            if (!empty($cfg['require_ip_allowlist'])) {
                $this->logRefusal($request, 'no_allowlist_configured', $ip);
                $this->notFound($request);
            }
        } elseif ($ip === '' || !IpUtils::checkIp($ip, $allowed)) {
            $this->logRefusal($request, 'ip_not_allowed', $ip);
            $this->notFound($request);
        }

        // 2. Lockout for an allowlisted address that keeps getting it wrong.
        $failKey = 'tally_push:fails:' . sha1($ip);
        if ((int) $this->store()->get($failKey, 0) >= (int) ($cfg['max_failures'] ?? 10)) {
            $this->logRefusal($request, 'locked_out', $ip);
            return response()->json(['error' => ['code' => 'locked_out', 'message' => 'Too many failed attempts. Try again later.']], 429);
        }

        // 3. Size, before anything else is parsed or hashed.
        $max = (int) ($cfg['max_body_bytes'] ?? 0);
        if ($max > 0 && strlen($request->getContent()) > $max) {
            return $this->deny($request, 413, 'payload_too_large', 'Request body exceeds ' . $max . ' bytes. Push a smaller date range.', $ip);
        }

        // 4. Bearer key.
        $configuredKey = (string) ($cfg['api_key'] ?? '');
        $presented = (string) $request->bearerToken();
        if ($configuredKey === '' || $presented === '' || !hash_equals($configuredKey, $presented)) {
            return $this->fail($request, $failKey, $cfg, 401, 'invalid_api_key', 'API key missing or incorrect', $ip);
        }

        // 5. Freshness: rejects a captured request replayed later.
        $timestamp = (string) $request->header('X-HRX-Timestamp', '');
        if ($timestamp === '' || !ctype_digit($timestamp)) {
            return $this->fail($request, $failKey, $cfg, 401, 'missing_timestamp', 'X-HRX-Timestamp missing or not epoch seconds', $ip);
        }
        $skew = abs(time() - (int) $timestamp);
        if ($skew > (int) $cfg['max_skew_seconds']) {
            return $this->fail($request, $failKey, $cfg, 401, 'stale_timestamp', "Timestamp is {$skew}s from server time", $ip);
        }

        $nonce = (string) $request->header('X-HRX-Nonce', '');
        if ($nonce === '') {
            return $this->fail($request, $failKey, $cfg, 401, 'missing_nonce', 'X-HRX-Nonce is required', $ip);
        }
        $nonceKey = 'tally_push:nonce:' . hash('sha256', $nonce);
        if (!$this->store()->add($nonceKey, 1, (int) $cfg['nonce_ttl_seconds'])) {
            return $this->fail($request, $failKey, $cfg, 401, 'replayed_nonce', 'This nonce has already been used', $ip);
        }

        // 6. HMAC over timestamp . nonce . raw body. Accepts any secret in the list, so a rotation can list both
        // the old and new secret until the puller (a single .env value) is updated.
        $header = (string) $request->header('X-HRX-Signature', '');
        if (!str_starts_with($header, 'sha256=')) {
            return $this->fail($request, $failKey, $cfg, 401, 'missing_signature', 'X-HRX-Signature must be sha256=<hex>', $ip);
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
            return $this->fail($request, $failKey, $cfg, 401, 'invalid_signature', 'HMAC signature did not match', $ip);
        }

        return $next($request);
    }

    /**
     * The caller's address. The connecting address by default; a header such as CF-Connecting-IP is believed ONLY
     * when the connection itself comes from a configured trusted proxy, so nobody can claim an allowlisted address
     * just by sending a header.
     */
    private function clientIp(Request $request, array $cfg): string
    {
        $remote = (string) $request->server->get('REMOTE_ADDR', '');
        $header = (string) ($cfg['ip_header'] ?? '');
        $proxies = $cfg['trusted_proxies'] ?? [];
        if ($header !== '' && !empty($proxies) && $remote !== '' && IpUtils::checkIp($remote, $proxies)) {
            $forwarded = trim((string) $request->headers->get($header, ''));
            if (filter_var($forwarded, FILTER_VALIDATE_IP)) {
                return $forwarded;
            }
        }
        return $remote;
    }

    /** A wrong credential from an allowlisted address: count it toward the lockout, then refuse. */
    private function fail(Request $request, string $failKey, array $cfg, int $status, string $code, string $message, string $ip): Response
    {
        $this->store()->add($failKey, 0, (int) ($cfg['failure_window_seconds'] ?? 900));
        $this->store()->increment($failKey);
        return $this->deny($request, $status, $code, $message, $ip);
    }

    private function deny(Request $request, int $status, string $code, string $message, string $ip): Response
    {
        $requestId = (string) \Illuminate\Support\Str::uuid();

        Log::channel(config('logging.default'))->warning('[tally_push] rejected request', [
            'request_id' => $requestId,
            'code' => $code,
            'ip' => $ip,
        ]);

        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'request_id' => $requestId,
            ],
        ], $status);
    }

    /**
     * The nonce replay guard and the lockout counters MUST persist between requests, whatever the app's default
     * cache driver is: with CACHE_STORE=array (a common local setting) or null they remember nothing, and a captured
     * request could then be replayed forever without anyone noticing. The file store always exists and persists.
     */
    private function store(): \Illuminate\Contracts\Cache\Repository
    {
        return Cache::store('file');
    }

    /** Byte for byte what the framework answers for a URL that really does not exist. */
    private function notFound(Request $request): never
    {
        throw new NotFoundHttpException(sprintf('The route %s could not be found.', $request->path()));
    }

    /** Refusals that are answered with a bare 404 still leave a trace for the owner. */
    private function logRefusal(Request $request, string $code, string $ip): void
    {
        Log::channel(config('logging.default'))->warning('[tally_push] refused (answered 404)', [
            'code' => $code,
            'ip' => $ip,
            'path' => $request->path(),
        ]);
    }
}
