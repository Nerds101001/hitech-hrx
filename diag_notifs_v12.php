<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$query = User::whereHas('roles', function ($query) {
    $query->whereIn('name', ['hr', 'admin']);
})->where('status', \App\Enums\UserAccountStatus::ACTIVE);

echo "SQL: " . $query->toSql() . "\n";
echo "Bindings: " . json_encode($query->getBindings()) . "\n";
$res = $query->get();
echo "Count: " . $res->count() . "\n";
foreach($res as $u) {
    echo "- {$u->email}\n";
}
