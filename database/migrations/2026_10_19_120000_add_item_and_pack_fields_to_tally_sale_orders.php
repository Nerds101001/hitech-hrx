<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // tally_sale_orders was API-push-sourced per order LINE already (quantity/unit/amount), but dropped the
        // item name and packaging detail that were already being parsed off the same voucher line and simply
        // never carried through -- added here rather than built as a new table. See app/Services/TallyGateway.php
        // parseVouchers() for where pack_* comes from (a real Tally custom field, confirmed 2026-10-17).
        Schema::table('tally_sale_orders', function (Blueprint $table) {
            $table->string('item_name', 500)->nullable()->after('party_name');
            $table->string('hsn', 20)->nullable()->after('item_name');
            // The item's batch/spec note (e.g. a batch GUID + a regulatory line like "COMPOSTABLE ISO17088
            // NOTIFICATION 09/2025 5% GST") -- a real, separate Tally field (BASICUSERDESCRIPTION, confirmed
            // 2026-10-19), not the same as item_name.
            $table->text('spec')->nullable();
            $table->decimal('rate', 15, 4)->nullable()->after('unit');
            $table->string('pack_cases', 40)->nullable();
            $table->string('pack_gross_qty', 40)->nullable();
            $table->string('pack_net_wt_qty', 40)->nullable();
            $table->string('pack_qty_number', 120)->nullable();
            // Real fields, confirmed 2026-10-19 against the company's own "Finance Approval DIO" Tally
            // report -- voucher-level (one value per order, duplicated onto every line here the same way
            // status/party_name already are). financial_approval: Ok / Make and Hold / Partial Qty Ok.
            $table->string('financial_approval', 60)->nullable()->index();
            $table->string('remarks', 500)->nullable();
        });

        // order_no on the invoice header -- lets the Work Orders report compute an order-to-billing lead time
        // by joining straight on it, instead of re-deriving a link that already existed on the parsed voucher
        // and was only ever being discarded.
        Schema::table('tally_invoices', function (Blueprint $table) {
            $table->string('order_no', 60)->nullable()->index()->after('invoice_no');
        });
    }

    public function down(): void
    {
        Schema::table('tally_sale_orders', function (Blueprint $table) {
            $table->dropColumn(['item_name', 'hsn', 'rate', 'pack_cases', 'pack_gross_qty', 'pack_net_wt_qty', 'pack_qty_number']);
        });
        Schema::table('tally_invoices', function (Blueprint $table) {
            $table->dropColumn('order_no');
        });
    }
};
