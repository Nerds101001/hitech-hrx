<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use App\Models\User;
use App\Enums\UserAccountStatus;
use Illuminate\Support\Facades\Hash;

// Bootstrap Laravel
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$email = 'mukul@rustx.com';
$user = User::where('email', $email)->first();

if ($user) {
    // We'll use Hash::make to be safe, though 'hashed' cast usually handles it
    $user->password = 'Mukul@6589';
    $user->status = UserAccountStatus::ACTIVE;
    $user->locked_until = null;
    $user->login_attempts = 0;
    $user->save();
    
    echo "SUCCESS: User $email has been unlocked. Status set to ACTIVE, and password updated to Mukul@6589.\n";
} else {
    echo "ERROR: User $email not found in the database.\n";
}
