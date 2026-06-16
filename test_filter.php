<?php
require __DIR__.'/vendor/autoload.php';
\ = require_once __DIR__.'/bootstrap/app.php';
\ = \->make(Illuminate\Contracts\Console\Kernel::class);
\->bootstrap();

\ = '1';
\ = ['2', '3'];

\ = new \App\Models\HRPolicy();
\->target_departments = \;

\ = empty(\->target_departments);
\ = in_array(\, \->target_departments);

echo "Empty: " . (\ ? 'true' : 'false') . "\n";
echo "InArray: " . (\ ? 'true' : 'false') . "\n";
