<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

echo "--- IT DEPT MANAGER ROLE CHECK ---\n";
$managers = User::role(['manager', 'Manager'])->where('department_id', 4)->get(['id', 'first_name', 'last_name']);
echo "Count: " . $managers->count() . "\n";
foreach ($managers as $m) {
    echo "ID: " . $m->id . " | Name: " . $m->first_name . " " . ($m->last_name ?? '') . "\n";
}
