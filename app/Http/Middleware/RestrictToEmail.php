<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route to one or more exact, named accounts -- not a role, not a permission. Used where
 * access is deliberately scoped to specific people rather than a tier (e.g. Tally Explorer: the
 * Trend tab goes to ai@rustx.com AND mukul@rustx.com, the rest of the feature to ai@rustx.com only).
 * Multiple emails are comma-separated in the route definition ('restrict.email:a@x.com,b@x.com'),
 * which Laravel's middleware-parameter parsing already splits into separate arguments here.
 * Comparison is case-insensitive; a logged-out request is refused the same as a mismatched one.
 */
class RestrictToEmail
{
    public function handle(Request $request, Closure $next, string ...$emails): Response
    {
        $userEmail = (string) $request->user()?->email;
        foreach ($emails as $email) {
            if (strcasecmp($userEmail, $email) === 0) {
                return $next($request);
            }
        }

        abort(403);
    }
}
