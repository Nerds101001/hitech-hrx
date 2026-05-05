<?php
$appRoot = '/home/u989061032/domains/hitechgroup.in/public_html/hrx';
require $appRoot . '/vendor/autoload.php';
$app = require_once $appRoot . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$emails = [
    'priyal@hitechgroup.in',
    'mukul@rustx.com',
    'suchita@rustx.com',
    'design@keep-it-fresh.com',
    'ecom@keep-it-fresh.com',
    'ecom1@keep-it-fresh.com',
    'newbiz6@drbio.in',
    'shivani.drbio@gmail.com'
];

echo "--- Current Status of Specific Users ---\n";

foreach($emails as $email) {
    $u = \App\Models\User::withoutGlobalScopes()->where('email', $email)->with(['site', 'leavePolicyProfile'])->first();
    if ($u) {
        echo "User: {$u->first_name} {$u->last_name} ({$u->email})\n";
        echo "  Site/Unit: " . ($u->site->name ?? 'NONE') . "\n";
        echo "  Leave Policy: " . ($u->leavePolicyProfile->name ?? 'NONE') . "\n";
    } else {
        echo "User not found: $email\n";
    }
}
