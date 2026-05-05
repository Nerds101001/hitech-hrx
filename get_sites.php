<?php
$appRoot = '/home/u989061032/domains/hitechgroup.in/public_html/hrx';
require $appRoot . '/vendor/autoload.php';
$app = require_once $appRoot . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

foreach(\App\Models\Site::withoutGlobalScopes()->get() as $s) {
    echo "ID: {$s->id}, Name: {$s->name}\n";
}
