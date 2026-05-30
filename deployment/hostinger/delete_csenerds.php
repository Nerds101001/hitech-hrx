<?php
require '/home/u989061032/domains/hitechgroup.in/public_html/hrx/vendor/autoload.php';
$app = require '/home/u989061032/domains/hitechgroup.in/public_html/hrx/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// Find the user
$user = DB::table('users')
    ->whereRaw('LOWER(email) = ?', ['csenerds@gmail.com'])
    ->whereRaw('LOWER(first_name) = ? OR LOWER(name) LIKE ?', ['admin', '%admin%'])
    ->first();

if (!$user) {
    echo "ERROR: User csenerds@gmail.com with name 'Admin' not found\n";
    
    // Show all users with this email for debugging
    $allUsers = DB::table('users')
        ->whereRaw('LOWER(email) = ?', ['csenerds@gmail.com'])
        ->get();
    
    if ($allUsers->count() > 0) {
        echo "\nFound users with email csenerds@gmail.com:\n";
        foreach ($allUsers as $u) {
            echo "  ID: {$u->id}, Name: {$u->first_name} {$u->last_name}, Status: {$u->status}\n";
        }
    } else {
        echo "\nNo users found with email csenerds@gmail.com\n";
    }
    exit(1);
}

echo "Found user to delete:\n";
echo "  ID: {$user->id}\n";
echo "  Name: {$user->first_name} {$user->last_name}\n";
echo "  Email: {$user->email}\n";
echo "  Status: {$user->status}\n";
echo "\nDeleting user...\n";

// Delete related records first (to avoid foreign key constraints)
DB::table('model_has_roles')->where('model_id', $user->id)->where('model_type', 'App\\Models\\User')->delete();
DB::table('model_has_permissions')->where('model_id', $user->id)->where('model_type', 'App\\Models\\User')->delete();

// Soft delete or hard delete the user
// Using soft delete (sets deleted_at timestamp)
DB::table('users')
    ->where('id', $user->id)
    ->update(['deleted_at' => now()]);

echo "SUCCESS: User soft-deleted (ID: {$user->id})\n";

// Verify
$deleted = DB::table('users')->where('id', $user->id)->whereNotNull('deleted_at')->first();
if ($deleted) {
    echo "Verified: User is soft-deleted (deleted_at: {$deleted->deleted_at})\n";
} else {
    echo "WARNING: Soft delete verification failed\n";
}
