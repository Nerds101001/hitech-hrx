<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The follow-up timeline for a trial. A trial's outcome evolves over time —
 * waiting for a result, a retrial, finally pass / fail / order — and each step
 * is a dated row here with a comment and (for waiting/retrial) a next expected
 * date. The newest row is the trial's current status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_trial_status_updates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('sales_trial_id')->index();
            $table->string('status', 40);                       // key from config/trial_status.php
            $table->text('comment')->nullable();                // remark for this update
            $table->date('expected_date')->nullable();          // next-result / retrial date (waiting/retrial)
            $table->unsignedBigInteger('logged_by_id')->nullable();
            $table->timestamps();

            $table->foreign('sales_trial_id')->references('id')->on('sales_trials')->cascadeOnDelete();
            $table->index(['sales_trial_id', 'created_at']);
        });

        // Denormalised onto the trial so lists and the overdue-follow-up reminder
        // never have to join the timeline. Kept in step whenever an update is added.
        Schema::table('sales_trials', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_trials', 'current_status')) {
                $table->string('current_status', 40)->nullable()->index()->after('status');
            }
            if (!Schema::hasColumn('sales_trials', 'next_expected_date')) {
                $table->date('next_expected_date')->nullable()->index()->after('current_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_trials', function (Blueprint $table) {
            if (Schema::hasColumn('sales_trials', 'next_expected_date')) {
                $table->dropColumn('next_expected_date');
            }
            if (Schema::hasColumn('sales_trials', 'current_status')) {
                $table->dropColumn('current_status');
            }
        });
        Schema::dropIfExists('sales_trial_status_updates');
    }
};
