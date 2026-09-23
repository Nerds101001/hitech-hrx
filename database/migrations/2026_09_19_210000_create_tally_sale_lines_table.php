<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item-level sales from Tally's SaleReportCRM export: the only file that carries
 * HSN, GST %, rate and taxable value per line. It covers only some invoices
 * (the ones Tally's CRM report includes), so every screen that uses it says so.
 *
 * Also prepares the other Tally tables for analytics that stays fast as the data
 * grows: the invoice-number series, the family of each item, the filter columns
 * (customer, salesperson, series, branch-transfer flag) copied onto the line
 * tables so they can be filtered without joining the invoice register, and three
 * per-invoice totals (taxable, GST, still-open) stamped by the import.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_sale_lines', function (Blueprint $t) {
            $t->id();
            $t->string('invoice_no', 60)->index();
            $t->string('series', 20)->nullable();
            $t->date('invoice_date')->index();
            $t->string('party_code', 20)->nullable()->index();
            $t->string('party_name')->nullable();
            $t->string('normalized_name')->nullable();
            $t->string('city', 120)->nullable()->index();
            $t->string('item_name', 500);
            $t->string('product_group', 60)->index();
            $t->string('hsn', 16)->nullable()->index();
            $t->decimal('gst_rate', 5, 2)->nullable();
            $t->decimal('quantity', 15, 3)->nullable();
            $t->string('unit', 20)->nullable();
            $t->decimal('rate', 15, 4)->nullable();
            $t->string('rate_unit', 20)->nullable();
            $t->decimal('taxable_amount', 15, 2);
            $t->decimal('tax_amount', 15, 2)->nullable();
            $t->date('due_date')->nullable();
            $t->string('credit_terms', 40)->nullable();
            $t->string('salesperson', 120)->nullable();
            $t->boolean('is_branch_transfer')->default(false);
        });

        Schema::table('tally_invoices', function (Blueprint $t) {
            $t->string('series', 20)->nullable()->after('invoice_no')->index();
            $t->decimal('taxable_total', 15, 2)->nullable();   // sum of its SaleReportCRM lines; NULL = no item/GST detail for this invoice
            $t->decimal('tax_total', 15, 2)->nullable();
            $t->decimal('open_amount', 15, 2)->nullable();     // still open in Tally's outstanding list (matched by bill number); NULL = not outstanding
        });

        Schema::table('tally_invoice_lines', function (Blueprint $t) {
            $t->string('product_group', 60)->nullable()->after('item_name')->index();
            $t->string('series', 20)->nullable();
            $t->string('party_code', 20)->nullable()->index();
            $t->string('party_name')->nullable();
            $t->string('salesperson', 120)->nullable();
            $t->boolean('is_branch_transfer')->default(false);
        });
    }

    public function down(): void
    {
        foreach (['product_group' => true, 'series' => false, 'party_code' => true, 'party_name' => false, 'salesperson' => false, 'is_branch_transfer' => false] as $col => $indexed) {
            if (Schema::hasColumn('tally_invoice_lines', $col)) {
                Schema::table('tally_invoice_lines', function (Blueprint $t) use ($col, $indexed) {
                    if ($indexed) {
                        $t->dropIndex([$col]);
                    }
                    $t->dropColumn($col);
                });
            }
        }
        if (Schema::hasColumn('tally_invoices', 'series')) {
            Schema::table('tally_invoices', function (Blueprint $t) {
                $t->dropIndex(['series']);
                $t->dropColumn(['series']);
            });
        }
        foreach (['taxable_total', 'tax_total', 'open_amount'] as $col) {
            if (Schema::hasColumn('tally_invoices', $col)) {
                Schema::table('tally_invoices', fn(Blueprint $t) => $t->dropColumn($col));
            }
        }
        Schema::dropIfExists('tally_sale_lines');
    }
};
