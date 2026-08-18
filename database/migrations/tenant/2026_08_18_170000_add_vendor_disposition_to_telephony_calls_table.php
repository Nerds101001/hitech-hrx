<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the two fields the real CentroVision CDR report carries that our
 * canonical contract doesn't: a separate ring time (distinct from talk
 * time), and the vendor's own disposition wording verbatim ("Answered By
 * Agent", "Ringing - No Answer", "Rejected By Switch", ...). `status`
 * stays the normalised, vendor-agnostic enum -- this is additive, for
 * showing the real, familiar language on reports without giving up the
 * vendor-independent contract.
 */
return new class extends Migration {
  public function up(): void
  {
    Schema::table('telephony_calls', function (Blueprint $table) {
      $table->unsignedInteger('ring_seconds')->nullable()->after('duration');
      $table->string('vendor_disposition', 100)->nullable()->after('disposition_notes');
    });
  }

  public function down(): void
  {
    Schema::table('telephony_calls', function (Blueprint $table) {
      $table->dropColumn(['ring_seconds', 'vendor_disposition']);
    });
  }
};
