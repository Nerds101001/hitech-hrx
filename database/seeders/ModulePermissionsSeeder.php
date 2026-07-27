<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Str;

class ModulePermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Clear cached permissions
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // Do NOT delete existing permissions to preserve custom user setups
        // Permission::query()->delete();

        $registry = config('permissions_registry');
        $createdCount = 0;

        if (!$registry) {
            $this->command->error("Registry not found. Did you clear the config cache?");
            return;
        }

        foreach ($registry as $module => $permissions) {
            foreach ($permissions as $permName => $humanLabel) {
                Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
                $createdCount++;
            }
        }

        // Prune permissions that were removed from the registry because they
        // were never checked anywhere (dead checkboxes). ONLY this explicit
        // list is deleted — never "anything not in the registry", because the
        // recruitment module's Title-case catalog and ai.training.manage are
        // legitimately checked in code without being registry keys.
        $deadKeys = [
            'employee.create', 'employee.invite', 'employee.view_basic', 'employee.manage_hr_location',
            'attendance.view_my', 'leave.apply',
            'expense.dashboard', 'expense.apply',
            'payroll.export', 'payroll.view_my_slips',
            'recruitment.create', 'recruitment.edit', 'recruitment.delete', 'recruitment.manage_jobs',
            'hierarchy.manage',
            'vault.view', 'vault.train', 'vault.delete', 'vault.manage_taxonomy',
            'audit.export', 'goals.view', 'scorecards.view',
            // legacy menu-only keys replaced by roles.manage / audit.view / settings.manage
            'roles.view', 'auditLogs.view', 'hr.settings.view',
        ];
        $pruned = Permission::whereIn('name', $deadKeys)->where('guard_name', 'web')->delete();
        if ($pruned > 0) {
            app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $this->command->info("Advanced Business Permissions seeded successfully! Total loaded: {$createdCount}, dead keys pruned: {$pruned}");
    }
}
