<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Notification;
use App\Models\User;

$u = User::where('email', 'purchase@rustx.com')->first();
if ($u) {
    $n = Notification::where('notifiable_id', $u->id)->where('created_at', '2026-05-04 09:23:53')->first();
    if ($n) {
        echo "Type: {$n->type}\n";
        echo "Data: " . json_encode($n->data) . "\n";
    } else {
        echo "Notification not found for NITASHA at that time.\n";
    }
}
