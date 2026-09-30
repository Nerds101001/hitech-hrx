<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who was on a call that someone logged by hand (Customer Profile > Log Call):
 * the customer contact they spoke to, and the person from our team who made or
 * took the call. Both are optional, so every call logged before this still
 * reads fine -- the Calls tab just shows "Not recorded" for the customer side.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_client_activities', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_contact_id')->nullable()->after('telephony_call_id');
            $table->unsignedBigInteger('our_user_id')->nullable()->after('customer_contact_id');

            $table->foreign('customer_contact_id')->references('id')->on('crm_client_contacts')->nullOnDelete();
            $table->foreign('our_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('crm_client_activities', function (Blueprint $table) {
            $table->dropForeign(['customer_contact_id']);
            $table->dropForeign(['our_user_id']);
            $table->dropColumn(['customer_contact_id', 'our_user_id']);
        });
    }
};
