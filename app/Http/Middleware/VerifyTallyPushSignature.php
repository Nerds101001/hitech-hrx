<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Guards POST /api/tally-push/sync and /sync-parties -- the endpoints the local Tally puller (hrx:tally-push)
 * calls.
 *
 * Credentials and the IP allowlist come from the tally_push_credentials table first (managed from the Tally
 * Push admin page) and fall back to config/tally_push.php, so an install that predates the admin UI keeps
 * working. EVERY inbound hit -- accepted or rejected -- is written to tally_push_requests so the admin page
 * can show time, source IP and the allow/reject outcome. That matters most when the allowlist is wrong: those
 * requests are refused here, before the controller, so without this log they would be invisible in the UI.
 *
 * Checks, cheapest and least informative first:
 *   0. Kill switch  -- TALLY_PUSH_ENABLED off: 404, as if the route did not exist.
 *   1. Source IP    -- only allowlisted addresses may even reach the rest; anyone else gets the same plain 404
 *                      a non-existent URL gets. An empty allowlist where one is required means "nobody".
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

        // 1. Source IP, against the effective allowlist (every active credential's list, plus the config list).
        $ip = $this->clientIp($request, $cfg);
        $allowed = $this->effectiveAllowlist($cfg);
        if (empty($allowed)) {
            if (!empty($cfg['require_ip_allowlist'])) {
                $this->deny($request, 404, 'no_allowlist_configured', 'No IP allowlist is configured', $ip);
                $this->notFound($request);
            }
        } elseif ($ip === '' || !IpUtils::checkIp($ip, $allowed)) {
            $this->deny($request, 404, 'ip_not_allowed', 'Source IP is not on the allowlist', $ip);
            $this->notFound($request);
        }

        // 2. Lockout for an allowlisted address that keeps getting it wrong.
        $failKey = 'tally_push:fails:' . sha1($ip);
        if ((int) $this->store()->get($failKey, 0) >= (int) ($cfg['max_failures'] ?? 10)) {
            return $this->deny($request, 429, 'locked_out', 'Too many failed attempts. Try again later.', $ip);
        }

        // 3. Size, before anything else is parsed or hashed.
        $max = (int) ($cfg['max_body_bytes'] ?? 0);
        if ($max > 0 && strlen($request->getContent()) > $max) {
            return $this->deny($request, 413, 'payload_too_large', 'Request body exceeds ' . $max . ' bytes. Push a smaller date range.', $ip);
        }

        // 4. Bearer key -> credential (DB first, config second).
        $presented = (string) $request->bearerToken();
        $credential = $this->resolveCredential($presented);
        if ($credential === null) {
            return $this->fail($request, $failKey, $cfg, 401, 'invalid_api_key', 'API key missing or incorrect', $ip);
        }

        // 4b. This credential's own allowlist, if it set a narrower one than the union checked at step 1.
        if (!empty($credential['allowed_ips']) && !IpUtils::checkIp($ip, $credential['allowed_ips'])) {
            return $this->fail($request, $failKey, $cfg, 404, 'ip_not_allowed', 'Source IP is not on this key\'s allowlist', $ip);
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
        // the old and new secret until the puller is updated.
        $header = (string) $request->header('X-HRX-Signature', '');
        if (!str_starts_with($header, 'sha256=')) {
            return $this->fail($request, $failKey, $cfg, 401, 'missing_signature', 'X-HRX-Signature must be sha256=<hex>', $ip);
        }
        $presentedSig = substr($header, 7);
        $signable = $timestamp . '.' . $nonce . '.' . $request->getContent();

        $matched = false;
        foreach (($credential['secrets'] ?? []) as $secret) {
            if ($secret !== '' && hash_equals(hash_hmac('sha256', $signable, $secret), $presentedSig)) {
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            return $this->fail($request, $failKey, $cfg, 401, 'invalid_signature', 'HMAC signature did not match', $ip);
        }

        // Proven genuine. Count the use, name the caller to the controller, and log the accepted request so the
        // admin page shows it next to the refusals.
        if (isset($credential['model'])) {
            $credential['model']->markUsed($ip);
        }
        $request->attributes->set('tally_push_credential_label', $credential['label'] ?? null);
        $this->recordAccepted($request, $ip, $credential['label'] ?? null);

        return $next($request);
    }

    /**
     * The union of every active credential's allowlist and the config list. Checking the IP first (before the
     * key) keeps the "a scanner gets a plain 404" property; the specific credential's own list is re-checked at
     * step 4b once we know which key presented. Cached briefly -- this runs on every push.
     *
     * @return array<int,string>
     */
    private function effectiveAllowlist(array $cfg): array
    {
        $fromDb = [];
        try {
            $fromDb = Cache::store('file')->remember('tally_push:allowlist', 60, function () {
                return \App\Models\TallyPushCredential::where('is_active', true)
                    ->pluck('allowed_ips')->filter()
                    ->flatMap(fn ($s) => array_map('trim', explode(',', (string) $s)))
                    ->filter()->unique()->values()->all();
            });
        } catch (\Throwable $e) {
            // Table may not exist yet mid-upgrade: fall back to config only.
        }

        return array_values(array_filter(array_unique(array_merge($fromDb, $cfg['allowed_ips'] ?? []))));
    }

    /**
     * Find the credential presenting this API key. Database first, config second (the pre-admin-UI path and the
     * fallback before anyone has generated a key). Cached for a minute, like telephony.
     *
     * @return array{secrets:array<int,string>,allowed_ips:array<int,string>|null,label:?string,model?:\App\Models\TallyPushCredential}|null
     */
    private function resolveCredential(string $presentedKey): ?array
    {
        if ($presentedKey === '') {
            return null;
        }

        try {
            $id = Cache::store('file')->remember(
                'tally_push:cred:' . hash('sha256', $presentedKey),
                60,
                fn () => \App\Models\TallyPushCredential::where('api_key', $presentedKey)
                    ->where('is_active', true)
                    ->value('id') ?? 0
            );

            if ($id) {
                $model = \App\Models\TallyPushCredential::find($id);
                if ($model && $model->is_active) {
                    return [
                        'secrets' => $model->acceptedSecrets(),
                        'allowed_ips' => $model->allowlist(),
                        'label' => $model->label,
                        'model' => $model,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[tally_push] credential lookup failed, using config', ['message' => $e->getMessage()]);
        }

        // ── config fallback ──
        $configured = (string) ($presentedKey !== '' ? (config('tally_push.inbound.api_key') ?? '') : '');
        if ($configured === '' || !hash_equals($configured, $presentedKey)) {
            return null;
        }

        return [
            'secrets' => array_values(array_filter(config('tally_push.inbound.secrets') ?? [])),
            'allowed_ips' => config('tally_push.inbound.allowed_ips') ?? [],
            'label' => null,
        ];
    }

    /**
     * The caller's address. The connecting address by default; a header such as CF-Connecting-IP is believed
     * ONLY when the connection itself comes from a configured trusted proxy.
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
        $requestId = (string) Str::uuid();

        Log::channel(config('logging.default'))->warning('[tally_push] rejected request', [
            'request_id' => $requestId,
            'code' => $code,
            'ip' => $ip,
        ]);

        $this->record($request, $ip, $code, $message, $status, $requestId);

        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'request_id' => $requestId,
            ],
        ], $status);
    }

    /**
     * Store a refused request, without trusting a word of it: only a bounded excerpt of the body is kept, since
     * an unauthenticated caller controls this content entirely. Wrapped in a catch so a logging failure never
     * turns a clean 401 into a 500.
     */
    private function record(Request $request, string $ip, string $code, string $message, int $status, string $requestId): void
    {
        try {
            $body = $request->getContent();
            \App\Models\TallyPushRequest::create([
                'tenant_id' => config('tally_push.default_tenant_id'),
                'endpoint' => Str::limit($request->path(), 60, ''),
                'source_ip' => $ip,
                'headers' => [
                    'timestamp' => Str::limit((string) $request->header('X-HRX-Timestamp'), 40, ''),
                    'nonce' => Str::limit((string) $request->header('X-HRX-Nonce'), 60, ''),
                    'user_agent' => Str::limit((string) $request->userAgent(), 120, ''),
                    'request_id' => $requestId,
                ],
                'payload' => Str::limit((string) $body, 2000, ' ...[truncated]'),
                'bytes' => strlen((string) $body),
                'signature_valid' => false,
                'status' => 'rejected',
                'http_status' => $status,
                'error' => $code . ': ' . $message,
                'received_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[tally_push] could not record a rejected request', ['message' => $e->getMessage()]);
        }
    }

    /** A request that passed every check. The body is genuine, but still only its size is kept, not its content. */
    private function recordAccepted(Request $request, string $ip, ?string $label): void
    {
        try {
            \App\Models\TallyPushRequest::create([
                'tenant_id' => config('tally_push.default_tenant_id'),
                'endpoint' => Str::limit($request->path(), 60, ''),
                'source_ip' => $ip,
                'headers' => [
                    'user_agent' => Str::limit((string) $request->userAgent(), 120, ''),
                ],
                'payload' => null,
                'bytes' => strlen((string) $request->getContent()),
                'signature_valid' => true,
                'status' => 'accepted',
                'http_status' => 200,
                'credential_label' => $label,
                'received_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[tally_push] could not record an accepted request', ['message' => $e->getMessage()]);
        }
    }

    /**
     * The nonce replay guard and lockout counters MUST persist between requests. With CACHE_STORE=array (a common
     * local setting) they remember nothing and a captured request could be replayed forever. The file store always
     * persists.
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
}
