<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

echo "--- DEPARTMENTS ---\n";
$depts = \App\Models\Department::limit(10)->get(['id', 'name']);
foreach ($depts as $dept) {
    echo "ID: " . $dept->id . " | Name: " . $dept->name . "\n";
}

echo "\n--- TEAMS ---\n";
$teams = \App\Models\Team::limit(10)->get(['id', 'name', 'department_id']);
foreach ($teams as $team) {
    echo "ID: " . $team->id . " | Name: " . $team->name . " | Parent Dept ID: " . ($team->department_id ?? 'NONE') . "\n";
}
