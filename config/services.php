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

  // Google Maps key for Live Location, Timeline, SOS map and client
  // create/edit. MUST be read via config() in Blade, never env(): env()
  // returns null once `php artisan config:cache` has run, which silently
  // broke every map in production.
  'google_maps' => [
    'key' => env('GOOGLE_MAPS_API_KEY'),
  ],

];