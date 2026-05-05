<?php
$appRoot = '/home/u989061032/domains/hitechgroup.in/public_html/hrx';
require $appRoot . '/vendor/autoload.php';
$app = require_once $appRoot . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$policy = \App\Models\LeavePolicyProfile::with('rules.leaveType')->find(19);
if ($policy) {
    echo "--- Policy: {$policy->name} ---\n";
    echo "Description: {$policy->description}\n";
    echo "Saturday Config: " . json_encode($policy->saturday_off_config) . "\n";
    foreach($policy->rules as $rule) {
        echo "- Type: {$rule->leaveType->name}\n";
        echo "  Annual Quota: {$rule->annual_quota}\n";
        echo "  Monthly Limit: {$rule->monthly_limit}\n";
        echo "  Carry Forward: " . ($rule->is_carry_forward ? 'Yes' : 'No') . "\n";
        echo "  Max CF Days: {$rule->carry_forward_max_days}\n";
        echo "  Short Leave: {$rule->short_leave_hours}h x {$rule->short_leave_per_month} per month\n";
    }
}
