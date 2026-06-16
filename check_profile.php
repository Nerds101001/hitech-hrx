<?php
$user = \App\Models\User::find(9);
echo json_encode([
    'profile_name' => $user->leavePolicyProfile ? $user->leavePolicyProfile->name : null,
    'profile_config' => $user->leavePolicyProfile ? $user->leavePolicyProfile->saturday_off_config : null,
    'shift_saturday' => $user->shift ? $user->shift->saturday : null
], JSON_PRETTY_PRINT);
