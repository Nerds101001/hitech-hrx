<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Designation;
use App\Models\Department;
use App\Models\Team;
use Spatie\Permission\Models\Role;

echo "--- DESIGNATIONS ---\n";
$des = Designation::withoutGlobalScopes()->get();
echo "Total: " . $des->count() . "\n";
foreach($des as $d) {
    echo "ID: {$d->id}, Name: {$d->name}, Tenant: [{$d->tenant_id}]\n";
}

echo "\n--- DEPARTMENTS ---\n";
$dep = Department::withoutGlobalScopes()->get();
echo "Total: " . $dep->count() . "\n";
foreach($dep as $de) {
    echo "ID: {$de->id}, Name: {$de->name}, Tenant: [{$de->tenant_id}]\n";
}

echo "\n--- ROLES ---\n";
$rol = Role::get();
echo "Total: " . $rol->count() . "\n";
foreach($rol as $r) {
    echo "ID: {$r->id}, Name: {$r->name}, Tenant: [" . ($r->tenant_id ?? 'N/A') . "]\n";
}
