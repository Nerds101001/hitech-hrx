<?php

if (function_exists('opcache_reset')) {
    $result = opcache_reset();
    echo json_encode([
        'success' => true,
        'message' => 'OPcache reset successful',
        'result' => $result
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'OPcache extension is not loaded or opcache_reset function does not exist'
    ]);
}
