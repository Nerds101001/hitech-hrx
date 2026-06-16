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
        Schema::table('sales_pipelines', function (Blueprint $table) {
            $table->boolean('converted_from_newbiz')->default(false)->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_pipelines', function (Blueprint $table) {
            $table->dropColumn('converted_from_newbiz');
        });
    }
};
