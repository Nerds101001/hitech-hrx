<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$profile = \App\Models\LeavePolicyProfile::where('name', 'Ludhaina')->first();
if ($profile) {
    $profile->saturday_off_config = ["2", "4"];
    $profile->save();
    echo "Updated Ludhaina profile saturday config to: " . json_encode($profile->saturday_off_config) . "\n";
} else {
    echo "Profile not found.\n";
}
