<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Department;
use App\Models\Designation;
use Illuminate\Support\Facades\DB;

$data = [
    'departments' => Department::all()->toArray(),
    'designations' => Designation::all()->toArray(),
    'users' => User::with('roles')->get()->map(function($user) {
        $u = $user->toArray();
        $u['password'] = $user->password; // get password hash
        $u['roles'] = $user->roles->pluck('name')->toArray();
        return $u;
    })->toArray(),
];

file_put_contents('live_data_export.json', json_encode($data, JSON_PRETTY_PRINT));
echo "Data exported successfully to live_data_export.json\n";
