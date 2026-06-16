<?php
$profiles = \App\Models\LeavePolicyProfile::select('name', 'saturday_off_config')->get();
$sites = \App\Models\Site::select('name', 'saturday_off_config')->get();
echo json_encode([
    'profiles' => $profiles,
    'sites' => $sites
], JSON_PRETTY_PRINT);
