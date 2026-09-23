<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prepares `audits` to be the system-wide activity log.
 *
 * Today the table carries ~84k rows from a handful of audited models. Once
 * every business model is audited the working estimate is 250 users x ~100
 * actions, so roughly 25k rows a day -- about 9M a year, a hundredfold
 * increase. Two things have to change before that is switched on.
 *
 * 1. INDEXES. The table has (auditable_type, auditable_id) and
 *    (user_id, user_type) and nothing on created_at. Every question anyone
 *    actually asks of an activity log is time-bounded -- "what did this
 *    person do on the 18th", "what happened to this record last week" -- and
 *    each one currently ends in a full scan plus a filesort. Three composite
 *    indexes, each leading with the thing being filtered and ending in
 *    created_at, turn all of them into range scans.
 *
 * 2. PARTITIONING. Monthly RANGE partitions on created_at. The win is not
 *    raw lookup speed (the indexes do that) -- it is that retention becomes
 *    DROP PARTITION, which is instant and reclaims the disk, instead of a
 *    DELETE over millions of rows that locks the table, bloats the undo log
 *    and leaves the file just as large. Date-bounded queries also prune to
 *    the months they touch.
 *
 * Partitioning requires the partition key in every unique key, so the primary
 * key becomes (id, created_at). `id` stays first and stays AUTO_INCREMENT, so
 * Eloquent keeps treating it as the key and nothing in the application
 * changes. created_at becomes DATETIME NOT NULL: MySQL will not RANGE COLUMNS
 * a TIMESTAMP, and DATETIME sidesteps the 2038 ceiling a log meant to be kept
 * for years would otherwise inherit.
 *
 * Doing this now is deliberate -- at 84k rows each ALTER is seconds. The same
 * work at 9M rows is an outage.
 */
return new class extends Migration
{
    /** Months of partitions created ahead of today, so nothing lands in pmax. */
    private const MONTHS_AHEAD = 12;

    public function up(): void
    {
        // A NULL partition key sorts into the first partition, which would
        // quietly mix undated rows in with February's. There are none today;
        // this is here so the NOT NULL below cannot fail on live data.
        DB::table('audits')->whereNull('created_at')->update([
            'created_at' => DB::raw('COALESCE(updated_at, NOW())'),
        ]);

        // TIMESTAMP -> DATETIME NOT NULL. Same stored value, no timezone
        // re-interpretation, and RANGE COLUMNS will accept it.
        DB::statement('ALTER TABLE `audits` MODIFY `created_at` DATETIME NOT NULL');

        // Drop and re-add in ONE statement: `id` is AUTO_INCREMENT and MySQL
        // refuses to leave an auto column keyless, even momentarily.
        DB::statement('ALTER TABLE `audits` DROP PRIMARY KEY, ADD PRIMARY KEY (`id`, `created_at`)');

        $this->addIndexes();
        $this->indexArchive();
        $this->partition();
    }

    private function addIndexes(): void
    {
        $existing = collect(DB::select('SHOW INDEX FROM `audits`'))->pluck('Key_name')->unique();

        $wanted = [
            // "what did this person do, when" -- the audit screen's main query.
            'audits_user_created_index' => '(`user_id`, `created_at`)',
            // "what happened to this record" -- the per-record history panel.
            'audits_auditable_created_index' => '(`auditable_type`, `auditable_id`, `created_at`)',
            // "everything on this date" / retention sweeps.
            'audits_created_index' => '(`created_at`)',
        ];

        foreach ($wanted as $name => $columns) {
            if ($existing->contains($name)) {
                continue;
            }
            DB::statement("ALTER TABLE `audits` ADD INDEX `{$name}` {$columns}");
        }

        // The two original indexes are now dead weight, and every one of them
        // is a B-tree that has to be written on all ~25k inserts a day.
        //
        //   (auditable_type, auditable_id) is a strict prefix of the new
        //   (auditable_type, auditable_id, created_at), so every lookup it
        //   served is served identically by the longer index.
        //
        //   (user_id, user_type) is beaten by (user_id, created_at) for the
        //   query people actually run. user_type is polymorphic but in
        //   practice single-valued, so it discriminates nothing -- the
        //   optimiser was picking this index and then filesorting by date.
        foreach (['audits_auditable_type_auditable_id_index', 'audits_user_id_user_type_index'] as $stale) {
            if ($existing->contains($stale)) {
                DB::statement("ALTER TABLE `audits` DROP INDEX `{$stale}`");
            }
        }
    }

    /**
     * audits_archive is where hrx:archive-audits parks rows older than twelve
     * months, and it inherited the same two indexes -- neither of which covers
     * created_at. It is empty today only because nothing is old enough yet;
     * once archiving starts it becomes the table people query for history, and
     * every one of those questions is date-bounded. Indexing it while it costs
     * nothing beats indexing it once it is the larger of the two.
     *
     * Not partitioned: it is the cold copy, written in monthly batches and
     * rarely read, so the added operational surface would not pay for itself.
     */
    private function indexArchive(): void
    {
        if (!Schema::hasTable('audits_archive')) {
            return;
        }

        $existing = collect(DB::select('SHOW INDEX FROM `audits_archive`'))->pluck('Key_name')->unique();

        $wanted = [
            'audits_archive_created_index' => '(`created_at`)',
            'audits_archive_user_created_index' => '(`user_id`, `created_at`)',
        ];

        foreach ($wanted as $name => $columns) {
            if (!$existing->contains($name)) {
                DB::statement("ALTER TABLE `audits_archive` ADD INDEX `{$name}` {$columns}");
            }
        }
    }

    private function partition(): void
    {
        if ($this->isPartitioned()) {
            return;
        }

        $oldest = DB::table('audits')->min('created_at');
        $start = $oldest
            ? \Carbon\Carbon::parse($oldest)->startOfMonth()
            : \Carbon\Carbon::now()->startOfMonth();
        $end = \Carbon\Carbon::now()->startOfMonth()->addMonths(self::MONTHS_AHEAD);

        // RANGE COLUMNS is "less than", so each partition is named for the
        // month it holds and bounded by the first instant of the next one.
        $parts = [];
        for ($m = $start->copy(); $m->lte($end); $m->addMonth()) {
            $parts[] = sprintf(
                "PARTITION `p%s` VALUES LESS THAN ('%s')",
                $m->format('Ym'),
                $m->copy()->addMonth()->format('Y-m-d H:i:s')
            );
        }
        // Catch-all so a row can never be rejected for having no home. The
        // maintenance command reorganises this into real months before it
        // ever holds anything.
        $parts[] = 'PARTITION `pmax` VALUES LESS THAN (MAXVALUE)';

        DB::statement(
            'ALTER TABLE `audits` PARTITION BY RANGE COLUMNS(`created_at`) (' . implode(', ', $parts) . ')'
        );
    }

    private function isPartitioned(): bool
    {
        return (bool) DB::selectOne(
            'SELECT COUNT(*) c FROM information_schema.PARTITIONS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND PARTITION_NAME IS NOT NULL',
            ['audits']
        )->c;
    }

    public function down(): void
    {
        if ($this->isPartitioned()) {
            DB::statement('ALTER TABLE `audits` REMOVE PARTITIONING');
        }

        foreach ([
            'audits_user_created_index',
            'audits_auditable_created_index',
            'audits_created_index',
        ] as $index) {
            if (collect(DB::select('SHOW INDEX FROM `audits`'))->pluck('Key_name')->contains($index)) {
                DB::statement("ALTER TABLE `audits` DROP INDEX `{$index}`");
            }
        }

        if (Schema::hasTable('audits_archive')) {
            $archIdx = collect(DB::select('SHOW INDEX FROM `audits_archive`'))->pluck('Key_name')->unique();
            foreach (['audits_archive_created_index', 'audits_archive_user_created_index'] as $i) {
                if ($archIdx->contains($i)) {
                    DB::statement("ALTER TABLE `audits_archive` DROP INDEX `{$i}`");
                }
            }
        }

        // Restore the originals only if this migration is what removed them --
        // re-adding an index that is still there aborts the whole rollback.
        $present = collect(DB::select('SHOW INDEX FROM `audits`'))->pluck('Key_name')->unique();
        foreach ([
            'audits_auditable_type_auditable_id_index' => '(`auditable_type`, `auditable_id`)',
            'audits_user_id_user_type_index' => '(`user_id`, `user_type`)',
        ] as $name => $columns) {
            if (!$present->contains($name)) {
                DB::statement("ALTER TABLE `audits` ADD INDEX `{$name}` {$columns}");
            }
        }

        DB::statement('ALTER TABLE `audits` DROP PRIMARY KEY, ADD PRIMARY KEY (`id`)');
        DB::statement('ALTER TABLE `audits` MODIFY `created_at` TIMESTAMP NULL DEFAULT NULL');
    }
};
