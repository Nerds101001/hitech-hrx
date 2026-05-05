<?php
$appRoot = '/home/u989061032/domains/hitechgroup.in/public_html/hrx';
require $appRoot . '/vendor/autoload.php';
$app = require_once $appRoot . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

foreach(\App\Models\KnowledgeBase::all() as $k) {
    echo "ID: {$k->id}\n";
    echo "Title: {$k->title}\n";
    echo "Category: {$k->category}\n";
    echo "Content:\n{$k->content}\n";
    echo "----------------------------------------\n";
}
