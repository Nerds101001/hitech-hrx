<?php
// This script runs on the production server to check the status of OnboardingMiddleware and UserDashboardController.

function checkFile($path) {
    echo "=== Checking file: $path ===\n";
    if (!file_exists($path)) {
        echo "File does not exist!\n\n";
        return;
    }
    
    $content = file_get_contents($path);
    echo "File Size: " . strlen($content) . " bytes\n";
    
    // Check if it's obfuscated
    if (preg_match("/gzinflate\(base64_decode\('(.*?)'\)\)/", $content, $matches)) {
        echo "Obfuscated: Yes\n";
        $decompressed = gzinflate(base64_decode($matches[1]));
        echo "Decompressed Content Snippet (first 2000 chars):\n";
        echo substr($decompressed, 0, 2000) . "\n...\n";
    } else {
        echo "Obfuscated: No\n";
        echo "Source Snippet:\n";
        echo substr($content, 0, 2000) . "\n...\n";
    }
    echo "\n";
}

$appRoot = "/home/u989061032/domains/hitechgroup.in/public_html/hrx";
checkFile("$appRoot/app/Http/Middleware/OnboardingMiddleware.php");
checkFile("$appRoot/app/Http/Controllers/tenant/users/UserDashboardController.php");
checkFile("$appRoot/bootstrap/app.php");
