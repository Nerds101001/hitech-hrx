<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a Sales Report / Sales Pipeline row (identified only by a freehand
 * party_name typed by the salesperson) to the CRM.API-synced customer it
 * actually is, once reconciled -- see SalesPipelineController::syncCrmMapping()/
 * resolveCrmMapping(), which mirrors the existing Setu reconciliation flow.
 * Nullable: a pipeline row is often a prospect that isn't in the CRM yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_pipelines', function (Blueprint $table) {
            $table->unsignedBigInteger('crm_client_mapping_id')->nullable()->index()->after('salesperson_id');
            $table->foreign('crm_client_mapping_id')->references('id')->on('crm_client_mappings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_pipelines', function (Blueprint $table) {
            $table->dropForeign(['crm_client_mapping_id']);
            $table->dropColumn('crm_client_mapping_id');
        });
    }
};
