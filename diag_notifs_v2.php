<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Notification;
use Spatie\Permission\Models\Role;

echo "--- ROLES IN DATABASE ---\n";
$roles = Role::all();
foreach($roles as $r) {
    echo "- ID: {$r->id}, Name: {$r->name}\n";
}

echo "\n--- USERS WITH HR/ADMIN ROLES ---\n";
$hrAdmins = User::whereHas('roles', function ($query) {
    $query->whereIn('name', ['hr', 'admin']);
})->get();
foreach($hrAdmins as $u) {
    echo "- {$u->getFullName()} ({$u->email}) | Roles: " . implode(',', $u->getRoleNames()->toArray()) . "\n";
}

echo "\n--- AUDIT OF RECENT NOTIFICATIONS ---\n";
$notifs = Notification::where('type', 'App\Notifications\Leave\NewLeaveRequest')->latest()->take(10)->get();
foreach($notifs as $n) {
    $data = $n->data;
    $employeeId = $data['request']['user_id'] ?? null;
    $recipient = User::find($n->notifiable_id);
    if ($employeeId) {
        $employee = User::find($employeeId);
        echo "Date: {$n->created_at} | Recipient: {$recipient->getFullName()} ({$recipient->email}) [Roles: " . implode(',', $recipient->getRoleNames()->toArray()) . "] | Applied By: " . $employee->getFullName() . " (Manager: " . ($employee->reportingTo?->getFullName() ?? 'None') . ")\n";
    }
}
