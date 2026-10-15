<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the Leaver Handover tool (preview/apply/show/export/revert, and its
 * sidebar entry) to the people named in config('handover.viewer_emails') who
 * are also Admin/HR -- same shape as RestrictTallyAccess / RestrictTelephonyAccess:
 * one static permits() that the middleware, the sidebar via App\Support\Nav,
 * and LeaverHandoverController all ask, so the rule lives in one place.
 *
 * Sits on top of the normal role rules, it does not replace them: holding
 * the admin role is NOT enough, the login email must be on the list.
 * Anyone else gets a 404, not a 403, so the pages do not even advertise
 * that they exist.
 */
class RestrictHandoverAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (static::permits($request->user())) {
            return $next($request);
        }

        abort(404);
    }

    /**
     * May this user see the Leaver Handover tool at all?
     *
     * @param  \App\Models\User|null  $user
     */
    public static function permits($user): bool
    {
        if (!$user || !$user->hasRole(['admin', 'Admin', 'hr', 'HR'])) {
            return false;
        }

        $allowed = array_map('strtolower', (array) config('handover.viewer_emails', ['ai@rustx.com']));
        $email = strtolower(trim((string) ($user->email ?? '')));

        return $email !== '' && in_array($email, $allowed, true);
    }
}
