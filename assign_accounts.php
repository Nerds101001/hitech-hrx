<?php

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$accountsRole = Role::firstOrCreate(['name' => 'accounts', 'guard_name' => 'web']);

$accountsDept = \App\Models\Department::withoutGlobalScopes()->where('name', 'Accounts Department')->first();
if ($accountsDept) {
    $designations = \App\Models\Designation::where('department_id', $accountsDept->id)->pluck('id');
    $users = User::whereIn('designation_id', $designations)->get();
    foreach ($users as $user) {
        $user->assignRole($accountsRole);
        echo "Assigned accounts role to: {$user->email}\n";
    }
} else {
    echo "Accounts Department not found.\n";
}

app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
echo "Done.\n";
