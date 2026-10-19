<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // alias/description/narration are plain Tally master fields -- real, but never wired into the sync
        // pipeline (hrx:tally-push-stock) until now, so production was always missing them even though they
        // were already being asked about ("item specs"). sku_code/colour/size/lamination/printing are this
        // company's own custom (UDF) fields, confirmed real via a live probe of two very different items
        // (a bag and an oil) on 2026-10-19 -- both exposed exactly these 5 custom fields and nothing else.
        Schema::table('tally_stock_items', function (Blueprint $table) {
            if (!Schema::hasColumn('tally_stock_items', 'item_alias')) {
                $table->string('item_alias', 255)->nullable()->after('name');
            }
            if (!Schema::hasColumn('tally_stock_items', 'description')) {
                $table->text('description')->nullable();
            }
            if (!Schema::hasColumn('tally_stock_items', 'narration')) {
                $table->text('narration')->nullable();
            }
            $table->string('sku_code', 60)->nullable();
            $table->string('colour', 60)->nullable();
            $table->string('size', 255)->nullable();
            $table->boolean('lamination')->nullable();
            $table->boolean('printing')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tally_stock_items', function (Blueprint $table) {
            $table->dropColumn(['sku_code', 'colour', 'size', 'lamination', 'printing']);
        });
    }
};
