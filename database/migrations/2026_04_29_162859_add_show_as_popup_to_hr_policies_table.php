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
            $table->boolean('show_as_popup')->default(true)->after('is_mandatory');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hr_policies', function (Blueprint $table) {
            $table->dropColumn('show_as_popup');
        });
    }
};
