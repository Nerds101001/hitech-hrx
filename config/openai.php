<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OpenAI API Key
    |--------------------------------------------------------------------------
    |
    | Here you may specify your OpenAI API Key.
    |
    */

    'api_key' => env('OPENAI_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | OpenAI Organization
    |--------------------------------------------------------------------------
    |
    | Here you may specify your OpenAI Organization.
    |
    */

    'organization' => env('OPENAI_ORGANIZATION'),

    /*
    |--------------------------------------------------------------------------
    | OpenAI Base URL
    |--------------------------------------------------------------------------
    |
    | Here you may specify the base URL for the OpenAI API.
    |
    */

    'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | The timeout may be used to specify the maximum number of seconds to wait
    | for a response. By default the timeout is 30 seconds.
    |
    */

    'request_timeout' => env('OPENAI_REQUEST_TIMEOUT', 30),

    'ai_model' => env('AI_MODEL', 'openai/gpt-oss-120b'),

    /*
    |--------------------------------------------------------------------------
    | Vault Audit — how long the browser is willing to wait
    |--------------------------------------------------------------------------
    |
    | The Digital Library audit runs the model against an uploaded document. The
    | provider's queue for the larger models swings from a few seconds to
    | several minutes, so the upload asks for an answer and gives up after this
    | many seconds, handing the file to the background backlog instead of
    | failing. Keep it comfortably under the web server's own limit.
    |
    | Background runs (vault:audit-pending) use audit_timeout_queued, which can
    | be generous because nobody is watching.
    |
    */

    'audit_timeout' => env('AI_AUDIT_TIMEOUT', 20),

    'audit_timeout_queued' => env('AI_AUDIT_TIMEOUT_QUEUED', 300),
];
