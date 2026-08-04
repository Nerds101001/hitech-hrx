<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cold storage for old audit rows.
 *
 * `audits` is the busiest table in the system (every model write appends to it)
 * and it only ever grows. Rather than partition it — which in MySQL means
 * rebuilding the table with a composite primary key, a locking DDL on the one
 * table every write depends on — rows simply move here once they age out.
 *
 * The archive keeps the identical structure and indexes (CREATE TABLE ... LIKE),
 * so a row can be read, or moved back, without any transformation.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audits_archive')) {
            return;
        }

        // LIKE copies columns, types and indexes exactly — safer than
        // hand-redeclaring 14 columns that must stay in step with `audits`.
        DB::statement('CREATE TABLE `audits_archive` LIKE `audits`');
    }

    public function down(): void
    {
        Schema::dropIfExists('audits_archive');
    }
};
