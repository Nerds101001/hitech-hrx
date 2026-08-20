<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRX employee <-> CentroVision dialer agent.
 *
 * This is the spine of the whole telephony feature. A call that cannot be
 * resolved to a row here lands on the exceptions screen instead of against an
 * employee, so keeping this table current is an operational duty, not a
 * one-time seed.
 */
return new class extends Migration
{
  public function up(): void
  {
    Schema::create('telephony_agent_map', function (Blueprint $table) {
      $table->id();
      $table->string('tenant_id')->nullable()->index();

      $table->unsignedBigInteger('user_id');

      // Vendor-side identity. Any one of these may be the only thing present on
      // a manual-dial event, so all four are indexed and all four are tried.
      $table->string('agent_id')->nullable()->index();      // AG-1042
      $table->string('agent_code')->nullable()->index();    // mehak
      $table->string('extension')->nullable()->index();     // 1042
      $table->string('agent_number')->nullable()->index();  // +919812345678 (SIM/handset)

      $table->string('default_campaign_id')->nullable();
      $table->string('default_campaign_name')->nullable();

      $table->boolean('is_active')->default(true)->index();
      $table->timestamp('last_seen_at')->nullable();
      $table->text('notes')->nullable();

      $table->unsignedBigInteger('created_by_id')->nullable();
      $table->unsignedBigInteger('updated_by_id')->nullable();
      $table->timestamps();
      $table->softDeletes();

      $table->unique(['tenant_id', 'agent_id']);
      $table->unique(['tenant_id', 'extension']);

      $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
      $table->foreign('created_by_id')->references('id')->on('users')->nullOnDelete();
      $table->foreign('updated_by_id')->references('id')->on('users')->nullOnDelete();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('telephony_agent_map');
  }
};
