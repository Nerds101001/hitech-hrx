<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$emails = ['purchase@rustx.com', 'Ardaman@hitechgroup.in', 'export2@rustx.com'];
foreach($emails as $e) {
    $u = User::where('email', $e)->first();
    if ($u) {
        echo "User: {$u->getFullName()} ({$u->email})\n";
        echo "Roles: " . implode(',', $u->getRoleNames()->toArray()) . "\n";
        echo "Permissions: " . implode(',', $u->getAllPermissions()->pluck('name')->toArray()) . "\n";
    }
}
