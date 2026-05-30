<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Spatie\Permission\Models\Role;
use App\Models\User;

$roleName = 'accounts';
$role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
echo "Role '{$roleName}' created/verified.\n";

$emails = ['accounts@rustx.com', 'accoutns2@rustx.com', 'accounts1@docotrrust.com'];
foreach ($emails as $email) {
    $user = User::where('email', $email)->first();
    if ($user) {
        $user->assignRole($roleName);
        echo "Assigned '{$roleName}' role to {$email}.\n";
    }
}
