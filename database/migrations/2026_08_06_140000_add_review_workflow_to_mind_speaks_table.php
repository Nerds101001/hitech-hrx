<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mind Speak submissions were write-only: an employee posted one and never
 * heard anything back, and admin/HR had no way to respond. This adds the
 * review loop.
 *
 *   pending      just submitted, nobody has looked yet
 *   acknowledged seen and replied to  (Idea / Complaint)
 *   approved     accepted             (Sales Pitch / Seminar / Competitor Insights)
 *   rejected     turned down with a reason; the employee may resubmit
 *
 * A resubmission is a NEW row pointing back at the rejected one, so the
 * original and the reviewer's reason survive as an audit trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mind_speaks', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->after('is_anonymous');
            // The reviewer's reply, or the reason for rejection.
            $table->text('admin_remarks')->nullable()->after('status');
            $table->unsignedBigInteger('reviewed_by_id')->nullable()->after('admin_remarks');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_id');
            $table->unsignedBigInteger('resubmitted_from_id')->nullable()->after('reviewed_at');

            $table->index(['tenant_id', 'status'], 'mind_speaks_status_idx');
            $table->index('resubmitted_from_id', 'mind_speaks_resub_idx');
        });
    }

    public function down(): void
    {
        Schema::table('mind_speaks', function (Blueprint $table) {
            $table->dropIndex('mind_speaks_status_idx');
            $table->dropIndex('mind_speaks_resub_idx');
            $table->dropColumn([
                'status', 'admin_remarks', 'reviewed_by_id', 'reviewed_at', 'resubmitted_from_id',
            ]);
        });
    }
};
