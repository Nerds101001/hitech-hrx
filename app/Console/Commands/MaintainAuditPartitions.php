<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the monthly partitions on `audits` rolling.
 *
 * Two jobs, both of which have to happen on a schedule or the partitioning
 * quietly stops being partitioning:
 *
 *   ROLL FORWARD -- the migration created twelve months ahead. When those run
 *   out every new row lands in the pmax catch-all, which grows without bound
 *   and can never be dropped. This reorganises pmax into real months, always
 *   keeping a buffer ahead of today.
 *
 *   PRUNE -- drop whole months past the retention horizon. This is the reason
 *   to partition at all: DROP PARTITION returns the disk immediately and takes
 *   no row locks, where DELETE over a few million rows holds the table, fills
 *   the undo log, and leaves the file exactly as big as it was.
 *
 * Dry run by default. Nothing is dropped without --force, because dropping a
 * partition is not recoverable from inside the database.
 */
class MaintainAuditPartitions extends Command
{
    protected $signature = 'audits:partitions
                            {--months-ahead=12 : Keep this many future months ready}
                            {--retain= : Drop months older than this many months (default: config audit.retain_months)}
                            {--allow-nonempty : Permit dropping a month that still holds rows}
                            {--force : Actually apply the changes}';

    protected $description = 'Roll the audits table partitions forward and prune ones past retention';

    private const TABLE = 'audits';

    public function handle(): int
    {
        if (!$this->isPartitioned()) {
            $this->error('`' . self::TABLE . '` is not partitioned. Run the migrations first.');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $this->line($force ? '<fg=yellow>APPLYING</> changes' : 'DRY RUN - nothing will be changed (pass --force)');
        $this->newLine();

        $this->rollForward($force);
        $this->newLine();
        $this->prune($force);

        return self::SUCCESS;
    }

    /** Turn the pmax catch-all into real months, keeping a buffer ahead. */
    private function rollForward(bool $force): void
    {
        $ahead = max(1, (int) $this->option('months-ahead'));
        $target = Carbon::now()->startOfMonth()->addMonths($ahead);

        $existing = $this->monthPartitions();
        $last = $existing->keys()->max();

        if ($last === null) {
            $this->warn('  no month partitions found; nothing to roll forward');

            return;
        }

        $cursor = Carbon::createFromFormat('Ym', (string) $last)->startOfMonth()->addMonth();
        $missing = [];
        while ($cursor->lte($target)) {
            $missing[] = $cursor->copy();
            $cursor->addMonth();
        }

        $this->info('Roll forward');
        $this->line(sprintf('  last month partition : p%s', $last));
        $this->line(sprintf('  want partitions up to: p%s', $target->format('Ym')));

        if ($missing === []) {
            $this->line('  <fg=green>already covered - nothing to add</>');

            return;
        }

        // Reorganise pmax into the missing months plus a fresh pmax. pmax is
        // empty in normal operation, so this rewrites almost nothing.
        $defs = [];
        foreach ($missing as $month) {
            $defs[] = sprintf(
                "PARTITION `p%s` VALUES LESS THAN ('%s')",
                $month->format('Ym'),
                $month->copy()->addMonth()->format('Y-m-d H:i:s')
            );
        }
        $defs[] = 'PARTITION `pmax` VALUES LESS THAN (MAXVALUE)';

        $sql = 'ALTER TABLE `' . self::TABLE . '` REORGANIZE PARTITION `pmax` INTO (' . implode(', ', $defs) . ')';

        $this->line(sprintf('  adding %d month(s): %s', count($missing), collect($missing)->map->format('Ym')->implode(', ')));

        if (!$force) {
            return;
        }

        DB::statement($sql);
        $this->line('  <fg=green>added</>');
    }

    /** Drop whole months that are past the retention horizon. */
    private function prune(bool $force): void
    {
        $retain = (int) ($this->option('retain') ?? config('audit.retain_months', 24));

        $this->info('Prune');

        if ($retain <= 0) {
            $this->line('  retention disabled (retain <= 0) - keeping everything');

            return;
        }

        $cutoff = Carbon::now()->startOfMonth()->subMonths($retain);
        $this->line(sprintf('  retaining %d months - dropping anything before %s', $retain, $cutoff->format('Y-m')));

        $stale = $this->monthPartitions()
            ->filter(fn ($rows, $ym) => Carbon::createFromFormat('Ym', (string) $ym)->startOfMonth()->lt($cutoff));

        if ($stale->isEmpty()) {
            $this->line('  <fg=green>nothing past retention</>');

            return;
        }

        foreach ($stale as $ym => $rows) {
            $this->line(sprintf('  p%s  %s rows', $ym, number_format($rows)));
        }
        $this->line(sprintf('  total: %s rows in %d partition(s)', number_format($stale->sum()), $stale->count()));

        if (!$force) {
            $this->warn('  not dropped - re-run with --force');

            return;
        }

        foreach ($stale->keys() as $ym) {
            // information_schema row counts are estimates, so confirm the month
            // is genuinely empty before destroying it. hrx:archive-audits is
            // what moves aged rows into audits_archive; if it has not run, or
            // fell behind, those rows are still the only copy and dropping the
            // partition would lose them outright. Emptiness is the proof that
            // archiving already happened.
            $exact = DB::selectOne(
                'SELECT COUNT(*) c FROM `' . self::TABLE . '` PARTITION (`p' . $ym . '`)'
            )->c;

            if ($exact > 0 && !$this->option('allow-nonempty')) {
                $this->warn(sprintf(
                    '  p%s still holds %s row(s) - skipped. Archive them first '
                    . '(hrx:archive-audits --live), or pass --allow-nonempty to discard them.',
                    $ym,
                    number_format($exact)
                ));
                continue;
            }

            DB::statement('ALTER TABLE `' . self::TABLE . '` DROP PARTITION `p' . $ym . '`');
            $this->line("  <fg=red>dropped</> p{$ym}" . ($exact > 0 ? " ({$exact} rows discarded)" : ' (empty)'));
        }
    }

    /** Month partitions as ym => approximate row count, oldest first. */
    private function monthPartitions(): \Illuminate\Support\Collection
    {
        return collect(DB::select(
            'SELECT PARTITION_NAME name, TABLE_ROWS rows_est
             FROM information_schema.PARTITIONS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND PARTITION_NAME IS NOT NULL
             ORDER BY PARTITION_ORDINAL_POSITION',
            [self::TABLE]
        ))
            ->filter(fn ($p) => preg_match('/^p\d{6}$/', $p->name))
            ->mapWithKeys(fn ($p) => [substr($p->name, 1) => (int) $p->rows_est]);
    }

    private function isPartitioned(): bool
    {
        return (bool) DB::selectOne(
            'SELECT COUNT(*) c FROM information_schema.PARTITIONS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND PARTITION_NAME IS NOT NULL',
            [self::TABLE]
        )->c;
    }
}
