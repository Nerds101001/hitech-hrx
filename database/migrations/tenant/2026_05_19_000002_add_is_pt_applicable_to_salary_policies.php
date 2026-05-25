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
        Schema::table('salary_policies', function (Blueprint $table) {
            $table->boolean('is_pt_applicable')->default(false)->after('pt_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salary_policies', function (Blueprint $table) {
            $table->dropColumn('is_pt_applicable');
        });
    }
};
