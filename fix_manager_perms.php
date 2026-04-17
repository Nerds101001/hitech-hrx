<?php

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- Harmonizing Permissions for Managers & Staff ---\n";

// Ensure permissions exist
$hierarchyPerm = Permission::firstOrCreate(['name' => 'organizationHierarchy.view']);
$leaveViewPerm = Permission::firstOrCreate(['name' => 'leaveRequests.view']);
$leaveApprovePerm = Permission::firstOrCreate(['name' => 'leaveRequests.approve']);
$expenseViewPerm = Permission::firstOrCreate(['name' => 'expenseRequests.view']);
$expenseApprovePerm = Permission::firstOrCreate(['name' => 'expenseRequests.approve']);
$approvalsViewPerm = Permission::firstOrCreate(['name' => 'approvals.view']); // Profile updates

// 1. Give Hierarchy View to EVERYONE
foreach (['admin', 'hr', 'manager', 'employee'] as $roleName) {
    if ($role = Role::where('name', $roleName)->first()) {
        $role->givePermissionTo($hierarchyPerm);
    }
}

// 2. Give Managers Approval Access
if ($manager = Role::where('name', 'manager')->first()) {
    $manager->givePermissionTo([
        $leaveViewPerm, 
        $leaveApprovePerm, 
        $expenseViewPerm, 
        $expenseApprovePerm, 
        $approvalsViewPerm
    ]);
    echo "Manager role updated with approval permissions.\n";
}

// 3. Ensure Employee can see their own leaves (usually they have separate permissions or role-based check)
if ($employee = Role::where('name', 'employee')->first()) {
    $employee->givePermissionTo([$leaveViewPerm, $expenseViewPerm]);
    echo "Employee role updated with view permissions.\n";
}

echo "Done.\n";
