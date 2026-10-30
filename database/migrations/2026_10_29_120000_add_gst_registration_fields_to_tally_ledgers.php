<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GSTREGISTRATIONTYPE ("Regular"/"Composition"/"Unregistered"/...), BILLCREDITPERIOD ("60 Days"), NARRATION
 * (the ledger's own "Notes" field, e.g. "60 DAYS CREDIT(TC)"), and ISTDSAPPLICABLE/ISTCSAPPLICABLE -- all
 * confirmed live against Tally (2026-10-29, both HI TECH and KIF companies) as real ledger-master fields
 * beyond the ones already synced, matching exactly what the Ledger Alteration screen (and its "More
 * Details"/F12 view) shows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tally_ledgers', function (Blueprint $t) {
            $t->string('registration_type', 40)->nullable()->after('gstin');
            $t->string('credit_period', 40)->nullable()->after('registration_type');
            $t->text('narration')->nullable()->after('address');
            $t->boolean('tds_applicable')->nullable()->after('narration');
            $t->boolean('tcs_applicable')->nullable()->after('tds_applicable');
        });
    }

    public function down(): void
    {
        Schema::table('tally_ledgers', function (Blueprint $t) {
            $t->dropColumn(['registration_type', 'credit_period', 'narration', 'tds_applicable', 'tcs_applicable']);
        });
    }
};
