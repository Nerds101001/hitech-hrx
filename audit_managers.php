<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Department;
use App\Models\User;

echo "--- DEPARTMENT MANAGER AUDIT (Role-based) ---\n";
foreach(Department::all() as $d) {
    $managers = User::role(['manager', 'Manager'])->where('department_id', $d->id)->get();
    echo "Dept: " . $d->name . " (ID: " . $d->id . ")\n";
    if ($managers->isEmpty()) {
        echo "  -> No role-based managers found.\n";
    } else {
        foreach ($managers as $m) {
            echo "  -> [Manager] " . $m->first_name . " " . $m->last_name . " (ID: " . $m->id . ")\n";
        }
    }
}
