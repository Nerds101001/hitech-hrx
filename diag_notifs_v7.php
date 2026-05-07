<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$hrAdmins = User::whereHas('roles', function ($query) {
    $query->whereIn('name', ['hr', 'admin']);
})->where('status', \App\Enums\UserAccountStatus::ACTIVE)->get();

echo "Count: " . $hrAdmins->count() . "\n";
foreach($hrAdmins as $u) {
    echo "- {$u->email}\n";
}
