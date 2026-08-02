<?php

namespace App\Console\Commands;

use App\Models\SetuSalespersonMapping;
use App\Models\User;
use App\Services\SetuReportService;
use Illuminate\Console\Command;

/**
 * Reconciles each mapped salesperson's Sales Report book against Setu for
 * the current fiscal year, via SetuReportService::reconcileSalesperson() —
 * the same method the Sales Report page's self-service Sync button calls,
 * so the CLI and the web path can never disagree on what counts as a blank
 * vs a disagreement.
 */
class SyncSetuSalesCommand extends Command
{
    protected $signature = 'hrx:sync-setu-sales
        {--live : Write changes. Default is dry-run.}
        {--overwrite : Also correct exact-match figures that differ from Setu and adopt Setu spelling. Without it, only blank months are filled.}
        {--salesperson= : Tally name of a single salesperson to sync, e.g. "Navin Joshi"}
        {--fy= : Fiscal year to sync, e.g. "FY-2025-2026". Defaults to the current FY.}
        {--quarter= : Q1|Q2|Q3|Q4 — narrows --fy to one quarter. Default is the whole year.}
        {--from-month= : Custom range start, "YYYY-MM". Cannot be combined with --quarter.}
        {--to-month= : Custom range end, "YYYY-MM". Cannot be combined with --quarter.}';
    protected $description = 'Reconcile Sales Report figures against Setu for one or all mapped salespeople';

    public function handle(SetuReportService $setu): int
    {
        $live = (bool) $this->option('live');
        $overwrite = (bool) $this->option('overwrite');
        $only = $this->option('salesperson');
        $fy = $this->option('fy');
        $quarter = $this->option('quarter');
        $fromOpt = $this->option('from-month');
        $toOpt = $this->option('to-month');

        if ($fy && !in_array($fy, $setu->availableFiscalYears(), true)) {
            $this->error("Unknown fiscal year \"{$fy}\". Available: " . implode(', ', $setu->availableFiscalYears()));
            return Command::FAILURE;
        }
        if ($quarter && ($fromOpt || $toOpt)) {
            $this->error('Cannot combine --quarter with --from-month/--to-month — pick one way to specify the range.');
            return Command::FAILURE;
        }

        try {
            $period = $fromOpt && $toOpt ? 'custom' : ($quarter ?: 'whole');
            [$fyLabel, $fromMonth, $toMonth, $periodLabel] = $setu->resolvePeriod($fy, $period, $fromOpt, $toOpt);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return Command::FAILURE;
        }

        $query = SetuSalespersonMapping::where('status', SetuSalespersonMapping::STATUS_CONFIRMED)
            ->whereNotNull('user_id');
        if ($only) {
            $query->where('tally_name', $only);
        }
        $mappings = $query->orderBy('tally_name')->get();

        if ($mappings->isEmpty()) {
            $this->error($only
                ? "No confirmed mapping found for \"{$only}\". Run hrx:setu-mapping-report first, or check it wasn't left pending."
                : 'No confirmed salesperson mappings found. Run hrx:setu-mapping-report first.');
            return Command::FAILURE;
        }

        $this->info(($live ? '*** LIVE RUN ***' : '*** DRY RUN — nothing will be written ***'));
        $this->line("Period: {$periodLabel}");
        $this->line('Mode: ' . ($overwrite ? 'blank-fill + OVERWRITE exact-match figures & adopt Setu spelling' : 'blank-fill only (pass --overwrite to also correct existing figures)'));
        $this->line('Salespeople to process: ' . $mappings->count() . "\n");

        $totalFilled = 0; $totalOverwritten = 0; $totalRenamed = 0; $totalNew = 0; $totalReview = 0;

        foreach ($mappings as $mapping) {
            $user = User::find($mapping->user_id);
            if (!$user) {
                $this->warn("Skipping \"{$mapping->tally_name}\" — mapped user_id {$mapping->user_id} not found.");
                continue;
            }

            $result = $setu->reconcileSalesperson($user, $mapping->tally_name, $live, $fyLabel, $fromMonth, $toMonth, $overwrite);
            if ($result['error']) {
                $this->error("{$mapping->tally_name}: {$result['error']} — skipping.");
                continue;
            }
            ['blankFills' => $blankFills, 'overwrites' => $overwrites, 'renames' => $renames, 'newCustomers' => $newCustomers, 'reviewItems' => $reviewItems] = $result;

            $this->line("--- {$mapping->tally_name} (user #{$user->id} {$user->first_name} {$user->last_name}) ---");
            if ($blankFills) {
                $this->table(['Customer', 'Month', 'Fill with (Lakhs)'],
                    array_map(fn($f) => [$f['customer'], $f['month'], $f['amount']], $blankFills));
            }
            if ($overwrites) {
                $this->warn($overwrite
                    ? 'Overwrites (existing figure corrected to Setu):'
                    : 'Disagreements (existing figure differs from Setu — NOT overwritten; pass --overwrite to correct):');
                $this->table(['Customer', 'Month', 'HRX has', 'Setu says'],
                    array_map(fn($d) => [$d['customer'], $d['month'], $d['hrx'], $d['setu']], $overwrites));
            }
            if ($renames) {
                $this->warn($overwrite ? 'Renamed to Setu spelling:' : 'Spelling differs from Setu (NOT renamed; pass --overwrite):');
                $this->table(['From', 'To'], array_map(fn($r) => [$r['from'], $r['to']], $renames));
            }
            if ($newCustomers) {
                $this->warn($overwrite
                    ? 'New customers (no similar existing row — created under Setu name):'
                    : 'New customers (no similar existing row — would be created with --overwrite):');
                $this->table(['Setu Customer', sprintf('%s total (Lakhs)', $periodLabel)],
                    array_map(fn($n) => [$n['customer'], $n['total']], $newCustomers));
            }
            if ($reviewItems) {
                $this->warn('Need human review (a SIMILAR existing customer exists — resolve on the Setu Sales Sync page, NEVER auto-acted):');
                $this->table(['Setu Customer', sprintf('%s total', $periodLabel), 'Closest existing candidate (score)'],
                    array_map(function ($r) {
                        $best = $r['candidates'][0] ?? null;
                        return [$r['setuCustomer'], $r['total'], $best ? "{$best['party_name']} ({$best['score']}%)" : '— none —'];
                    }, $reviewItems));
            }
            if (!$blankFills && !$overwrites && !$renames && !$newCustomers && !$reviewItems) {
                $this->line('Nothing to reconcile — already in sync.');
            }
            $this->line('');

            $totalFilled += count($blankFills);
            $totalOverwritten += count($overwrites);
            $totalRenamed += count($renames);
            $totalNew += count($newCustomers);
            $totalReview += count($reviewItems);
        }

        $this->info(sprintf(
            "=== Summary: %d blank%s filled, %d figure%s %s, %d name%s %s, %d new customer%s %s, %d item%s need review ===",
            $totalFilled, $totalFilled === 1 ? '' : 's',
            $totalOverwritten, $totalOverwritten === 1 ? '' : 's', ($live && $overwrite) ? 'corrected' : 'differ',
            $totalRenamed, $totalRenamed === 1 ? '' : 's', ($live && $overwrite) ? 'standardised' : 'differ',
            $totalNew, $totalNew === 1 ? '' : 's', ($live && $overwrite) ? 'created' : 'to create',
            $totalReview, $totalReview === 1 ? '' : 's'
        ));
        if (!$live) {
            $this->warn('Dry run — re-run with --live' . ($overwrite ? ' --overwrite to write the fills + corrections + new customers above.' : ' to write the blank-fills above (add --overwrite to also correct figures and create new customers).'));
        }
        $this->warn('Review items (a similar existing customer exists) are never written by this command — resolve them on the Setu Sales Sync page.');

        return Command::SUCCESS;
    }
}
