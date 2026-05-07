<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\LeaveRequest;
use App\Notifications\Leave\NewLeaveRequest;
use App\Helpers\NotificationHelper;

$u = User::where('email', 'abc1010012@gmail.com')->first(); // A dummy employee
if (!$u) {
    echo "Dummy user not found.\n";
    exit;
}

$lr = LeaveRequest::create([
    'user_id' => $u->id,
    'leave_type_id' => 1,
    'from_date' => now()->addDays(10)->toDateString(),
    'to_date' => now()->addDays(11)->toDateString(),
    'user_notes' => 'DIAGNOSTIC TEST - PLEASE IGNORE',
    'status' => 'pending'
]);

echo "Created test leave request ID: {$lr->id}\n";

// Now call the notification helper
NotificationHelper::notifyAdminHR(new NewLeaveRequest($lr));

echo "Notification triggered. Check notifications table for ID: {$lr->id}\n";
