<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-company support for the Tally push pipeline (see config/services.php tally.companies). Every tally_*
 * table gets a `company` column (the short code, e.g. 'hitech'/'kif') so a second company's data can live
 * alongside the first instead of overwriting it -- everything pushed so far was pulled from exactly one
 * company, so MySQL's own column default backfills every existing row as 'hitech' with no separate UPDATE.
 *
 * Three tables had a unique constraint that assumed a single company and would silently let company B's row
 * collide with (and overwrite) company A's: tally_invoices.invoice_no, tally_parties.normalized_name,
 * tally_receipts.receipt_key. Each becomes a composite unique(company, <original column>) instead -- same
 * uniqueness guarantee within a company, no cross-company collision.
 */
return new class extends Migration
{
    /** Tables that get a plain indexed `company` column, in insertion order (no unique-key change needed). */
    private const PLAIN = [
        'tally_invoice_lines', 'tally_outstanding', 'tally_sale_orders', 'tally_sale_lines', 'tally_deliveries',
        'tally_purchases', 'tally_purchase_lines', 'tally_purchase_orders', 'tally_payments', 'tally_stock_items',
        'tally_ledgers',
    ];

    public function up(): void
    {
        foreach (self::PLAIN as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('company', 20)->default('hitech')->index()->after('id');
            });
        }

        Schema::table('tally_invoices', function (Blueprint $t) {
            $t->string('company', 20)->default('hitech')->after('id');
            $t->dropUnique(['invoice_no']);
            $t->unique(['company', 'invoice_no']);
        });

        Schema::table('tally_parties', function (Blueprint $t) {
            $t->string('company', 20)->default('hitech')->after('id');
            $t->dropUnique(['normalized_name']);
            $t->unique(['company', 'normalized_name']);
        });

        Schema::table('tally_receipts', function (Blueprint $t) {
            $t->string('company', 20)->default('hitech')->after('id');
            $t->dropUnique(['receipt_key']);
            $t->unique(['company', 'receipt_key']);
        });
    }

    public function down(): void
    {
        Schema::table('tally_invoices', function (Blueprint $t) {
            $t->dropUnique(['company', 'invoice_no']);
            $t->dropColumn('company');
            $t->unique(['invoice_no']);
        });

        Schema::table('tally_parties', function (Blueprint $t) {
            $t->dropUnique(['company', 'normalized_name']);
            $t->dropColumn('company');
            $t->unique(['normalized_name']);
        });

        Schema::table('tally_receipts', function (Blueprint $t) {
            $t->dropUnique(['company', 'receipt_key']);
            $t->dropColumn('company');
            $t->unique(['receipt_key']);
        });

        foreach (self::PLAIN as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('company');
            });
        }
    }
};
