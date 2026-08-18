<?php

use App\Enums\CallOrigin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a webhook event create its own call record instead of being dropped
 * when HRX never placed the call in the first place -- manual dialing, or
 * any path outside the click-to-call button. Those calls have no known
 * HRX agent, hence agent_id becoming nullable.
 */
return new class extends Migration {
  public function up(): void
  {
    Schema::table('telephony_calls', function (Blueprint $table) {
      $table->dropForeign(['agent_id']);
    });

    Schema::table('telephony_calls', function (Blueprint $table) {
      $table->foreignId('agent_id')->nullable()->change();
      $table->string('origin', 20)->default(CallOrigin::HRX->value)->after('vendor');
      // The vendor's own raw call/channel id. call_uuid stays a clean UUID
      // for every call regardless of origin; this is purely how a second
      // webhook event for the same externally-discovered call gets matched
      // back to the row the first event created, since the vendor's own id
      // is very unlikely to be a UUID itself.
      $table->string('vendor_ref', 191)->nullable()->index()->after('call_uuid');
    });

    Schema::table('telephony_calls', function (Blueprint $table) {
      $table->foreign('agent_id')->references('id')->on('users')->nullOnDelete();
    });
  }

  public function down(): void
  {
    Schema::table('telephony_calls', function (Blueprint $table) {
      $table->dropForeign(['agent_id']);
      $table->dropColumn(['origin', 'vendor_ref']);
    });

    Schema::table('telephony_calls', function (Blueprint $table) {
      $table->foreignId('agent_id')->nullable(false)->change();
      $table->foreign('agent_id')->references('id')->on('users')->cascadeOnDelete();
    });
  }
};
