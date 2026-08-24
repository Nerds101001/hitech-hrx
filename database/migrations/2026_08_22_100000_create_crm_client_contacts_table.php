<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * People inside a CRM customer's own organisation -- their purchase team,
 * finance team, etc. -- as opposed to crm_client_mappings' own contact_
 * person/email/phone, which is a single flat field and can't represent more
 * than one contact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_client_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();

            $table->unsignedBigInteger('crm_client_mapping_id')->index();
            $table->string('name');
            $table->string('role', 20)->default('other');
            $table->string('email')->nullable();
            $table->string('phone')->nullable()->index();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('crm_client_mapping_id')->references('id')->on('crm_client_mappings')->cascadeOnDelete();
            $table->foreign('created_by_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_client_contacts');
    }
};
