<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two scoring options the Production scorecard needs and the engine lacked:
 *
 *  trend              'increase' (default — achieved ÷ target, what every
 *                     existing KPI already does) or 'decrease' (target ÷
 *                     achieved: a KPI where less is better, e.g. waste,
 *                     mandays, complaints).
 *  result_cap_percent The most a result may count for. NULL keeps the
 *                     historical behaviour (linear KPIs stop at 100%);
 *                     a value such as 200 lets over-achievement score above
 *                     the KPI's marks.
 *
 * Both default to the old behaviour, so every existing template and
 * assignment scores exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['kpi_templates', 'kpi_assignments'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (!Schema::hasColumn($table, 'trend')) {
                    $t->string('trend', 10)->default('increase');
                }
                if (!Schema::hasColumn($table, 'result_cap_percent')) {
                    $t->decimal('result_cap_percent', 6, 2)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['kpi_templates', 'kpi_assignments'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                foreach (['trend', 'result_cap_percent'] as $col) {
                    if (Schema::hasColumn($table, $col)) {
                        $t->dropColumn($col);
                    }
                }
            });
        }
    }
};
