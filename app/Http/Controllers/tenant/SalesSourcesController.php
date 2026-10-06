<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SetuCustomer;
use App\Models\TallyImport;
use App\Models\TallySaleOrder;
use App\Services\CompanySales;
use App\Services\CrmCustomerResolver;
use App\Services\TallyAnalytics;
use App\Support\NameMatcher;
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
        abort_unless(\App\Http\Middleware\RestrictTallyAccess::permits($user), 404);   // the named viewer(s) only
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
            'lastSync' => TallyImport::latestSync(),
            'pageConfigs' => ['contentLayout' => 'wide'],
        ];
    }

    // ------------------------------------------------------------------ compare

    public function compare(Request $request, CrmCustomerResolver $crm)
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
            'agree' => count(array_filter($rows, fn($r) => !$r['differs'] && $r['tally'] !== null && $r['setu'] !== null && $r['manual'] !== null)),
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

        // Names that certainly belong to one CRM customer link straight to its Customer Profile; the rest go to findCustomer().
        $profileIds = $crm->resolve(array_column($paginator->items(), 'key'));

        return view('tenant.sales-sources.compare', $this->shared('compare') + compact(
            'months', 'month', 'paginator', 'totals', 'q', 'status', 'sort', 'setuMonthsCached', 'setuSyncedAt', 'setuBehindDays', 'tallyLast', 'profileIds'
        ) + ['per' => $per, 'tolerance' => self::TOLERANCE_LAKH]);
    }

    /**
     * Company profile for a name on these pages. A name that certainly belongs
     * to one CRM customer goes straight to that Customer Profile (team,
     * contacts, calls). Any other company gets a profile built from what HRX
     * holds about it -- Tally, Setu and the Sales Report -- with the CRM
     * look-alikes listed for a person to pick from. Read-only.
     */
    public function company(Request $request, CrmCustomerResolver $crm, CompanySales $sales)
    {
        $this->authorizeAccess();

        $name = trim((string) $request->input('name', ''));
        $key = NameMatcher::normalize($name);
        abort_if($key === '', 404);

        if ($id = ($crm->resolve([$key])[$key] ?? null)) {
            return redirect()->route('crm-clients.profile', $id);
        }

        $co = $sales->for([$key]);
        $candidates = $crm->candidates($name);
        // Ledger-master facts (credit limit, GSTIN, PAN, contact) -- a separate sync from the voucher data
        // above (see hrx:tally-push-parties), so it can be missing even when Tally sales exist.
        $party = DB::table('tally_parties')->where('normalized_name', $key)->first();

        return view('tenant.sales-sources.company', $this->shared('compare') + [
            'name' => $co['name'] ?: $name,
            'co' => $co,
            'party' => $party,
            'candidates' => $candidates,
            'sameName' => count(array_filter($candidates, fn($c) => $c['why'] === 'same')),
            'back' => str_contains((string) url()->previous(), '/sales-sources') && !str_contains((string) url()->previous(), '/sales-sources/company')
                ? url()->previous() : route('sales-sources.index'),
        ]);
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

        // Dispatched/pending per order, for just this page's orders -- matched from tally_deliveries (Delivery
        // Note vouchers) by order_no, grouped by unit since one order can carry more than one (a straight qty
        // subtraction across different units would be meaningless). No dispatch/due DATE exists anywhere in
        // Tally for this company (checked directly on both voucher types), so this shows what HAS actually
        // gone out and what hasn't -- never an estimated or expected date, which would be a guess presented as fact.
        // Tally's unit field is free text (no fixed list), and the same physical unit can be entered
        // differently on an order vs. the delivery note raised against it (e.g. "No" vs "Nos") -- grouping by
        // the exact string would then show a fully-dispatched order as pending. unitKey() folds that kind of
        // spelling drift together for MATCHING only; the original text is still what's displayed.
        $unitKey = fn(?string $u) => $u === null ? '' : rtrim(mb_strtoupper(trim($u)), 'S');
        $orderNos = $orders->pluck('order_no')->unique()->filter()->values();
        $dispatch = [];
        if ($orderNos->isNotEmpty()) {
            $orderedRaw = DB::table('tally_sale_orders')->whereIn('order_no', $orderNos)->get(['order_no', 'unit', 'quantity']);
            $deliveredRaw = DB::table('tally_deliveries')->whereIn('order_no', $orderNos)->get(['order_no', 'unit', 'quantity', 'delivery_no', 'delivery_date']);

            $orderedByUnit = $orderedRaw->groupBy(fn($r) => $r->order_no . '|' . $unitKey($r->unit))
                ->map(fn($rows) => ['unit' => $rows->first()->unit, 'qty' => $rows->sum('quantity')]);
            $deliveredByUnit = $deliveredRaw->groupBy(fn($r) => $r->order_no . '|' . $unitKey($r->unit))
                ->map(fn($rows) => ['qty' => $rows->sum('quantity'), 'last_date' => $rows->max('delivery_date'), 'notes' => $rows->pluck('delivery_no')->unique()->count()]);
            $notesByOrder = $deliveredRaw->groupBy('order_no')->map(fn($rows) => $rows->pluck('delivery_no')->unique()->count());
            $lastDateByOrder = $deliveredRaw->groupBy('order_no')->map(fn($rows) => $rows->max('delivery_date'));

            // preserveKeys=true: without it, groupBy() re-indexes each group to 0,1,2... and the composite
            // "orderNo|unitKey" key each $line needs for the $deliveredByUnit lookup just below is lost.
            foreach ($orderedByUnit->groupBy(fn($v, $k) => explode('|', $k)[0], true) as $orderNo => $lines) {
                $parts = [];
                $dispatchedAny = false;
                foreach ($lines as $key => $line) {
                    $ordered = (float) $line['qty'];
                    $d = $deliveredByUnit->get($key);
                    $dispatched = $d ? (float) $d['qty'] : 0.0;
                    if ($dispatched > 0) {
                        $dispatchedAny = true;
                    }
                    $pending = max(0, $ordered - $dispatched);
                    $parts[] = ['unit' => $line['unit'], 'ordered' => $ordered, 'dispatched' => $dispatched, 'pending' => $pending];
                }
                $dispatch[$orderNo] = [
                    'parts' => $parts, 'has_dispatch_data' => $dispatchedAny,
                    'notes' => (int) ($notesByOrder->get($orderNo) ?? 0), 'last_date' => $lastDateByOrder->get($orderNo),
                    'fully_dispatched' => $parts !== [] && collect($parts)->every(fn($p) => $p['pending'] <= 0.0001),
                ];
            }
        }

        return view('tenant.sales-sources.orders', $this->shared('orders') + compact('orders', 'totals', 'statuses', 'q', 'status', 'dispatch'));
    }

    /** Every Delivery Note raised against one order, for the drill-down modal on the Orders page. */
    public function orderDispatch(Request $request)
    {
        $this->authorizeAccess();
        $orderNo = (string) $request->input('order_no');
        abort_if($orderNo === '', 404);

        $ordered = DB::table('tally_sale_orders')->where('order_no', $orderNo)
            ->selectRaw('unit, SUM(quantity) AS qty')->groupBy('unit')->get();
        $deliveries = DB::table('tally_deliveries')->where('order_no', $orderNo)
            ->orderBy('delivery_date')->orderBy('delivery_no')
            ->get(['delivery_date', 'delivery_no', 'item_name', 'quantity', 'unit', 'transporter_name', 'vehicle_number', 'transport_mode', 'document_number', 'document_date', 'consignee_name', 'consignee_address', 'consignee_gstin']);

        // Group the flat SKU-line rows into one entry per delivery note for display.
        $notes = $deliveries->groupBy('delivery_no')->map(function ($lines) {
            $first = $lines->first();
            return [
                'delivery_no' => $first->delivery_no, 'delivery_date' => $first->delivery_date,
                'transporter_name' => $first->transporter_name, 'vehicle_number' => $first->vehicle_number,
                'transport_mode' => $first->transport_mode, 'document_number' => $first->document_number, 'document_date' => $first->document_date,
                'consignee_name' => $first->consignee_name, 'consignee_address' => $first->consignee_address, 'consignee_gstin' => $first->consignee_gstin,
                'items' => $lines->map(fn($l) => ['item' => $l->item_name, 'qty' => (float) $l->quantity, 'unit' => $l->unit])->values(),
            ];
        })->values();

        return response()->json([
            'order_no' => $orderNo,
            'ordered' => $ordered->map(fn($o) => ['unit' => $o->unit, 'qty' => (float) $o->qty]),
            'notes' => $notes,
        ]);
    }

    // ------------------------------------------------------------------ unmapped customers

    /**
     * Real Tally customers (branch transfers excluded) with no certain CRM match -- the "invoicing someone
     * with no salesperson/ccare/finance contact on file" gap. Ranked by sales value so the highest-value
     * accounts get onboarded into CRM first. A creatable CrmClientMapping needs a crm_company_code that only
     * the real CRM/lead system can supply, so this deliberately doesn't try to auto-create one -- it surfaces
     * WHO needs a mapping, and the best-guess CRM name (if any) worth double-checking as a possible near-match
     * before assuming a new record is needed.
     */
    public function unmapped(Request $request)
    {
        $this->authorizeAccess();

        $q = trim((string) $request->input('q', ''));
        $per = $this->perPage($request);
        $page = LengthAwarePaginator::resolveCurrentPage();

        $data = TallyAnalytics::memo('unmapped.' . md5(json_encode([$q, $page, $per])), function () use ($q, $per, $page) {
            $customers = DB::table('tally_invoices')->where('is_branch_transfer', 0)->whereNotNull('normalized_name')->where('normalized_name', '!=', '')
                ->when($q !== '', fn($w) => $w->where('party_name', 'like', "%{$q}%"))
                ->groupBy('normalized_name')
                ->selectRaw('normalized_name, MAX(party_name) AS name, SUM(amount) AS total, COUNT(*) AS invoices, MIN(invoice_date) AS first_date, MAX(invoice_date) AS last_date')
                ->orderByDesc('total')->get();

            // One fetch of crm_client_mappings for the whole page: passed into resolve() (instead of letting it
            // fetch again internally) and reused for near-match scoring below, rather than querying the same
            // ~3,000+ rows twice.
            $resolver = app(CrmCustomerResolver::class);
            $crmNames = $resolver->crmNames();
            $matched = $resolver->resolve($customers->pluck('normalized_name')->all(), $crmNames);
            $unmatched = $customers->reject(fn($c) => isset($matched[$c->normalized_name]))->values();

            $crmNormalized = collect($crmNames)
                ->map(fn($m) => ['id' => $m['id'], 'name' => $m['name'], 'norm' => NameMatcher::normalize($m['name'])])
                ->filter(fn($m) => $m['norm'] !== '');

            $slice = $unmatched->forPage($page, $per)->values();
            $rows = $slice->map(function ($c) use ($crmNormalized) {
                // Same ranking CrmCustomerResolver::candidates() uses on the Company page, so the two screens
                // never disagree about whether a name is a likely match: same > near (>=85% similar) > part
                // (one name is the other with something added/removed on the end, e.g. "KISWOK" / "KISWOKUNIT1").
                $best = null;
                $bestRank = 3;
                foreach ($crmNormalized as $m) {
                    similar_text($c->normalized_name, $m['norm'], $pct);
                    $rank = $pct >= 99.9 ? 0 : ($pct >= 85.0 ? 1 : (CrmCustomerResolver::isPartOf($c->normalized_name, $m['norm']) ? 2 : null));
                    if ($rank !== null && ($rank < $bestRank || ($rank === $bestRank && $pct > ($best['score'] ?? 0)))) {
                        $bestRank = $rank;
                        $best = ['id' => $m['id'], 'name' => $m['name'], 'score' => round($pct, 1)];
                    }
                }
                return [
                    'name' => $c->name, 'total' => (float) $c->total, 'invoices' => (int) $c->invoices,
                    'first_date' => $c->first_date, 'last_date' => $c->last_date, 'near' => $best,
                ];
            });

            return [
                'rows' => $rows->all(), 'total' => $unmatched->count(),
                'value' => (float) $unmatched->sum('total'), 'universe' => $customers->count(), 'mapped' => $customers->count() - $unmatched->count(),
            ];
        });

        $rows = new LengthAwarePaginator($data['rows'], $data['total'], $per, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return view('tenant.sales-sources.unmapped', $this->shared('unmapped') + compact('rows', 'q') + [
            'unmappedValue' => $data['value'], 'universe' => $data['universe'], 'mapped' => $data['mapped'],
        ]);
    }

    // ------------------------------------------------------------------ credit limits & party master

    /**
     * Credit limit, GSTIN, PAN and contact per customer -- from Tally's own ledger master
     * (hrx:tally-push-parties), not derived from invoices. A separate, smaller sync from the day-to-day
     * voucher pipeline (see tally_parties migration doc comment), so a customer can have Tally sales but no
     * row here yet, or vice versa.
     */
    public function parties(Request $request)
    {
        $this->authorizeAccess();

        $q = trim((string) $request->input('q', ''));
        $filter = (string) $request->input('filter', ''); // '' | 'credit' | 'no_credit' | 'gstin' | 'no_gstin'
        $sort = (string) $request->input('sort', 'credit_limit');
        $per = $this->perPage($request);

        $query = DB::table('tally_parties')
            ->when($q !== '', fn($w) => $w->where('party_name', 'like', "%{$q}%"))
            ->when($filter === 'credit', fn($w) => $w->whereNotNull('credit_limit'))
            ->when($filter === 'no_credit', fn($w) => $w->whereNull('credit_limit'))
            ->when($filter === 'gstin', fn($w) => $w->whereNotNull('gstin'))
            ->when($filter === 'no_gstin', fn($w) => $w->whereNull('gstin'));

        $totals = (clone $query)->selectRaw('COUNT(*) AS n, COUNT(credit_limit) AS with_credit, SUM(credit_limit) AS credit_sum, COUNT(gstin) AS with_gstin, COUNT(contact_person) AS with_contact')->first();

        $byName = $sort === 'name';
        $parties = (clone $query)
            ->when(!$byName, fn($w) => $w->orderByRaw('credit_limit IS NULL')->orderByDesc('credit_limit'))
            ->when($byName, fn($w) => $w->orderBy('party_name'))
            ->paginate($per)->withQueryString();

        // Tally party codes, where the file import happens to have one for the same customer -- a convenience
        // cross-reference, not this page's own data (tally_parties has no code column; see project memory on
        // why Tally's party_code isn't retrievable via the API).
        $codes = DB::table('tally_invoices')->whereIn('normalized_name', $parties->pluck('normalized_name'))
            ->whereNotNull('party_code')->selectRaw('normalized_name, MIN(party_code) AS code')
            ->groupBy('normalized_name')->pluck('code', 'normalized_name');

        return view('tenant.sales-sources.parties', $this->shared('parties') + compact('parties', 'totals', 'codes', 'q', 'filter', 'sort'));
    }

    // ------------------------------------------------------------------ team

    /**
     * Every employee who holds a salesperson/ccare/new-biz/billing/finance role on at least one CRM account,
     * with how many accounts they hold in each role -- a directory view over what CrmClientMapping already
     * stores; each row links to the existing per-employee client list (crm-clients.employee). Complements
     * unmapped(): that page shows which CUSTOMERS have no one assigned, this shows which EMPLOYEES do the
     * assigning and how thin coverage still is (2.1% of CRM records have a salesperson, checked earlier today).
     */
    public function team(Request $request)
    {
        $this->authorizeAccess();

        $q = trim((string) $request->input('q', ''));
        $roleCols = ['salesperson_id' => 'Salesperson', 'ccare_id' => 'CCare', 'new_biz_id' => 'New Biz', 'billing_id' => 'Billing', 'finance_id' => 'Finance'];

        $counts = [];
        foreach ($roleCols as $col => $label) {
            foreach (DB::table('crm_client_mappings')->whereNull('deleted_at')->whereNotNull($col)->groupBy($col)->selectRaw("{$col} AS uid, COUNT(*) AS n")->get() as $r) {
                $counts[$r->uid][$col] = (int) $r->n;
            }
        }

        $users = DB::table('users')->whereIn('id', array_keys($counts))
            ->when($q !== '', fn($w) => $w->where(fn($x) => $x->where('first_name', 'like', "%{$q}%")->orWhere('last_name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")))
            ->get(['id', 'first_name', 'last_name', 'email']);

        $rows = $users->map(function ($u) use ($counts, $roleCols) {
            $c = $counts[$u->id] ?? [];
            $total = array_sum($c);
            return [
                'id' => $u->id, 'name' => trim("{$u->first_name} {$u->last_name}") ?: $u->email, 'email' => $u->email,
                'counts' => array_map(fn($col) => $c[$col] ?? 0, array_keys($roleCols)), 'total' => $total,
            ];
        })->sortByDesc('total')->values();

        $totals = [
            'employees' => $rows->count(),
            'accounts_with_any_role' => DB::table('crm_client_mappings')->whereNull('deleted_at')
                ->where(fn($w) => $w->whereNotNull('salesperson_id')->orWhereNotNull('ccare_id')->orWhereNotNull('new_biz_id')->orWhereNotNull('billing_id')->orWhereNotNull('finance_id'))->count(),
            'accounts_total' => DB::table('crm_client_mappings')->whereNull('deleted_at')->count(),
        ];

        return view('tenant.sales-sources.team', $this->shared('team') + compact('rows', 'roleCols', 'totals', 'q'));
    }

    // ------------------------------------------------------------------ finance / receivables

    /**
     * Every outstanding bill, grouped by customer: who owns it on the finance side, how old it is (per-bill
     * exact age, not a bucket midpoint), and an interest-cost estimate on the overdue money.
     *
     * The interest RATE is not stored anywhere -- Tally doesn't carry one and nothing in HRX tracks a cost of
     * capital -- so it is a plain adjustable input on this page (?rate=, default 12% p.a., a common Indian
     * short-term borrowing rate) rather than a guessed constant presented as fact. The figure it produces is
     * clearly labelled as an estimate at whatever rate is currently entered, recalculated live when it changes.
     */
    public function finance(Request $request)
    {
        $this->authorizeAccess();

        $rate = (float) $request->input('rate', 12);
        $rate = max(0.0, min(100.0, $rate));
        $q = trim((string) $request->input('q', ''));
        $per = $this->perPage($request);

        $bills = DB::table('tally_outstanding')->where('ledger_group', 'Sundry Debtors')
            ->get(['normalized_name', 'party_name', 'party_code', 'bill_ref', 'bill_date', 'amount']);

        // Finance person per customer, matched by normalized name -- the same key tally_outstanding already
        // carries, so no fuzzy matching is needed here (unlike unmapped(), which is finding names with NO
        // existing link at all).
        $financeByNorm = [];
        foreach (DB::table('crm_client_mappings')->whereNull('deleted_at')->whereNotNull('finance_id')->get(['crm_company_name', 'finance_id']) as $m) {
            $n = NameMatcher::normalize((string) $m->crm_company_name);
            if ($n !== '') {
                $financeByNorm[$n] = (int) $m->finance_id;
            }
        }
        $financeUsers = DB::table('users')->whereIn('id', array_values(array_unique($financeByNorm)))->get(['id', 'first_name', 'last_name'])->keyBy('id');

        $today = now()->startOfDay();
        $byParty = [];
        foreach ($bills as $b) {
            if ($b->bill_date === null || $b->amount === null) {
                continue;
            }
            $age = (int) \Carbon\Carbon::parse($b->bill_date)->startOfDay()->diffInDays($today);
            $amount = (float) $b->amount;
            $interest = $amount * ($age / 365) * ($rate / 100);
            $bucket = $age <= 30 ? 'b0' : ($age <= 60 ? 'b1' : ($age <= 90 ? 'b2' : 'b3'));

            $key = $b->normalized_name ?: ('n:' . $b->party_name);
            if (!isset($byParty[$key])) {
                $fid = $financeByNorm[$b->normalized_name] ?? null;
                $byParty[$key] = [
                    'name' => $b->party_name, 'party_code' => $b->party_code, 'normalized_name' => $b->normalized_name,
                    'finance_id' => $fid, 'finance_name' => $fid && $financeUsers->has($fid) ? trim("{$financeUsers[$fid]->first_name} {$financeUsers[$fid]->last_name}") : null,
                    'bills' => 0, 'due' => 0.0, 'interest' => 0.0, 'oldest' => 0, 'b0' => 0.0, 'b1' => 0.0, 'b2' => 0.0, 'b3' => 0.0,
                    'last_payment_date' => null, 'last_payment_amount' => null, 'days_since_payment' => null,
                ];
            }
            $byParty[$key]['bills']++;
            $byParty[$key]['due'] += $amount;
            $byParty[$key]['interest'] += $interest;
            $byParty[$key]['oldest'] = max($byParty[$key]['oldest'], $age);
            $byParty[$key][$bucket] += $amount;
        }

        // Last customer payment per party, from Receipt vouchers pulled straight off Tally's API (tally_receipts)
        // -- the file-based import never carried this at all, so it's purely additive: never overwrites, only
        // fills in what dues-only data can't show, i.e. whether a customer with a big balance is actually paying.
        $lastPayment = DB::table('tally_receipts')
            ->select('normalized_name', 'receipt_date', 'amount')
            ->whereNotNull('normalized_name')->where('normalized_name', '!=', '')
            ->orderByDesc('receipt_date')
            ->get()
            ->groupBy('normalized_name')
            ->map(fn($rows) => ['date' => $rows->first()->receipt_date, 'amount' => (float) $rows->first()->amount])
            ->all();

        foreach ($byParty as &$p) {
            $lp = $lastPayment[$p['normalized_name']] ?? null;
            if ($lp) {
                $p['last_payment_date'] = $lp['date'];
                $p['last_payment_amount'] = $lp['amount'];
                $p['days_since_payment'] = (int) \Carbon\Carbon::parse($lp['date'])->startOfDay()->diffInDays($today);
            }
        }
        unset($p);

        if ($q !== '') {
            $byParty = array_filter($byParty, fn($p) => stripos($p['name'], $q) !== false || ($p['finance_name'] && stripos($p['finance_name'], $q) !== false));
        }

        $rows = collect($byParty)->sortByDesc('due')->values();
        $totals = [
            'parties' => $rows->count(), 'due' => $rows->sum('due'), 'interest' => $rows->sum('interest'),
            'over90' => $rows->sum('b3'), 'unassigned' => $rows->filter(fn($p) => !$p['finance_name'])->count(),
            'collected_30d' => (float) DB::table('tally_receipts')->where('receipt_date', '>=', $today->copy()->subDays(30)->toDateString())->sum('amount'),
            'collected_mtd' => (float) DB::table('tally_receipts')->where('receipt_date', '>=', $today->copy()->startOfMonth()->toDateString())->sum('amount'),
        ];

        $page = LengthAwarePaginator::resolveCurrentPage();
        $paged = new LengthAwarePaginator($rows->forPage($page, $per)->values(), $rows->count(), $per, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return view('tenant.sales-sources.finance', $this->shared('finance') + compact('paged', 'totals', 'q', 'rate'));
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
