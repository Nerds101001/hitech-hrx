<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Department;
use App\Models\Designation;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

$json = file_get_contents('live_data_export.json');
$data = json_decode($json, true);

if (!$data) {
    die("Error parsing JSON\n");
}

DB::beginTransaction();
try {
    // Disable FK checks and truncate
    DB::statement('SET FOREIGN_KEY_CHECKS=0;');
    DB::table('users')->truncate();
    DB::table('departments')->truncate();
    DB::table('designations')->truncate();
    DB::table('model_has_roles')->truncate();
    DB::table('cc_salesperson_map')->truncate();

    // Import Departments
    foreach ($data['departments'] as $dept) {
        Department::updateOrCreate(['id' => $dept['id']], $dept);
    }
    echo "Departments imported.\n";

    // Import Designations
    foreach ($data['designations'] as $desig) {
        Designation::updateOrCreate(['id' => $desig['id']], $desig);
    }
    echo "Designations imported.\n";

    // Import Users
    foreach ($data['users'] as $uData) {
        $roles = $uData['roles'] ?? [];
        unset($uData['roles']);
        
        $user = User::updateOrCreate(['id' => $uData['id']], $uData);
        
        // Sync Roles
        if (!empty($roles) && $user) {
            // Ensure roles exist
            foreach ($roles as $roleName) {
                Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            }
            $user->syncRoles($roles);
        }
    }
    echo "Users imported.\n";

    // Import cc mappings if present
    if (isset($data['mappings'])) {
        foreach ($data['mappings'] as $map) {
            DB::table('cc_salesperson_map')->updateOrInsert(
                ['id' => $map['id']],
                (array)$map
            );
        }
        echo "Mappings imported.\n";
    }

    DB::commit();
    echo "All data imported successfully!\n";
} catch (\Exception $e) {
    DB::rollBack();
    echo "Error importing data: " . $e->getMessage() . "\n";
}
