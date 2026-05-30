<?php
require '/home/u989061032/domains/hitechgroup.in/public_html/hrx/vendor/autoload.php';
$app = require '/home/u989061032/domains/hitechgroup.in/public_html/hrx/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$oldEmail = 'legal.rustx@gmail.com';
$newEmail = 'rustx.legal@gmail.com';

$user = DB::table('users')->whereRaw('LOWER(email) = ?', [strtolower($oldEmail)])->first();

if (!$user) {
    echo "ERROR: User {$oldEmail} not found\n";
    exit(1);
}

echo "Found: ID: {$user->id}, Name: {$user->first_name} {$user->last_name}, Email: {$user->email}\n";

// Check new email not already taken
$exists = DB::table('users')->whereRaw('LOWER(email) = ?', [strtolower($newEmail)])->whereNull('deleted_at')->first();
if ($exists) {
    echo "ERROR: Email {$newEmail} is already in use by user ID: {$exists->id}\n";
    exit(1);
}

DB::table('users')->where('id', $user->id)->update(['email' => $newEmail, 'updated_at' => now()]);

$updated = DB::table('users')->where('id', $user->id)->value('email');
echo "SUCCESS: Email updated to {$updated}\n";
