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
        Schema::table('travel_claims', function (Blueprint $table) {
            $table->string('month_start_odometer_proof')->nullable()->after('sales_collection');
            $table->string('month_end_odometer_proof')->nullable()->after('month_start_odometer_proof');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('travel_claims', function (Blueprint $table) {
            $table->dropColumn('month_start_odometer_proof');
            $table->dropColumn('month_end_odometer_proof');
        });
    }
};
