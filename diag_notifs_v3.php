<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\LeaveRequest;
use App\Models\User;

$requests = LeaveRequest::whereBetween('created_at', ['2026-05-04 09:20:00', '2026-05-04 09:30:00'])->get();
foreach($requests as $r) {
    $u = User::find($r->user_id);
    echo "ID: {$r->id}, Employee: " . ($u ? $u->getFullName() : 'Unknown') . " (ID: {$r->user_id}), Created At: {$r->created_at}\n";
}
