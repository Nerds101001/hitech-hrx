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
            $cols = ['location', 'expense_type', 'amount', 'bill_path'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('travel_claim_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('travel_claim_items', function (Blueprint $table) {
            if (!Schema::hasColumn('travel_claim_items', 'location')) {
                $table->string('location')->nullable();
            }
            if (!Schema::hasColumn('travel_claim_items', 'expense_type')) {
                $table->string('expense_type')->nullable();
            }
            if (!Schema::hasColumn('travel_claim_items', 'amount')) {
                $table->decimal('amount', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('travel_claim_items', 'bill_path')) {
                $table->string('bill_path')->nullable();
            }
        });
    }
};
