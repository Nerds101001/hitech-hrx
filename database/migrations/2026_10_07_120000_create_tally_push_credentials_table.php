<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DB-backed credentials for the Tally push endpoint, so the key, secret and
 * IP allowlist can be generated, rotated and edited from the admin page
 * instead of hand-editing .env. Mirrors telephony_credentials exactly; the
 * api_key is stored in the clear (it is an identifier that travels in the
 * Authorization header), the secret is encrypted at rest by the model cast.
 *
 * VerifyTallyPushSignature reads this table first and falls back to
 * config/tally_push.php, so an install that predates this page keeps working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_push_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->string('label')->default('Tally Office PC');
            $table->string('api_key', 128)->unique();
            $table->text('secret');                       // encrypted by the model cast
            $table->text('previous_secret')->nullable();  // encrypted; kept valid during a rotation window
            $table->timestamp('previous_secret_expires_at')->nullable();
            $table->text('allowed_ips')->nullable();       // comma list / CIDR; null => fall back to config
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->unsignedBigInteger('use_count')->default(0);
            $table->timestamp('rotated_at')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->foreign('created_by_id')->references('id')->on('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_push_credentials');
    }
};
