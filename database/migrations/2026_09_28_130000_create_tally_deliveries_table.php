<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery Note vouchers from Tally's API (hrx:tally-push), one row per SKU line -- what's actually been
 * dispatched, matched back to the Sales Order it was raised against via order_no, wherever Tally recorded that
 * link (confirmed present on ~89% of Delivery Notes; the rest genuinely carry no order reference in Tally
 * itself). Paired with tally_sale_orders (ordered qty) to compute dispatched-vs-pending per order line.
 *
 * No dispatch/due DATE field exists anywhere in Tally for this company (checked directly on both Sales Order
 * and Delivery Note vouchers) -- delivery_date here is when the dispatch ACTUALLY happened, not a target/ETA.
 * transporter_name/vehicle_number/document_number are real fields Tally supports, but checked live and found
 * unfilled on every Delivery Note pulled so far -- shown when present, not fabricated when absent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_deliveries', function (Blueprint $table) {
            $table->id();
            $table->date('delivery_date')->index();
            $table->string('delivery_no', 60)->index();
            $table->string('order_no', 60)->nullable()->index();
            $table->string('party_name')->nullable();
            $table->string('normalized_name')->nullable()->index();
            $table->string('item_name', 500)->nullable();
            $table->decimal('quantity', 15, 3)->nullable();
            $table->string('unit', 20)->nullable();
            $table->string('transporter_name')->nullable();
            $table->string('vehicle_number', 40)->nullable();
            $table->string('transport_mode', 40)->nullable();
            $table->string('document_number', 60)->nullable();
            $table->date('document_date')->nullable();
            $table->decimal('distance_km', 10, 2)->nullable();
            $table->string('consignee_name')->nullable();
            $table->text('consignee_address')->nullable();
            $table->string('consignee_gstin', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_deliveries');
    }
};
