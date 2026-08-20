<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the telephony screens to a named list of people while the feature is
 * being tested.
 *
 * The role rules underneath are the real, permanent ones: own calls for an
 * employee, the team for a manager, everything for admin and HR. This sits on
 * top of them as a temporary gate, so that a feature which is live on
 * production but not yet trusted is not visible to the nine admin and HR users
 * who would otherwise find it in the menu and reasonably assume the numbers on
 * it mean something.
 *
 * Driven entirely by TELEPHONY_RESTRICT_TO_EMAILS. Empty or unset means no
 * restriction and the normal role rules apply on their own, so removing the
 * gate at the end of testing is one env change and a config:clear -- no deploy,
 * no code change, nothing to remember to delete.
 */
class RestrictTelephonyAccess
{
  public function handle(Request $request, Closure $next): Response
  {
    $allowed = config('telephony.restrict_to_emails', []);

    // No list configured: the feature is open to whoever the role rules allow.
    if (empty($allowed)) {
      return $next($request);
    }

    $user = $request->user();
    $email = strtolower(trim((string) ($user->email ?? '')));

    if ($email !== '' && in_array($email, $allowed, true)) {
      return $next($request);
    }

    // 403 rather than a redirect: this is a deliberate refusal, and bouncing
    // someone to a dashboard with no explanation reads as a broken link.
    abort(403, 'Telephony reporting is still being tested and is limited to the project team.');
  }
}
