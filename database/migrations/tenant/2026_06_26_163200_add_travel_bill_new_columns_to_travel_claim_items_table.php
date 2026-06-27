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
        Schema::table('travel_claim_items', function (Blueprint $table) {
            if (!Schema::hasColumn('travel_claim_items', 'petrol_slip_proof')) {
                $table->string('petrol_slip_proof')->nullable()->after('photo_path');
            }
            if (!Schema::hasColumn('travel_claim_items', 'auto_taxi_amount')) {
                $table->decimal('auto_taxi_amount', 10, 2)->default(0)->after('conveyance_amount');
            }
            if (!Schema::hasColumn('travel_claim_items', 'auto_taxi_proof')) {
                $table->string('auto_taxi_proof')->nullable()->after('auto_taxi_amount');
            }
        });

        Schema::table('travel_claims', function (Blueprint $table) {
            if (!Schema::hasColumn('travel_claims', 'objection_notes')) {
                $table->text('objection_notes')->nullable()->after('remarks');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('travel_claim_items', function (Blueprint $table) {
            $cols = ['petrol_slip_proof', 'auto_taxi_amount', 'auto_taxi_proof'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('travel_claim_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('travel_claims', function (Blueprint $table) {
            if (Schema::hasColumn('travel_claims', 'objection_notes')) {
                $table->dropColumn('objection_notes');
            }
        });
    }
};
