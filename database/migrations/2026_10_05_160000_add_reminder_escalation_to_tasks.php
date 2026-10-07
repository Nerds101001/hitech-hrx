<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two reminder stamps on tasks, each written once so a notice never repeats -
 * the same "send once, then stamp" pattern delay_notified_at already uses.
 *   due_soon_notified_at  - the day-before "due soon" nudge to the assignee
 *   escalated_at          - the "still overdue" escalation to their manager
 * Both NULL on every existing row, so nothing changes for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->timestamp('due_soon_notified_at')->nullable()->after('delay_notified_at');
            $table->timestamp('escalated_at')->nullable()->after('due_soon_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['due_soon_notified_at', 'escalated_at']);
        });
    }
};
