<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock item master (inventory), a full-table-replace snapshot fed by hrx:tally-push-stock from Tally's StockItem
 * collection. One row per item: its group/category, base unit, opening & closing quantity/value/rate, and GST/HSN.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_stock_items', function (Blueprint $t) {
            $t->id();
            $t->string('name', 500)->index();
            $t->string('stock_group')->nullable()->index();      // Tally's Parent
            $t->string('category')->nullable()->index();
            $t->string('base_unit', 20)->nullable();
            $t->decimal('opening_qty', 15, 3)->nullable();
            $t->decimal('opening_value', 15, 2)->nullable();
            $t->decimal('closing_qty', 15, 3)->nullable();
            $t->string('closing_unit', 20)->nullable();
            $t->decimal('closing_value', 15, 2)->nullable();
            $t->decimal('closing_rate', 15, 4)->nullable();
            $t->string('closing_rate_unit', 20)->nullable();
            $t->string('hsn', 16)->nullable();
            $t->decimal('gst_rate', 6, 2)->nullable();
            $t->timestamp('synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_stock_items');
    }
};
