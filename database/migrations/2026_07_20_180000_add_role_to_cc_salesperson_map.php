<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add a role ('ccare' | 'newbiz') to cc_salesperson_map so one agent can hold
 * BOTH roles for the same salesperson (two rows, one per role). Previously the
 * role was derived from the agent's department, which made dual-role agents
 * impossible. Existing rows are backfilled from the agent's department.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('cc_salesperson_map', 'role')) {
            Schema::table('cc_salesperson_map', function (Blueprint $table) {
                $table->string('role', 16)->nullable()->after('sales_user_id');
            });
        }

        // Backfill from the agent's department (New Biz -> newbiz, else ccare).
        DB::statement("
            UPDATE cc_salesperson_map m
            JOIN users u ON u.id = m.cc_user_id
            LEFT JOIN departments d ON d.id = u.department_id
            SET m.role = CASE
                WHEN LOWER(COALESCE(d.name,'')) LIKE '%new biz%'
                  OR LOWER(COALESCE(d.name,'')) LIKE '%new business%' THEN 'newbiz'
                ELSE 'ccare'
            END
            WHERE m.role IS NULL
        ");
    }

    public function down(): void
    {
        if (Schema::hasColumn('cc_salesperson_map', 'role')) {
            Schema::table('cc_salesperson_map', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }
};
