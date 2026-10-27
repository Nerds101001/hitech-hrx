<?php

namespace App\Console\Commands;

use App\Models\SalesGroupMapping;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Classifies every distinct tally_ledgers.ledger_group value (company=hitech)
 * against HRX's Sales roster, storing the result in sales_group_mappings for
 * review/correction.
 *
 * Originally sourced from tally_invoices.salesperson instead (Tally's own
 * per-voucher Cost Centre tag), on the theory that ledger_group was too
 * noisy a proxy. That theory held up for classification purposes, but
 * tally_invoices.salesperson turned out to be blank on ~100% of invoices in
 * several months (confirmed 2026-10-26 across a full Apr-Oct resync) — the
 * Trend/Tally Explorer reconciliation this whole feature is meant to match
 * has used tally_ledgers.ledger_group all along and proved accurate to the
 * rupee against Setu's own exports, so switched back to it here too.
 *
 * Mirrors SetuMappingReportCommand's classification rules exactly (same
 * noise/location/brand exclusion lists, same confirm/suggest thresholds) so
 * the two features behave consistently.
 */
class SalesGroupMappingSyncCommand extends Command
{
    protected $signature = 'hrx:sales-group-mapping-sync';
    protected $description = 'Classify Tally invoice salesperson tags against HRX users and store the reviewable mapping';

    private const NOISE_PATTERNS = [
        '/^list-group-item$/i',
        '/advances against assets/i',
        '/debtors/i',
        '/creditors/i',
        '/payable/i',
        '/msme/i',
        '/not defined/i',
        '/^office$/i',
        '/^others?$/i',
        '/supplier/i',
        '/transporter/i',
        '/traders/i',
        '/import (services|suppliers)/i',
        '/export party/i',
        '/bank/i',
        '/cash/i',
        '/expenses/i',
        '/duties.*taxes/i',
        '/provisions/i',
        '/loans/i',
        '/fixed assets/i',
        '/capital account/i',
        '/current (assets|liabilities)/i',
        '/indirect (incomes|expenses)/i',
        '/direct (incomes|expenses)/i',
        '/stock-in-hand/i',
        '/secured loans/i',
        '/unsecured loans/i',
        '/investments/i',
        '/branch.*divisions/i',
        '/^branch transfer$/i',
        '/misc.*expenses/i',
        '/suspense/i',
    ];

    private const KNOWN_LOCATIONS = [
        'agra', 'aurangabaad', 'chennai', 'delhi ncr', 'haridwar', 'hyderabaad',
        'jamshedpur', 'madhya pardesh', 'maharashtra', 'noida', 'punjab',
        'rajasthan', 'vadodara',
    ];

    private const KNOWN_NON_STAFF_COMPANIES = ['bajaj electrical', 'maruti suzuki'];

    private const CONFIRM_THRESHOLD = 85.0;
    private const SUGGEST_THRESHOLD = 55.0;

    public function handle(): int
    {
        $names = DB::table('tally_ledgers')
            ->where('company', 'hitech')
            ->whereNotNull('ledger_group')->where('ledger_group', '!=', '')
            ->distinct()->orderBy('ledger_group')->pluck('ledger_group');
        $this->info('Distinct Tally ledger groups to classify: ' . $names->count());

        $brands = ['rust-x', 'dr. bio', 'fillezy', 'kif', 'zorbit', 'tuffpaulin', 'neutrasafe',
                   'keep-it-fresh', 'keepitfresh', 'hi tech -kif', 'tuffpaulin office'];

        $roster = User::where('status', 'active')
            ->notTerminated()
            ->whereHas('department', fn($q) => $q->where('name', 'like', '%Sales%'))
            ->get(['id', 'first_name', 'last_name']);
        $alsoUsed = User::whereIn('id', DB::table('sales_pipelines')->whereNotNull('salesperson_id')->distinct()->pluck('salesperson_id'))
            ->whereNotIn('id', $roster->pluck('id'))
            ->get(['id', 'first_name', 'last_name']);
        $roster = $roster->concat($alsoUsed);

        $this->line("HRX candidate roster: {$roster->count()} people\n");

        $rosterNorm = $roster->map(fn($u) => [
            'id' => $u->id,
            'name' => trim($u->first_name . ' ' . $u->last_name),
            'norm' => self::normalize(trim($u->first_name . ' ' . $u->last_name)),
        ]);

        $rows = [];
        $counts = ['excluded' => 0, 'confirmed' => 0, 'pending' => 0, 'kept' => 0];

        foreach ($names as $name) {
            // Never touch a row a human already reviewed (confirmed/excluded)
            // — this command only classifies NEW groups or re-suggests
            // still-pending ones, exactly like the Setu version's intent
            // (updateOrCreate on every run, but confirmed/excluded rows just
            // get overwritten with the identical values they already hold).
            $existing = SalesGroupMapping::where('tally_salesperson', $name)->first();
            if ($existing && $existing->status !== SalesGroupMapping::STATUS_PENDING) {
                $counts['kept']++;
                continue;
            }

            $lower = mb_strtolower($name);
            $excludeReason = null;
            foreach (self::NOISE_PATTERNS as $p) { if (preg_match($p, $lower)) { $excludeReason = 'ledger bucket'; break; } }
            if (!$excludeReason && in_array($lower, self::KNOWN_LOCATIONS, true)) $excludeReason = 'city/state territory';
            if (!$excludeReason && in_array($lower, $brands, true)) $excludeReason = 'brand name';
            if (!$excludeReason && in_array($lower, self::KNOWN_NON_STAFF_COMPANIES, true)) $excludeReason = 'known company, not HRX staff';

            if ($excludeReason) {
                SalesGroupMapping::updateOrCreate(
                    ['tally_salesperson' => $name],
                    ['user_id' => null, 'status' => SalesGroupMapping::STATUS_EXCLUDED, 'note' => $excludeReason, 'tenant_id' => null]
                );
                $rows[] = [$name, '(excluded)', '-', $excludeReason];
                $counts['excluded']++;
                continue;
            }

            $needle = self::normalize($name);
            $scored = $rosterNorm->map(function ($r) use ($needle) {
                similar_text($needle, $r['norm'], $pct);
                return $r + ['score' => $pct];
            })->sortByDesc('score')->values();

            $top = $scored->first();
            $second = $scored->get(1);
            $ambiguous = $top && $second && ($second['score'] >= $top['score'] - 8) && $second['score'] >= self::SUGGEST_THRESHOLD;

            if ($top && $top['score'] >= self::CONFIRM_THRESHOLD && !$ambiguous) {
                SalesGroupMapping::updateOrCreate(
                    ['tally_salesperson' => $name],
                    ['user_id' => $top['id'], 'status' => SalesGroupMapping::STATUS_CONFIRMED,
                     'note' => sprintf('auto-confirmed, %.0f%% match', $top['score']), 'tenant_id' => null]
                );
                $rows[] = [$name, $top['name'], sprintf('%.0f%%', $top['score']), 'confirmed'];
                $counts['confirmed']++;
                continue;
            }

            $note = $top && $top['score'] >= self::SUGGEST_THRESHOLD
                ? sprintf('best guess only — needs manual confirmation%s', $ambiguous ? "; also close: {$second['name']} ({$second['score']}%)" : '')
                : 'no strong candidate — needs manual identification';
            $suggestedId = $top && $top['score'] >= self::SUGGEST_THRESHOLD ? $top['id'] : null;

            SalesGroupMapping::updateOrCreate(
                ['tally_salesperson' => $name],
                ['user_id' => $suggestedId, 'status' => SalesGroupMapping::STATUS_PENDING, 'note' => $note, 'tenant_id' => null]
            );
            $rows[] = [$name, $top && $top['score'] >= self::SUGGEST_THRESHOLD ? $top['name'] . ' ?' : '(none)',
                       $top && $top['score'] >= self::SUGGEST_THRESHOLD ? sprintf('%.0f%%', $top['score']) : '-', 'PENDING REVIEW'];
            $counts['pending']++;
        }

        if ($rows) {
            $this->table(['Salesperson tag', 'Best HRX match', 'Score', 'Status'], $rows);
        }
        $this->info(sprintf(
            "\nExcluded: %d   Auto-confirmed: %d   Needs review: %d   Already reviewed (untouched): %d",
            $counts['excluded'], $counts['confirmed'], $counts['pending'], $counts['kept']
        ));
        $this->warn('Review the PENDING rows at /sales-group-mappings before relying on them for Tally Sales Sync.');

        return Command::SUCCESS;
    }

    private static function normalize(string $s): string
    {
        $s = mb_strtoupper($s);
        return preg_replace('/[^A-Z]/', '', $s);
    }
}
