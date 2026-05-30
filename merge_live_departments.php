<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
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
    foreach($merges as $sourceId => $targetId) {
        // Only merge if source exists
        if (!DB::table('departments')->where('id', $sourceId)->exists()) {
            continue;
        }

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

    // Rename Standardizations
    DB::table('departments')->where('name', 'QUALITY')->update(['name' => 'Quality Department']);
    DB::table('departments')->where('name', 'PRODUCTION')->update(['name' => 'Production Department']);
    DB::table('departments')->where('name', 'MAINTAINENCE')->update(['name' => 'Maintenance Department']);
    DB::table('departments')->where('name', 'Designing')->update(['name' => 'Design Department']);
    DB::table('departments')->where('name', 'Billing')->update(['name' => 'Billing Department']);
    DB::table('departments')->where('name', 'Legal')->update(['name' => 'Legal Department']);
    DB::table('departments')->where('name', 'Export')->update(['name' => 'Export Department']);
    DB::table('departments')->where('name', 'Ecommerce')->update(['name' => 'Ecommerce Department']);
    DB::table('departments')->where('name', 'New Biz')->update(['name' => 'New Business Department']);
    DB::table('departments')->where('name', 'Customer care')->update(['name' => 'Customer Care']);
    
    DB::commit();
    echo "All merges and renames completed successfully on live server!\n";
} catch (\Exception $e) {
    DB::rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}
