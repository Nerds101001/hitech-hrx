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
        Schema::table('hr_policies', function (Blueprint $table) {
            $table->json('target_departments')->nullable()->after('show_as_popup')->comment('Array of department IDs. Null means all departments.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hr_policies', function (Blueprint $table) {
            $table->dropColumn('target_departments');
        });
    }
};
