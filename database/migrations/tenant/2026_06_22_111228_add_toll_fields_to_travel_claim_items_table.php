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
            if (!Schema::hasColumn('travel_claim_items', 'toll_amount')) {
                $table->decimal('toll_amount', 10, 2)->default(0);
            }
            if (!Schema::hasColumn('travel_claim_items', 'toll_proof')) {
                $table->string('toll_proof')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('travel_claim_items', function (Blueprint $table) {
            if (Schema::hasColumn('travel_claim_items', 'toll_amount')) {
                $table->dropColumn('toll_amount');
            }
            if (Schema::hasColumn('travel_claim_items', 'toll_proof')) {
                $table->dropColumn('toll_proof');
            }
        });
    }
};
