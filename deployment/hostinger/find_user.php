<?php
require '/home/u989061032/domains/hitechgroup.in/public_html/hrx/vendor/autoload.php';
$app = require '/home/u989061032/domains/hitechgroup.in/public_html/hrx/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// Search for any user with 'legal' or 'rustx' in email
$users = DB::table('users')
    ->whereRaw("LOWER(email) LIKE '%legal%' OR LOWER(email) LIKE '%rustx%'")
    ->whereNull('deleted_at')
    ->get(['id', 'first_name', 'last_name', 'email', 'status']);

if ($users->isEmpty()) {
    echo "No users found with 'legal' or 'rustx' in email\n";
} else {
    echo "Found " . $users->count() . " user(s):\n";
    foreach ($users as $u) {
        echo "  ID: {$u->id} | Name: {$u->first_name} {$u->last_name} | Email: {$u->email} | Status: {$u->status}\n";
    }
}
