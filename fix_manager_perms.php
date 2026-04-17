<?php

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- Harmonizing Permissions for Managers & Staff (v2) ---\n";

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
    echo "Manager role updated.\n";
}

// 2. Give Employees Hierarchy Access
if ($employee = Role::where('name', 'employee')->first()) {
    $employee->givePermissionTo([$hierarchyViewPerm]);
    echo "Employee role updated.\n";
}

// Clear Cache
app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
echo "Permission cache cleared.\n";

echo "Done.\n";
