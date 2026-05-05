<?php
$appRoot = '/home/u989061032/domains/hitechgroup.in/public_html/hrx';
require $appRoot . '/vendor/autoload.php';
$app = require_once $appRoot . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$schema = \Illuminate\Support\Facades\DB::select("DESCRIBE leave_policy_profile_rules");
foreach($schema as $col) {
    echo "{$col->Field} ({$col->Type})\n";
}
