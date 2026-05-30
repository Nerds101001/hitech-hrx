<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

$email = 'accounts@demo.com';
$user = User::where('email', $email)->first();

if (!$user) {
    $user = User::create([
        'first_name' => 'Demo',
        'last_name' => 'Accounts',
        'email' => $email,
        'password' => Hash::make('password'),
        'status' => 'active',
        'code' => 'EMP-ACC1',
        'tenant_id' => 1
    ]);
    echo "Created user $email\n";
} else {
    $user->password = Hash::make('password');
    $user->save();
    echo "Updated user $email\n";
}

$role = Role::where('name', 'accounts')->first();
if ($role) {
    $user->assignRole($role);
    echo "Assigned accounts role to $email\n";
} else {
    echo "Role not found\n";
}
