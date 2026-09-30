<?php

namespace App\Services;

use App\Models\SetuCustomer;
use Illuminate\Support\Facades\DB;

/**
 * Everything HRX holds about one company's sales, gathered by normalised name
 * (App\Support\NameMatcher::normalize) from the three sources the Sales Sources
 * page compares -- Tally invoices (rupees, shown here in Lakhs), Setu and the
 * manual Sales Report (both already Lakhs) -- plus its Tally receivables and
 * sale orders. Read-only. Used by the Sales Sources company page and by the
 * CRM Customer Profile, so both show the same numbers.
 */
class CompanySales
{
    private const RUPEES_PER_LAKH = 100000;

    /** A gap under this (Lakhs = Rs 500) is rounding, not a real difference. */
    public const TOLERANCE_LAKH = 0.005;

    /**
     * Tally sales per month in Lakhs for the sales-trend chart, and the first and
     * last month (YYYY-MM) the Tally export covers at all -- so a month outside
     * that window reads "no data", not "zero sales".
     *
     * @param  string[]  $keys
     * @return array{byMonth: array<string,float>, from: ?string, to: ?string}
     */
    public function tallyByMonth(array $keys): array
    {
        $keys = array_values(array_unique(array_filter($keys)));
        $window = DB::table('tally_invoices')->selectRaw("DATE_FORMAT(MIN(invoice_date), '%Y-%m') AS a, DATE_FORMAT(MAX(invoice_date), '%Y-%m') AS b")->first();
        $byMonth = $keys ? DB::table('tally_invoices')->whereIn('normalized_name', $keys)->where('is_branch_transfer', 0)
            ->selectRaw("DATE_FORMAT(invoice_date, '%Y-%m') AS m, SUM(amount) AS amount")->groupBy('m')->pluck('amount', 'm')
            ->map(fn($v) => (float) $v / self::RUPEES_PER_LAKH)->all() : [];

        return ['byMonth' => $byMonth, 'from' => $window->a ?? null, 'to' => $window->b ?? null];
    }

    /**
     * @param  string[]  $keys  normalised names that certainly are this company
     * @return array<string,mixed>
     */
    public function for(array $keys, int $monthsShown = 12): array
    {
        $keys = array_values(array_unique(array_filter($keys)));
        $window = DB::table('tally_invoices')->selectRaw('MIN(invoice_date) AS a, MAX(invoice_date) AS b')->first();

        $out = [
            'keys' => $keys, 'hasData' => false, 'name' => null, 'codes' => null, 'tallySps' => null, 'manualSps' => [],
            'invoiceCount' => 0, 'firstDate' => null, 'lastDate' => null, 'branchTransfers' => null,
            'tally' => [], 'tallyCount' => [], 'setu' => [], 'manual' => [], 'monthRows' => [], 'invoices' => collect(),
            'outstanding' => null, 'orders' => null,
            'coverage' => ['from' => $window->a ?? null, 'to' => $window->b ?? null],
        ];
        if (!$keys) {
            return $out;
        }

        // Tally: real customer sales only (branch transfers are not customers).
        $base = DB::table('tally_invoices')->whereIn('normalized_name', $keys)->where('is_branch_transfer', 0);
        $id = (clone $base)->selectRaw('MAX(party_name) AS name, GROUP_CONCAT(DISTINCT party_code ORDER BY party_code SEPARATOR ", ") AS codes,
                GROUP_CONCAT(DISTINCT salesperson ORDER BY salesperson SEPARATOR ", ") AS sps, COUNT(*) AS invoices,
                MIN(invoice_date) AS first_date, MAX(invoice_date) AS last_date')->first();
        // Stock moved between Hi Tech's own branches is invoiced in Tally too, but it is not a customer sale and is left out above.
        $branch = DB::table('tally_invoices')->whereIn('normalized_name', $keys)->where('is_branch_transfer', 1)
            ->selectRaw('COUNT(*) AS n, COALESCE(SUM(amount), 0) AS amount')->first();
        $byMonth = (clone $base)->selectRaw("DATE_FORMAT(invoice_date, '%Y-%m') AS m, SUM(amount) AS amount, COUNT(*) AS n")->groupBy('m')->get();
        $tally = $byMonth->pluck('amount', 'm')->map(fn($v) => round((float) $v / self::RUPEES_PER_LAKH, 3))->all();
        $tallyCount = $byMonth->pluck('n', 'm')->map(fn($v) => (int) $v)->all();
        $invoices = (clone $base)->orderByDesc('invoice_date')->orderByDesc('id')->limit(100)
            ->get(['invoice_no', 'invoice_date', 'amount', 'salesperson', 'destination', 'open_amount']);
        $itemCounts = $invoices->isEmpty() ? collect() : DB::table('tally_invoice_lines')->whereIn('invoice_no', $invoices->pluck('invoice_no')->all())
            ->groupBy('invoice_no')->selectRaw('invoice_no, COUNT(*) AS n')->pluck('n', 'invoice_no');
        $invoices->each(fn($i) => $i->items = (int) ($itemCounts[$i->invoice_no] ?? 0));

        // Setu: same rule as the Compare page (a later non-zero row for the same name wins); different names add up.
        $setu = [];
        $perName = [];
        foreach (SetuCustomer::whereIn('normalized_name', $keys)->get(['normalized_name', 'recent_months']) as $s) {
            foreach (($s->recent_months ?? []) as $m => $v) {
                if ($v !== null && (float) $v != 0.0) {
                    $perName[$s->normalized_name][$m] = (float) $v;
                }
            }
        }
        foreach ($perName as $months) {
            foreach ($months as $m => $v) {
                $setu[$m] = round(($setu[$m] ?? 0.0) + $v, 3);
            }
        }

        // Manual: the Sales Report rows linked to this customer.
        $manualBase = DB::table('sales_pipeline_months AS m')
            ->join('sales_pipelines AS p', 'p.id', '=', 'm.sales_pipeline_id')
            ->join('customers AS c', 'c.id', '=', 'p.customer_id')
            ->whereNull('p.deleted_at')->where('p.is_locked', 1)->whereIn('c.normalized_name', $keys);
        $manual = (clone $manualBase)->selectRaw('m.month_year AS m, SUM(m.sale_amount) AS amount')->groupBy('m.month_year')
            ->havingRaw('SUM(m.sale_amount) <> 0')->pluck('amount', 'm')->map(fn($v) => round((float) $v, 3))->all();
        $manualSps = (clone $manualBase)->leftJoin('users AS u', 'u.id', '=', 'p.salesperson_id')
            ->selectRaw('DISTINCT TRIM(CONCAT(u.first_name, " ", u.last_name)) AS sp')->pluck('sp')->filter()->values()->all();

        // The months to show, newest first, and whether the sources agree in each.
        $months = collect(array_merge(array_keys($tally), array_keys($setu), array_keys($manual)))->unique()->sortDesc()->take($monthsShown)->values()->all();
        $monthRows = array_map(function ($m) use ($tally, $setu, $manual) {
            $t = $tally[$m] ?? null;
            $s = $setu[$m] ?? null;
            $u = $manual[$m] ?? null;
            $present = array_filter([$t, $s, $u], fn($v) => $v !== null);
            $gap = count($present) > 1 ? max($present) - min($present) : 0.0;
            return ['m' => $m, 'tally' => $t, 'setu' => $s, 'manual' => $u, 'gap' => $gap, 'differs' => $gap > self::TOLERANCE_LAKH];
        }, $months);

        // Receivables and orders straight from the Tally exports (rupees).
        $outstanding = DB::table('tally_outstanding')->whereIn('normalized_name', $keys)->where('ledger_group', 'Sundry Debtors')
            ->selectRaw('COUNT(*) AS bills, COALESCE(SUM(amount), 0) AS total, MIN(bill_date) AS oldest,
                         COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), bill_date) > 90 THEN amount ELSE 0 END), 0) AS over90')->first();
        $orders = DB::table('tally_sale_orders')->whereIn('normalized_name', $keys)
            ->selectRaw('COUNT(*) AS n, COALESCE(SUM(amount), 0) AS total')->first();

        return array_merge($out, [
            'hasData' => $tally || $setu || $manual || (int) $branch->n > 0 || (int) $outstanding->bills > 0 || (int) $orders->n > 0,
            'name' => $id->name, 'codes' => $id->codes, 'tallySps' => $id->sps, 'manualSps' => $manualSps,
            'invoiceCount' => (int) $id->invoices, 'firstDate' => $id->first_date, 'lastDate' => $id->last_date,
            'branchTransfers' => (int) $branch->n > 0 ? ['n' => (int) $branch->n, 'lakh' => round((float) $branch->amount / self::RUPEES_PER_LAKH, 2)] : null,
            'tally' => $tally, 'tallyCount' => $tallyCount, 'setu' => $setu, 'manual' => $manual,
            'monthRows' => $monthRows, 'invoices' => $invoices, 'outstanding' => $outstanding, 'orders' => $orders,
        ]);
    }
}
