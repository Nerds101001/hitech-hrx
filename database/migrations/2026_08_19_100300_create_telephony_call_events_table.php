<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only event log. Never updated, never deleted by application code.
 * If normalisation logic changes, telephony_calls can be rebuilt from here.
 */
return new class extends Migration
{
  public function up(): void
  {
    Schema::create('telephony_call_events', function (Blueprint $table) {
      $table->id();
      $table->string('tenant_id')->nullable()->index();

      $table->uuid('event_id')->index();
      $table->string('call_uid')->index();
      $table->string('event_type', 64)->index();
      $table->unsignedInteger('sequence')->default(0);
      $table->timestamp('occurred_at')->index();
      $table->json('payload');
      $table->timestamps();

      // The idempotency guarantee lives here, at the database level, not in a
      // race-prone "select then insert" in PHP.
      $table->unique(['tenant_id', 'event_id']);
      $table->index(['call_uid', 'sequence']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('telephony_call_events');
  }
};
