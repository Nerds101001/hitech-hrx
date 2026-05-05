<?php
$appRoot = '/home/u989061032/domains/hitechgroup.in/public_html/hrx';
require $appRoot . '/vendor/autoload.php';
$app = require_once $appRoot . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- Current Users with No Site/Unit Assigned (Fresh List) ---\n";

$users = \App\Models\User::withoutGlobalScopes()
    ->with('department')
    ->whereNull('site_id')
    ->get();

foreach($users as $u) {
    echo "- " . $u->first_name . " " . $u->last_name . " (" . $u->email . ") | Dept: " . ($u->department->name ?? 'Unassigned') . "\n";
}

echo "\nTotal Unassigned Users: " . $users->count() . "\n";
