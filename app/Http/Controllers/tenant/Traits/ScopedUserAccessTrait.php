<?php

namespace App\Http\Controllers\tenant\Traits;

use App\Models\User;
use App\Enums\UserAccountStatus;

/**
 * ScopedUserAccessTrait
 *
 * Provides a consistent, centralised way for controllers to determine
 * which user IDs the currently logged-in person is authorised to see.
 *
 *   Admin / HR / Accounts  →  null  (all users, no restriction)
 *   Manager                →  their direct reports + themselves
 *   Employee               →  themselves only
 *
 * Usage: `use ScopedUserAccessTrait;` in any tenant controller.
 */
trait ScopedUserAccessTrait
{
    /**
     * Returns an array of user IDs the current user may access,
     * or null if they have access to all users (admin/hr/accounts).
     *
     * @param  \App\Models\User  $user
     * @return int[]|null
     */
    private function getScopedUserIds(User $user): ?array
    {
        // Admins, HR, and Accounts see everyone
        if ($user->hasRole(['admin', 'Admin', 'super_admin', 'hr', 'HR', 'accounts'])) {
            return null;
        }

        // Managers see their direct reports + themselves
        if ($user->hasRole('manager')) {
            $ids   = User::where('reporting_to_id', $user->id)->pluck('id')->toArray();
            $ids[] = $user->id;
            return $ids;
        }

        // Everyone else sees only themselves
        return [$user->id];
    }
}
