<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Login-to-logout sessions. Totals are taken from the vendor rather than
 * derived here, so HRX and the dialer User Session report cannot disagree.
 */
return new class extends Migration
{
  public function up(): void
  {
    Schema::create('telephony_agent_sessions', function (Blueprint $table) {
      $table->id();
      $table->string('tenant_id')->nullable()->index();

      $table->string('session_id')->index();
      $table->unsignedBigInteger('user_id')->nullable()->index();
      $table->string('agent_id')->nullable()->index();
      $table->string('agent_code')->nullable();
      $table->string('extension')->nullable();
      $table->string('agent_number')->nullable();

      $table->string('mode', 8)->nullable();
      $table->string('campaign_id')->nullable();
      $table->string('campaign_name')->nullable();

      $table->timestamp('login_at')->nullable()->index();
      $table->timestamp('logout_at')->nullable();

      $table->unsignedInteger('login_seconds')->default(0);
      $table->unsignedInteger('ready_seconds')->default(0);
      $table->unsignedInteger('talk_seconds')->default(0);
      $table->unsignedInteger('hold_seconds')->default(0);
      $table->unsignedInteger('wrap_seconds')->default(0);
      $table->unsignedInteger('idle_seconds')->default(0);
      $table->unsignedInteger('break_seconds')->default(0);
      $table->decimal('occupancy_percent', 5, 2)->nullable();
      $table->unsignedInteger('calls_handled')->default(0);
      $table->unsignedInteger('calls_missed')->default(0);

      $table->json('breaks')->nullable();
      $table->json('device')->nullable();

      $table->boolean('is_open')->default(true)->index();
      $table->timestamps();

      $table->unique(['tenant_id', 'session_id']);
      $table->index(['tenant_id', 'user_id', 'login_at']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('telephony_agent_sessions');
  }
};
