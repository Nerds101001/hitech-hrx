<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Team;
use App\Models\Department;
use App\Models\User;

echo "Teams: " . Team::withoutGlobalScopes()->count() . "\n";
echo "Departments: " . Department::withoutGlobalScopes()->count() . "\n";
echo "Users: " . User::withoutGlobalScopes()->count() . "\n";
