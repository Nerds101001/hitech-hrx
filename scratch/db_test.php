<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $settings = \App\Models\Settings::first();
    echo "SETTINGS FOUND WITH LOCALHOST: " . ($settings ? "YES" : "NO");
} catch (Exception $e) {
    echo "ERROR WITH LOCALHOST: " . $e->getMessage();
}
