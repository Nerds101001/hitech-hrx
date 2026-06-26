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
            if (!Schema::hasColumn('travel_claim_items', 'from_location')) {
                $table->string('from_location')->nullable()->after('date');
            }
            if (!Schema::hasColumn('travel_claim_items', 'to_location')) {
                $table->string('to_location')->nullable()->after('from_location');
            }
            if (!Schema::hasColumn('travel_claim_items', 'party_visited')) {
                $table->string('party_visited')->nullable()->after('to_location');
            }
            if (!Schema::hasColumn('travel_claim_items', 'mode_of_travel')) {
                $table->string('mode_of_travel')->nullable()->after('party_visited');
            }
            if (!Schema::hasColumn('travel_claim_items', 'start_meter')) {
                $table->decimal('start_meter', 10, 2)->nullable()->after('mode_of_travel');
            }
            if (!Schema::hasColumn('travel_claim_items', 'end_meter')) {
                $table->decimal('end_meter', 10, 2)->nullable()->after('start_meter');
            }
            if (!Schema::hasColumn('travel_claim_items', 'distance_km')) {
                $table->decimal('distance_km', 8, 2)->default(0)->after('end_meter');
            }
            if (!Schema::hasColumn('travel_claim_items', 'photo_path')) {
                $table->string('photo_path')->nullable()->after('distance_km');
            }
            if (!Schema::hasColumn('travel_claim_items', 'penalty_applied')) {
                $table->boolean('penalty_applied')->default(false)->after('photo_path');
            }
            if (!Schema::hasColumn('travel_claim_items', 'conveyance_amount')) {
                $table->decimal('conveyance_amount', 10, 2)->default(0)->after('penalty_applied');
            }
            if (!Schema::hasColumn('travel_claim_items', 'food_allowance')) {
                $table->decimal('food_allowance', 10, 2)->default(0)->after('conveyance_amount');
            }
            if (!Schema::hasColumn('travel_claim_items', 'lodging_amount')) {
                $table->decimal('lodging_amount', 10, 2)->default(0)->after('food_allowance');
            }
            if (!Schema::hasColumn('travel_claim_items', 'courier_amount')) {
                $table->decimal('courier_amount', 10, 2)->default(0)->after('lodging_amount');
            }
            if (!Schema::hasColumn('travel_claim_items', 'other_amount')) {
                $table->decimal('other_amount', 10, 2)->default(0)->after('courier_amount');
            }
            if (!Schema::hasColumn('travel_claim_items', 'is_outstation')) {
                $table->boolean('is_outstation')->default(false)->after('conveyance_amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('travel_claim_items', function (Blueprint $table) {
            $cols = [
                'from_location', 'to_location', 'party_visited', 'mode_of_travel',
                'start_meter', 'end_meter', 'distance_km', 'photo_path',
                'penalty_applied', 'conveyance_amount', 'is_outstation',
                'food_allowance', 'lodging_amount', 'courier_amount', 'other_amount'
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('travel_claim_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
