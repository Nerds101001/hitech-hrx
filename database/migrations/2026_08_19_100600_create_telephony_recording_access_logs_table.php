<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who listened to whose call, and when.
 *
 * In an HR system this log matters as much as the audio. Denied attempts are
 * recorded too - a pattern of refused attempts is itself the signal.
 */
return new class extends Migration
{
  public function up(): void
  {
    Schema::create('telephony_recording_access_logs', function (Blueprint $table) {
      $table->id();
      $table->string('tenant_id')->nullable()->index();

      $table->unsignedBigInteger('user_id')->index();
      $table->string('recording_uid')->index();
      $table->string('call_uid')->index();

      $table->string('action', 16)->default('play');  // play | download | denied
      $table->string('reason')->nullable();           // why it was denied
      $table->string('ip', 45)->nullable();
      $table->string('user_agent', 512)->nullable();
      $table->timestamps();

      $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
      $table->index(['tenant_id', 'created_at']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('telephony_recording_access_logs');
  }
};
