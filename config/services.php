<?php

return [

  /*
  |--------------------------------------------------------------------------
  | Third Party Services
  |--------------------------------------------------------------------------
  |
  | This file is for storing the credentials for third party services such
  | as Mailgun, Postmark, AWS and more. This file provides the de facto
  | location for this type of information, allowing packages to have
  | a conventional file to locate the various service credentials.
  |
  */

  'postmark' => [
    'token' => env('POSTMARK_TOKEN'),
  ],

  'ses' => [
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
  ],

  'slack' => [
    'notifications' => [
      'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
      'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
    ],
  ],

  'crm' => [
    'base_url' => env('CRM_API_BASE_URL', 'https://api-crm.rustx.net'),
    'username' => env('CRM_API_USERNAME'),
    'password' => env('CRM_API_PASSWORD'),
  ],

  // Setu (insights.effortlessanalytics.com) — Tally-sourced sales insights,
  // used to reconcile actual invoiced sales into sales_pipelines. See
  // App\Services\SetuReportService.
  'setu' => [
    'base_url' => env('SETU_BASE_URL', 'https://insights.effortlessanalytics.com'),
    'access_token' => env('SETU_ACCESS_TOKEN'),
    'report_id' => env('SETU_SALES_REPORT_ID', 2899),
  ],

  // Tally's daily text exports (Abc.txt, Invoice Details.txt, OutstandingDet.txt,
  // PartyWiseSaleOrder.txt) -- read by `hrx:import-tally`. On the server this is
  // the inbox the exports are uploaded to; locally it can point at the folder
  // Tally writes to.
  'tally' => [
    'export_dir' => env('TALLY_EXPORT_DIR', storage_path('app/tally')),

    // Tally's own HTTP/XML gateway (the port opened by Tally's Client/Server setting; ODBC uses the same one).
    // READ-ONLY use only, see App\Services\TallyGateway. `gateway_company` is the name of the ONE company that
    // may be read: every request names it, and a pull refuses to start if it is not the company Tally has
    // loaded, so a sandbox and the real books can never be mixed up.
    'gateway_url' => env('TALLY_GATEWAY_URL', 'http://localhost:9000'),
    'gateway_company' => env('TALLY_GATEWAY_COMPANY', ''),
    'gateway_timeout' => (int) env('TALLY_GATEWAY_TIMEOUT', 30),

    // Where the ONE write this app does (Credit & Contacts > update a customer's contact person/mobile,
    // App\Http\Controllers\tenant\TallyContactUpdateController) actually goes. Deliberately a SEPARATE,
    // unset-by-default pair from gateway_url/gateway_company above (which default to the sandbox) -- so a
    // write can never silently land on whatever company happens to be configured for reads, and the feature
    // fails with a clear "not configured" error until someone deliberately points it at a real company.
    'write_gateway_url' => env('TALLY_WRITE_GATEWAY_URL', ''),
    'write_gateway_company' => env('TALLY_WRITE_GATEWAY_COMPANY', ''),

    // The companies the push commands (hrx:tally-push and its sibling master-sync commands) know how to pull.
    // Each one is kept as its own tagged slice of the tally_* tables (see the 2026_10_14_000000 migration's
    // company column) rather than a separate database -- Tally itself can have several companies open at once
    // (TallyGateway::companies()/assertCompany() already read whichever is named per-request), so no separate
    // Tally instance or manual company-switching is needed to pull both.
    //
    // 'code' (the array key) is what every push command's --company option and every tally_* row's `company`
    // column store -- short and stable, never rename one once data has been pushed under it. 'name' must be
    // the EXACT string Tally shows under Gateway of Tally > F3 (Company) / "List of Companies" -- copy it
    // character for character, including the parenthetical date range Tally appends.
    //
    // write_gateway_company above still works unmodified as the default when a command's --company is omitted
    // (back-compat for the scheduled tasks until deployment/office-pc/*.ps1 are updated to pass --company
    // explicitly), so adding a company here never changes what today's unmodified cron pushes.
    'companies' => [
      'hitech' => [
        'name' => env('TALLY_COMPANY_HITECH_NAME', env('TALLY_WRITE_GATEWAY_COMPANY', '')),
        'label' => env('TALLY_COMPANY_HITECH_LABEL', 'Hi Tech'),
      ],
      'kif' => [
        'name' => env('TALLY_COMPANY_KIF_NAME', ''),
        'label' => env('TALLY_COMPANY_KIF_LABEL', 'Keep It Fresh'),
      ],
    ],
  ],

  // Google Maps key for Live Location, Timeline, SOS map and client
  // create/edit. MUST be read via config() in Blade, never env(): env()
  // returns null once `php artisan config:cache` has run, which silently
  // broke every map in production.
  'google_maps' => [
    'key' => env('GOOGLE_MAPS_API_KEY'),
  ],

  // Shared secret a telephony vendor must send back on every call-event
  // push to our webhook receiver. See App\Http\Controllers\Api\TelephonyWebhookController.
  'telephony_webhook' => [
    'secret' => env('TELEPHONY_WEBHOOK_SECRET'),
  ],

  // Dinstar SMS/GSM gateway (office LAN, e.g. 192.168.2.6) -- auth is plain
  // HTTP Basic using the device's own web-login credentials, not a
  // separate API token. See App\Services\DinstarSmsService.
  'dinstar' => [
    'base_url' => env('DINSTAR_BASE_URL'),
    'username' => env('DINSTAR_USERNAME'),
    'password' => env('DINSTAR_PASSWORD'),
  ],

];