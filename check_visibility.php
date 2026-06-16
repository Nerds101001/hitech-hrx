<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$policy = \App\Models\HRPolicy::find(20);
$users = \App\Models\User::whereIn('department_id', [1, 10])->get()->groupBy('department_id');

foreach($users as $dept => $deptUsers) {
    $user = $deptUsers->first();
    echo "User " . $user->id . " from Dept " . $dept . ": ";
    $isVisible = false;
    if (empty($policy->target_departments)) {
        $isVisible = true;
    } else {
        $isVisible = in_array((string)$user->department_id, $policy->target_departments);
    }
    echo ($isVisible ? "VISIBLE" : "HIDDEN") . "\n";
}
