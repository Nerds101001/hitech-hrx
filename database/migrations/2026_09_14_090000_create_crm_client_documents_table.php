<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Files attached to a CRM customer (Customer Profile's Documents tab) --
 * contracts, PO copies, anything a rep wants on the account's record.
 * Stored under storage/app/public/crm-client-documents like every other
 * user-uploaded file in this app; nothing here is generated, so no
 * companion migration is needed for existing rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_client_documents', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();

            $table->unsignedBigInteger('crm_client_mapping_id')->index();
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);

            $table->unsignedBigInteger('uploaded_by_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('crm_client_mapping_id')->references('id')->on('crm_client_mappings')->cascadeOnDelete();
            $table->foreign('uploaded_by_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_client_documents');
    }
};
