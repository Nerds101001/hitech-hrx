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
        $permissions = DB::table('model_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_id', $u->id)
            ->select('permissions.name')
            ->get();
        echo "User: {$u->email}\n";
        foreach($permissions as $p) {
            echo "- Permission: {$p->name}\n";
        }
    }
}
