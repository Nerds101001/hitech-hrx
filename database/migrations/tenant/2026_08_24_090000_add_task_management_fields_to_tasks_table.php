<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs the Task Management portal: HR/admin assigning one task to many
 * people at once (individually, by department, or both) and the delayed-task
 * email nudge.
 *
 * batch_id groups every row a single "assign" action created, so the admin
 * list can show one entry ("Q3 audit — 12 people") while each person still
 * gets their own row to track their own status against — the whole point
 * being that no one sees anyone else's task.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('batch_id', 36)->nullable()->after('id')->index();
            $table->timestamp('completed_at')->nullable()->after('status');
            // Set once a "task overdue" email has gone out, so the daily
            // check does not re-notify the same person every day it stays open.
            $table->timestamp('delay_notified_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['batch_id', 'completed_at', 'delay_notified_at']);
        });
    }
};
