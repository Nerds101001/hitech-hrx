<?php

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- Assigning Admin/HR Upload Permissions ---\n";

$viewPerm = Permission::firstOrCreate(['name' => 'library.view']);
$uploadPerm = Permission::firstOrCreate(['name' => 'library.upload']);

foreach (['admin', 'hr'] as $roleName) {
    $role = Role::where('name', $roleName)->first();
    if ($role) {
        echo "Giving library.view and library.upload to $roleName...\n";
        $role->givePermissionTo($viewPerm);
        $role->givePermissionTo($uploadPerm);
    }
}

echo "Done.\n";
