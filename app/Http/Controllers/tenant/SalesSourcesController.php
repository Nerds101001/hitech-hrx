<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SetuCustomer;
use App\Models\TallyImport;
use App\Models\TallySaleOrder;
use App\Services\TallyAnalytics;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * "Sales Sources" -- one place to see the same sales as the three systems
 * report them: the manual Sales Report (sales_pipeline_months), Setu, and the
 * Tally daily exports (tally_* tables, loaded by `hrx:import-tally`).
 *
 * Read-only by design: nothing here writes to the Sales Report or changes a
 * mapping. It exists so the sources can be compared before any one of them is
 * trusted to sync the Sales Report. Amounts are shown in Lakhs where they meet
 * the Sales Report/Setu (both store Lakhs) and in rupees where they are pure
 * Tally (invoices, outstanding, orders).
 *
 * Company-wide data (every customer, every salesperson, receivables), so it is
 * limited to admin/HR/accounts -- the same set the route group enforces.
 */
class SalesSourcesController extends Controller
{
    private const RUPEES_PER_LAKH = 100000;

    /** A gap under this (Lakhs = Rs 500) is rounding, not a real difference. */
    private const TOLERANCE_LAKH = 0.005;

    private function authorizeAccess(): void
    {
        $user = Auth::user();
        abort_unless($user && $user->hasRole(['admin', 'Admin', 'super_admin', 'hr', 'HR', 'accounts']), 403);
    }

    private function perPage(Request $request): int
    {
        return in_array((int) $request->input('per'), [25, 50, 100, 200], true) ? (int) $request->input('per') : 50;
    }

    /** Months present in the Tally invoice export, oldest first (YYYY-MM). */
    private function tallyMonths(): array
    {
        return DB::table('tally_invoices')
            ->selectRaw("DATE_FORMAT(invoice_date, '%Y-%m') AS m")->distinct()->orderBy('m')->pluck('m')->all();
    }

    /** [first day, last day] of a validated YYYY-MM. */
    private function monthBounds(string $month): array
    {
        $first = \Carbon\Carbon::createFromFormat('!Y-m-d', $month . '-01');
        return [$first->toDateString(), $first->copy()->endOfMonth()->toDateString()];
    }

    private function shared(string $tab): array
    {
        return [
            'tab' => $tab,
            'lastImport' => TallyImport::latestGood(),
            'pageConfigs' => ['contentLayout' => 'wide'],
        ];
    }

    // ------------------------------------------------------------------ compare

    public function compare(Request $request)
    {
        $this->authorizeAccess();

        $months = array_reverse($this->tallyMonths());          // newest first for the picker
        $month = in_array($request->input('m'), $months, true) ? $request->input('m') : ($months[0] ?? now()->format('Y-m'));
        [$from, $to] = $this->monthBounds($month);

        // Tally: real customer sales only, in rupees -> Lakhs.
        $tally = DB::table('tally_invoices')
            ->where('is_branch_transfer', 0)->whereNotNull('party_code')->whereNotNull('normalized_name')
            ->whereBetween('invoice_date', [$from, $to])
            ->selectRaw('normalized_name, MAX(party_name) AS name, MIN(party_code) AS party_code, COUNT(*) AS invoices, SUM(amount) AS amount,
                         GROUP_CONCAT(DISTINCT salesperson ORDER BY salesperson SEPARATOR ", ") AS sps')
            ->groupBy('normalized_name')->get()->keyBy('normalized_name');

        // Setu: the local cache (already Lakhs). It only ever holds the last few
        // months as of its last sync, so an absent month here means "not cached".
        $setuAll = SetuCustomer::get(['name', 'normalized_name', 'recent_months', 'last_synced_at']);
        $setu = [];
        foreach ($setuAll as $s) {
            $v = ($s->recent_months ?? [])[$month] ?? null;
            if ($v !== null && (float) $v != 0.0) {
                $setu[$s->normalized_name] = ['name' => $s->name, 'amount' => (float) $v];
            }
        }
        $setuMonthsCached = $setuAll->flatMap(fn($s) => array_keys($s->recent_months ?? []))->unique()->sort()->values()->all();
        $setuSyncedAt = $setuAll->max('last_synced_at');

        // How far Setu's cache lags the Tally data. Only meaningful for the
        // latest month: an in-progress month can't match until Setu re-syncs.
        $tallyLast = DB::table('tally_invoices')->max('invoice_date');
        $setuBehindDays = ($setuSyncedAt && $tallyLast && $month === ($months[0] ?? null))
            ? max(0, \Carbon\Carbon::parse($setuSyncedAt)->startOfDay()->diffInDays(\Carbon\Carbon::parse($tallyLast)->startOfDay(), false))
            : 0;

        // Manual: the Sales Report rows linked to a canonical customer (Lakhs).
        $manual = DB::table('sales_pipeline_months AS m')
            ->join('sales_pipelines AS p', 'p.id', '=', 'm.sales_pipeline_id')
            ->join('customers AS c', 'c.id', '=', 'p.customer_id')
            ->leftJoin('users AS u', 'u.id', '=', 'p.salesperson_id')
            ->whereNull('p.deleted_at')->where('p.is_locked', 1)->where('m.month_year', $month)
            ->selectRaw('c.normalized_name, MAX(c.canonical_name) AS name, SUM(m.sale_amount) AS amount,
                         GROUP_CONCAT(DISTINCT TRIM(CONCAT(u.first_name, " ", u.last_name)) SEPARATOR ", ") AS sps')
            ->groupBy('c.normalized_name')->havingRaw('SUM(m.sale_amount) <> 0')
            ->get()->keyBy('normalized_name');

        $rows = [];
        foreach (array_unique(array_merge($tally->keys()->all(), array_keys($setu), $manual->keys()->all())) as $key) {
            $t = isset($tally[$key]) ? round($tally[$key]->amount / self::RUPEES_PER_LAKH, 3) : null;
            $s = $setu[$key]['amount'] ?? null;
            $m = isset($manual[$key]) ? round((float) $manual[$key]->amount, 3) : null;

            $present = array_filter([$t, $s, $m], fn($v) => $v !== null);
            $gap = count($present) > 1 ? max($present) - min($present) : 0.0;

            $rows[$key] = [
                'key' => $key,
                'name' => $tally[$key]->name ?? $setu[$key]['name'] ?? $manual[$key]->name,
                'party_code' => $tally[$key]->party_code ?? null,
                'invoices' => $tally[$key]->invoices ?? null,
                'tally' => $t, 'setu' => $s, 'manual' => $m,
                'gap' => $gap,
                'differs' => count($present) > 1 && $gap > self::TOLERANCE_LAKH,
                'sps_tally' => $tally[$key]->sps ?? null,
                'sps_manual' => $manual[$key]->sps ?? null,
                'top' => max($present),
            ];
        }

        // Company-level totals for the month, before any filter narrows the list.
        $totals = [
            'tally' => array_sum(array_column($rows, 'tally')),
            'setu' => array_sum(array_column($rows, 'setu')),
            'manual' => array_sum(array_column($rows, 'manual')),
            'customers' => count($rows),
            'differs' => count(array_filter($rows, fn($r) => $r['differs'])),
            'no_manual' => count(array_filter($rows, fn($r) => $r['tally'] !== null && $r['manual'] === null)),
            'no_setu' => count(array_filter($rows, fn($r) => $r['tally'] !== null && $r['setu'] === null)),
            'no_tally' => count(array_filter($rows, fn($r) => $r['tally'] === null)),
        ];

        // Filters
        $q = trim((string) $request->input('q', ''));
        $status = (string) $request->input('status', 'all');
        $list = array_filter($rows, function ($r) use ($q, $status) {
            if ($q !== '' && stripos($r['name'] . ' ' . $r['party_code'], $q) === false) return false;
            return match ($status) {
                'differs' => $r['differs'],
                'no_manual' => $r['tally'] !== null && $r['manual'] === null,
                'no_setu' => $r['tally'] !== null && $r['setu'] === null,
                'no_tally' => $r['tally'] === null,
                'agree' => !$r['differs'] && $r['tally'] !== null && $r['setu'] !== null && $r['manual'] !== null,
                default => true,
            };
        });

        $sort = (string) $request->input('sort', 'amount');
        usort($list, match ($sort) {
            'gap' => fn($a, $b) => $b['gap'] <=> $a['gap'],
            'name' => fn($a, $b) => strcasecmp($a['name'], $b['name']),
            default => fn($a, $b) => $b['top'] <=> $a['top'],
        });

        $per = $this->perPage($request);
        $page = max(1, (int) $request->input('page', 1));
        $paginator = new LengthAwarePaginator(array_slice($list, ($page - 1) * $per, $per), count($list), $per, $page, [
            'path' => $request->url(), 'query' => $request->query(),
        ]);

        return view('tenant.sales-sources.compare', $this->shared('compare') + compact(
            'months', 'month', 'paginator', 'totals', 'q', 'status', 'sort', 'setuMonthsCached', 'setuSyncedAt', 'setuBehindDays', 'tallyLast'
        ) + ['per' => $per, 'tolerance' => self::TOLERANCE_LAKH]);
    }

    // ------------------------------------------------------ tally (outstanding, orders)

    /** Receivables/payables per party with ageing buckets (ageing is as of today). */
    public function outstanding(Request $request)
    {
        $this->authorizeAccess();

        $group = (string) $request->input('group', 'Sundry Debtors');
        $q = trim((string) $request->input('q', ''));
        $per = $this->perPage($request);
        $page = LengthAwarePaginator::resolveCurrentPage();

        // Three grouped scans of the whole outstanding list; cached per import and day (ageing is counted from today), so a click is instant.
        $data = TallyAnalytics::memo('outstanding.' . md5(json_encode([$group, $q, $page, $per])), function () use ($group, $q, $per) {
            $groups = DB::table('tally_outstanding')->selectRaw('ledger_group, COUNT(*) AS c')->groupBy('ledger_group')->orderByDesc('c')->limit(12)->pluck('c', 'ledger_group');

            $base = DB::table('tally_outstanding')->where('ledger_group', $group)
                ->when($q !== '', fn($w) => $w->where(fn($x) => $x->where('party_name', 'like', "%{$q}%")->orWhere('party_code', 'like', "%{$q}%")));

            $totals = (clone $base)->selectRaw("COALESCE(SUM(amount), 0) AS total, COUNT(*) AS bills,
                    COUNT(DISTINCT COALESCE(party_code, normalized_name)) AS parties,
                    COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), bill_date) > 90 THEN amount ELSE 0 END), 0) AS over90")->first();

            $parties = (clone $base)->selectRaw("COALESCE(party_code, CONCAT('n:', normalized_name)) AS pkey, MAX(party_code) AS party_code, MAX(party_name) AS party_name,
                    COUNT(*) AS bills, SUM(amount) AS total,
                    SUM(CASE WHEN DATEDIFF(CURDATE(), bill_date) <= 30 THEN amount ELSE 0 END) AS a0,
                    SUM(CASE WHEN DATEDIFF(CURDATE(), bill_date) BETWEEN 31 AND 60 THEN amount ELSE 0 END) AS a1,
                    SUM(CASE WHEN DATEDIFF(CURDATE(), bill_date) BETWEEN 61 AND 90 THEN amount ELSE 0 END) AS a2,
                    SUM(CASE WHEN DATEDIFF(CURDATE(), bill_date) > 90 THEN amount ELSE 0 END) AS a3,
                    MAX(DATEDIFF(CURDATE(), bill_date)) AS oldest")
                ->groupBy('pkey')->orderByRaw('SUM(amount) DESC')->paginate($per);

            return ['groups' => $groups, 'totals' => $totals, 'items' => $parties->items(), 'total' => $parties->total()];
        });

        $groups = $data['groups'];
        $totals = $data['totals'];
        $parties = new LengthAwarePaginator($data['items'], $data['total'], $per, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return view('tenant.sales-sources.outstanding', $this->shared('outstanding') + compact('parties', 'totals', 'groups', 'group', 'q'));
    }

    public function outstandingBills(Request $request)
    {
        $this->authorizeAccess();
        $key = (string) $request->input('key');

        $bills = DB::table('tally_outstanding')
            ->where('ledger_group', (string) $request->input('group', 'Sundry Debtors'))
            ->when(str_starts_with($key, 'n:'), fn($w) => $w->where('normalized_name', substr($key, 2)), fn($w) => $w->where('party_code', $key))
            ->orderBy('bill_date')
            ->get(['bill_ref', 'bill_date', 'amount', 'credit_days'])
            ->map(fn($b) => [
                'bill_ref' => $b->bill_ref, 'date' => \Carbon\Carbon::parse($b->bill_date)->format('d M Y'),
                'age' => (int) \Carbon\Carbon::parse($b->bill_date)->startOfDay()->diffInDays(now()->startOfDay()), 'amount' => (float) $b->amount, 'credit_days' => $b->credit_days,
            ]);

        return response()->json(['bills' => $bills]);
    }

    public function orders(Request $request)
    {
        $this->authorizeAccess();

        $q = trim((string) $request->input('q', ''));
        $status = (string) $request->input('status', '');

        $query = TallySaleOrder::query()
            ->when($status !== '', fn($w) => $w->where('status', $status))
            ->when($q !== '', fn($w) => $w->where(fn($x) => $x->where('party_name', 'like', "%{$q}%")->orWhere('order_no', 'like', "%{$q}%")->orWhere('party_code', 'like', "%{$q}%")));

        $totals = (clone $query)->selectRaw('COUNT(*) AS n, COUNT(DISTINCT order_no) AS orders, COALESCE(SUM(amount), 0) AS amount')->first();
        $orders = $query->orderByDesc('order_date')->orderByDesc('id')->paginate($this->perPage($request))->withQueryString();
        $statuses = DB::table('tally_sale_orders')->whereNotNull('status')->distinct()->orderBy('status')->pluck('status');

        return view('tenant.sales-sources.orders', $this->shared('orders') + compact('orders', 'totals', 'statuses', 'q', 'status'));
    }

    // --------------------------------------------------------------------- setu

    /** What Setu says, from the local cache -- so it can sit next to Tally. */
    public function setu(Request $request)
    {
        $this->authorizeAccess();

        $q = trim((string) $request->input('q', ''));
        $months = SetuCustomer::pluck('recent_months')->flatMap(fn($m) => array_keys($m ?? []))->unique()->sort()->values()->all();

        $customers = SetuCustomer::query()
            ->when($q !== '', fn($w) => $w->where('name', 'like', "%{$q}%"))
            ->orderByDesc('recent_total')->paginate($this->perPage($request))->withQueryString();

        $norms = $customers->pluck('normalized_name')->all();
        $inTally = DB::table('tally_invoices')->whereIn('normalized_name', $norms)->where('is_branch_transfer', 0)
            ->selectRaw('normalized_name, MIN(party_code) AS party_code')->groupBy('normalized_name')->pluck('party_code', 'normalized_name');
        $hrx = Customer::whereIn('normalized_name', $norms)->get(['normalized_name', 'setu_match_status'])->keyBy('normalized_name');

        return view('tenant.sales-sources.setu', $this->shared('setu') + compact('customers', 'months', 'inTally', 'hrx', 'q'));
    }
}
