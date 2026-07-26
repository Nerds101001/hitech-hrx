<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/**
 * Registers the granular "module admin" permissions that let a specific
 * user manage CCare/sales-field attendance or view all sales/CCare data
 * without being promoted to the full admin/hr role. Idempotent — safe to
 * re-run on any environment.
 */
class CcAttendanceSalesOpsPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissionNames = [
            'cc_attendance.manage',
            'sales_ops.view_all',
        ];

        foreach ($permissionNames as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // admin/hr already pass the equivalent hasRole() checks unconditionally,
        // but attach the permissions too so the role editor UI reflects reality.
        foreach (['admin', 'hr'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo($permissionNames);
            }
        }
    }
}
