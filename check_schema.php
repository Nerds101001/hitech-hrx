<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "--- DEPARTMENTS COLUMNS ---\n";
print_r(Schema::getColumnListing('departments'));

echo "\n--- TEAMS COLUMNS ---\n";
print_r(Schema::getColumnListing('teams'));

echo "\n--- DESIGNATIONS COLUMNS ---\n";
print_r(Schema::getColumnListing('designations'));

echo "\n--- DESIGNATIONS DATA (First 5) ---\n";
$designations = DB::table('designations')->limit(5)->get();
foreach ($designations as $ds) {
    echo "ID: " . $ds->id . " | Name: " . $ds->name . " | Dept ID: " . ($ds->department_id ?? 'NULL') . "\n";
}

echo "\n--- TEAMS DATA (First 5) ---\n";
$teams = DB::table('teams')->limit(5)->get();
foreach ($teams as $t) {
    echo "ID: " . $t->id . " | Name: " . $t->name . "\n";
}
