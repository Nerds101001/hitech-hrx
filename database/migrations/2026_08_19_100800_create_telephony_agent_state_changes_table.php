<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Live state transitions - drives the wallboard and the break/offtime report. */
return new class extends Migration
{
  public function up(): void
  {
    Schema::create('telephony_agent_state_changes', function (Blueprint $table) {
      $table->id();
      $table->string('tenant_id')->nullable()->index();

      $table->string('session_id')->nullable()->index();
      $table->unsignedBigInteger('user_id')->nullable()->index();
      $table->string('agent_id')->nullable()->index();

      $table->string('state', 16)->index();          // ready|oncall|hangup|wrapup|idle|break|offline|dialing|paused
      $table->string('previous_state', 16)->nullable();
      $table->string('reason')->nullable();          // break reason

      $table->timestamp('occurred_at')->index();
      $table->unsignedInteger('duration_seconds')->nullable(); // filled when the next state arrives
      $table->timestamps();

      // Named explicitly: the auto-generated name would be 66 characters and
      // MySQL caps identifiers at 64.
      $table->index(['tenant_id', 'agent_id', 'occurred_at'], 'tel_state_tenant_agent_time_idx');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('telephony_agent_state_changes');
  }
};
