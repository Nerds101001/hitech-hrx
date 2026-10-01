<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Tally sale figure CCare / New Biz record against a salesperson for a month.
 *
 * It sits on salesperson_targets because that table already holds exactly one row
 * per salesperson per month, which is the same grain - a separate table would only
 * duplicate that key and then have to be kept in step with it.
 *
 * Nullable on purpose: null means "nobody has entered anything yet", which is a
 * different thing from a recorded zero, and the modal shows the two differently.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salesperson_targets', function (Blueprint $table) {
            $table->decimal('tally_sale', 15, 2)->nullable()->after('bingo_target');
            $table->unsignedBigInteger('tally_sale_by_id')->nullable()->after('tally_sale');
            $table->timestamp('tally_sale_at')->nullable()->after('tally_sale_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('salesperson_targets', function (Blueprint $table) {
            $table->dropColumn(['tally_sale', 'tally_sale_by_id', 'tally_sale_at']);
        });
    }
};
