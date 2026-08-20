<?php

return [

  /*
  |----------------------------------------------------------------------
  | Inbound: what the vendor sends us
  |----------------------------------------------------------------------
  | These credentials are issued BY HRX TO the vendor. Rotate the secret
  | every 180 days; both old and new may be listed during a rotation window.
  */
  'inbound' => [
    'api_key' => env('TELEPHONY_INBOUND_API_KEY'),

    // Comma separated. During rotation put the new secret first.
    'secrets' => array_values(array_filter(array_map(
      'trim',
      explode(',', (string) env('TELEPHONY_INBOUND_SECRETS', ''))
    ))),

    // Comma separated IPv4/IPv6 or CIDR. Empty = allowlist disabled (dev only).
    'allowed_ips' => array_values(array_filter(array_map(
      'trim',
      explode(',', (string) env('TELEPHONY_ALLOWED_IPS', ''))
    ))),

    // Reject a request whose X-HRX-Timestamp is further away than this.
    'max_skew_seconds' => (int) env('TELEPHONY_MAX_SKEW', 300),

    // How long a nonce is remembered, to kill replays.
    'nonce_ttl_seconds' => (int) env('TELEPHONY_NONCE_TTL', 600),

    // Max events accepted in one /events/batch call.
    'max_batch' => 500,

    // Hard ceiling on the raw request body, in bytes.
    //
    // A rich call.completed event is 2-3 KB and the largest honest request is
    // a 500-event batch at roughly 1.5 MB, so 5 MB is generous. Anything above
    // it is a vendor bug or an attempt to fill the disk: the payload column is
    // longText, so without this an oversized body is accepted and stored.
    'max_body_bytes' => (int) env('TELEPHONY_MAX_BODY_BYTES', 5 * 1024 * 1024),

    // Webhooks arrive unauthenticated by session, so there is no tenant in
    // context. HRX resolves the tenant from the matched agent-map row and
    // falls back to this when the agent is unknown.
    //
    // This MUST never resolve to null. Every telephony table carries a unique
    // index whose first column is tenant_id, and MySQL treats NULLs in a
    // unique index as distinct - a null tenant would silently stop
    // (tenant_id, event_id) from deduplicating anything, so a replayed event
    // would be written twice with no error. 'default' is correct for this
    // install: it is single-company and TenantTrait's scope is disabled.
    'default_tenant_id' => env('TELEPHONY_DEFAULT_TENANT_ID') ?: 'default',
  ],

  /*
  |----------------------------------------------------------------------
  | Outbound: the gateway we call
  |----------------------------------------------------------------------
  */
  'vendor' => [
    'base_url' => env('TELEPHONY_VENDOR_BASE_URL', 'http://192.168.2.5/smart/api/v1'),
    'api_key' => env('TELEPHONY_VENDOR_API_KEY'),
    'timeout' => (int) env('TELEPHONY_VENDOR_TIMEOUT', 10),
    'connect_timeout' => (int) env('TELEPHONY_VENDOR_CONNECT_TIMEOUT', 4),
    'retry_times' => 2,
    'retry_sleep_ms' => 250,
    // Verify TLS. Only ever false for an on-prem box with a self-signed cert.
    'verify_tls' => (bool) env('TELEPHONY_VENDOR_VERIFY_TLS', true),
  ],

  /*
  |----------------------------------------------------------------------
  | Recordings
  |----------------------------------------------------------------------
  */
  'recording' => [
    'signed_url_ttl' => (int) env('TELEPHONY_RECORDING_TTL', 300),
    // Roles that may play a recording belonging to someone else.
    'supervisor_roles' => ['admin', 'hr', 'manager'],
    // Roles that see full customer numbers. Everyone else sees them masked.
    'unmasked_roles' => ['admin', 'hr'],
  ],

  /*
  |----------------------------------------------------------------------
  | Reconciliation
  |----------------------------------------------------------------------
  */
  'reconcile' => [
    // Per-call talk-time tolerance in seconds. Count gap tolerance is always 0.
    'talk_tolerance_seconds' => 2,
    'page_size' => 500,
  ],

  /*
  |----------------------------------------------------------------------
  | Timezone the gateway reports in. Used only when a payload sends a naive
  | timestamp; a compliant payload carries its own offset and wins.
  |----------------------------------------------------------------------
  */
  'vendor_timezone' => env('TELEPHONY_VENDOR_TZ', 'Asia/Kolkata'),

  // Queue the ingest jobs land on. Keep it off the default queue so a backlog
  // of payroll or mail jobs never delays a live call popup.
  'queue' => env('TELEPHONY_QUEUE', 'telephony'),
];
