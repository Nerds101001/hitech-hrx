<?php

$file = 'd:/live_server/app/Http/Controllers/tenant/AttendanceController.php';

if (file_exists($file)) {
    $content = file_get_contents($file);
    $originalContent = $content;
    
    // Replace ['admin', 'hr'] with ['admin', 'hr', 'accounts']
    $content = str_replace("['admin', 'hr']", "['admin', 'hr', 'accounts']", $content);
    
    if ($content !== $originalContent) {
        file_put_contents($file, $content);
        echo "Updated AttendanceController.php\n";
    } else {
        echo "No changes needed in AttendanceController.php\n";
    }
} else {
    echo "AttendanceController.php not found\n";
}
