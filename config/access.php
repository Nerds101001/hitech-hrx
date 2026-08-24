<?php

return [

  /*
  |--------------------------------------------------------------------------
  | Super delegate accounts
  |--------------------------------------------------------------------------
  |
  | Normal delegate access is a per-account pointer (users.access_delegate_id)
  | set for vacant org-chart positions. The accounts listed here bypass that
  | pointer: from the Employee Directory they may enter ANY active account in
  | one click, for support/debugging. Everything else about the delegate
  | session is unchanged — the original id is held in the session and the
  | header hub's "Exit Delegate Login" restores it.
  |
  | Comma-separated in SUPER_DELEGATE_EMAILS to override without a code change.
  |
  */

  'super_delegate_emails' => array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('SUPER_DELEGATE_EMAILS', 'ai@rustx.com'))
  ))),

  /*
  |--------------------------------------------------------------------------
  | Click to call
  |--------------------------------------------------------------------------
  |
  | Places a call through the Smart Agent dialer from the employee dashboard.
  |
  | The dialer speaks plain HTTP while HRX is served over HTTPS, so the browser
  | can never call it directly — the request goes to HRX and HRX relays it. The
  | endpoint also sits behind a firewall that allows specific source addresses,
  | which is why this only works from the production server.
  |
  | 'agents' maps an HRX login to that person's dialer identity; the two are
  | unrelated systems with different user ids. Only the logins listed here get
  | the button, so adding someone is a config change, not a code change.
  |
  */

  'click_to_call' => [

    'enabled' => (bool) env('CLICK_TO_CALL_ENABLED', true),

    'endpoint' => env('CLICK_TO_CALL_URL', 'http://45.120.40.194:18111/'),

    // The dialer answers in ~50ms; anything slower means it is unwell.
    'timeout' => (int) env('CLICK_TO_CALL_TIMEOUT', 10),

    // 'vendor' picks which dialer ClickToCallService actually talks to for
    // this agent. Omitted (or 'centrovision') means the shared CentroVision
    // gateway above — every agent before Meenu. 'convoque' means the
    // per-agent Convoque box under 'convoque' below instead; only DIAL is
    // wired for that vendor, see ClickToCallService::dialConvoque().
    'agents' => [
      'bhatiamehak52@gmail.com' => [
        'campaign'  => 'mehak',
        'agent'     => 'mehak',
        'client_id' => '8006',
      ],
      'newbiz@hitechgroup.in' => [
        'vendor'    => 'convoque',
        'extension' => '217',
      ],
    ],

    // Convoque agents, keyed by the same email as above. Kept separate from
    // 'agents' rather than folded in, since CentroVision and Convoque agents
    // share almost no fields — a single merged shape would be mostly-null
    // either way round.
    'convoque' => [
      'base_url'   => env('CONVOQUE_BASE_URL', 'http://103.100.218.170:8000'),
      'auth_codes' => [
        'newbiz@hitechgroup.in' => env('CONVOQUE_NEWBIZ_AUTH_CODE'),
      ],
    ],
  ],

];
