<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per call_uid, normalised from the event stream.
 *
 * Named telephony_call_records, not telephony_calls: the 2026-08-18 telephony
 * feature already owns telephony_calls with an entirely different schema.
 * Table names are invisible to the vendor, so the published contract is
 * unaffected.
 *
 * This application runs with app.timezone = Asia/Kolkata, so datetimes are
 * stored as IST wall time like every other HRX table. Payloads arrive with an
 * explicit offset and are converted on the way in, so a gateway that ever
 * starts sending UTC still lands on the correct local instant.
 */
return new class extends Migration
{
  public function up(): void
  {
    Schema::create('telephony_call_records', function (Blueprint $table) {
      $table->id();
      $table->string('tenant_id')->nullable()->index();

      $table->string('call_uid')->index();                  // vendor RID/UID
      $table->uuid('ref_id')->nullable()->index();          // HRX correlation id
      $table->string('parent_call_uid')->nullable()->index();

      $table->unsignedBigInteger('user_id')->nullable()->index();
      $table->string('agent_id')->nullable()->index();
      $table->string('agent_code')->nullable();
      $table->string('agent_name')->nullable();
      $table->string('extension')->nullable();
      $table->string('agent_number')->nullable();

      $table->string('origin', 24)->nullable()->index();    // api_click_to_call | manual_dial | inbound | ...
      $table->string('direction', 12)->nullable()->index();
      $table->string('mode', 8)->nullable();                // auto | manual

      $table->string('caller_number')->nullable()->index();
      $table->string('callee_number')->nullable()->index();
      $table->string('did_number')->nullable();

      $table->string('campaign_id')->nullable()->index();
      $table->string('campaign_name')->nullable();
      $table->string('queue')->nullable();
      $table->string('skill')->nullable();
      $table->string('lead_source')->nullable();
      $table->string('lead_id')->nullable();
      $table->string('customer_name')->nullable();

      $table->string('gateway_id')->nullable();
      $table->string('channel')->nullable();
      $table->string('sim_msisdn')->nullable();
      $table->string('operator')->nullable();

      $table->string('status', 20)->nullable()->index();
      $table->string('hangup_by', 16)->nullable();
      $table->string('hangup_cause', 48)->nullable();
      $table->integer('hangup_cause_code')->nullable();

      $table->timestamp('initiated_at')->nullable()->index();
      $table->timestamp('agent_ring_at')->nullable();
      $table->timestamp('agent_answer_at')->nullable();
      $table->timestamp('customer_ring_at')->nullable();
      $table->timestamp('answered_at')->nullable();
      $table->timestamp('ended_at')->nullable();
      $table->timestamp('wrapup_start_at')->nullable();
      $table->timestamp('wrapup_end_at')->nullable();
      $table->timestamp('queue_enter_at')->nullable();
      $table->timestamp('queue_exit_at')->nullable();

      $table->unsignedInteger('agent_ring_seconds')->default(0);
      $table->unsignedInteger('customer_ring_seconds')->default(0);
      $table->unsignedInteger('pdd_seconds')->default(0);
      $table->unsignedInteger('queue_wait_seconds')->default(0);
      $table->unsignedInteger('talk_seconds')->default(0);
      $table->unsignedInteger('hold_seconds')->default(0);
      $table->unsignedInteger('wrap_seconds')->default(0);
      $table->unsignedInteger('total_seconds')->default(0);
      $table->unsignedInteger('billed_seconds')->nullable();

      $table->string('disposition_code', 48)->nullable()->index();
      $table->string('disposition_name')->nullable();
      $table->string('sub_disposition_code', 48)->nullable();
      $table->string('sub_disposition_name')->nullable();
      $table->text('remarks')->nullable();
      $table->timestamp('callback_at')->nullable();

      $table->boolean('was_transferred')->default(false);
      $table->boolean('was_conferenced')->default(false);
      $table->boolean('was_forwarded')->default(false);
      $table->boolean('has_recording')->default(false)->index();

      $table->json('hold_events')->nullable();
      $table->json('quality')->nullable();
      $table->json('cost')->nullable();

      // Set when no agent map row matched. Surfaced on the exceptions screen.
      $table->boolean('is_unassigned')->default(false)->index();
      // Highest sequence seen, so a late event never overwrites a newer one.
      $table->unsignedInteger('last_sequence')->default(0);
      $table->boolean('is_complete')->default(false)->index();
      // Set by telephony:reconcile when the row was backfilled rather than pushed.
      $table->boolean('via_reconciliation')->default(false);

      $table->timestamps();

      $table->unique(['tenant_id', 'call_uid']);
      $table->index(['tenant_id', 'user_id', 'initiated_at']);
      $table->index(['tenant_id', 'initiated_at']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('telephony_call_records');
  }
};
