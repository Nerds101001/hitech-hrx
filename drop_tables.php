<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

DB::statement('SET FOREIGN_KEY_CHECKS=0;');
Schema::dropIfExists('appraisal_scorecards');
Schema::dropIfExists('sales_pitch_audits');
Schema::dropIfExists('ninety_day_goals');
Schema::dropIfExists('appraisal_cycles');
Schema::dropIfExists('learn_hitech_logs');
DB::statement('SET FOREIGN_KEY_CHECKS=1;');

echo "Tables dropped.\n";
