<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * tally_sale_orders.remarks was varchar(500), which a real September voucher
 * (629 chars, a multi-line packing/dispatch note) already exceeds -- found
 * while re-syncing September to chase a small Setu-vs-local total mismatch;
 * the push failed outright with a 422 rather than silently truncating.
 * TEXT removes the ceiling instead of picking another number that's still
 * just a guess at Tally's own limit (none is enforced there).
 */
return new class extends Migration
{
    public function up(): void
    {
        // No doctrine/dbal in this app, so Schema::change() isn't available -- straight to SQL,
        // matching every other column-type alteration already in this codebase.
        DB::statement('ALTER TABLE tally_sale_orders MODIFY remarks TEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tally_sale_orders MODIFY remarks VARCHAR(500) NULL');
    }
};
