<?php

/**
 * Tunables for login logging. Read via config('auth_security.*').
 */
return [
    // Whether to also log successful/informational steps (OTP sent, login
    // success, trusted-device, logout). Failed attempts are ALWAYS logged.
    'log_successful' => (bool) env('AUTH_LOG_SUCCESSFUL', true),
];
