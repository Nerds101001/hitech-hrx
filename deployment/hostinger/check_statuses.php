<?php
require '/home/u989061032/domains/hitechgroup.in/public_html/hrx/vendor/autoload.php';
$app = require '/home/u989061032/domains/hitechgroup.in/public_html/hrx/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$statuses = DB::table('document_requests')
    ->select('status')
    ->distinct()
    ->get();

foreach ($statuses as $s) {
    echo "STATUS: '{$s->status}'\n";
}
