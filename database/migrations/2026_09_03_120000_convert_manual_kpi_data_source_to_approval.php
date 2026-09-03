<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A "manual" data_source KPI counted the moment someone typed a number in —
 * no HR review at all. That was fine for a while, but it means a
 * self-reported figure (unique customers visited, quotations sent, etc.)
 * went straight onto the scorecard unchecked. Every manually-typed KPI now
 * needs the same HR sign-off "approval" entries already require; only the
 * auto:* (computed-from-live-data) sources stay untouched.
 *
 * Also flips both columns' DEFAULT from 'manual' to 'approval' -- otherwise
 * KpiController::store()'s per-employee assign path (which never sets
 * data_source explicitly) would keep silently recreating the same
 * no-approval gap on every new row.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('kpi_templates')->where('data_source', 'manual')->update(['data_source' => 'approval']);
        DB::table('kpi_assignments')->where('data_source', 'manual')->update(['data_source' => 'approval']);

        if (Schema::hasColumn('kpi_templates', 'data_source')) {
            DB::statement("ALTER TABLE kpi_templates MODIFY data_source VARCHAR(40) NOT NULL DEFAULT 'approval'");
        }
        if (Schema::hasColumn('kpi_assignments', 'data_source')) {
            DB::statement("ALTER TABLE kpi_assignments MODIFY data_source VARCHAR(40) NOT NULL DEFAULT 'approval'");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('kpi_templates', 'data_source')) {
            DB::statement("ALTER TABLE kpi_templates MODIFY data_source VARCHAR(40) NOT NULL DEFAULT 'manual'");
        }
        if (Schema::hasColumn('kpi_assignments', 'data_source')) {
            DB::statement("ALTER TABLE kpi_assignments MODIFY data_source VARCHAR(40) NOT NULL DEFAULT 'manual'");
        }
        // Row data itself is not reverted: we cannot tell which rows were
        // 'manual' before this ran versus already 'approval' on their own.
    }
};
