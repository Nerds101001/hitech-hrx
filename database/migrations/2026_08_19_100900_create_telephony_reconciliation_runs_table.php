<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nightly proof that HRX and the gateway agree. A non-zero gap is a defect to
 * root-cause, not a tolerance to widen - so every run is kept.
 */
return new class extends Migration
{
  public function up(): void
  {
    Schema::create('telephony_reconciliation_runs', function (Blueprint $table) {
      $table->id();
      $table->string('tenant_id')->nullable()->index();

      $table->date('period_date')->index();
      $table->timestamp('period_start');
      $table->timestamp('period_end');

      $table->unsignedInteger('vendor_count')->default(0);
      $table->unsignedInteger('local_count')->default(0);
      $table->integer('count_gap')->default(0);
      $table->unsignedInteger('vendor_talk_seconds')->default(0);
      $table->unsignedInteger('local_talk_seconds')->default(0);
      $table->integer('talk_gap_seconds')->default(0);
      $table->unsignedInteger('backfilled')->default(0);

      $table->string('result', 16)->default('pending')->index(); // pass | investigate | failed
      $table->text('error')->nullable();
      $table->json('missing_call_uids')->nullable();

      $table->timestamps();

      $table->unique(['tenant_id', 'period_date']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('telephony_reconciliation_runs');
  }
};
