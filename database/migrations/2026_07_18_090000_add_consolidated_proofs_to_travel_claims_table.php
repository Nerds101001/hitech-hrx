<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-day bill uploads are being replaced by one consolidated file per category,
 * submitted once for the whole month. These four columns hold those files on the
 * claim header. The old per-item *_proof columns on travel_claim_items are left
 * in place so historical claims still display their day-wise proofs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_claims', function (Blueprint $table) {
            $table->string('food_bill_proof', 255)->nullable()->after('month_end_odometer_proof');
            $table->string('conveyance_petrol_proof', 255)->nullable()->after('food_bill_proof');
            $table->string('toll_parking_proof', 255)->nullable()->after('conveyance_petrol_proof');
            $table->string('customer_courier_proof', 255)->nullable()->after('toll_parking_proof');
        });
    }

    public function down(): void
    {
        Schema::table('travel_claims', function (Blueprint $table) {
            $table->dropColumn([
                'food_bill_proof',
                'conveyance_petrol_proof',
                'toll_parking_proof',
                'customer_courier_proof',
            ]);
        });
    }
};
