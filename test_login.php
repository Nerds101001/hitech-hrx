<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$email = 'ppc5.rustx@gmail.com';
$password = 'Vane@8840';

$u = \App\Models\User::where('email', $email)->first();
if (!$u) {
    echo "User not found with email: $email\n";
    $u530 = \App\Models\User::find(530);
    if ($u530) {
        echo "User 530 has email: " . $u530->email . "\n";
        $u = $u530;
    }
}

if ($u) {
    echo "User Found. ID: {$u->id}, Name: {$u->first_name}\n<br>";
    echo "Status: {$u->status}\n<br>";
    echo "Locked: {$u->is_locked}\n<br>";
    
    // Reset it just in case
    $u->password = \Hash::make($password);
    $u->is_locked = 0;
    $u->login_attempts = 0;
    $u->status = 'active';
    $u->save();
    
    echo "Password and status reset successfully to Vane@8840.\n<br>";
}
