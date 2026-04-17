<?php

use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Checking field_employee role...\n";
$role = DB::table('roles')->where('name', 'field_employee')->first();
if ($role) {
    echo "Found. Updating name to 'employee'...\n";
    DB::table('roles')->where('id', $role->id)->update(['name' => 'employee']);
    echo "Updated.\n";
} else {
    echo "Not found.\n";
}
