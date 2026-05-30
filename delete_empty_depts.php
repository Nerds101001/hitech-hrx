<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$depts = \App\Models\Department::withCount('users')->having('users_count', 0)->get();
$count = 0;
foreach($depts as $d) {
    echo "Deleting: " . $d->name . "\n";
    $d->delete();
    $count++;
}
echo "Deleted $count unused departments.\n";
