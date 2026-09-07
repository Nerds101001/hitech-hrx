<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two additions, both requested together:
 *
 * 1. CC / co-owner visibility - a third party can be added to a task so it
 *    shows up on their dashboard as something they're watching, without
 *    making them the assignee. task_ccs is a real pivot rather than a JSON
 *    column on tasks (unlike task_schedules.target_user_ids, which is a
 *    point-in-time snapshot by design) because a CC list needs to be
 *    queried both directions - "who is CC'd on this task" and "which tasks
 *    is this person CC'd on" - and stays small enough that a join is cheap.
 *    task_schedules gets its own cc_user_ids JSON column instead, following
 *    the same snapshot reasoning target_user_ids already uses: a schedule's
 *    CC list is decided once at creation and carried forward by each fired
 *    Task via TaskScheduleService, not re-resolved live.
 *
 * 2. completion_expected_date - the assignee's own promise of when they'll
 *    actually finish, separate from due_date (which the assigner sets).
 *    expected_date_notified_at is the same guard-column shape
 *    delay_notified_at already uses for NotifyOverdueTasks, so the new
 *    reminder command can reuse that exact pattern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks', 'completion_expected_date')) {
                $table->date('completion_expected_date')->nullable()->after('due_date');
            }
            if (!Schema::hasColumn('tasks', 'expected_date_notified_at')) {
                $table->timestamp('expected_date_notified_at')->nullable()->after('delay_notified_at');
            }
        });

        Schema::table('task_schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('task_schedules', 'cc_user_ids')) {
                $table->json('cc_user_ids')->nullable()->after('target_user_ids');
            }
        });

        if (!Schema::hasTable('task_ccs')) {
            Schema::create('task_ccs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained('tasks')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('created_by_id')->nullable()->constrained('users')->onDelete('set null');
                $table->string('tenant_id', 191)->nullable();
                $table->timestamps();
                $table->unique(['task_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('task_ccs');

        Schema::table('task_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('task_schedules', 'cc_user_ids')) {
                $table->dropColumn('cc_user_ids');
            }
        });

        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'expected_date_notified_at')) {
                $table->dropColumn('expected_date_notified_at');
            }
            if (Schema::hasColumn('tasks', 'completion_expected_date')) {
                $table->dropColumn('completion_expected_date');
            }
        });
    }
};
