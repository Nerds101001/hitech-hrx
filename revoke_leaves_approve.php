<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$accountsRole = \Spatie\Permission\Models\Role::where('name', 'accounts')->first();
if ($accountsRole) {
    $accountsRole->revokePermissionTo('hr.leaves.approve');
    $accountsRole->revokePermissionTo('leaveRequests.approve');
    echo "Revoked leave approval permissions.\n";
}

app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
echo "Done.\n";
