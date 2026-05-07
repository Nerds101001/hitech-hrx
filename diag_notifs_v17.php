<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Notification;
use App\Models\User;

echo "Recent Notifications Audit (Last 50 entries)\n";
echo str_repeat("-", 80) . "\n";
echo sprintf("%-20s | %-30s | %-20s | %-20s\n", "Date", "Recipient", "Type", "Subject/Request ID");
echo str_repeat("-", 80) . "\n";

$notifs = Notification::orderBy('created_at', 'desc')->limit(50)->get();

foreach($notifs as $n) {
    $u = User::find($n->notifiable_id);
    $data = json_decode($n->data, true);
    
    $type = class_basename($n->type);
    $recipient = $u ? $u->email : "Unknown ({$n->notifiable_id})";
    $date = $n->created_at->toDateTimeString();
    
    $subject = "";
    if (isset($data['title'])) {
        $subject = $data['title'];
    } elseif (isset($data['message'])) {
        $subject = substr($data['message'], 0, 30) . "...";
    }
    
    if (isset($data['request']['id'])) {
        $subject .= " (Req ID: {$data['request']['id']})";
    }

    echo sprintf("%-20s | %-30s | %-20s | %-20s\n", $date, substr($recipient, 0, 30), $type, $subject);
}
