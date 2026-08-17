<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How often a customer places an order — Monthly / Quarterly / Semi-Annually
 * / Annually. Plain nullable string rather than a DB enum, matching type/
 * product/status_stage elsewhere on this table: the dropdown options live in
 * the Blade view, so adding a new interval later never needs a migration.
 * Left blank by default ("keep it open") until someone on the Sales Report
 * actually classifies that customer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_pipelines', function (Blueprint $table) {
            $table->string('order_interval')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('sales_pipelines', function (Blueprint $table) {
            $table->dropColumn('order_interval');
        });
    }
};
