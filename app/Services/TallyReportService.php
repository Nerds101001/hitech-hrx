<?php

namespace App\Services;

use App\Models\SalesPipeline;
use App\Models\SalesPipelineMonth;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Tally-powered equivalent of SetuReportService — same reconciliation shape
 * (blankFills/overwrites/renames/newCustomers/reviewItems against the
 * Sales Report book), but sourced from the app's OWN local tally_sale_lines
 * + tally_ledgers (populated by the already-live Tally push jobs) instead of
 * the external Setu API, and scoped to a salesperson via their confirmed
 * SalesGroupMapping ledger_group(s) instead of a single Setu "Salesperson"
 * identity string.
 *
 * Sales figures are GST-inclusive (taxable_amount + tax_amount) net of
 * signed credit/debit notes, company scoped to 'hitech' -- the same
 * methodology verified against Setu's own exports to the rupee in Tally
 * Explorer's Trend page (see TallyExplorerController::partPivot() /
 * creditDebitNoteAdjustmentPivot()). An earlier version of this method
 * summed tally_invoices.amount directly (GST-exclusive, no note netting, no
 * company filter) -- switched 2026-10-27 after that discrepancy was traced
 * and fixed for Trend but never carried back here.
 *
 * The period-math methods below (fiscal year labels, quarter bounds, month
 * lists) are plain date arithmetic, not Tally-specific, and are duplicated
 * from SetuReportService rather than shared — same "parallel feature, not a
 * premature shared base class" pattern already used elsewhere in this
 * codebase (Setu sync vs CRM mapping sync).
 */
class TallyReportService
{
    private const RUPEES_PER_LAKH = 100000;

    public function currentFiscalYearLabel(?Carbon $asOf = null): string
    {
        $asOf ??= now();
        $startYear = $asOf->month >= 4 ? $asOf->year : $asOf->year - 1;
        return "FY-{$startYear}-" . ($startYear + 1);
    }

    /** The 5 fiscal years available to pick from, most recent first. */
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
        return Carbon::createFromFormat('Y-m-d', $monthYear . '-01')->format('M Y');
    }

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
     * Every customer's monthly Tally sales for the given sales-group tag(s)
     * (tally_ledgers.ledger_group), narrowed to a fromMonth..toMonth range
     * (or the whole fyLabel year if both are null) —
     * `[customerName => ['YYYY-MM' => Lakhs]]`, keyed by each
     * normalized_name's most-complete party_name spelling (mirrors Setu's
     * customer-name keying so the same NameMatcher dedup logic downstream
     * behaves identically either way).
     *
     * GST-inclusive (taxable_amount + tax_amount), net of signed
     * credit/debit notes (a credit note subtracts, a debit note adds) --
     * same convention as Tally Explorer's Trend page.
     */
    public function periodMonthlySales(array $salespersonTags, ?string $fyLabel = null, ?string $fromMonth = null, ?string $toMonth = null): array
    {
        if (empty($salespersonTags)) {
            return [];
        }
        $fyLabel ??= $this->currentFiscalYearLabel();
        if (!$fromMonth && !$toMonth) {
            [$fromMonth] = $this->quarterMonthRange($fyLabel, 'Q1');
            [, $toMonth] = $this->quarterMonthRange($fyLabel, 'Q4');
        }

        $salesRows = DB::table('tally_sale_lines as sl')
            ->join('tally_ledgers as l', 'sl.normalized_name', '=', 'l.normalized_name')
            ->where('sl.company', 'hitech')
            ->whereIn('l.ledger_group', $salespersonTags)
            ->whereRaw("DATE_FORMAT(sl.invoice_date, '%Y-%m') BETWEEN ? AND ?", [$fromMonth, $toMonth])
            ->selectRaw("sl.normalized_name, MAX(sl.party_name) as party_name, DATE_FORMAT(sl.invoice_date, '%Y-%m') AS month_key, SUM(sl.taxable_amount + IFNULL(sl.tax_amount, 0)) AS total")
            ->groupBy('sl.normalized_name', 'month_key')
            ->get();

        $noteRows = DB::table('tally_credit_debit_note_lines as n')
            ->join('tally_ledgers as l', 'n.normalized_name', '=', 'l.normalized_name')
            ->where('n.company', 'hitech')
            ->whereIn('l.ledger_group', $salespersonTags)
            ->whereIn('n.note_type', ['credit_sale', 'debit_sale'])
            ->whereRaw("DATE_FORMAT(n.note_date, '%Y-%m') BETWEEN ? AND ?", [$fromMonth, $toMonth])
            ->selectRaw("n.normalized_name, DATE_FORMAT(n.note_date, '%Y-%m') AS month_key, SUM(CASE WHEN n.note_type = 'credit_sale' THEN -(n.amount + IFNULL(n.tax_amount, 0)) ELSE (n.amount + IFNULL(n.tax_amount, 0)) END) AS adj")
            ->groupBy('n.normalized_name', 'month_key')
            ->get();

        $byNorm = [];
        foreach ($salesRows as $row) {
            $key = $row->normalized_name;
            if (!isset($byNorm[$key])) {
                $byNorm[$key] = ['name' => $row->party_name, 'months' => []];
            }
            if (mb_strlen($row->party_name ?? '') > mb_strlen($byNorm[$key]['name'] ?? '')) {
                $byNorm[$key]['name'] = $row->party_name;
            }
            $byNorm[$key]['months'][$row->month_key] = (float) ($byNorm[$key]['months'][$row->month_key] ?? 0) + (float) $row->total;
        }
        foreach ($noteRows as $row) {
            $key = $row->normalized_name;
            if (!isset($byNorm[$key])) {
                $byNorm[$key] = ['name' => $key, 'months' => []];
            }
            $byNorm[$key]['months'][$row->month_key] = (float) ($byNorm[$key]['months'][$row->month_key] ?? 0) + (float) $row->adj;
        }

        $out = [];
        foreach ($byNorm as $p) {
            $out[$p['name']] = array_map(fn ($rupees) => round($rupees / self::RUPEES_PER_LAKH, 3), $p['months']);
        }
        return $out;
    }

    private const FUZZY_FLOOR = 80.0;

    /**
     * Reconcile one salesperson's Sales Report book against their Tally
     * ledger group(s) for one fiscal year (optionally narrowed to a
     * fromMonth..toMonth range). Mirrors
     * SetuReportService::reconcileSalesperson() exactly — see that method's
     * docblock for the full blankFills/overwrites/renames/newCustomers/
     * reviewItems semantics, which are unchanged here; only the data source
     * (local tally_invoices vs the external Setu API) differs.
     */
    public function reconcileSalesperson(User $user, array $ledgerGroups, bool $live = false, ?string $fyLabel = null, ?string $fromMonth = null, ?string $toMonth = null, bool $overwrite = false): array
    {
        $fyLabel ??= $this->currentFiscalYearLabel();
        $period = ($fromMonth && $toMonth)
            ? self::formatMonthLabel($fromMonth) . ' – ' . self::formatMonthLabel($toMonth)
            : "Whole Year {$fyLabel}";
        $empty = ['error' => null, 'fyLabel' => $fyLabel, 'period' => $period,
                  'blankFills' => [], 'overwrites' => [], 'renames' => [], 'newCustomers' => [], 'reviewItems' => []];

        $tallyByCustomer = $this->periodMonthlySales($ledgerGroups, $fyLabel, $fromMonth, $toMonth);

        $existingList = SalesPipeline::withTrashed()
            ->where('salesperson_id', $user->id)
            ->where('is_locked', true)
            ->with('months')
            ->get();
        $existingByNorm = $existingList->keyBy(fn($p) => \App\Support\NameMatcher::normalize($p->party_name));
        $tenantId = optional($existingList->first())->tenant_id;

        $blankFills = []; $overwrites = []; $renames = []; $newCustomers = []; $reviewItems = [];
        $allCustomers = [];

        foreach ($tallyByCustomer as $customerName => $months) {
            if (!array_filter($months)) {
                continue;
            }
            $pipeline = $existingByNorm->get(\App\Support\NameMatcher::normalize($customerName));

            if (!$pipeline) {
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

            if ($pipeline->party_name !== $customerName) {
                $renames[] = ['pipeline_id' => $pipeline->id, 'from' => $pipeline->party_name, 'to' => $customerName];
            }

            $monthsByKey = $pipeline->months->keyBy('month_year');
            foreach ($months as $monthYear => $tallyAmount) {
                if ($tallyAmount == 0.0) continue;
                $existingMonth = $monthsByKey->get($monthYear);
                $existingAmount = $existingMonth ? (float) $existingMonth->sale_amount : 0.0;

                if ($existingAmount == 0.0) {
                    $blankFills[] = ['pipeline_id' => $pipeline->id, 'customer' => $customerName,
                                     'month' => $monthYear, 'amount' => $tallyAmount];
                } elseif (round($existingAmount, 2) != round($tallyAmount, 2)) {
                    $overwrites[] = ['pipeline_id' => $pipeline->id, 'customer' => $customerName,
                                     'month' => $monthYear, 'hrx' => $existingAmount, 'setu' => $tallyAmount];
                }
            }
            $allCustomers[] = ['customer' => $customerName, 'status' => 'exact', 'pipeline_id' => $pipeline->id,
                               'renamedFrom' => $pipeline->party_name !== $customerName ? $pipeline->party_name : null,
                               'total' => round(array_sum($months), 3),
                               'cells' => $this->buildCellMap($months, $monthsByKey, 'exact')];
        }

        if ($live) {
            foreach ($blankFills as $f) {
                SalesPipelineMonth::updateOrCreate(
                    ['sales_pipeline_id' => $f['pipeline_id'], 'month_year' => $f['month']],
                    ['sale_amount' => $f['amount'], 'tenant_id' => null]
                );
            }
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

    private function buildCellMap(array $tallyMonths, $hrxByKey, string $mode): array
    {
        $out = [];
        foreach ($tallyMonths as $monthYear => $tally) {
            $tally = round((float) $tally, 3);
            if ($mode === 'exact') {
                $m = $hrxByKey ? $hrxByKey->get($monthYear) : null;
                $hrx = $m ? round((float) $m->sale_amount, 3) : 0.0;
                $change = 'same';
                if ($tally != 0.0) {
                    if ($hrx == 0.0) $change = 'fill';
                    elseif (round($hrx, 2) != round($tally, 2)) $change = 'overwrite';
                }
                $out[$monthYear] = ['setu' => $tally, 'hrx' => $hrx, 'change' => $change];
            } else {
                $out[$monthYear] = ['setu' => $tally, 'hrx' => null, 'change' => $tally != 0.0 ? $mode : 'none'];
            }
        }
        return $out;
    }

    private function fuzzyCandidates(string $tallyName, $existingList): array
    {
        $candidates = $existingList->map(fn($p) => [
            'label' => $p->party_name,
            'pipeline_id' => $p->id,
            'party_name' => $p->party_name,
            'currentTotal' => round((float) $p->months->sum('sale_amount'), 3),
        ])->all();

        $matches = \App\Support\NameMatcher::bestMatches($tallyName, $candidates, self::FUZZY_FLOOR, 5);

        return array_map(fn($m) => array_diff_key($m, ['label' => null]), $matches);
    }
}
