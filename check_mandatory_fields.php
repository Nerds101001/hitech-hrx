<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

$columns = DB::select('DESCRIBE users');
$mandatory = [];
foreach($columns as $col) {
    if($col->Null == 'NO' && $col->Default === NULL && $col->Extra != 'auto_increment') {
        $mandatory[] = $col->Field;
    }
}
echo implode(', ', $mandatory) . "\n";
