<?php

namespace App\Support;

use App\Models\AuthLoginLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Central recorder for authentication events (web + mobile), success or failure.
 *
 * Every call is wrapped so a logging/DB hiccup can NEVER break sign-in — security
 * visibility must not become a new way to lock anyone out.
 */
class AuthLog
{
    // Event constants (stored in auth_login_logs.event)
    public const UNKNOWN_USER       = 'unknown_user';       // email/username not found
    public const PASSWORD_FAILED    = 'password_failed';    // real account, wrong password
    public const ACCOUNT_LOCKED     = 'account_locked';     // tried while locked
    public const ACCOUNT_INACTIVE   = 'account_inactive';   // not an active status
    public const OTP_SENT           = 'otp_sent';           // password ok, code emailed
    public const OTP_FAILED         = 'otp_failed';         // wrong/expired code
    public const OTP_LOCKED         = 'otp_locked';         // locked after OTP strikes
    public const LOGIN_SUCCESS      = 'login_success';      // fully signed in (post-OTP)
    public const TRUSTED_DEVICE     = 'trusted_device_login'; // OTP skipped, trusted device
    public const BRUTE_FORCE_LOCK   = 'brute_force_lock';   // account locked after 3 fails
    public const LOGOUT             = 'logout';

    /**
     * Resolve the real client IP, looking through Cloudflare / proxy headers
     * first (prod sits behind Hostinger's CDN, so $request->ip() alone is the
     * edge node, not the visitor).
     */
    public static function clientIp(Request $request): ?string
    {
        foreach (['CF-Connecting-IP', 'True-Client-IP'] as $h) {
            $v = $request->header($h);
            if (is_string($v) && $v !== '') {
                return trim($v);
            }
        }
        $xff = $request->header('X-Forwarded-For');
        if (is_string($xff) && $xff !== '') {
            $first = trim(explode(',', $xff)[0]);
            if ($first !== '') {
                return $first;
            }
        }
        return $request->ip();
    }

    /** Write one attempt row. Never throws. */
    public static function record(
        string $event,
        ?User $user,
        ?string $emailInput,
        Request $request,
        bool $successful,
        array $meta = [],
        string $guard = 'web'
    ): void {
        // Successful/informational steps can be muted via config; failures always log.
        if ($successful && !config('auth_security.log_successful', true)) {
            return;
        }
        try {
            AuthLoginLog::create([
                'user_id'     => $user?->id,
                'email_input' => $emailInput ? mb_substr($emailInput, 0, 191) : ($user?->email),
                'guard'       => $guard,
                'event'       => $event,
                'successful'  => $successful,
                'ip_address'  => self::clientIp($request),
                'user_agent'  => mb_substr((string) $request->userAgent(), 0, 2000),
                'meta'        => $meta ?: null,
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('AuthLog::record failed: ' . $e->getMessage());
        }
    }
}
