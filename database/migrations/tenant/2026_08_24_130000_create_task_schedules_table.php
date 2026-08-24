<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A recurring assignment: "every Tuesday" or "every 7th of the month",
 * targeting a fixed set of people, decided once at creation. A daily
 * scheduled command checks each active row and, on a day it matches,
 * creates one Task per target person — exactly like a manual bulk-assign,
 * just fired automatically instead of by a click.
 *
 * target_user_ids is a snapshot taken once when the schedule is created
 * (the same department-resolution the one-off assign form already does),
 * not re-resolved on every firing. That is deliberate: it is what makes
 * "remove this one person from the recurring assignment" a one-line array
 * edit instead of needing an exclusion list layered on top of live
 * department membership.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->enum('frequency', ['daily', 'weekly', 'monthly']);
            $table->unsignedTinyInteger('weekday')->nullable();     // 0=Sunday..6=Saturday, weekly only
            $table->unsignedTinyInteger('day_of_month')->nullable(); // 1-31, monthly only, clamped to the real last day
            $table->json('target_user_ids');
            $table->foreignId('assigned_by_id')->constrained('users')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->date('last_run_on')->nullable(); // dedupes a schedule firing twice on the same day
            $table->foreignId('created_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('tenant_id', 191)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('task_schedule_id')->nullable()->after('batch_id')
                ->constrained('task_schedules')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_schedule_id');
        });
        Schema::dropIfExists('task_schedules');
    }
};
