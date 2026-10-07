<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row for EVERY inbound hit on the Tally push endpoint -- accepted and
 * rejected alike -- so the admin page can show time, source IP and the
 * allow/reject outcome with its reason. Mirrors telephony_webhook_deliveries.
 *
 * This is the piece that makes a wrong IP allowlist visible: those requests are
 * refused in the middleware, before TallyPushController and before any row in
 * tally_push_runs (which only logs pushes that already passed auth), so without
 * this table they would be invisible in the UI -- indistinguishable from the
 * office PC never having sent anything.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_push_requests', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->string('endpoint', 64)->nullable();          // e.g. api/tally-push/sync
            $table->string('source_ip', 45)->nullable()->index();
            $table->json('headers')->nullable();                 // timestamp / nonce / user_agent / request_id
            $table->longText('payload')->nullable();             // bounded excerpt; only kept for refused calls
            $table->unsignedInteger('bytes')->nullable();        // request body size
            $table->boolean('signature_valid')->default(false)->index();
            // received -> accepted (auth passed) | rejected (refused at the gate) | failed (passed auth, processing blew up)
            $table->string('status', 24)->default('received')->index();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('error')->nullable();                   // "code: message" for a refusal
            $table->string('credential_label')->nullable();      // which credential authenticated an accepted call
            $table->unsignedBigInteger('run_id')->nullable();    // links an accepted call to its tally_push_runs row
            $table->timestamp('received_at')->useCurrent();
            $table->timestamps();

            $table->index(['status', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_push_requests');
    }
};
