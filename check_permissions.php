<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Role;

$role = Role::where('name', 'accounts')->first();
if ($role) {
    echo "=== Permissions for role 'accounts' ===\n";
    $permissions = $role->permissions->pluck('name')->toArray();
    if (empty($permissions)) {
        echo "No permissions assigned to 'accounts' role.\n";
    } else {
        foreach ($permissions as $perm) {
            echo "- {$perm}\n";
        }
    }
} else {
    echo "Role 'accounts' not found.\n";
}
