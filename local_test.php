<?php
require __DIR__.'/vendor/autoload.php';
\ = require_once __DIR__.'/bootstrap/app.php';
\ = \->make(Illuminate\Contracts\Console\Kernel::class);
\->bootstrap();

\ = \App\Models\HRPolicy::latest()->first();
echo "Policy: " . \->title . "\n";
echo "Target Depts: " . json_encode(\->target_departments) . "\n";
echo "Raw Target Depts: " . \->getRawOriginal('target_departments') . "\n";

\ = \App\Models\User::where('first_name', 'Nagender')->first();
\ = in_array((string)\->department_id, \->target_departments ?? []);
echo "User Dept: " . \->department_id . "\n";
echo "In Array: " . (\ ? 'true' : 'false') . "\n";
