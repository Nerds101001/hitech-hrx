<?php

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- Admin Permissions ---\n";
$role = Role::where('name', 'admin')->first();
if ($role) {
    echo "Role: " . $role->name . "\n";
    echo "Permissions: " . $role->permissions->pluck('name')->implode(', ') . "\n";
} else {
    echo "Admin role not found\n";
}

echo "\n--- All Available Permissions ---\n";
Permission::all()->pluck('name')->each(function ($p) {
    echo "- " . $p . "\n";
});
