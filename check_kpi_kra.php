<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;

echo "Tasks Table Exists: " . (Schema::hasTable('tasks') ? 'YES' : 'NO') . "\n";
echo "SalesTargets Table Exists: " . (Schema::hasTable('sales_targets') ? 'YES' : 'NO') . "\n";

$settings = \App\Models\Settings::first();
echo "Available Modules: " . json_encode($settings->available_modules) . "\n";
