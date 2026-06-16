<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$u = \App\Models\User::find(530);
if ($u) {
    echo json_encode([
        'id' => $u->id,
        'name' => $u->first_name . ' ' . $u->last_name,
        'email' => $u->email,
        'status' => $u->status,
        'is_locked' => $u->is_locked,
        'lock_reason' => $u->lock_reason,
        'login_attempts' => $u->login_attempts,
        'password' => $u->password ? 'Set' : 'Empty'
    ], JSON_PRETTY_PRINT);
} else {
    echo "User not found";
}
