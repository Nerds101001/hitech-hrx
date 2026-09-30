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

    /*
    |--------------------------------------------------------------------------
    | AI Model
    |--------------------------------------------------------------------------
    |
    | Despite the OpenAI naming, this stack talks to NVIDIA NIM
    | (OPENAI_BASE_URL is integrate.api.nvidia.com, the key is an nvapi- one),
    | so these are NVIDIA-hosted builds and NVIDIA retires them on its own
    | schedule. The previous default, openai/gpt-oss-120b, reached end of life
    | on 2026-09-03: the endpoint answers 410 Gone with a body that carries no
    | "choices" key, so every call surfaced as `Undefined array key "choices"`
    | and every AI feature had been dead for weeks without anyone reading the
    | actual response.
    |
    | Check `GET {base_url}/models` before changing this - it lists what is
    | genuinely available, and openai/gpt-oss-20b was the only surviving
    | openai/* build when this was last checked.
    |
    */

    'ai_model' => env('AI_MODEL', 'openai/gpt-oss-20b'),

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
