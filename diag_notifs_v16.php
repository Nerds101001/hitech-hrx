<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;

$emails = ['purchase@rustx.com', 'Ardaman@hitechgroup.in', 'export2@rustx.com'];
foreach($emails as $e) {
    $u = User::where('email', $e)->first();
    if ($u) {
        $roles = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_id', $u->id)
            ->select('roles.name')
            ->get();
        echo "User: {$u->email}\n";
        foreach($roles as $r) {
            echo "- Role: {$r->name}\n";
        }
    }
}
