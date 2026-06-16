<?php
$file = 'app/Http/Controllers/tenant/AttendanceController.php';
$content = file_get_contents($file);
$content = str_replace('480', '465', $content);
file_put_contents($file, $content);
echo "Replaced 480 with 465 successfully.";
