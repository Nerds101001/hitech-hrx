<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A KPI/KRA catalogue that is defined once per DEPARTMENT and then stamped onto
 * every matching employee, instead of an admin hand-creating the same row for
 * each person. kpi_assignments stays the per-employee record the scoring engine
 * already works on; a template is only the blueprint it is generated from.
 *
 * Also widens scoring: 'threshold' (hit X% of target or score nothing) and
 * 'banded' (tiered marks) join the existing 'timeline' and 'target', and the
 * flat late_score grows into a late policy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();

            $table->string('title');
            $table->string('description')->nullable();
            $table->enum('category', ['KPI', 'KRA'])->default('KPI');
            // A KPI hangs under a KRA heading; a KRA has no parent.
            $table->unsignedBigInteger('parent_template_id')->nullable()->index();

            // null department = applies to every department in the tenant.
            $table->unsignedBigInteger('department_id')->nullable()->index();

            $table->enum('frequency', ['daily','weekly','fortnightly','monthly','quarterly','yearly'])->default('monthly');
            // Kept as a string, not an enum: adding a scoring type should not
            // need a migration that rewrites the table.
            $table->string('scoring_type', 20)->default('target');

            $table->decimal('max_marks', 8, 2)->default(5);
            $table->decimal('target_value', 12, 2)->nullable();
            // threshold: score max_marks when achieved/target >= this %, else 0
            $table->decimal('threshold_percent', 5, 2)->nullable();
            // banded: [{"min":90,"marks":10},{"min":75,"marks":7}], highest match wins
            $table->json('bands')->nullable();

            // none | fixed | percent | per_day
            $table->string('late_policy', 20)->default('fixed');
            $table->decimal('late_score', 8, 2)->default(0);
            $table->decimal('late_percent', 5, 2)->nullable();
            $table->unsignedTinyInteger('grace_days')->default(0);
            $table->decimal('per_day_penalty', 8, 2)->nullable();

            // manual | approval | auto:<key>  (auto keys are wired later)
            $table->string('data_source', 40)->default('manual');
            $table->unsignedBigInteger('approver_id')->nullable();

            $table->unsignedTinyInteger('check_day')->nullable();
            $table->unsignedTinyInteger('check_month')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'department_id', 'is_active'], 'kpi_tpl_scope_idx');
        });

        Schema::table('kpi_assignments', function (Blueprint $table) {
            // Which template produced this row, so edits can be re-synced and
            // hand-made assignments (template_id null) are left alone.
            $table->unsignedBigInteger('kpi_template_id')->nullable()->index()->after('work_control_routine_id');
            $table->string('kra_group')->nullable()->after('category');
            $table->decimal('threshold_percent', 5, 2)->nullable()->after('target_value');
            $table->json('bands')->nullable()->after('threshold_percent');
            $table->string('late_policy', 20)->default('fixed')->after('late_score');
            $table->decimal('late_percent', 5, 2)->nullable()->after('late_policy');
            $table->unsignedTinyInteger('grace_days')->default(0)->after('late_percent');
            $table->decimal('per_day_penalty', 8, 2)->nullable()->after('grace_days');
            $table->string('data_source', 40)->default('manual')->after('per_day_penalty');
            $table->unsignedBigInteger('approver_id')->nullable()->after('data_source');
        });

        // scoring_type is an ENUM('timeline','target') and must accept the two
        // new modes. Widening to VARCHAR keeps future modes migration-free.
        DB::statement("ALTER TABLE kpi_assignments MODIFY scoring_type VARCHAR(20) NOT NULL DEFAULT 'timeline'");
    }

    public function down(): void
    {
        Schema::table('kpi_assignments', function (Blueprint $table) {
            $table->dropColumn([
                'kpi_template_id','kra_group','threshold_percent','bands','late_policy',
                'late_percent','grace_days','per_day_penalty','data_source','approver_id',
            ]);
        });
        DB::statement("ALTER TABLE kpi_assignments MODIFY scoring_type ENUM('timeline','target') NOT NULL DEFAULT 'timeline'");
        Schema::dropIfExists('kpi_templates');
    }
};
