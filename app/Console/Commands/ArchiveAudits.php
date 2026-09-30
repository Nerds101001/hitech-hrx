<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Move aged audit rows out of `audits` into `audits_archive`.
 *
 * Why this and not partitioning: MySQL requires the partition key to be part of
 * every unique key, so partitioning `audits` by date means rebuilding it with a
 * composite primary key — a locking DDL on the table every single write touches.
 * Moving rows achieves the same goal (keep the hot table small) with plain
 * INSERT/DELETE inside small transactions, no schema change, and it is fully
 * reversible: the archive is structurally identical, so rows can be moved back.
 *
 * Nothing is lost — this is a relocation, not a purge. Compliance history stays
 * queryable in `audits_archive`.
 *
 *   php artisan hrx:archive-audits                 # dry run, 12 months
 *   php artisan hrx:archive-audits --months=6      # dry run, 6 months
 *   php artisan hrx:archive-audits --live          # actually move
 */
class ArchiveAudits extends Command
{
    protected $signature = 'hrx:archive-audits
                            {--months=12 : Archive rows older than this many months}
                            {--chunk=1000 : Rows moved per transaction}
                            {--live : Perform the move (default is a dry run)}';

    protected $description = 'Move audit rows older than N months into audits_archive.';

    public function handle(): int
    {
        $months = max(1, (int) $this->option('months'));
        $chunk  = max(100, (int) $this->option('chunk'));
        $live   = (bool) $this->option('live');

        if (!Schema::hasTable('audits_archive')) {
            $this->error('audits_archive does not exist — run the migration first.');
            return Command::FAILURE;
        }

        $cutoff = now()->subMonths($months)->startOfDay();

        $total   = DB::table('audits')->count();
        $movable = DB::table('audits')->where('created_at', '<', $cutoff)->count();

        $this->info(($live ? '*** LIVE ***' : '*** DRY RUN — nothing will move ***'));
        $this->line("cutoff        : {$cutoff} (older than {$months} months)");
        $this->line("audits total  : " . number_format($total));
        $this->line("to archive    : " . number_format($movable));
        $this->line("will remain   : " . number_format($total - $movable));

        if ($movable === 0) {
            $this->info('Nothing old enough to archive.');
            return Command::SUCCESS;
        }

        if (!$live) {
            $this->warn('Re-run with --live to perform the move.');
            return Command::SUCCESS;
        }

        $moved = 0;
        $bar = $this->output->createProgressBar($movable);

        while (true) {
            // Take the ids first: selecting and deleting by explicit id keeps the
            // transaction small and makes a partial failure safe to re-run.
            $ids = DB::table('audits')
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($chunk)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            try {
                DB::transaction(function () use ($ids, $cutoff, &$moved) {
                    // The created_at bound is redundant for correctness --
                    // every one of these ids was selected as older than the
                    // cutoff -- but `audits` is partitioned by month on
                    // created_at, and an id-only predicate cannot prune, so
                    // without it both statements visit every partition.
                    $rows = DB::table('audits')->whereIn('id', $ids)
                        ->where('created_at', '<', $cutoff)->get()
                        ->map(fn ($r) => (array) $r)->all();

                    // insertOrIgnore: a re-run after a partial failure must not
                    // trip the primary key on rows that already made it across.
                    DB::table('audits_archive')->insertOrIgnore($rows);
                    DB::table('audits')->whereIn('id', $ids)
                        ->where('created_at', '<', $cutoff)->delete();
                    $moved += count($rows);
                });
            } catch (\Throwable $e) {
                $bar->finish();
                $this->newLine(2);
                $this->error('Archive failed after ' . number_format($moved) . ' rows: ' . $e->getMessage());
                Log::error('hrx:archive-audits failed', ['moved' => $moved, 'error' => $e->getMessage()]);
                return Command::FAILURE;
            }

            $bar->advance($ids->count());
        }

        $bar->finish();
        $this->newLine(2);

        $remaining = DB::table('audits')->count();
        $archived  = DB::table('audits_archive')->count();

        $this->info('Archived ' . number_format($moved) . ' rows.');
        $this->line('audits now       : ' . number_format($remaining));
        $this->line('audits_archive   : ' . number_format($archived));

        Log::info('hrx:archive-audits completed', [
            'moved' => $moved, 'cutoff' => (string) $cutoff,
            'audits_remaining' => $remaining, 'archive_total' => $archived,
        ]);

        return Command::SUCCESS;
    }
}
