<?php
$appRoot = '/home/u989061032/domains/hitechgroup.in/public_html/hrx';
require $appRoot . '/vendor/autoload.php';
$app = require_once $appRoot . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$rules = \App\Models\LeavePolicyProfileRule::where('profile_id', 19)->with('leaveType')->get();
foreach($rules as $rule) {
    echo "Leave Type: " . $rule->leaveType->name . "\n";
    echo "Max Per Month: " . ($rule->max_per_month ?? '0') . "\n";
    echo "Max Per Year: " . ($rule->max_per_year ?? '0') . "\n";
    echo "Short Leave Hours: " . ($rule->short_leave_hours ?? '0') . "h\n";
    echo "Short Leave Count: " . ($rule->short_leave_per_month ?? '0') . " per month\n";
    echo "--------------------------\n";
}
