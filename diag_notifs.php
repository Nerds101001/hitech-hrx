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

echo "\n--- AUDIT OF A RECENT NOTIFICATION ---\n";
$lastNotif = Notification::where('type', 'App\Notifications\Leave\NewLeaveRequest')->latest()->first();
if ($lastNotif) {
    $data = $lastNotif->data;
    $employeeId = $data['request']['user_id'] ?? null;
    if ($employeeId) {
        $employee = User::find($employeeId);
        echo "Employee: " . $employee->getFullName() . " (ID: $employeeId)\n";
        echo "Reporting To ID: " . $employee->reporting_to_id . "\n";
        $mgr = $employee->reportingTo;
        echo "Reporting Manager: " . ($mgr ? $mgr->getFullName() . " ({$mgr->email})" : "None") . "\n";
        
        echo "\nRecipients for this leave (simulated):\n";
        $hrAdminsList = User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['hr', 'admin']);
        })->where('status', \App\Enums\UserAccountStatus::ACTIVE)->get();
        
        $notifiables = $hrAdminsList;
        if ($mgr) {
            $notifiables = $notifiables->push($mgr);
        }
        $notifiables = $notifiables->filter()->unique('id');
        
        foreach($notifiables as $n) {
            echo "- " . $n->getFullName() . " ({$n->email}) | Roles: " . implode(',', $n->getRoleNames()->toArray()) . "\n";
        }
    }
}
