<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * tally_credit_debit_note_lines.amount is the TAXABLE portion (same convention
 * as tally_sale_lines.taxable_amount -- a raw Tally line amount, GST computed
 * separately), but the Sales/Purchase trend totals it nets against are
 * GST-INCLUSIVE. Netting the taxable-only note amount against a tax-inclusive
 * total under-subtracts by exactly the note's own GST -- found by diffing a
 * real month customer-by-customer against Setu: one customer's two credit
 * notes' 18% GST accounted for the entire remaining gap.
 *
 * Adds tax_amount (computed from the already-stored gst_rate, same formula
 * TallyPushController's saleLineRow()/purchaseLineRow() use) and backfills
 * existing rows so this doesn't need a re-pull from Tally.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tally_credit_debit_note_lines', function (Blueprint $t) {
            $t->decimal('tax_amount', 15, 2)->nullable()->after('amount');
        });

        DB::statement('UPDATE tally_credit_debit_note_lines SET tax_amount = ROUND(amount * gst_rate / 100, 2) WHERE gst_rate IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('tally_credit_debit_note_lines', function (Blueprint $t) {
            $t->dropColumn('tax_amount');
        });
    }
};
