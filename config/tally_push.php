<?php

/**
 * The Tally-to-live-server sync: a LOCAL command (hrx:tally-push, using App\Services\TallyGateway) on the PC
 * where Tally itself runs reads sales data and pushes it to this server's own /api/tally-push/sync endpoint.
 *
 * There is exactly one caller, not a pool of vendors, so this deliberately does NOT use a database-backed
 * credential table with an admin UI (unlike config/telephony.php, which has to support key rotation and
 * multiple named vendors) -- one shared key + secret pair in .env is enough. The verification mechanism
 * (bearer key, HMAC-SHA256 over timestamp.nonce.body, timestamp freshness, nonce replay-guard, IP allowlist)
 * mirrors App\Http\Middleware\VerifyTelephonySignature exactly, because that pattern is already proven here.
 *
 * Defaults are FAIL-CLOSED for anything that is not a local developer machine: on a real server the endpoint is
 * off until TALLY_PUSH_ENABLED=true, and even then it refuses every caller until an IP allowlist is set.
 */
return [

  /*
  |----------------------------------------------------------------------
  | Inbound: checked by VerifyTallyPushSignature on the RECEIVING server
  |----------------------------------------------------------------------
  */
  'inbound' => [
    // Master kill switch. Off (the default) = the endpoint answers 404 to everyone, as if it did not exist.
    // A local developer machine turns it on explicitly in .env.
    'enabled' => filter_var(env('TALLY_PUSH_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    'api_key' => env('TALLY_PUSH_API_KEY'),

    // Comma separated. During a secret rotation put the new one first and keep the old one listed until every
    // puller has picked up the new value.
    'secrets' => array_values(array_filter(array_map(
      'trim',
      explode(',', (string) env('TALLY_PUSH_SECRETS', ''))
    ))),

    // Comma separated IPv4/IPv6 or CIDR: the only addresses allowed to send. Checked FIRST, before the key, and
    // anyone else gets a plain 404 (the endpoint looks like it does not exist).
    'allowed_ips' => array_values(array_filter(array_map(
      'trim',
      explode(',', (string) env('TALLY_PUSH_ALLOWED_IPS', ''))
    ))),

    // When true an EMPTY allowlist means "nobody", not "everybody". Default: required everywhere except a local
    // developer machine (APP_ENV=local), where the puller talks to localhost.
    'require_ip_allowlist' => filter_var(env('TALLY_PUSH_REQUIRE_IP_ALLOWLIST', env('APP_ENV') !== 'local'), FILTER_VALIDATE_BOOLEAN),

    // Only if the server sits behind a CDN/reverse proxy that hides the caller's real address: the header that
    // carries it (e.g. CF-Connecting-IP) is believed ONLY when the connection itself comes from one of the
    // trusted proxy addresses/CIDRs. Left empty, the connecting address is used as-is, which is the right answer
    // for a plain Hostinger site with no CDN in front.
    'ip_header' => trim((string) env('TALLY_PUSH_IP_HEADER', '')),
    'trusted_proxies' => array_values(array_filter(array_map(
      'trim',
      explode(',', (string) env('TALLY_PUSH_TRUSTED_PROXIES', ''))
    ))),

    // Wrong key/signature/timestamp/nonce attempts from one address before it is locked out for the window.
    'max_failures' => (int) env('TALLY_PUSH_MAX_FAILURES', 10),
    'failure_window_seconds' => (int) env('TALLY_PUSH_FAILURE_WINDOW', 900),

    'max_skew_seconds' => (int) env('TALLY_PUSH_MAX_SKEW', 300),
    'nonce_ttl_seconds' => (int) env('TALLY_PUSH_NONCE_TTL', 600),

    // One day's invoices + sale orders, pretty-printed JSON, comfortably fits well under this.
    'max_body_bytes' => (int) env('TALLY_PUSH_MAX_BODY_BYTES', 25 * 1024 * 1024),
  ],

  /*
  |----------------------------------------------------------------------
  | Who may VIEW the Tally data (Sales Sources, its analytics/exports, the Tally tabs on a Customer Profile and
  | the menu entry). Comma separated login emails. Set it to the one person allowed and EVERYONE else -- other
  | admins, HR, accounts -- gets a 404. Empty means: a local developer machine falls back to the normal role
  | rules, and any other server shows the data to NOBODY. See App\Http\Middleware\RestrictTallyAccess.
  |----------------------------------------------------------------------
  */
  'viewers' => array_values(array_filter(array_map(
    fn ($e) => strtolower(trim($e)),
    explode(',', (string) env('TALLY_VIEWER_EMAILS', ''))
  ))),

  /*
  |----------------------------------------------------------------------
  | Outbound: used only by the LOCAL puller (hrx:tally-push). Irrelevant on the live server, which never sends
  | one of these requests, only receives them.
  |----------------------------------------------------------------------
  */
  'outbound' => [
    // e.g. https://hrx.hitechgroup.in/api/tally-push/sync in production, http://localhost:8001/api/tally-push/sync
    // while proving this out locally. Anything other than https:// is refused by the puller unless it points at
    // this machine itself.
    'target_url' => env('TALLY_PUSH_TARGET_URL'),
    'api_key' => env('TALLY_PUSH_API_KEY'),
    // The CURRENT secret to sign with. Must be one of the receiving server's 'inbound.secrets'.
    'secret' => env('TALLY_PUSH_SECRET'),
    'timeout' => (int) env('TALLY_PUSH_HTTP_TIMEOUT', 60),
  ],

];
