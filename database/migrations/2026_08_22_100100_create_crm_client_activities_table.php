<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The comment/activity feed on a customer's page. A "call" row is created
 * automatically when a linked telephony call is dispositioned (see
 * TelephonyCallController::disposition()); "comment" rows are typed by a
 * user. needs_follow_up/follow_up_* are a lightweight flag on the entry
 * itself rather than a row in `tasks` -- that table's geofence/site/form
 * columns don't apply to a call follow-up, and it only links to the
 * unrelated Client model today, not crm_client_mappings.
 *
 * Read in descending id order and paginated by the controller -- a
 * long-lived account can accumulate one row per call, and this table must
 * never become the kind of unpaginated, ever-growing list that slows a page
 * down as data grows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_client_activities', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();

            $table->unsignedBigInteger('crm_client_mapping_id');
            $table->string('type', 20)->default('comment');
            $table->text('body')->nullable();
            $table->unsignedBigInteger('telephony_call_id')->nullable();

            $table->boolean('needs_follow_up')->default(false);
            $table->dateTime('follow_up_due_at')->nullable();
            $table->unsignedBigInteger('follow_up_assigned_to_id')->nullable();
            $table->dateTime('follow_up_completed_at')->nullable();

            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Feed queries are always "this customer, newest first" -- a
            // composite index matching that access pattern exactly.
            $table->index(['crm_client_mapping_id', 'id']);

            $table->foreign('crm_client_mapping_id')->references('id')->on('crm_client_mappings')->cascadeOnDelete();
            $table->foreign('telephony_call_id')->references('id')->on('telephony_calls')->nullOnDelete();
            $table->foreign('follow_up_assigned_to_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_client_activities');
    }
};
