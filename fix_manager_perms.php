<?php

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- Harmonizing Permissions for Managers & Staff (v4) ---\n";

// Safe check for tenancy presence to prevent crash on non-tenant environments
$hasTenancy = class_exists('Stancl\Tenancy\Database\Models\Tenant') && function_exists('tenancy');

if ($hasTenancy) {
    // Resolve Tenant model dynamically to prevent compile-time import crash
    $tenantClass = 'App\Models\Tenant';
    $tenants = $tenantClass::all();
    
    if ($tenants->isEmpty()) {
        echo "Tenancy is present but no tenants found. Running on default connection...\n";
        runPermissions();
    } else {
        foreach ($tenants as $tenant) {
            echo "Processing Tenant: {$tenant->id}...\n";
            tenancy()->initialize($tenant);
            try {
                runPermissions();
            } catch (\Exception $e) {
                echo "  Error: " . $e->getMessage() . "\n";
            }
            tenancy()->end();
        }
    }
} else {
    echo "Tenancy not detected. Running on default database connection...\n";
    runPermissions();
}

echo "Done.\n";

function runPermissions() {
    // Sidebar View Permissions
    $approvalsViewPerm = Permission::firstOrCreate(['name' => 'hr.approvals.view']);
    $settingsViewPerm = Permission::firstOrCreate(['name' => 'hr.settings.view']);
    $hierarchyViewPerm = Permission::firstOrCreate(['name' => 'organizationHierarchy.view']);

    // Leave & Expense Permissions
    $leaveViewPerm = Permission::firstOrCreate(['name' => 'leaveRequests.view']);
    $leaveApprovePerm = Permission::firstOrCreate(['name' => 'leaveRequests.approve']);
    $expenseViewPerm = Permission::firstOrCreate(['name' => 'expenseRequests.view']);
    $expenseApprovePerm = Permission::firstOrCreate(['name' => 'expenseRequests.approve']);
    $approvalsIndexPerm = Permission::firstOrCreate(['name' => 'approvals.view']);

    // 1. Give Managers Sidebar Access
    if ($manager = Role::where('name', 'manager')->first()) {
        $manager->givePermissionTo([
            $approvalsViewPerm,
            $hierarchyViewPerm,
            $leaveViewPerm, 
            $leaveApprovePerm, 
            $expenseViewPerm, 
            $expenseApprovePerm, 
            $approvalsIndexPerm
        ]);
        echo "  Manager role updated.\n";
    }

    // 2. Give Employees Hierarchy Access
    if ($employee = Role::where('name', 'employee')->first()) {
        $employee->givePermissionTo([$hierarchyViewPerm]);
        echo "  Employee role updated.\n";
    }

    // Clear Cache
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    echo "  Permission cache cleared.\n";
}
