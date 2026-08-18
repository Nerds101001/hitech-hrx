<?php

use App\Enums\CallEndedBy;
use App\Enums\TelephonyCallStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('telephony_calls', function (Blueprint $table) {
      $table->id();
      $table->uuid('call_uuid')->unique(); // the public call_id every consumer of the contract sees
      $table->string('vendor', 40)->default('mock'); // which adapter handled this call
      $table->foreignId('agent_id')->constrained('users')->onDelete('cascade');
      $table->string('campaign_id', 100)->nullable();
      $table->string('from_number', 20);
      $table->string('to_number', 20);
      $table->enum('status', [
        TelephonyCallStatus::QUEUED->value,
        TelephonyCallStatus::RINGING->value,
        TelephonyCallStatus::CONNECTED->value,
        TelephonyCallStatus::BUSY->value,
        TelephonyCallStatus::NO_ANSWER->value,
        TelephonyCallStatus::INVALID_NUMBER->value,
        TelephonyCallStatus::FAILED->value,
        TelephonyCallStatus::ENDED->value,
      ])->default(TelephonyCallStatus::QUEUED->value);
      $table->enum('ended_by', [
        CallEndedBy::AGENT->value,
        CallEndedBy::CUSTOMER->value,
        CallEndedBy::SYSTEM->value,
      ])->nullable();
      $table->string('end_reason', 191)->nullable();
      $table->timestamp('queued_at')->nullable();
      $table->timestamp('ringing_at')->nullable();
      $table->timestamp('connected_at')->nullable();
      $table->timestamp('ended_at')->nullable();
      $table->unsignedInteger('duration')->nullable(); // seconds, connect to end
      $table->string('disposition_code', 100)->nullable();
      $table->text('disposition_notes')->nullable();
      $table->string('recording_url')->nullable();
      $table->uuid('redial_of_call_uuid')->nullable(); // soft link, not an enforced FK — mirrors the vendors' own refID convention
      $table->string('idempotency_key', 191)->nullable()->unique();
      $table->json('raw_vendor_response')->nullable(); // whatever the real adapter got back, kept server-side only

      $table->foreignId('created_by_id')->nullable()->constrained('users')->onDelete('set null');
      $table->foreignId('updated_by_id')->nullable()->constrained('users')->onDelete('set null');
      $table->string('tenant_id', 191)->nullable();
      $table->softDeletes();
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('telephony_calls');
  }
};
