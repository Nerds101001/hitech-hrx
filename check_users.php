<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

echo "=== DATABASE: " . DB::connection()->getDatabaseName() . " ===\n";

$users = User::all();
echo "Total Users: " . $users->count() . "\n\n";

foreach ($users as $user) {
    $roles = $user->getRoleNames()->toArray();
    $dept = $user->department ? $user->department->name : ($user->designation && $user->designation->department ? $user->designation->department->name : 'None');
    echo "ID: {$user->id} | Name: {$user->first_name} {$user->last_name} | Email: {$user->email} | Dept: {$dept} | Roles: " . implode(', ', $roles) . "\n";
}

echo "\nAll Roles in Database:\n";
foreach (Role::all() as $role) {
    echo "- Name: {$role->name} | Guard: {$role->guard_name}\n";
}
