<?php
require 'd:/live_server/vendor/autoload.php';
$app = require_once 'd:/live_server/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\TrainingQuestion;
use App\Models\TrainingModule;

echo "=== QUESTIONS COUNT ===\n";
$modules = TrainingModule::withCount('questions')->get();
foreach ($modules as $m) {
    echo "Module ID: {$m->id}, Title: {$m->title}, Questions Count: {$m->questions_count}\n";
}
echo "Done.\n";
