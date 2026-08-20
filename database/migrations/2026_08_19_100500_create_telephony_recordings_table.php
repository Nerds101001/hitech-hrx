<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recording metadata only. The audio itself never lands on HRX disk: HRX asks
 * the gateway for a <=300s signed URL at play time and streams the bytes
 * through its own permission layer.
 */
return new class extends Migration
{
  public function up(): void
  {
    Schema::create('telephony_recordings', function (Blueprint $table) {
      $table->id();
      $table->string('tenant_id')->nullable()->index();

      $table->string('recording_uid')->index();
      $table->string('call_uid')->index();
      $table->string('recording_type', 20)->default('call'); // call | transfer_leg | conference | voicemail

      $table->unsignedInteger('duration_seconds')->nullable();
      $table->string('format', 8)->nullable();
      $table->unsignedBigInteger('size_bytes')->nullable();
      $table->string('channels', 8)->nullable();
      $table->string('checksum_sha256', 64)->nullable();
      $table->date('retention_until')->nullable();

      $table->timestamp('ready_at')->nullable();
      $table->timestamps();

      $table->unique(['tenant_id', 'recording_uid']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('telephony_recordings');
  }
};
