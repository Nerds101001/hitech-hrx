<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route to one exact, named account -- not a role, not a permission. Used where access is
 * deliberately scoped to a single person rather than a tier (e.g. Tally Explorer's Trend tab,
 * explicitly restricted to one named account while the rest of the feature goes to another).
 * Comparison is case-insensitive; a logged-out request is refused the same as a mismatched one.
 */
class RestrictToEmail
{
    public function handle(Request $request, Closure $next, string $email): Response
    {
        $userEmail = (string) $request->user()?->email;
        if (strcasecmp($userEmail, $email) !== 0) {
            abort(403);
        }

        return $next($request);
    }
}
