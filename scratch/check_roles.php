<?php
require 'd:/live_server/vendor/autoload.php';
$app = require_once 'd:/live_server/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Check columns on roles table
$cols = Schema::getColumnListing('roles');
echo "Roles table columns: " . implode(', ', $cols) . "\n\n";

// List all roles
$roles = DB::table('roles')->get();
echo "=== EXISTING ROLES ===\n";
foreach ($roles as $r) {
    $line = "ID: {$r->id} | name: {$r->name}";
    if (isset($r->guard_name)) $line .= " | guard: {$r->guard_name}";
    echo $line . "\n";
}
echo "\nTotal: " . $roles->count() . "\n";
