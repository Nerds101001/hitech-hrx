<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which customer this call belongs to, so it can post to their activity
 * feed on disposition. Set directly when an agent dials from a customer's
 * page; otherwise resolved best-effort by phone-number match against
 * crm_client_mappings/crm_client_contacts (see App\Support\CrmPhoneMatcher).
 * Indexed: the disposition/webhook path looks this up by phone, and the
 * customer page looks calls up by this column.
 */
return new class extends Migration {
  public function up(): void
  {
    Schema::table('telephony_calls', function (Blueprint $table) {
      $table->unsignedBigInteger('crm_client_mapping_id')->nullable()->index()->after('agent_id');
      $table->foreign('crm_client_mapping_id')->references('id')->on('crm_client_mappings')->nullOnDelete();
    });
  }

  public function down(): void
  {
    Schema::table('telephony_calls', function (Blueprint $table) {
      $table->dropForeign(['crm_client_mapping_id']);
      $table->dropColumn('crm_client_mapping_id');
    });
  }
};
