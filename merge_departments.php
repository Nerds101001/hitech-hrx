<?php
require 'd:/live_server/vendor/autoload.php';
$app = require_once 'd:/live_server/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Department;

$merges = [
    1 => 10,  // CCARE -> Customer care
    9 => 3,   // HR -> HR Department
    19 => 2,  // Sales -> Sales Department
    12 => 8   // Admin -> Admin Department
];

DB::beginTransaction();
try {
    // Standardize 'Customer care' to 'Customer Care'
    $custCare = Department::find(10);
    if($custCare) {
        $custCare->name = 'Customer Care';
        $custCare->save();
    }

    foreach($merges as $sourceId => $targetId) {
        // Update Users
        DB::table('users')->where('department_id', $sourceId)->update(['department_id' => $targetId]);
        
        // Update Designations
        DB::table('designations')->where('department_id', $sourceId)->update(['department_id' => $targetId]);

        // Update Department Managers
        DB::table('department_managers')->where('department_id', $sourceId)->update(['department_id' => $targetId]);

        // Update Transfers
        if (DB::getSchemaBuilder()->hasTable('employee_transfers')) {
            DB::table('employee_transfers')->where('from_department_id', $sourceId)->update(['from_department_id' => $targetId]);
            DB::table('employee_transfers')->where('to_department_id', $sourceId)->update(['to_department_id' => $targetId]);
        }

        // Update other references
        $tables = [
            'production_schedules', 'machines', 'production_entries', 'work_orders'
        ];
        foreach($tables as $t) {
            if (DB::getSchemaBuilder()->hasTable($t)) {
                if (DB::getSchemaBuilder()->hasColumn($t, 'department_id')) {
                    DB::table($t)->where('department_id', $sourceId)->update(['department_id' => $targetId]);
                }
            }
        }
        
        // Parent ID in departments
        DB::table('departments')->where('parent_id', $sourceId)->update(['parent_id' => $targetId]);

        // Delete source
        DB::table('departments')->where('id', $sourceId)->delete();
        
        echo "Merged Department ID $sourceId into $targetId.\n";
    }
    
    DB::commit();
    echo "All merges completed successfully!\n";
} catch (\Exception $e) {
    DB::rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}
