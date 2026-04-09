<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('asset_categories', function (Blueprint $table) {
            $table->json('parameters')->nullable()->after('description');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->json('extra_details')->nullable()->after('notes');
            $table->string('warranty_bill')->nullable()->after('warranty_expiry');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_categories', function (Blueprint $table) {
            $table->dropColumn('parameters');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['extra_details', 'warranty_bill']);
        });
    }
};
