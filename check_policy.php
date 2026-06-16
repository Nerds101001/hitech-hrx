<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$policies = \App\Models\HRPolicy::all();
foreach($policies as $policy) {
    if (!empty($policy->target_departments)) {
        echo "Policy " . $policy->id . " target_departments: " . json_encode($policy->target_departments) . "\n";
    }
}
