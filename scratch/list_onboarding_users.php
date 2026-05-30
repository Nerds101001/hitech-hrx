<?php
require 'd:/live_server/vendor/autoload.php';
$app = require_once 'd:/live_server/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$users = User::whereIn('status', ['onboarding', 'onboarding_submitted', 'onboarding_requested'])->get(['id', 'email', 'name', 'status', 'is_training_required']);
foreach ($users as $u) {
    echo "ID: {$u->id} | Email: {$u->email} | Name: {$u->name} | Status: {$u->status->value} | Training Required: " . ($u->is_training_required ? 'Yes' : 'No') . "\n";
}
