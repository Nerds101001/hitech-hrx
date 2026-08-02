<?php

namespace App\Console\Commands;

use App\Models\SetuSalespersonMapping;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Classifies a Tally salesperson-ledger export (free text, one name per
 * line) against HRX's real Sales-department roster, and stores the result
 * in setu_salesperson_mappings for review/correction.
 *
 * Tally's ledger mixes real salespeople with accounting buckets (Advances
 * Against Assets, Non-Msme, ...), city/state territory names, and brand
 * names — none of which are people and must never resolve to a user. Of the
 * genuine name candidates, common surnames (Singh/Sharma/Kumar) collide
 * between two or three different HRX employees and can't be resolved with
 * confidence automatically — those are left `pending` for a human to pick.
 */
class SetuMappingReportCommand extends Command
{
    protected $signature = 'hrx:setu-mapping-report {file? : Path to the Tally ledger export, one name per line}';
    protected $description = 'Classify a Tally salesperson ledger list against HRX users and store the reviewable mapping';

    // Ledger/accounting bucket keywords — never a person.
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
    ];

    // City/state names used as Tally territory buckets, not people. Cross-
    // checked against this session's known `address` column values.
    private const KNOWN_LOCATIONS = [
        'agra', 'aurangabaad', 'chennai', 'delhi ncr', 'haridwar', 'hyderabaad',
        'jamshedpur', 'madhya pardesh', 'maharashtra', 'noida', 'punjab',
        'rajasthan', 'vadodara',
    ];

    // Real companies that happen to appear in the ledger but are not HRX staff.
    private const KNOWN_NON_STAFF_COMPANIES = ['bajaj electrical', 'maruti suzuki'];

    private const CONFIRM_THRESHOLD = 85.0; // similarity % at/above which we auto-confirm
    private const SUGGEST_THRESHOLD = 55.0; // below this, don't even suggest

    public function handle(): int
    {
        $path = $this->argument('file') ?? resource_path('setu/tally_salesperson_ledger.txt');
        if (!is_file($path)) {
            $this->error("File not found: {$path}");
            return Command::FAILURE;
        }
        $names = array_values(array_filter(array_map('trim', file($path)), fn($l) => $l !== ''));
        $this->info('Tally entries to classify: ' . count($names));

        // Same brand set as SalesPipelineController::BRANDS, plus the
        // spelling variants seen in this specific Tally export.
        $brands = ['rust-x', 'dr. bio', 'fillezy', 'kif', 'zorbit', 'tuffpaulin', 'neutrasafe',
                   'keep-it-fresh', 'keepitfresh', 'hi tech -kif', 'tuffpaulin office'];

        $roster = User::where('status', 'active')
            ->notTerminated()
            ->whereHas('department', fn($q) => $q->where('name', 'like', '%Sales%'))
            ->get(['id', 'first_name', 'last_name']);
        // Also include the handful of non-Sales-dept users already used as
        // salesperson_id somewhere (e.g. Manpreet Kaur Walia, Customer Care +
        // New Biz dept) — same set PipelineImportController's dropdown misses
        // too, but worth catching here since Tally doesn't know about HRX
        // department boundaries.
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
        $counts = ['excluded' => 0, 'confirmed' => 0, 'pending' => 0];

        foreach ($names as $name) {
            $lower = mb_strtolower($name);

            $excludeReason = null;
            foreach (self::NOISE_PATTERNS as $p) { if (preg_match($p, $lower)) { $excludeReason = 'ledger bucket'; break; } }
            if (!$excludeReason && in_array($lower, self::KNOWN_LOCATIONS, true)) $excludeReason = 'city/state territory';
            if (!$excludeReason && in_array($lower, $brands, true)) $excludeReason = 'brand name';
            if (!$excludeReason && in_array($lower, self::KNOWN_NON_STAFF_COMPANIES, true)) $excludeReason = 'known company, not HRX staff';

            if ($excludeReason) {
                SetuSalespersonMapping::updateOrCreate(
                    ['tally_name' => $name],
                    ['user_id' => null, 'status' => SetuSalespersonMapping::STATUS_EXCLUDED, 'note' => $excludeReason, 'tenant_id' => null]
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
            $ambiguous = $second && ($second['score'] >= $top['score'] - 8) && $second['score'] >= self::SUGGEST_THRESHOLD;

            if ($top['score'] >= self::CONFIRM_THRESHOLD && !$ambiguous) {
                SetuSalespersonMapping::updateOrCreate(
                    ['tally_name' => $name],
                    ['user_id' => $top['id'], 'status' => SetuSalespersonMapping::STATUS_CONFIRMED,
                     'note' => sprintf('auto-confirmed, %.0f%% match', $top['score']), 'tenant_id' => null]
                );
                $rows[] = [$name, $top['name'], sprintf('%.0f%%', $top['score']), 'confirmed'];
                $counts['confirmed']++;
                continue;
            }

            $note = $top['score'] >= self::SUGGEST_THRESHOLD
                ? sprintf('best guess only — needs manual confirmation%s', $ambiguous ? "; also close: {$second['name']} ({$second['score']}%)" : '')
                : 'no strong candidate — needs manual identification';
            $suggestedId = $top['score'] >= self::SUGGEST_THRESHOLD ? $top['id'] : null;

            SetuSalespersonMapping::updateOrCreate(
                ['tally_name' => $name],
                ['user_id' => $suggestedId, 'status' => SetuSalespersonMapping::STATUS_PENDING, 'note' => $note, 'tenant_id' => null]
            );
            $rows[] = [$name, $top['score'] >= self::SUGGEST_THRESHOLD ? $top['name'] . ' ?' : '(none)',
                       $top['score'] >= self::SUGGEST_THRESHOLD ? sprintf('%.0f%%', $top['score']) : '-', 'PENDING REVIEW'];
            $counts['pending']++;
        }

        $this->table(['Tally name', 'Best HRX match', 'Score', 'Status'], $rows);
        $this->info(sprintf(
            "\nExcluded: %d   Auto-confirmed: %d   Needs review: %d",
            $counts['excluded'], $counts['confirmed'], $counts['pending']
        ));
        $this->warn('Review the PENDING rows in setu_salesperson_mappings (or via a follow-up UI) before running hrx:sync-setu-sales for anyone not confirmed.');

        return Command::SUCCESS;
    }

    private static function normalize(string $s): string
    {
        $s = mb_strtoupper($s);
        return preg_replace('/[^A-Z]/', '', $s);
    }
}
