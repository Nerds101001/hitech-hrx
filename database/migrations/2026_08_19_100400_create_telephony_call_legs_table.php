<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Transfer, conference and forwarding legs hanging off a parent call. */
return new class extends Migration
{
  public function up(): void
  {
    Schema::create('telephony_call_legs', function (Blueprint $table) {
      $table->id();
      $table->string('tenant_id')->nullable()->index();

      $table->string('call_uid')->index();          // parent
      $table->string('child_call_uid')->nullable()->index();
      $table->string('leg_type', 16)->index();      // transfer | conference | forward
      $table->string('sub_type', 20)->nullable();   // blind | attended | warm | unconditional | busy | no_answer ...

      $table->string('from_agent_id')->nullable();
      $table->string('to_agent_id')->nullable();
      $table->string('from_number')->nullable();
      $table->string('to_number')->nullable();
      $table->string('to_extension')->nullable();
      $table->string('to_queue')->nullable();

      $table->string('conference_id')->nullable()->index();
      $table->json('participants')->nullable();

      $table->timestamp('started_at')->nullable();
      $table->timestamp('ended_at')->nullable();
      $table->unsignedInteger('consult_seconds')->nullable();
      $table->string('recording_uid')->nullable();

      $table->timestamps();

      $table->index(['tenant_id', 'call_uid', 'leg_type']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('telephony_call_legs');
  }
};
