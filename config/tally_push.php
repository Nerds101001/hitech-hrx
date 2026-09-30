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
 */
return [

  /*
  |----------------------------------------------------------------------
  | Inbound: checked by VerifyTallyPushSignature on the RECEIVING server
  |----------------------------------------------------------------------
  */
  'inbound' => [
    'api_key' => env('TALLY_PUSH_API_KEY'),

    // Comma separated. During a secret rotation put the new one first and keep the old one listed until every
    // puller has picked up the new value.
    'secrets' => array_values(array_filter(array_map(
      'trim',
      explode(',', (string) env('TALLY_PUSH_SECRETS', ''))
    ))),

    // Comma separated IPv4/IPv6 or CIDR. Empty = allowlist disabled (fine while proving this out; tighten once
    // the office's public IP is known -- see the deployment notes).
    'allowed_ips' => array_values(array_filter(array_map(
      'trim',
      explode(',', (string) env('TALLY_PUSH_ALLOWED_IPS', ''))
    ))),

    'max_skew_seconds' => (int) env('TALLY_PUSH_MAX_SKEW', 300),
    'nonce_ttl_seconds' => (int) env('TALLY_PUSH_NONCE_TTL', 600),

    // One day's invoices + sale orders, pretty-printed JSON, comfortably fits well under this.
    'max_body_bytes' => (int) env('TALLY_PUSH_MAX_BODY_BYTES', 25 * 1024 * 1024),
  ],

  /*
  |----------------------------------------------------------------------
  | Outbound: used only by the LOCAL puller (hrx:tally-push). Irrelevant on the live server, which never sends
  | one of these requests, only receives them.
  |----------------------------------------------------------------------
  */
  'outbound' => [
    // e.g. https://hrx.hitechgroup.in/api/tally-push/sync in production, http://localhost:8001/api/tally-push/sync
    // while proving this out locally.
    'target_url' => env('TALLY_PUSH_TARGET_URL'),
    'api_key' => env('TALLY_PUSH_API_KEY'),
    // The CURRENT secret to sign with. Must be one of the receiving server's 'inbound.secrets'.
    'secret' => env('TALLY_PUSH_SECRET'),
    'timeout' => (int) env('TALLY_PUSH_HTTP_TIMEOUT', 60),
  ],

];
