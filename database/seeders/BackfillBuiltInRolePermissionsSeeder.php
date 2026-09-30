<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/**
 * Stores, for hr / manager / accounts, the permission rows their role NAME
 * already grants in code (config/role_builtin_permissions.php), so the Roles
 * & Permissions matrix shows those boxes ticked instead of blank.
 *
 * Add-only and idempotent: givePermissionTo never removes a row, and a row
 * the role already holds is skipped. Every listed permission is only ever
 * checked as "role OR permission" for that role, so the new rows change no
 * one's access.
 *
 * Supersedes CcAttendanceSalesOpsPermissionSeeder for this purpose - that
 * one's hand-written lists are broader than the code (e.g. it gives manager
 * leave.approve and payroll.view, which would open sidebar items managers
 * don't see today). Don't run it.
 */
class BackfillBuiltInRolePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $builtIn = config('role_builtin_permissions', []);

        // The one place a role-held permission is read in bulk rather than as
        // "role OR permission": AdminRoleGuard lets an employee/office_employee
        // past if they hold any ELEVATED permission through ANY of their
        // roles. A person holding e.g. hr + employee would be let in once hr
        // carries those rows, so refuse to run while anyone is in that state.
        $mixed = User::whereHas('roles', fn($q) => $q->whereIn('name', array_keys($builtIn)))
            ->whereHas('roles', fn($q) => $q->whereIn('name', ['employee', 'office_employee']))
            ->pluck('id');
        if ($mixed->isNotEmpty()) {
            $this->command?->error('Aborted - users holding a built-in role AND employee/office_employee: ' . $mixed->implode(', '));
            return;
        }

        // Every checkbox on the Roles page needs its Permission row, or ticking
        // it saves nothing (the save keeps only names that exist). Production
        // was missing 49 of them. A row nobody holds grants nothing.
        $created = 0;
        foreach (collect(config('permissions_registry', []))->flatMap(fn($perms) => array_keys($perms)) as $name) {
            $created += Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'])->wasRecentlyCreated ? 1 : 0;
        }
        $this->command?->info("permission rows created: {$created}");

        foreach ($builtIn as $roleName => $permissionNames) {
            $role = Role::where('name', $roleName)->first();
            if (!$role) {
                continue;
            }

            foreach ($permissionNames as $name) {
                Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            }

            $missing = array_values(array_diff($permissionNames, $role->permissions->pluck('name')->all()));
            if ($missing) {
                $role->givePermissionTo($missing);
            }
            $this->command?->info("{$roleName}: added " . count($missing) . ' of ' . count($permissionNames));
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
