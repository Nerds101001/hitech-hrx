<?php
$log = file_get_contents('/home/u989061032/domains/hitechgroup.in/public_html/hrx/storage/logs/laravel.log');
$lines = explode("\n", $log);
$last = array_slice($lines, -5000);
foreach($last as $line) {
    if (stripos($line, 'otp') !== false || stripos($line, 'priyal') !== false || stripos($line, 'email') !== false) {
        echo $line . "\n";
    }
}
