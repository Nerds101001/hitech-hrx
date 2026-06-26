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
            if (!Schema::hasColumn('travel_claim_items', 'additional_food_amount')) {
                $table->decimal('additional_food_amount', 10, 2)->default(0);
            }
            if (!Schema::hasColumn('travel_claim_items', 'additional_food_proof')) {
                $table->string('additional_food_proof')->nullable();
            }
            if (!Schema::hasColumn('travel_claim_items', 'special_approval_amount')) {
                $table->decimal('special_approval_amount', 10, 2)->default(0);
            }
            if (!Schema::hasColumn('travel_claim_items', 'special_approval_proof')) {
                $table->string('special_approval_proof')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('travel_claim_items', function (Blueprint $table) {
            if (Schema::hasColumn('travel_claim_items', 'additional_food_amount')) {
                $table->dropColumn('additional_food_amount');
            }
            if (Schema::hasColumn('travel_claim_items', 'additional_food_proof')) {
                $table->dropColumn('additional_food_proof');
            }
            if (Schema::hasColumn('travel_claim_items', 'special_approval_amount')) {
                $table->dropColumn('special_approval_amount');
            }
            if (Schema::hasColumn('travel_claim_items', 'special_approval_proof')) {
                $table->dropColumn('special_approval_proof');
            }
        });
    }
};
