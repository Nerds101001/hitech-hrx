<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_client_mappings', function (Blueprint $table) {
            // Contact fields fetched from CRM getCompanyById
            if (!Schema::hasColumn('crm_client_mappings', 'email')) {
                $table->string('email')->nullable()->after('crm_stage');
            }
            if (!Schema::hasColumn('crm_client_mappings', 'phone')) {
                $table->string('phone')->nullable()->after('email');
            }
            if (!Schema::hasColumn('crm_client_mappings', 'address')) {
                $table->text('address')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('crm_client_mappings', 'contact_person')) {
                $table->string('contact_person')->nullable()->after('address');
            }

            // Additional team roles: Billing and Finance
            if (!Schema::hasColumn('crm_client_mappings', 'billing_id')) {
                $table->unsignedBigInteger('billing_id')->nullable()->after('new_biz_id');
                $table->foreign('billing_id')->references('id')->on('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('crm_client_mappings', 'finance_id')) {
                $table->unsignedBigInteger('finance_id')->nullable()->after('billing_id');
                $table->foreign('finance_id')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_client_mappings', function (Blueprint $table) {
            $table->dropForeign(['billing_id']);
            $table->dropForeign(['finance_id']);
            $table->dropColumn(['email', 'phone', 'address', 'contact_person', 'billing_id', 'finance_id']);
        });
    }
};
