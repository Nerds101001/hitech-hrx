<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = App\Models\User::where('status', 'active')->first();
if ($admin) {
    auth()->login($admin);
}

$cats = App\Models\AssetCategory::all();
echo "Categories:\n";
foreach($cats as $cat) {
    echo "ID: {$cat->id} | Name: {$cat->name}\n";
}

$assets = App\Models\Asset::all();
echo "\nAssets count: " . $assets->count() . "\n";
$laptopCount = 0;
$desktopCount = 0;
foreach($assets as $asset) {
    $catName = $asset->category->name ?? 'None';
    if ($catName === 'Laptops') {
        $laptopCount++;
    } elseif ($catName === 'Desktops') {
        $desktopCount++;
    } else {
        echo "Asset: {$asset->name} has Category: {$catName}\n";
    }
}
echo "Laptops in DB: {$laptopCount}\n";
echo "Desktops in DB: {$desktopCount}\n";
