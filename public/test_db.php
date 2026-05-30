<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$requests = \Illuminate\Support\Facades\DB::table('document_requests')->get(['id', 'status', 'admin_remarks']);
foreach ($requests as $r) {
    echo "ID: {$r->id} | Status: {$r->status} | Remarks: {$r->admin_remarks}\n";
}
unlink(__FILE__);
