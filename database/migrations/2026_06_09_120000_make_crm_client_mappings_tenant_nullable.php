<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_client_mappings', function (Blueprint $table) {
            // Make tenant_id nullable to match the rest of the system
            // (users.tenant_id is nullable; local dev users may not have one set)
            $table->string('tenant_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('crm_client_mappings', function (Blueprint $table) {
            $table->string('tenant_id')->nullable(false)->change();
        });
    }
};
