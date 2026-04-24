<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Enums\UserAccountStatus;

$managers = User::whereIn('status', [UserAccountStatus::ACTIVE, UserAccountStatus::ONBOARDING])->get();
echo "Total Managers: " . $managers->count() . "\n";
foreach($managers as $m) {
    echo "ID: {$m->id}, Name: {$m->name}, Status: " . $m->status->value . ", Tenant: [{$m->tenant_id}]\n";
}
