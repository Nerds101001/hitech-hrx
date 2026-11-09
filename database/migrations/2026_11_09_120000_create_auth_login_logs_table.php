<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dedicated, append-only log of every authentication attempt (web + mobile),
 * success or failure, with the originating IP and device. Separate from the
 * model `audits` table on purpose: this must capture attempts that never touch
 * a model row (unknown username, blocked IP) and must stay cheap to write.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('auth_login_logs')) {
            return;
        }

        Schema::create('auth_login_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable();   // matched account, if any
            $table->string('email_input', 191)->nullable();      // what the visitor typed
            $table->string('guard', 20)->default('web');         // web | api
            $table->string('event', 40);                         // see App\Support\AuthLog constants
            $table->boolean('successful')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'created_at'], 'all_user_created_idx');
            $table->index(['ip_address', 'created_at'], 'all_ip_created_idx');
            $table->index(['event', 'created_at'], 'all_event_created_idx');
            $table->index(['successful', 'created_at'], 'all_success_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_login_logs');
    }
};
