<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

$roles = Role::all()->keyBy('id');
$assignments = DB::table('model_has_roles')->get();

foreach($assignments as $a) {
    $u = User::find($a->model_id);
    $r = $roles->get($a->role_id);
    if ($u && $r) {
        echo "User: {$u->getFullName()} ({$u->email}) | Role: {$r->name}\n";
    }
}
