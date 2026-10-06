<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps everything built on Tally data (Sales Sources and its analytics/exports, the Tally tabs on a Customer
 * Profile, the sidebar entry) to the people named in TALLY_VIEWER_EMAILS -- on the live server, one person.
 *
 * Sits on top of the normal role rules (admin/HR/accounts), it does not replace them: holding the admin role is
 * NOT enough, the login email must be on the list. Anyone else gets a 404, not a 403, so the pages do not even
 * advertise that they exist.
 *
 * Same shape as RestrictTelephonyAccess (one static permits() that the middleware, the sidebar via App\Support\Nav
 * and the controllers all ask), with one deliberate difference: an EMPTY list is not "open to everyone". On a
 * local developer machine it falls back to the normal role rules so nothing changes day to day; on any other
 * server it means nobody, so a missing .env value can never expose financial data.
 */
class RestrictTallyAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (static::permits($request->user())) {
            return $next($request);
        }

        abort(404);
    }

    /**
     * May this user see Tally-derived data at all?
     *
     * @param  \App\Models\User|null  $user
     */
    public static function permits($user): bool
    {
        if (!$user) {
            return false;
        }

        $allowed = config('tally_push.viewers', []);

        if (empty($allowed)) {
            return app()->environment('local');
        }

        $email = strtolower(trim((string) ($user->email ?? '')));

        return $email !== '' && in_array($email, $allowed, true);
    }
}
