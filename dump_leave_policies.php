<?php

use App\Models\LeavePolicyProfile;
use App\Models\LeaveType;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$profiles = LeavePolicyProfile::with('rules.leaveType')->get();

echo "--- LEAVE POLICY PROFILES ---\n\n";

foreach ($profiles as $profile) {
    echo "ID: {$profile->id}\n";
    echo "Name: {$profile->name}\n";
    echo "Description: {$profile->description}\n";
    echo "Sat Off Config: " . json_encode($profile->saturday_off_config) . "\n";
    echo "Rules:\n";
    foreach ($profile->rules as $rule) {
        $type = $rule->leaveType->name ?? 'Unknown';
        echo "  - Type: {$type} ({$rule->leaveType->code})\n";
        echo "    Applicable: " . ($rule->is_applicable ? 'Yes' : 'No') . "\n";
        echo "    Max/Month: " . ($rule->max_per_month ?? 'N/A') . "\n";
        echo "    Max/Year: " . ($rule->max_per_year ?? 'N/A') . "\n";
        echo "    Carry Forward: " . ($rule->is_carry_forward ? 'Yes' : 'No') . "\n";
        echo "    Carry Forward Cap: " . ($rule->carry_forward_max_days ?? '6 (Default)') . "\n";
        echo "    Consecutive Limit: " . ($rule->max_consecutive_days ?? 'N/A') . "\n";
    }
    echo "---------------------------\n\n";
}
