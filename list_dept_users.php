<?php
require 'd:/live_server/vendor/autoload.php';
$app = require_once 'd:/live_server/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$depts = App\Models\Department::orderBy('name')->get();

foreach($depts as $d) {
    $users = App\Models\User::where('department_id', $d->id)->where('status', 'active')->get();
    echo "\n=== {$d->name} ({$users->count()} active users) ===\n";
    foreach($users as $u) {
        echo "- {$u->first_name} {$u->last_name} ({$u->email})\n";
    }
}
