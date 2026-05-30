<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$accountsDept = \App\Models\Department::withoutGlobalScopes()->where('name', 'Accounts Department')->first();
$designations = \App\Models\Designation::where('department_id', $accountsDept->id)->pluck('id');
echo "Designations count: " . count($designations) . "\n";
$users = \App\Models\User::whereIn('designation_id', $designations)->get();
echo "Users count: " . count($users) . "\n";
