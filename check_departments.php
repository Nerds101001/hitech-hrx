<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$depts = \App\Models\Department::withCount('users')->orderBy('name')->get();

echo "ID | Name | Total User Count\n";
echo str_repeat('-', 40) . "\n";
foreach($depts as $d) {
    echo str_pad($d->id, 4) . '| ' . str_pad($d->name, 35) . '| ' . $d->users_count . "\n";
}
