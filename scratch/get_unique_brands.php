<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

try {
    $tax = \App\Models\LibraryTaxonomy::where('type', 'brand')->pluck('name')->toArray();
    echo "LibraryTaxonomy brands:\n";
    print_r($tax);
} catch (\Exception $e) {
    echo "Error querying LibraryTaxonomy: " . $e->getMessage() . "\n";
}
