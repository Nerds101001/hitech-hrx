<?php

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use App\Models\Department;
use App\Models\Designation;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- Setting up Accounts Role (v2) ---\n";

// Safe check for tenancy presence to prevent crash on non-tenant environments
$hasTenancy = class_exists('Stancl\Tenancy\Database\Models\Tenant') && function_exists('tenancy');

if ($hasTenancy) {
    // Resolve Tenant model dynamically to prevent compile-time import crash
    $tenantClass = 'App\Models\Tenant';
    $tenants = $tenantClass::all();
    
    if ($tenants->isEmpty()) {
        echo "Tenancy is present but no tenants found. Running on default connection...\n";
        runAccountsRoleSetup();
    } else {
        foreach ($tenants as $tenant) {
            echo "Processing Tenant: {$tenant->id}...\n";
            tenancy()->initialize($tenant);
            try {
                runAccountsRoleSetup();
            } catch (\Exception $e) {
                echo "  Error: " . $e->getMessage() . "\n";
            }
            tenancy()->end();
        }
    }
} else {
    echo "Tenancy not detected. Running on default database connection...\n";
    runAccountsRoleSetup();
}

echo "Done.\n";

function runAccountsRoleSetup() {
    // Create or get the 'accounts' role
    $accountsRole = Role::firstOrCreate(['name' => 'accounts', 'guard_name' => 'web']);

    // Define the permissions needed based on user request:
    $permissions = [
        'hr.leaves.view', 'hr.leaves.approve', 'leaveRequests.view', 'leaveRequests.approve', // View and mark leaves approved
        'payroll.manage', 'payroll.view', // Update salary breakdown, generate payroll, display to users
        'hr.attendance.view', 'hr.attendance.manage', 'Manage Attendance', // Check and import attendance
        'hr.employees.view', 'hr.employees.edit', // Update employee salary details
        'hr.approvals.view', 'approvals.view', 'hr.dashboard.view', // General access to HR modules
    ];

    // Ensure permissions exist and gather them
    $permsToSync = [];
    foreach ($permissions as $permName) {
        $permsToSync[] = Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
    }

    // Sync permissions to the accounts role
    $accountsRole->syncPermissions($permsToSync);
    echo "  Accounts role synced with " . count($permsToSync) . " permissions.\n";

    // Find the accounts department and assign the role to those users
    $accountsDept = Department::where('name', 'Accounts')->first();
    if ($accountsDept) {
        $designations = Designation::where('department_id', $accountsDept->id)->pluck('id');
        $users = User::whereIn('designation_id', $designations)->get();
        foreach ($users as $user) {
            $user->assignRole($accountsRole);
            echo "  Assigned accounts role to: {$user->email}\n";
        }
    } else {
        echo "  Accounts department not found.\n";
    }

    // Clear Cache
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    echo "  Permission cache cleared.\n";
}
