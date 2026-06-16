<?php
require __DIR__.'/vendor/autoload.php';
\ = require_once __DIR__.'/bootstrap/app.php';
\ = \->make(Illuminate\Contracts\Console\Kernel::class);
\->bootstrap();

\ = \App\Models\User::find(9);
\ = \App\Models\HRPolicy::find(19);

\ = in_array((string)\->department_id, \->target_departments);
file_put_contents('test_output.txt', 'User Dept: ' . \->department_id . ' | Policy Depts: ' . json_encode(\->target_departments) . ' | In Array: ' . (\ ? 'true' : 'false'));
