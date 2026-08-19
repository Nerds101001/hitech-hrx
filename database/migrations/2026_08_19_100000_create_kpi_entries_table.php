<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Weekly submissions that roll up into a monthly KPI check.
 *
 * Scoring stays monthly so a department's marks still total what the templates
 * define — a weekly *check* would award its full marks every week and inflate a
 * 150-mark scorecard to roughly 250. Staff instead log a number each week here,
 * HR approves it, and the month's achieved_value is the sum of what was
 * approved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('kpi_check_id')->index();
            $table->unsignedBigInteger('kpi_assignment_id')->index();
            $table->unsignedBigInteger('user_id')->index();

            // The week this entry covers. Monthly-entry KPIs use the month.
            $table->date('period_start');
            $table->date('period_end');

            $table->decimal('value', 12, 2)->default(0);
            $table->string('note', 255)->nullable();

            // submitted -> approved | rejected. Only approved entries count.
            $table->string('status', 20)->default('submitted')->index();
            $table->unsignedBigInteger('reviewed_by_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 255)->nullable();

            $table->timestamps();

            // One entry per assignment per week; re-submitting updates it.
            $table->unique(['kpi_assignment_id', 'period_start'], 'kpi_entries_assignment_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_entries');
    }
};
