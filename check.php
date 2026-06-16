<?php
require __DIR__.'/vendor/autoload.php';
\ = require_once __DIR__.'/bootstrap/app.php';
\->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\ = \Illuminate\Support\Facades\Schema::hasTable('travel_claims');
echo "Has table travel_claims? " . (\ ? 'YES' : 'NO') . PHP_EOL;

\ = \Illuminate\Support\Facades\DB::select('SHOW TABLES');
echo "Number of tables: " . count(\) . PHP_EOL;
