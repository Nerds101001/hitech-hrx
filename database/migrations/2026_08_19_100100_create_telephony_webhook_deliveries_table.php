<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every inbound webhook, stored raw before anything is interpreted.
 *
 * Build and deploy this table first. The day the vendor says "we sent it" and
 * you say "we never got it", this is the only thing that settles the argument.
 */
return new class extends Migration
{
  public function up(): void
  {
    Schema::create('telephony_webhook_deliveries', function (Blueprint $table) {
      $table->id();
      $table->string('tenant_id')->nullable()->index();

      $table->uuid('event_id')->nullable()->index();
      $table->string('event_type', 64)->nullable()->index();
      $table->string('call_uid')->nullable()->index();

      $table->string('endpoint', 64);              // events | recordings.ready | agent-sessions | cdr.import
      $table->string('source_ip', 45)->nullable();
      $table->json('headers')->nullable();
      $table->longText('payload');

      $table->boolean('signature_valid')->default(false)->index();
      // received -> queued -> processed | failed | duplicate | rejected
      $table->string('status', 24)->default('received')->index();
      $table->unsignedSmallInteger('attempts')->default(0);
      $table->text('error')->nullable();

      $table->timestamp('received_at')->useCurrent();
      $table->timestamp('processed_at')->nullable();
      $table->timestamps();

      $table->index(['status', 'received_at']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('telephony_webhook_deliveries');
  }
};
