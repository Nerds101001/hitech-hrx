<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$res = \Illuminate\Support\Facades\DB::table('travel_claims')->get();
print_r($res);
