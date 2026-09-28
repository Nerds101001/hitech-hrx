<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Recruitment is for HR only. RecruitmentPermissionSeeder used to hand the
 * whole recruitment catalogue ("Manage Job", "Create Job", ...) to the manager
 * role as well, so every manager held 27 recruitment boxes. This takes them
 * back off the manager role. admin and hr keep theirs; nothing is deleted from
 * the permissions table, and no person's own special permissions are touched.
 *
 * Re-run safe: a second run finds nothing to remove.
 *
 *   php artisan db:seed --class=RemoveRecruitmentFromManagerRoleSeeder --force
 */
class RemoveRecruitmentFromManagerRoleSeeder extends Seeder
{
    /** Every recruitment permission name, old catalogue and registry key alike. */
    public const RECRUITMENT = [
        'recruitment.view',
        'Manage Job', 'Create Job', 'Edit Job', 'Delete Job', 'Show Job',
        'Manage Job Application', 'Create Job Application', 'Edit Job Application', 'Delete Job Application',
        'Show Job Application', 'Move Job Application', 'Add Job Application Skill', 'Add Job Application Note',
        'Delete Job Application Note',
        'Manage Job Category', 'Create Job Category', 'Edit Job Category', 'Delete Job Category',
        'Manage Job Stage', 'Create Job Stage', 'Edit Job Stage', 'Delete Job Stage',
        'Manage Custom Question', 'Create Custom Question', 'Edit Custom Question', 'Delete Custom Question',
        'Manage Interview Schedule', 'Create Interview Schedule', 'Edit Interview Schedule', 'Delete Interview Schedule',
        'Manage Job OnBoard', 'Create Job OnBoard', 'Edit Job OnBoard', 'Delete Job OnBoard',
    ];

    public function run(): void
    {
        $role = Role::where('name', 'manager')->where('guard_name', 'web')->first();
        if (!$role) {
            $this->command?->warn('No manager role - nothing to do.');
            return;
        }

        // A permission the role holds by name in code is locked on the Roles
        // page and must stay (config/role_builtin_permissions.php).
        $keep = config('role_builtin_permissions.manager', []);
        $remove = $role->permissions()->whereIn('name', self::RECRUITMENT)->pluck('name')
            ->reject(fn ($p) => in_array($p, $keep, true))->values();

        foreach ($remove as $p) {
            $role->revokePermissionTo($p);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info($remove->isEmpty()
            ? 'Manager role holds no recruitment permissions - nothing to remove.'
            : 'Removed ' . $remove->count() . ' recruitment permissions from the manager role: ' . $remove->implode(', '));
    }
}
