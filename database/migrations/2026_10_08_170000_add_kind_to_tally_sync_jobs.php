<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The jobs queue started life carrying only date-range backfills. The Tally Push admin page now also queues
 * "refresh outstanding" and "refresh customer ledgers" from the same card, and those have no date range -- so a
 * 'kind' discriminator is added and from_date/to_date become nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tally_sync_jobs', function (Blueprint $t) {
            $t->string('kind', 16)->default('backfill')->after('status')->index();
        });
        // from_date/to_date only apply to a backfill; a refresh job leaves them null.
        DB::statement('ALTER TABLE tally_sync_jobs MODIFY from_date DATE NULL, MODIFY to_date DATE NULL');
    }

    public function down(): void
    {
        Schema::table('tally_sync_jobs', function (Blueprint $t) {
            $t->dropColumn('kind');
        });
    }
};
