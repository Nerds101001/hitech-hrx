<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$u = \App\Models\User::where('email', 'accounts@doctorrust.com')->first();
if ($u) {
    $u->assignRole('accounts');
    echo 'Assigned accounts role to ' . $u->email . "\n";
} else {
    echo "User accounts@doctorrust.com not found.\n";
}
