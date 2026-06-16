<?php
$p = App\Models\LeavePolicyProfile::where('name', 'Even Saturday Off')->first();
$u = App\Models\User::where('leave_policy_profile_id', $p->id)->first();
if($u) {
    echo 'User: ' . $u->first_name . ' ' . $u->last_name . PHP_EOL;
    $date = Carbon\Carbon::parse('2026-06-13');
    echo 'Is Working Day: ' . (App\Services\LeavePolicyService::isWorkingDay($u, $date) ? 'true' : 'false') . PHP_EOL;
} else {
    echo 'No user assigned to this profile' . PHP_EOL;
}
