<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendor API credentials, managed from the admin UI instead of .env.
 *
 * The env variables still work and are still the fallback, but they mean every
 * key rotation needs SSH access, a file edit and a config:clear -- which for a
 * credential that should rotate every 180 days is enough friction that it
 * quietly never happens.
 *
 * The api_key is stored in the clear on purpose: it travels in an
 * Authorization header on every request, so it is an identifier, not a secret,
 * and it has to be looked up by value. The shared secret is encrypted, because
 * that one never crosses the wire and is what actually proves a request is
 * genuine.
 */
return new class extends Migration
{
  public function up(): void
  {
    Schema::create('telephony_credentials', function (Blueprint $table) {
      $table->id();
      $table->string('tenant_id')->nullable()->index();

      $table->string('label')->default('CentroVision');
      $table->string('api_key', 128)->unique();

      // Encrypted at rest via the model's casts.
      $table->text('secret');

      // During a rotation both secrets are accepted, so the vendor can switch
      // over without a coordinated cutover and without dropping a single call.
      $table->text('previous_secret')->nullable();
      $table->timestamp('previous_secret_expires_at')->nullable();

      // Per-credential allowlist. Falls back to config when null, so an
      // existing TELEPHONY_ALLOWED_IPS keeps working.
      $table->text('allowed_ips')->nullable();

      $table->boolean('is_active')->default(true)->index();

      // Populated by the middleware on every accepted request. This is what
      // answers "are they actually sending?" without reading a log file.
      $table->timestamp('last_used_at')->nullable();
      $table->string('last_used_ip', 45)->nullable();
      $table->unsignedBigInteger('use_count')->default(0);

      $table->timestamp('rotated_at')->nullable();
      $table->unsignedBigInteger('created_by_id')->nullable();
      $table->text('notes')->nullable();

      $table->timestamps();

      $table->foreign('created_by_id')->references('id')->on('users')->nullOnDelete();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('telephony_credentials');
  }
};
