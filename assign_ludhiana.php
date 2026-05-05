<?php
$appRoot = '/home/u989061032/domains/hitechgroup.in/public_html/hrx';
require $appRoot . '/vendor/autoload.php';
$app = require_once $appRoot . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Department;
use App\Services\LeaveAccrualService;

$ludhianaId = 19;

echo "Starting Ludhiana Leave Policy assignment...\n";

// 1. Target Departments
$targetDepts = ['Export', 'Purchase Department', 'Finance Department', 'Accounts Department', 'IT Department'];
$deptIds = Department::withoutGlobalScopes()->whereIn('name', $targetDepts)->pluck('id')->toArray();

// 2. Target Specific Emails
$specificEmails = [
    'hr@rustx.com',
    'ccare4@rustx.com',
    'ccare6@drbio.in',
    'export1@tuffpaulin.com'
];

// Combine targets
$users = User::withoutGlobalScopes()
    ->where(function($q) use ($deptIds, $specificEmails) {
        $q->whereIn('department_id', $deptIds)
          ->orWhereIn('email', $specificEmails);
    })
    ->get();

$count = 0;
foreach($users as $user) {
    $user->leave_policy_profile_id = $ludhianaId;
    $user->save();
    
    // Initialize leaves for the new policy
    LeaveAccrualService::initializeForUser($user);
    $count++;
    echo "Processed: {$user->email}\n";
}

echo "\nLudhiana Policy assigned to $count users successfully.\n";
