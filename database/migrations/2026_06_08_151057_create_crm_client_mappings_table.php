<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_client_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();

            // CRM company identifier (Comp_Code from api-crm.rustx.net)
            $table->unsignedBigInteger('crm_company_code')->index();
            // Cache the name so we don't always need to call CRM API
            $table->string('crm_company_name');
            $table->string('crm_city')->nullable();
            $table->string('crm_state')->nullable();
            $table->string('crm_stage')->nullable();

            // HRX employee assignments
            $table->unsignedBigInteger('salesperson_id')->nullable();
            $table->unsignedBigInteger('ccare_id')->nullable();
            $table->unsignedBigInteger('new_biz_id')->nullable();

            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // One mapping per CRM company per tenant
            $table->unique(['tenant_id', 'crm_company_code']);

            $table->foreign('salesperson_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('ccare_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('new_biz_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_client_mappings');
    }
};
