<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-person-per-role team assignment for a CRM customer (Customer 360's
 * "Internal Team" card). The 5 columns already on crm_client_mappings
 * (salesperson_id/ccare_id/new_biz_id/billing_id/finance_id) stay exactly as
 * they are — telephony call-linking and CrmClientMappingController's
 * existing sync/visibility code all depend on them as-is. This table is a
 * purely additive layer letting a role hold more than one person (e.g. two
 * New Biz reps); CrmClientMappingController keeps the singular columns
 * loosely mirrored to each role's first/primary person so those other
 * subsystems keep working unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_client_mapping_team', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_client_mapping_id')->constrained('crm_client_mappings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['sales_person', 'ccare', 'new_biz', 'billing', 'finance', 'other']);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();

            $table->unique(['crm_client_mapping_id', 'user_id', 'role'], 'crm_team_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_client_mapping_team');
    }
};
