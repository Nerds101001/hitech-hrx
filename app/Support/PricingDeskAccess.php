<?php

namespace App\Support;

use App\Models\User;

/**
 * Who sees the Sales Pricing Desk, and who gets its Admin tab.
 *
 * Two layers, same pattern as the rest of the app's department-based gates
 * (see hr_notifications.php's Finance NOC departments): a sensible default
 * by department (config/pricing_desk.php), plus the `pricing_desk.view` /
 * `pricing_desk.manage` permissions as the admin-toggleable override — grant
 * or revoke either per role (Role Permission Matrix) or per person (User
 * Special Permissions), no code change needed for that part.
 */
class PricingDeskAccess
{
    public static function canView(User $user): bool
    {
        if ($user->hasRole(['admin', 'Admin', 'hr', 'HR'])) {
            return true;
        }
        if ($user->can('pricing_desk.view') || $user->can('pricing_desk.manage')) {
            return true;
        }

        return self::inDefaultDepartment($user);
    }

    public static function canManage(User $user): bool
    {
        return $user->hasRole(['admin', 'Admin']) || $user->can('pricing_desk.manage');
    }

    /** Whole-department-name match, not substring — "Pre Sales"/"Channel Sales" stay excluded. */
    private static function inDefaultDepartment(User $user): bool
    {
        $dept = strtolower(trim(optional($user->department)->name ?? ''));
        if ($dept === '') {
            return false;
        }

        $allowed = array_map(
            fn($n) => strtolower(trim($n)), (array) config('pricing_desk.visible_departments', [])
        );

        return in_array($dept, $allowed, true);
    }
}
