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
            if (!Schema::hasColumn('travel_claim_items', 'transport_amount')) {
                $table->decimal('transport_amount', 10, 2)->default(0);
            }
            if (!Schema::hasColumn('travel_claim_items', 'transport_proof')) {
                $table->string('transport_proof')->nullable();
            }
            if (!Schema::hasColumn('travel_claim_items', 'bills_amount')) {
                $table->decimal('bills_amount', 10, 2)->default(0);
            }
            if (!Schema::hasColumn('travel_claim_items', 'bills_proof')) {
                $table->string('bills_proof')->nullable();
            }
            if (!Schema::hasColumn('travel_claim_items', 'freight_amount')) {
                $table->decimal('freight_amount', 10, 2)->default(0);
            }
            if (!Schema::hasColumn('travel_claim_items', 'freight_proof')) {
                $table->string('freight_proof')->nullable();
            }
            if (!Schema::hasColumn('travel_claim_items', 'courier_proof')) {
                $table->string('courier_proof')->nullable();
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
                'transport_amount', 'transport_proof',
                'bills_amount', 'bills_proof',
                'freight_amount', 'freight_proof',
                'courier_proof'
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('travel_claim_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
