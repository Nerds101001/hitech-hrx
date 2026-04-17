<?php

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- Assigning Library Permissions ---\n";

$perm = Permission::firstOrCreate(['name' => 'library.view']);

$manager = Role::where('name', 'manager')->first();
if ($manager) {
    echo "Giving library.view to manager...\n";
    $manager->givePermissionTo($perm);
}

$employee = Role::where('name', 'employee')->first();
if ($employee) {
    echo "Giving library.view to employee...\n";
    $employee->givePermissionTo($perm);
}

echo "Done.\n";
