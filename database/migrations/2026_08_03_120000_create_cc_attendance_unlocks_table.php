<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-managed override of the salesperson-attendance filling window.
 *
 * The window was previously two hardcoded constants (OPEN_FROM / OPEN_TO in
 * CcAttendanceController) covering July 2026, so extending it for a late team
 * meant editing and redeploying code. This table lets Admin/HR open a date
 * range from the UI, for everyone or for named users, and switch it off again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cc_attendance_unlocks', function (Blueprint $table) {
            $table->id();
            $table->date('from_date');
            $table->date('to_date');

            // 'all' = every marker; 'selected' = only the ids in user_ids.
            $table->string('scope', 16)->default('all');
            $table->json('user_ids')->nullable();

            // Switched off rather than deleted, so a window can be reused and
            // the audit trail of who opened what survives.
            $table->boolean('is_active')->default(true);

            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Every lock check filters on these two.
            $table->index(['is_active', 'from_date', 'to_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cc_attendance_unlocks');
    }
};
