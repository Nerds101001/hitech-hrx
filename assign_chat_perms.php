<?php

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- Assigning Bot Chat Permissions ---\n";

$chatPerm = Permission::firstOrCreate(['name' => 'bot.chat']);

foreach (['admin', 'hr', 'manager', 'employee'] as $roleName) {
    $role = Role::where('name', $roleName)->first();
    if ($role) {
        echo "Giving bot.chat to $roleName...\n";
        $role->givePermissionTo($chatPerm);
    }
}

echo "Done.\n";
