<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enrich the ledger master with the address/identity fields Tally does expose (state, country, pincode, mailing
 * name, email, address) so the Trend pivot can break down / filter sales & purchases by State / Country / Company
 * / Pincode -- all from the gateway, so they work on live (unlike the file-only salesperson/city columns).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tally_ledgers', function (Blueprint $t) {
            $t->string('state', 60)->nullable()->index()->after('primary_group');
            $t->string('country', 60)->nullable()->index()->after('state');
            $t->string('pincode', 16)->nullable()->index()->after('country');
            $t->string('mailing_name')->nullable()->after('pincode');
            $t->string('email')->nullable()->after('mailing_name');
            $t->text('address')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('tally_ledgers', function (Blueprint $t) {
            $t->dropColumn(['state', 'country', 'pincode', 'mailing_name', 'email', 'address']);
        });
    }
};
