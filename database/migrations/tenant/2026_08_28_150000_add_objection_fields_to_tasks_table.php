<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Lets an admin/HR send a completed task back for correction, the same shape
 * as Travel Claim's objection flow: a reason, who raised it and when, and a
 * dedicated status so it is unambiguous from a plain rejection.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE tasks MODIFY status ENUM('new', 'in_progress', 'completed', 'cancelled', 'hold', 'rejected', 'reassigned', 'reopened', 'resolved', 'closed', 'objection') DEFAULT 'new'");
        // task_updates.update_type is its own separate enum, and the two new
        // TaskUpdate rows this flow creates (objection / resubmit) need a
        // value on it too, same as 'complete' already has for MyTasksController::complete().
        DB::statement("ALTER TABLE task_updates MODIFY update_type ENUM('comment', 'location', 'image', 'document', 'hold', 'un_hold', 'start', 'complete', 'form', 'objection', 'resubmit')");

        Schema::table('tasks', function (Blueprint $table) {
            $table->text('objection_notes')->nullable()->after('completed_at');
            $table->unsignedBigInteger('objected_by_id')->nullable()->after('objection_notes');
            $table->timestamp('objected_at')->nullable()->after('objected_by_id');

            $table->foreign('objected_by_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['objected_by_id']);
            $table->dropColumn(['objection_notes', 'objected_by_id', 'objected_at']);
        });

        DB::statement("ALTER TABLE tasks MODIFY status ENUM('new', 'in_progress', 'completed', 'cancelled', 'hold', 'rejected', 'reassigned', 'reopened', 'resolved', 'closed') DEFAULT 'new'");
        DB::statement("ALTER TABLE task_updates MODIFY update_type ENUM('comment', 'location', 'image', 'document', 'hold', 'un_hold', 'start', 'complete', 'form')");
    }
};
