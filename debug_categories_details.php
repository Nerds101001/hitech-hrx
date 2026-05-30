<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = App\Models\User::where('status', 'active')->first();
if ($admin) {
    auth()->login($admin);
}

$assets = App\Models\Asset::all();
foreach($assets as $asset) {
    $catName = $asset->category->name ?? 'None';
    $catId = $asset->category_id;
    echo "Asset: {$asset->name} | Cat ID: {$catId} | Cat Name: {$catName}\n";
}
