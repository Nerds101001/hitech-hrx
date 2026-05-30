<?php
require 'd:/live_server/vendor/autoload.php';
$app = require_once 'd:/live_server/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

echo "=== USER LIST (TENANT USERS) ===\n\n";

$users = User::orderBy('id', 'desc')->take(20)->get();
foreach ($users as $user) {
    $statusStr = $user->status instanceof \BackedEnum ? $user->status->value : (string)$user->status;
    echo "ID: {$user->id} | Name: {$user->first_name} {$user->last_name} | Email: {$user->email} | Status: {$statusStr}\n";
}
echo "\nDone.\n";
