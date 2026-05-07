<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Notification;
use App\Models\User;

$notifs = Notification::where('created_at', '2026-05-04 09:23:53')->get();
echo "Total Notifications: " . $notifs->count() . "\n";
foreach($notifs as $n) {
    $u = User::find($n->notifiable_id);
    if ($u) {
        $roles = $u->getRoleNames()->toArray();
        echo "- {$u->email} | Roles: " . implode(',', $roles) . "\n";
    }
}
