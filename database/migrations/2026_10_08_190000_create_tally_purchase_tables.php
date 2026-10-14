<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase-side mirrors of the sales tables, fed by the same Day Book push (hrx:tally-push now also maps Purchase,
 * Purchase Order vouchers). Purchase vouchers carry the same inventory+GST detail a sales invoice does, just on the
 * supplier side. Like the sales line tables these have no unique key -- the push does delete-by-key-then-insert
 * scoped to the keys in each day's payload.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Purchase voucher header (one row per purchase bill).
        Schema::create('tally_purchases', function (Blueprint $t) {
            $t->id();
            $t->string('purchase_key', 80)->index();         // the voucher GUID -- supplier bill numbers aren't unique
            $t->string('purchase_no', 60)->index();
            $t->string('series', 20)->nullable()->index();
            $t->date('purchase_date')->nullable()->index();
            $t->string('party_name')->nullable();            // the supplier
            $t->string('normalized_name')->nullable()->index();
            $t->decimal('amount', 15, 2)->default(0);
            $t->json('extra')->nullable();                   // party_gstin, place_of_supply, reference
        });

        // Purchase voucher SKU lines (one row per item line, with HSN + GST like sale_lines).
        Schema::create('tally_purchase_lines', function (Blueprint $t) {
            $t->id();
            $t->string('purchase_key', 80)->index();         // FK to tally_purchases.purchase_key (the voucher GUID)
            $t->string('purchase_no', 60)->index();
            $t->string('series', 20)->nullable();
            $t->date('purchase_date')->nullable()->index();
            $t->string('party_name')->nullable();
            $t->string('normalized_name')->nullable()->index();
            $t->string('item_name', 500)->nullable()->index();
            $t->string('product_group', 60)->nullable()->index();
            $t->string('hsn', 16)->nullable();
            $t->decimal('gst_rate', 6, 2)->nullable();
            $t->decimal('quantity', 15, 3)->nullable();
            $t->string('unit', 20)->nullable();
            $t->decimal('rate', 15, 4)->nullable();
            $t->string('rate_unit', 20)->nullable();
            $t->decimal('taxable_amount', 15, 2)->default(0);
            $t->decimal('tax_amount', 15, 2)->nullable();
        });

        // Open purchase orders (one row per order line), mirror of tally_sale_orders.
        Schema::create('tally_purchase_orders', function (Blueprint $t) {
            $t->id();
            $t->string('order_key', 80)->index();            // the voucher GUID -- so re-pushes replace per-voucher, not per-number
            $t->date('order_date')->nullable()->index();
            $t->string('order_no', 60)->nullable()->index();
            $t->string('party_name')->nullable();            // the supplier
            $t->string('normalized_name')->nullable()->index();
            $t->string('item_name', 500)->nullable();
            $t->decimal('quantity', 15, 3)->nullable();
            $t->string('unit', 20)->nullable();
            $t->decimal('amount', 15, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_purchase_lines');
        Schema::dropIfExists('tally_purchases');
        Schema::dropIfExists('tally_purchase_orders');
    }
};
