<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Notification;
use App\Models\User;

$notifs = Notification::orderBy('created_at', 'desc')->limit(20)->get();
foreach($notifs as $n) {
    $data = json_decode($n->data, true);
    if (isset($data['request']['id']) && $data['request']['id'] == 17) {
        $u = User::find($n->notifiable_id);
        echo "Recipient: {$u->email}\n";
    }
}
