<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\LeaveType;
foreach(LeaveType::all() as $t) {
    echo $t->name . ' - ' . $t->code . " (is_short_leave: " . ($t->is_short_leave ? 'Yes' : 'No') . ")" . PHP_EOL;
}
