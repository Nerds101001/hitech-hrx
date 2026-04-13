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
        Schema::table('probation_evaluations', function (Blueprint $table) {
            $table->string('hr_decision')->nullable()->after('hr_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('probation_evaluations', function (Blueprint $table) {
            $table->dropColumn('hr_decision');
        });
    }
};
