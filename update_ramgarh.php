<?php
$appRoot = '/home/u989061032/domains/hitechgroup.in/public_html/hrx';
require $appRoot . '/vendor/autoload.php';
$app = require_once $appRoot . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Department;

$ramgarhId = 6;

echo "Starting Ramgarh Unit assignment...\n";

// 1. Update by Departments
$targetDepts = ['Export', 'Purchase Department', 'Finance Department', 'Accounts Department', 'IT Department'];
$deptIds = Department::withoutGlobalScopes()->whereIn('name', $targetDepts)->pluck('id')->toArray();

if (!empty($deptIds)) {
    $updatedDeptsCount = User::withoutGlobalScopes()
        ->whereIn('department_id', $deptIds)
        ->update(['site_id' => $ramgarhId]);
    echo "Updated $updatedDeptsCount users based on Department selection.\n";
} else {
    echo "Warning: Target departments not found.\n";
}

// 2. Update Specific Users
$specificEmails = [
    'hr@rustx.com',       // Raman
    'ccare4@rustx.com',   // Nidhi
    'ccare6@drbio.in',    // Manpreet 1
    'export1@tuffpaulin.com' // Manpreet 2
];

$updatedUsersCount = User::withoutGlobalScopes()
    ->whereIn('email', $specificEmails)
    ->update(['site_id' => $ramgarhId]);

echo "Updated $updatedUsersCount specific users (Raman, Nidhi, Manpreet).\n";

echo "Ramgarh Unit assignment completed successfully.\n";
