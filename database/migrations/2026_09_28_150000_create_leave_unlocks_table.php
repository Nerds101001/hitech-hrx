<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin/HR-opened windows that let people apply for leave on dates older than
 * the one-week back-dating limit (App\Support\LeaveBackdate) - the leave
 * counterpart of cc_attendance_unlocks. For everyone or for named users;
 * switched off rather than deleted so the record of who opened what survives.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_unlocks', function (Blueprint $table) {
            $table->id();
            $table->date('from_date');
            $table->date('to_date');

            // 'all' = everyone; 'selected' = only the ids in user_ids.
            $table->string('scope', 16)->default('all');
            $table->json('user_ids')->nullable();

            $table->boolean('is_active')->default(true);

            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Every leave application filters on these.
            $table->index(['is_active', 'from_date', 'to_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_unlocks');
    }
};
