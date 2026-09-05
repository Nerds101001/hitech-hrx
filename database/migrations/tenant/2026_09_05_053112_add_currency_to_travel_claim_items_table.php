<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_claim_items', function (Blueprint $table) {
            // Every existing row is implicitly INR - this only lets a new row
            // say otherwise. No conversion happens anywhere: the claim total
            // is still a plain sum of whatever numbers are on each row, so a
            // foreign-currency entry records the figure the employee actually
            // paid, it does not convert it into the claim's INR total.
            if (!Schema::hasColumn('travel_claim_items', 'currency')) {
                $table->string('currency', 3)->default('INR')->after('mode_of_travel');
            }
        });
    }

    public function down(): void
    {
        Schema::table('travel_claim_items', function (Blueprint $table) {
            if (Schema::hasColumn('travel_claim_items', 'currency')) {
                $table->dropColumn('currency');
            }
        });
    }
};
