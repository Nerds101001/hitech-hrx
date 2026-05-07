<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

echo "--- IT DEPARTMENT USER CHECK ---\n";
$users = User::where('department_id', 4)->get(['id', 'first_name', 'last_name']);
echo "Count: " . $users->count() . "\n";
foreach ($users as $u) {
    echo "ID: " . $u->id . " | Name: " . $u->first_name . " " . $u->last_name . "\n";
}
