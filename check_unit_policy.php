<?php
$appRoot = '/home/u989061032/domains/hitechgroup.in/public_html/hrx';
require $appRoot . '/vendor/autoload.php';
$app = require_once $appRoot . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$policy = \App\Models\UnitLeavePolicy::where('site_id', 6)->first();
if ($policy) {
    print_r($policy->toArray());
} else {
    echo "No site-level policy found for Site 6.\n";
}
