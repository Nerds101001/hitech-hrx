<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite indexes for the Work Orders item-line queries (panelData()/financialData() in
 * WorkOrdersController): each of these tables already had single-column indexes on the individual
 * filter columns, but the actual queries filter/group on PAIRS of them, which MySQL can only use one
 * of without a matching composite index -- confirmed slow (907ms-4.7s per request) before this.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tally_sale_orders', function (Blueprint $table) {
            if (!$this->indexExists('tally_sale_orders', 'tally_sale_orders_company_order_date_index')) {
                $table->index(['company', 'order_date'], 'tally_sale_orders_company_order_date_index');
            }
        });

        Schema::table('tally_ledgers', function (Blueprint $table) {
            if (!$this->indexExists('tally_ledgers', 'tally_ledgers_company_normalized_name_index')) {
                $table->index(['company', 'normalized_name'], 'tally_ledgers_company_normalized_name_index');
            }
        });

        Schema::table('tally_deliveries', function (Blueprint $table) {
            if (!$this->indexExists('tally_deliveries', 'tally_deliveries_order_no_item_name_index')) {
                $table->index(['order_no', 'item_name'], 'tally_deliveries_order_no_item_name_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tally_sale_orders', function (Blueprint $table) {
            $table->dropIndex('tally_sale_orders_company_order_date_index');
        });
        Schema::table('tally_ledgers', function (Blueprint $table) {
            $table->dropIndex('tally_ledgers_company_normalized_name_index');
        });
        Schema::table('tally_deliveries', function (Blueprint $table) {
            $table->dropIndex('tally_deliveries_order_no_item_name_index');
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $conn = Schema::getConnection();
        $db = $conn->getDatabaseName();
        return $conn->selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$db, $table, $index]
        )->c > 0;
    }
};
