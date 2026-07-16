<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('work_control_routine_id')->nullable()->index();
            $table->string('title');
            $table->enum('category', ['KPI', 'KRA'])->default('KPI');
            $table->enum('frequency', ['daily', 'weekly', 'fortnightly', 'monthly', 'quarterly', 'yearly'])->default('daily');
            // timeline: on-time = full marks, late/missed = late_score
            // target:   score = min(achieved/target, 1) * max_marks
            $table->enum('scoring_type', ['timeline', 'target'])->default('timeline');
            $table->decimal('max_marks', 8, 2)->default(5);
            $table->decimal('late_score', 8, 2)->default(0);
            $table->decimal('target_value', 12, 2)->nullable();
            // weekly/fortnightly: ISO weekday 1(Mon)-7(Sun) the check falls on
            // monthly/quarterly/yearly: day of month the check falls on
            $table->unsignedTinyInteger('check_day')->nullable();
            // quarterly: month within quarter 1-3; yearly: calendar month 1-12
            $table->unsignedTinyInteger('check_month')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamps();
        });

        Schema::create('kpi_checks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('kpi_assignment_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_date');
            $table->enum('status', ['pending', 'completed_on_time', 'completed_late', 'missed'])->default('pending');
            $table->dateTime('completed_at')->nullable();
            $table->decimal('achieved_value', 12, 2)->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->string('remarks')->nullable();
            $table->unsignedBigInteger('evaluated_by_id')->nullable();
            $table->timestamps();

            $table->unique(['kpi_assignment_id', 'period_start'], 'kpi_checks_assignment_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_checks');
        Schema::dropIfExists('kpi_assignments');
    }
};
