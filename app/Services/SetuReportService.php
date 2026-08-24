<?php

namespace App\Services;

use App\Models\SalesPipeline;
use App\Models\SalesPipelineMonth;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Wraps the Setu (insights.effortlessanalytics.com) sales-insights API,
 * sourced from Tally. Setu returns realised/invoiced sales per customer,
 * bucketed by Indian fiscal year and FY-relative month (4=April .. 3=March)
 * — used to reconcile the Sales Report book (sales_pipelines/is_locked=1).
 *
 * The API returns every fiscal year it has data for regardless of the
 * from_date/to_date sent (confirmed by inspection), so FY selection is done
 * client-side in extractMonthlySales().
 */
class SetuReportService
{
    // Setu's `tptl` is raw rupees; every amount column in sales_pipelines /
    // sales_pipeline_months is stored in Lakhs (confirmed this session by
    // cross-checking live pulls against values already hand-verified in
    // FixNavinJoshiJulyFinalSeeder.php — Signode India and Syrma Sgs both
    // matched to the rupee, e.g. 948956 / 100000 = 9.49).
    private const RUPEES_PER_LAKH = 100000;

    protected string $baseUrl;
    protected ?string $accessToken;
    protected int $reportId;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.setu.base_url', ''), '/');
        $this->accessToken = config('services.setu.access_token');
        $this->reportId = (int) config('services.setu.report_id', 2899);
    }

    /**
     * The current Indian fiscal year as Setu labels it, e.g. "FY-2026-2027"
     * for any date between 2026-04-01 and 2027-03-31.
     */
    public function currentFiscalYearLabel(?Carbon $asOf = null): string
    {
        $asOf ??= now();
        $startYear = $asOf->month >= 4 ? $asOf->year : $asOf->year - 1;
        return "FY-{$startYear}-" . ($startYear + 1);
    }

    /** Default from/to date bounds for the current FY (Apr 1 .. today or Mar 31). */
    public function currentFiscalYearBounds(?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $startYear = $asOf->month >= 4 ? $asOf->year : $asOf->year - 1;
        $from = Carbon::create($startYear, 4, 1);
        $to = min($asOf->copy(), Carbon::create($startYear + 1, 3, 31));
        return [$from->toDateString(), $to->toDateString()];
    }

    /**
     * Raw call to Setu for one salesperson (Tally ledger name). Returns the
     * decoded `data.data` payload (customer => Sales => FY => month => {qty,tptl})
     * or null on failure (logged).
     */
    public function fetchSalespersonReport(string $tallyName, ?string $fromDate = null, ?string $toDate = null): ?array
    {
        if (!$this->accessToken) {
            Log::error('Setu API: no access token configured (SETU_ACCESS_TOKEN)');
            return null;
        }
        [$defaultFrom, $defaultTo] = $this->currentFiscalYearBounds();

        try {
            $response = Http::withToken($this->accessToken)
                ->timeout(30)
                ->post("{$this->baseUrl}/setu", [
                    'report_id' => $this->reportId,
                    'from_date' => $fromDate ?? $defaultFrom,
                    'to_date' => $toDate ?? $defaultTo,
                    'setu_report_type' => 'my_report',
                    'global_filter' => [
                        'pos' => [['type' => 'Salesperson', 'value' => [$tallyName]]],
                        'neg' => [],
                    ],
                ]);

            if (!$response->successful()) {
                Log::error('Setu API request failed', [
                    'salesperson' => $tallyName, 'status' => $response->status(), 'body' => $response->body(),
                ]);
                return null;
            }

            $json = $response->json();
            if (($json['status'] ?? null) != 200 || !isset($json['data']['data'])) {
                Log::error('Setu API returned an unexpected shape', ['salesperson' => $tallyName, 'body' => $response->body()]);
                return null;
            }
            return $json['data']['data'];
        } catch (\Throwable $e) {
            Log::error('Setu API exception', ['salesperson' => $tallyName, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Extract just the requested fiscal year's monthly sale (`tptl`) figures
     * from a raw fetchSalespersonReport() payload, converting Setu's
     * FY-relative month key (4..12, 1..3) into a calendar "YYYY-MM" key.
     *
     * Returns [customerName => ['YYYY-MM' => float, ...], ...], with amounts
     * already converted from Setu's raw rupees into Lakhs (HRX's convention
     * for sales_pipeline_months.sale_amount). Includes zero-value months too
     * — the caller decides what a zero means (nothing to reconcile) versus
     * what a missing customer means (not in Setu at all).
     */
    public function extractMonthlySales(array $rawData, ?string $fyLabel = null): array
    {
        $fyLabel ??= $this->currentFiscalYearLabel();
        // "FY-2026-2027" -> startYear=2026
        if (!preg_match('/^FY-(\d{4})-(\d{4})$/', $fyLabel, $m)) {
            throw new \InvalidArgumentException("Malformed fiscal year label: {$fyLabel}");
        }
        $startYear = (int) $m[1];

        $out = [];
        foreach ($rawData as $customer => $txnTypes) {
            $sales = $txnTypes['Sales'] ?? null;
            if (!$sales || !isset($sales[$fyLabel])) {
                continue;
            }
            $months = [];
            foreach ($sales[$fyLabel] as $monthNum => $figures) {
                $monthNum = (int) $monthNum;
                $calendarYear = $monthNum >= 4 ? $startYear : $startYear + 1;
                $monthYear = sprintf('%04d-%02d', $calendarYear, $monthNum);
                $months[$monthYear] = round((float) ($figures['tptl'] ?? 0) / self::RUPEES_PER_LAKH, 3);
            }
            $out[$customer] = $months;
        }
        return $out;
    }

    /**
     * The 5 fiscal years Setu returns data for, most recent first — pure date
     * math off currentFiscalYearLabel(), no extra API call needed (Setu
     * always returns all 5 years in one fetch regardless of date params).
     */
    public function availableFiscalYears(int $count = 5): array
    {
        preg_match('/^FY-(\d{4})-/', $this->currentFiscalYearLabel(), $m);
        $startYear = (int) $m[1];
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $y = $startYear - $i;
            $out[] = "FY-{$y}-" . ($y + 1);
        }
        return $out;
    }

    /**
     * Month-range bounds ("YYYY-MM") for one quarter of an Indian fiscal
     * year: Q1=Apr-Jun, Q2=Jul-Sep, Q3=Oct-Dec, Q4=Jan-Mar. Q4 falls in the
     * FY label's SECOND calendar year — uses the same
     * month>=4-belongs-to-startYear rule as extractMonthlySales() so the two
     * can't silently diverge.
     */
    public function quarterMonthRange(string $fyLabel, string $quarter): array
    {
        if (!preg_match('/^FY-(\d{4})-(\d{4})$/', $fyLabel, $m)) {
            throw new \InvalidArgumentException("Malformed fiscal year label: {$fyLabel}");
        }
        $startYear = (int) $m[1];
        [$fromMonthNum, $toMonthNum] = match ($quarter) {
            'Q1' => [4, 6],
            'Q2' => [7, 9],
            'Q3' => [10, 12],
            'Q4' => [1, 3],
            default => throw new \InvalidArgumentException("Unknown quarter: {$quarter}"),
        };
        $fromYear = $fromMonthNum >= 4 ? $startYear : $startYear + 1;
        $toYear = $toMonthNum >= 4 ? $startYear : $startYear + 1;
        return [sprintf('%04d-%02d', $fromYear, $fromMonthNum), sprintf('%04d-%02d', $toYear, $toMonthNum)];
    }

    /**
     * The one shared interpreter turning a (fy, period, fromMonth, toMonth)
     * request into concrete reconcileSalesperson() bounds — used by both the
     * CLI and the web endpoint so they can't disagree on what "Q2" or
     * "whole year" means. $period is one of whole|Q1|Q2|Q3|Q4|custom.
     *
     * @return array{0: string, 1: ?string, 2: ?string, 3: string} [fyLabel, fromMonth, toMonth, displayLabel]
     */
    public function resolvePeriod(?string $fy, string $period = 'whole', ?string $fromMonth = null, ?string $toMonth = null): array
    {
        $fyLabel = $fy ?? $this->currentFiscalYearLabel();

        if ($period === 'whole') {
            return [$fyLabel, null, null, "Whole Year {$fyLabel}"];
        }
        if (in_array($period, ['Q1', 'Q2', 'Q3', 'Q4'], true)) {
            [$fromMonth, $toMonth] = $this->quarterMonthRange($fyLabel, $period);
            return [$fyLabel, $fromMonth, $toMonth, "{$period} (" . self::formatMonthLabel($fromMonth) . ' – ' . self::formatMonthLabel($toMonth) . ')'];
        }
        if ($period === 'custom') {
            if (!$fromMonth || !$toMonth) {
                throw new \InvalidArgumentException('Custom period requires both fromMonth and toMonth.');
            }
            return [$fyLabel, $fromMonth, $toMonth, self::formatMonthLabel($fromMonth) . ' – ' . self::formatMonthLabel($toMonth)];
        }
        throw new \InvalidArgumentException("Unknown period: {$period}");
    }

    private static function formatMonthLabel(string $monthYear): string
    {
        // Explicit day=01, not createFromFormat('Y-m', ...) — that fills the
        // day with TODAY's day-of-month, which overflows into the next
        // month whenever today is the 29th-31st and the target month is
        // shorter (e.g. parsing "2025-09" on the 31st silently yields Oct).
        return Carbon::createFromFormat('Y-m-d', $monthYear . '-01')->format('M Y');
    }

    /**
     * Ordered calendar-month keys ("YYYY-MM", Apr→Mar) for a fiscal year,
     * narrowed to a fromMonth..toMonth range if given. Drives the month
     * columns in the sync preview / review tables so they render in order.
     *
     * @return array<int, string>
     */
    public function fiscalYearMonths(string $fyLabel, ?string $fromMonth = null, ?string $toMonth = null): array
    {
        if (!preg_match('/^FY-(\d{4})-(\d{4})$/', $fyLabel, $m)) {
            throw new \InvalidArgumentException("Malformed fiscal year label: {$fyLabel}");
        }
        $startYear = (int) $m[1];
        $out = [];
        foreach ([4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3] as $monthNum) {
            $year = $monthNum >= 4 ? $startYear : $startYear + 1;
            $key = sprintf('%04d-%02d', $year, $monthNum);
            if ($fromMonth && $key < $fromMonth) continue;
            if ($toMonth && $key > $toMonth) continue;
            $out[] = $key;
        }
        return $out;
    }

    /**
     * Fetch + parse a salesperson's Setu report for one fiscal year, narrowed
     * to a fromMonth..toMonth range if given, as `[customerName => [YYYY-MM =>
     * Lakhs]]`. Returns null on fetch failure (logged). Shared by
     * reconcileSalesperson() and the resolve endpoint so both see the exact
     * same period-filtered figures (the resolve endpoint re-fetches
     * server-side rather than trusting client-sent money).
     */
    public function periodMonthlySales(string $tallyName, ?string $fyLabel = null, ?string $fromMonth = null, ?string $toMonth = null): ?array
    {
        $fyLabel ??= $this->currentFiscalYearLabel();
        $raw = $this->fetchSalespersonReport($tallyName);
        if ($raw === null) {
            return null;
        }
        $byCustomer = $this->extractMonthlySales($raw, $fyLabel);
        if ($fromMonth || $toMonth) {
            $byCustomer = array_map(
                fn($months) => array_filter($months, function ($monthYear) use ($fromMonth, $toMonth) {
                    if ($fromMonth && $monthYear < $fromMonth) return false;
                    if ($toMonth && $monthYear > $toMonth) return false;
                    return true;
                }, ARRAY_FILTER_USE_KEY),
                $byCustomer
            );
        }
        return $byCustomer;
    }

    /** A Setu customer must score at least this % (similar_text, on normalised
     *  names) against an existing row to be offered as a fuzzy candidate. Below
     *  it, the customer is treated as genuinely new. This floor only decides
     *  what is SHOWN for review — it never auto-acts; a human always picks. */
    private const FUZZY_FLOOR = 80.0;

    /**
     * Reconcile one salesperson's Sales Report book (sales_pipelines,
     * is_locked=1) against Setu for one fiscal year (current, by default) —
     * optionally narrowed to a fromMonth..toMonth range within it. Shared by
     * hrx:sync-setu-sales (CLI) and SalesPipelineController (the Sync button /
     * Sync Center) — one implementation, so every path agrees on what counts
     * as an exact match vs a fuzzy one.
     *
     * Setu (from Tally) is the source of truth for both values and names, so a
     * customer whose name is IDENTICAL after safe normalisation (see
     * App\Support\NameMatcher::normalize — `&`↔`AND`, punctuation, case only; distinguishing
     * tokens like Unit I/II, G-05, (N), SEZ survive) is treated as certainly
     * the same entity, and on `$overwrite` its blank months are filled, its
     * differing months are corrected to Setu's figure, and its stored name is
     * standardised to Setu's spelling. Anything only SIMILAR (or with no match)
     * is never auto-acted — it is returned in `reviewItems` for a human to
     * resolve (update-which-row vs create-new), because near-identical names
     * can be genuinely different sub-entities and one company can already exist
     * under several spellings.
     *
     * Write gating: `blankFills` are written whenever `$live` (safe,
     * non-destructive — unchanged from Parts 2-3). `overwrites` and `renames`
     * are written only when `$live && $overwrite`. `reviewItems` are NEVER
     * written here — only the resolve endpoint applies them. With
     * `$overwrite=false` (the default) behaviour is exactly Parts 2-3.
     *
     * @return array{
     *   error: ?string, fyLabel: string, period: string,
     *   blankFills: array<int, array{pipeline_id:int, customer:string, month:string, amount:float}>,
     *   overwrites: array<int, array{pipeline_id:int, customer:string, month:string, hrx:float, setu:float}>,
     *   renames: array<int, array{pipeline_id:int, from:string, to:string}>,
     *   newCustomers: array<int, array{customer:string, total:float, months:array<string,float>}>,
     *   reviewItems: array<int, array{setuCustomer:string, total:float, months:array<string,float>, candidates:array<int, array{pipeline_id:int, party_name:string, currentTotal:float, score:float}>}>,
     * }
     */
    public function reconcileSalesperson(User $user, string $tallyName, bool $live = false, ?string $fyLabel = null, ?string $fromMonth = null, ?string $toMonth = null, bool $overwrite = false): array
    {
        $fyLabel ??= $this->currentFiscalYearLabel();
        $period = ($fromMonth && $toMonth)
            ? self::formatMonthLabel($fromMonth) . ' – ' . self::formatMonthLabel($toMonth)
            : "Whole Year {$fyLabel}";
        $empty = ['error' => null, 'fyLabel' => $fyLabel, 'period' => $period,
                  'blankFills' => [], 'overwrites' => [], 'renames' => [], 'newCustomers' => [], 'reviewItems' => []];

        $setuByCustomer = $this->periodMonthlySales($tallyName, $fyLabel, $fromMonth, $toMonth);
        if ($setuByCustomer === null) {
            return ['error' => 'Setu fetch failed — see the application log.'] + $empty;
        }

        // Existing book for this salesperson. Keep the full list (for fuzzy
        // scoring — a company may exist under several spellings, all of which
        // must be offered as candidates) and a normalised-name index (for the
        // exact-match lookup).
        $existingList = SalesPipeline::withTrashed()
            ->where('salesperson_id', $user->id)
            ->where('is_locked', true)
            ->with('months')
            ->get();
        $existingByNorm = $existingList->keyBy(fn($p) => \App\Support\NameMatcher::normalize($p->party_name));
        $tenantId = optional($existingList->first())->tenant_id;

        $blankFills = []; $overwrites = []; $renames = []; $newCustomers = []; $reviewItems = [];
        // Every Setu customer for this salesperson, with a per-month cell state
        // (same / fill / overwrite / new / review) so the preview can show the
        // COMPLETE fetched dataset and highlight exactly which cells change.
        $allCustomers = [];

        foreach ($setuByCustomer as $customerName => $months) {
            if (!array_filter($months)) {
                continue; // no non-zero Setu data in this period — nothing to reconcile
            }
            $pipeline = $existingByNorm->get(\App\Support\NameMatcher::normalize($customerName));

            if (!$pipeline) {
                // No exact match. If a SIMILAR existing row exists, it needs a
                // human decision (could be a different sub-entity — Unit I/II) —
                // hold it in reviewItems. If NOTHING is similar, it's genuinely
                // a new customer — auto-create it on apply (there's no existing
                // row it could be confused with), but surface it in the preview
                // first so the user sees what's being added.
                $candidates = $this->fuzzyCandidates($customerName, $existingList);
                if ($candidates) {
                    $reviewItems[] = [
                        'setuCustomer' => $customerName,
                        'total' => round(array_sum($months), 3),
                        'months' => $months,
                        'candidates' => $candidates,
                    ];
                    $allCustomers[] = ['customer' => $customerName, 'status' => 'review', 'pipeline_id' => null,
                                       'renamedFrom' => null, 'total' => round(array_sum($months), 3),
                                       'cells' => $this->buildCellMap($months, null, 'review')];
                } else {
                    $newCustomers[] = [
                        'customer' => $customerName,
                        'total' => round(array_sum($months), 3),
                        'months' => $months,
                    ];
                    $allCustomers[] = ['customer' => $customerName, 'status' => 'new', 'pipeline_id' => null,
                                       'renamedFrom' => null, 'total' => round(array_sum($months), 3),
                                       'cells' => $this->buildCellMap($months, null, 'new')];
                }
                continue;
            }

            // EXACT match (certainly the same entity). Adopt Setu's spelling.
            if ($pipeline->party_name !== $customerName) {
                $renames[] = ['pipeline_id' => $pipeline->id, 'from' => $pipeline->party_name, 'to' => $customerName];
            }

            $monthsByKey = $pipeline->months->keyBy('month_year');
            foreach ($months as $monthYear => $setuAmount) {
                if ($setuAmount == 0.0) continue; // nothing to reconcile
                $existingMonth = $monthsByKey->get($monthYear);
                $existingAmount = $existingMonth ? (float) $existingMonth->sale_amount : 0.0;

                if ($existingAmount == 0.0) {
                    $blankFills[] = ['pipeline_id' => $pipeline->id, 'customer' => $customerName,
                                     'month' => $monthYear, 'amount' => $setuAmount];
                } elseif (round($existingAmount, 2) != round($setuAmount, 2)) {
                    $overwrites[] = ['pipeline_id' => $pipeline->id, 'customer' => $customerName,
                                     'month' => $monthYear, 'hrx' => $existingAmount, 'setu' => $setuAmount];
                }
            }
            $allCustomers[] = ['customer' => $customerName, 'status' => 'exact', 'pipeline_id' => $pipeline->id,
                               'renamedFrom' => $pipeline->party_name !== $customerName ? $pipeline->party_name : null,
                               'total' => round(array_sum($months), 3),
                               'cells' => $this->buildCellMap($months, $monthsByKey, 'exact')];
        }

        if ($live) {
            // Blank fills: always safe (nothing to destroy), written on any live run.
            foreach ($blankFills as $f) {
                SalesPipelineMonth::updateOrCreate(
                    ['sales_pipeline_id' => $f['pipeline_id'], 'month_year' => $f['month']],
                    ['sale_amount' => $f['amount'], 'tenant_id' => null]
                );
            }
            // Overwrites + renames: destructive, only on explicit $overwrite.
            // Written through the models (never raw SQL) so every change is
            // captured in the audit log — the only recovery path.
            if ($overwrite) {
                foreach ($overwrites as $o) {
                    SalesPipelineMonth::updateOrCreate(
                        ['sales_pipeline_id' => $o['pipeline_id'], 'month_year' => $o['month']],
                        ['sale_amount' => $o['setu'], 'tenant_id' => null]
                    );
                }
                foreach ($renames as $r) {
                    $p = SalesPipeline::withTrashed()->find($r['pipeline_id']);
                    if ($p) {
                        $p->party_name = $r['to'];
                        $p->save();
                    }
                }
                // Auto-create the genuinely-new (no-similar-match) customers,
                // adopting Setu's exact name + monthly figures. updateOrCreate
                // on (party_name, salesperson_id) so a re-run never duplicates.
                foreach ($newCustomers as $nc) {
                    $p = SalesPipeline::withTrashed()->updateOrCreate(
                        ['party_name' => $nc['customer'], 'salesperson_id' => $user->id],
                        ['is_locked' => true, 'type' => 'Customer', 'tenant_id' => $tenantId]
                    );
                    foreach (array_filter($nc['months']) as $monthYear => $amount) {
                        SalesPipelineMonth::updateOrCreate(
                            ['sales_pipeline_id' => $p->id, 'month_year' => $monthYear],
                            ['sale_amount' => $amount, 'tenant_id' => $p->tenant_id]
                        );
                    }
                }
            }
        }

        return ['error' => null, 'fyLabel' => $fyLabel, 'period' => $period,
                'blankFills' => $blankFills, 'overwrites' => $overwrites,
                'renames' => $renames, 'newCustomers' => $newCustomers, 'reviewItems' => $reviewItems,
                'allCustomers' => $allCustomers];
    }

    /**
     * Per-month cell state for one customer, for the comprehensive preview
     * table. $mode: 'exact' compares Setu against the matched HRX row (same /
     * fill / overwrite); 'new' and 'review' have no HRX counterpart yet.
     *
     * @return array<string, array{setu:float, hrx:?float, change:string}>
     */
    private function buildCellMap(array $setuMonths, $hrxByKey, string $mode): array
    {
        $out = [];
        foreach ($setuMonths as $monthYear => $setu) {
            $setu = round((float) $setu, 3);
            if ($mode === 'exact') {
                $m = $hrxByKey ? $hrxByKey->get($monthYear) : null;
                $hrx = $m ? round((float) $m->sale_amount, 3) : 0.0;
                $change = 'same';
                if ($setu != 0.0) {
                    if ($hrx == 0.0) $change = 'fill';
                    elseif (round($hrx, 2) != round($setu, 2)) $change = 'overwrite';
                }
                $out[$monthYear] = ['setu' => $setu, 'hrx' => $hrx, 'change' => $change];
            } else { // 'new' or 'review' — no existing counterpart
                $out[$monthYear] = ['setu' => $setu, 'hrx' => null, 'change' => $setu != 0.0 ? $mode : 'none'];
            }
        }
        return $out;
    }

    /**
     * Existing rows scoring >= FUZZY_FLOOR against a Setu customer name
     * (similar_text on normalised strings), best first, capped at 5. Used to
     * offer a human "did you mean this existing customer?" candidates — never
     * to auto-match. Returns every sibling above the floor so a multi-spelling
     * company shows all its variants.
     *
     * @param  \Illuminate\Support\Collection<int, SalesPipeline>  $existingList
     * @return array<int, array{pipeline_id:int, party_name:string, currentTotal:float, score:float}>
     */
    private function fuzzyCandidates(string $setuName, $existingList): array
    {
        $candidates = $existingList->map(fn($p) => [
            'label' => $p->party_name,
            'pipeline_id' => $p->id,
            'party_name' => $p->party_name,
            'currentTotal' => round((float) $p->months->sum('sale_amount'), 3),
        ])->all();

        $matches = \App\Support\NameMatcher::bestMatches($setuName, $candidates, self::FUZZY_FLOOR, 5);

        // Drop the internal 'label' key -- callers only expect the shape above.
        return array_map(fn($m) => array_diff_key($m, ['label' => null]), $matches);
    }
}
