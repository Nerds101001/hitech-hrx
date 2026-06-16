<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo json_encode(Illuminate\Support\Facades\Schema::getColumnListing('attendances'), JSON_PRETTY_PRINT);
