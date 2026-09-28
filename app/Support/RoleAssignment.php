<?php

namespace App\Support;

/**
 * A role made on the Roles & Permissions page is a set of extra boxes on top
 * of being an employee - "Travel Admin", "HR Executive" - not a replacement
 * for it. Self-service pages (My Leaves, My Attendance, the employee
 * dashboard), the sidebar and a number of checks ask "is this person an
 * employee?", so a person given a custom role keeps 'employee' as well.
 */
class RoleAssignment
{
    /** Roles that stand on their own; any other role name is a custom role. */
    public const BASE_ROLES = ['admin', 'hr', 'manager', 'accounts', 'employee', 'office_employee', 'super_admin', 'customer'];

    public static function isCustom(?string $role): bool
    {
        return $role !== null && $role !== '' && !in_array(strtolower($role), self::BASE_ROLES, true);
    }

    /** The roles to give someone picked for $role. */
    public static function rolesFor(string $role): array
    {
        return self::isCustom($role) ? [$role, 'employee'] : [$role];
    }

    /**
     * The role to show and preselect for a person: their custom role when
     * they have one alongside 'employee', otherwise their only role. Without
     * this the employee form preselected 'employee', and saving it again
     * quietly took the custom role away.
     */
    public static function primaryRole($user): ?string
    {
        $names = $user->roles->pluck('name');

        return $names->first(fn ($r) => strtolower($r) !== 'employee') ?? $names->first();
    }
}
