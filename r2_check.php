<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Storage;

echo "--- R2 Diagnostic Start ---\n";
echo "Testing connectivity to: " . config('filesystems.disks.r2.endpoint') . "\n";
echo "Targeting Bucket: " . config('filesystems.disks.r2.bucket') . "\n";

try {
    $disk = Storage::disk('r2');
    
    // Attempt to list files in the root
    echo "Attempting to list files in root...\n";
    $files = $disk->files();
    echo "Connection Successful! Found " . count($files) . " files.\n";

} catch (\Exception $e) {
    echo "DIAGNOSTIC FAILURE:\n";
    echo $e->getMessage() . "\n";
    
    if (str_contains($e->getMessage(), 'NoSuchBucket')) {
        echo "\n[!] ANALYSIS: Cloudflare says the bucket does not exist.\n";
        echo "Please verify that 'hrx-library' is the EXACT name in your R2 dashboard.\n";
    }
}
echo "--- R2 Diagnostic End ---\n";
