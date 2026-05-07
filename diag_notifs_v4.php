<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Notification;
use App\Models\User;

$notifs = Notification::where('created_at', '2026-05-04 09:23:53')->get();
foreach($notifs as $n) {
    $u = User::find($n->notifiable_id);
    echo "Type: {$n->type} | Recipient: " . ($u ? $u->getFullName() . " ({$u->email})" : "Unknown") . " | Data: " . json_encode($n->data) . "\n";
}
