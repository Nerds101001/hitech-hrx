<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Work Orders (= Tally Sales Orders) -- the first module of the Tally feature-request list from the
 * 2026-10-17 "what can we make" conversation. Built strictly on what a raw Tally voucher probe that day
 * confirmed actually exists: item-level order detail (item/HSN/rate/qty/amount, all 100% populated),
 * pending qty (NOT a Tally field -- computed by matching tally_deliveries, same approach already proven on
 * the Sale Orders page), and order-to-billing / order-to-delivery lead time (computed from dates already in
 * tally_sale_orders/tally_invoices/tally_deliveries). Production Approval does not exist anywhere in Tally
 * (confirmed absent from every voucher of every type) -- but Financial Approval DOES exist (UDF:
 * UDFFINANCIALAPPROVAL, voucher-level), found on a second pass 2026-10-19 after an initial scan missed it
 * (it's wrapped in a repeatable .LIST whose namespace this export actually declares -- see
 * TallyGateway::udf()'s doc comment for the full story). Surfaced here as the Financial tab.
 *
 * Tracking details for a completed (fully dispatched) order can be edited here even though Tally itself
 * barely uses tracking numbers for this company (confirmed "Not Applicable" on every voucher checked) --
 * edits go to work_order_tracking_notes, a table the sync pipeline never touches, so they survive the next
 * 2-hourly push instead of being silently wiped like a column on tally_deliveries would be.
 */
class WorkOrdersController extends Controller
{
    private function authorizeAccess(): void
    {
        $user = Auth::user();
        abort_unless($user && $user->hasRole(['admin', 'Admin', 'super_admin', 'hr', 'HR', 'accounts']), 403);
        abort_unless(\App\Http\Middleware\RestrictTallyAccess::permits($user), 404);
    }

    private function company(Request $request): ?string
    {
        $c = (string) $request->input('company', 'hitech');
        return in_array($c, ['hitech', 'kif'], true) ? $c : null; // null = both companies, unscoped
    }

    private function perPage(Request $request): int
    {
        return in_array((int) $request->input('per'), [25, 50, 100, 200], true) ? (int) $request->input('per') : 50;
    }

    /** Order-number prefix -> real-world unit name, confirmed with the user 2026-10-19 (not guessed). A
     *  prefix not in this map (a rare manual-entry typo/variant -- 23 distinct prefixes exist, these 10
     *  cover all but ~80 of 21,810 real orders) falls back to showing the raw prefix instead of a label. */
    public const UNIT_PREFIXES = [
        'W-RG' => 'Ramgarh', 'W-PG' => 'Patalganga', 'M99W' => 'Manesar 99', 'M18W' => 'Manesar 18',
        'W-CH' => 'Chennai', 'W-LD' => 'Jamalpur', 'W-GJ' => 'Ahmedabad', 'W-SL' => 'Sahnewal',
        'KRW' => 'KIF Ramgarh', 'KMW' => 'KIF Manesar',
    ];

    private function unitPrefix(string $orderNo): string
    {
        if (preg_match('/^M(\d{2})W/', $orderNo, $m)) {
            return 'M' . $m[1] . 'W';
        }
        if (preg_match('/^W-([A-Za-z]{2,3})\d/', $orderNo, $m)) {
            return 'W-' . $m[1];
        }
        return preg_match('/^([A-Za-z]+(?:-[A-Za-z]+)?)/', $orderNo, $m) ? $m[1] : '?';
    }

    private function unitLabel(string $orderNo): string
    {
        $p = $this->unitPrefix($orderNo);
        return self::UNIT_PREFIXES[$p] ?? $p;
    }

    /**
     * The page shell only -- filter choices and the four tabs' badge counts. Every tab (including All/
     * Pending/Dispatch, not just Financial) now loads its stats/charts/table the same way: an empty
     * container painted here, filled a moment later by panelData()/financialData() as JSON. This replaced
     * an earlier version that server-rendered All/Pending/Dispatch as a plain table with no charts --
     * kept only long enough to prove the page before the user asked for the same treatment everywhere.
     */
    public function index(Request $request)
    {
        $this->authorizeAccess();

        $company = $this->company($request);
        // "subTab" (not "tab") -- the shared sales-sources layout already uses $tab for its own top-level
        // nav highlighting (analytics/compare/orders/...); this page's internal All/Pending/Dispatch/
        // Financial switch needs a name of its own or it would silently fight that variable.
        $subTab = in_array($request->input('tab'), ['all', 'pending', 'dispatch', 'financial'], true) ? $request->input('tab') : 'all';
        $q = trim((string) $request->input('q', ''));
        $unitCase = $this->unitCaseSql();

        $orderedAgg = DB::table('tally_sale_orders')
            ->when($company, fn($w) => $w->where('company', $company))
            ->groupBy('order_no')
            ->selectRaw('order_no, SUM(quantity) AS ordered_qty');
        $deliveredAgg = DB::table('tally_deliveries')->groupBy('order_no')->selectRaw('order_no, SUM(quantity) AS delivered_qty');
        $base = fn() => DB::query()->fromSub($orderedAgg, 'o')->leftJoinSub($deliveredAgg, 'd', 'd.order_no', '=', 'o.order_no');
        $applyTab = fn($q2, string $t) => match ($t) {
            'pending' => $q2->whereRaw('COALESCE(d.delivered_qty, 0) < o.ordered_qty'),
            'dispatch' => $q2->whereRaw('d.delivered_qty IS NOT NULL')->whereRaw('d.delivered_qty >= o.ordered_qty'),
            default => $q2,
        };

        $allOrdersCount = (clone $base())->count();
        $pendingCount = $applyTab(clone $base(), 'pending')->count();
        $dispatchCount = $applyTab(clone $base(), 'dispatch')->count();
        // Badge count for the Financial tab: orders On Hold right now -- the ones actually needing attention,
        // not a count of everything (every order eventually gets a financial_approval value).
        $financialHoldCount = DB::table('tally_sale_orders')->when($company, fn($w) => $w->where('company', $company))
            ->where('financial_approval', 'Make and Hold')->distinct('order_no')->count('order_no');

        return view('tenant.sales-sources.work-orders', [
            'tab' => 'work-orders', 'subTab' => $subTab, 'company' => $company, 'q' => $q,
            'allOrdersCount' => $allOrdersCount, 'pendingCount' => $pendingCount, 'dispatchCount' => $dispatchCount,
            'financialHoldCount' => $financialHoldCount,
            'unitPrefixes' => self::UNIT_PREFIXES,
            'categories' => DB::table('tally_stock_items')->when($company, fn($w) => $w->where('company', $company))
                ->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'lastImport' => \App\Models\TallyImport::latestGood(),
            'lastSync' => \App\Models\TallyImport::latestSync(),
            'pageConfigs' => ['contentLayout' => 'wide'],
            'charts' => true,
        ]);
    }

    /**
     * The All/Pending/Dispatch tabs' data: stats, value-by-day trend, value-by-unit donut, a dispatch-
     * status donut (dispatched / partially dispatched / not dispatched, by value), a pending-by-customer
     * rollup, and the paginated table. The table is now ITEM-LINE level (one row per order line: item name,
     * stock group, ordered qty, amount, financial approval/remarks), not one row per order -- explicit
     * request 2026-10-19: "item wise data... just like [the Tally Partywise Sale Order report]". Dispatch
     * status is matched per LINE (item_name within the order), the same item+order match the item-expand
     * popup already used client-side, now done once in SQL for every row instead of per-click.
     * Same shape and the same reused unitCaseSql() as financialData(), deliberately kept as a SEPARATE
     * query from it -- this one needs the dispatch/delivery join, financialData() needs the credit/
     * outstanding join, and a single query doing both would run both joins on every request.
     */
    public function panelData(Request $request)
    {
        $this->authorizeAccess();

        $company = $this->company($request);
        $tab = in_array($request->input('tab'), ['all', 'pending', 'dispatch'], true) ? $request->input('tab') : 'all';
        $from = $this->date($request->input('from')) ?? now()->subDays(29)->toDateString();
        $to = $this->date($request->input('to')) ?? now()->toDateString();
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        $party = trim((string) $request->input('party', ''));
        $unit = trim((string) $request->input('unit', ''));
        $category = trim((string) $request->input('category', ''));
        $group = trim((string) $request->input('group', ''));
        $per = $this->perPage($request);
        $page = max(1, (int) $request->input('page', 1));
        [$sort, $dir] = $this->sortParams($request, ['order_date', 'order_no', 'party_name', 'unit', 'stock_group', 'amount', 'item_name', 'quantity', 'sales_group'], 'order_date');
        $unitCase = $this->unitCaseSql();

        $base = DB::table('tally_sale_orders AS o')
            ->leftJoin('tally_stock_items AS si', 'si.name', '=', 'o.item_name')
            // tally_ledgers.ledger_group is the customer's immediate ledger group in Tally's own chart of
            // accounts -- for this company that group is routinely named after the salesperson, territory
            // or product line the customer sits under (e.g. "ISHAN SIKKA", "PUNJAB", "Fillezy"), confirmed
            // 2026-10-19 by sampling real values. Not a guess, not a new sync -- tally_ledgers was already
            // pulled (hrx:tally-push-ledgers) for the Credit & Contacts page; this just joins it in here.
            // Joined against a DEDUPED subquery, not the raw table: 103 (company, normalized_name) pairs in
            // tally_ledgers repeat (Tally itself has the odd same-named ledger twice), and a raw join on
            // normalized_name alone would silently multiply those orders' lines in the result.
            ->leftJoinSub($this->salesGroupLedgers(), 'tl', fn($j) => $j->on('tl.normalized_name', '=', 'o.normalized_name')->on('tl.company', '=', 'o.company'))
            ->when($company, fn($w) => $w->where('o.company', $company))
            ->whereBetween('o.order_date', [$from, $to])
            ->when($party !== '', fn($w) => $w->where(fn($w2) => $w2->where('o.party_name', 'like', "%{$party}%")->orWhere('o.order_no', 'like', "%{$party}%")))
            ->when($unit !== '', fn($w) => $w->whereRaw("{$unitCase} = ?", [$unit]))
            ->when($category !== '', fn($w) => $w->where('si.category', $category))
            ->when($group !== '', fn($w) => $w->where('tl.ledger_group', $group));

        $lineRows = (clone $base)->selectRaw(
            "o.id, o.order_no, o.company, o.order_date, o.party_name, o.item_name, o.quantity, o.unit AS line_unit, o.amount, "
            . "o.financial_approval, o.remarks, si.category AS stock_group, tl.ledger_group AS sales_group, {$unitCase} AS unit"
        );

        // Materialized to a plain array, NOT passed as a subquery builder: a whereIn() subquery here re-runs
        // $base (which itself joins stock_items + the ledger-group subquery) as a correlated subquery for
        // every row of tally_deliveries/tally_invoices -- tried that first, made it slower (3s instead of
        // 900ms). Built from the LEAN filteredOrderNumbers() helper, not a clone of $base, so this lookup
        // skips the stock_items/ledger joins entirely in the common case (no stock-group/sales-group filter
        // active) -- that alone cut this query from ~140ms to a few ms.
        $filteredOrderNos = $this->filteredOrderNumbers($company, $from, $to, $party, $unit, $category, $group, $unitCase)->distinct()->pluck('o.order_no');

        // Delivered qty matched per ITEM within the order (not just per order) -- a multi-item order where
        // only some lines have shipped must show THOSE lines as dispatched and the rest as pending, which an
        // order-level sum can't tell apart. transporter_name/vehicle_number/document_number (the docket/LR
        // no.) ride along for the Dispatch tab's two extra columns -- same fields the order modal already
        // shows, usually blank for this company (confirmed earlier), but shown when Tally actually has them.
        $deliveredByItem = DB::table('tally_deliveries')->whereIn('order_no', $filteredOrderNos)->groupBy('order_no', 'item_name')
            ->selectRaw('order_no, item_name, SUM(quantity) AS delivered_qty, MAX(delivery_date) AS last_delivery_date, '
                . 'COUNT(DISTINCT delivery_no) AS delivery_notes, MAX(transporter_name) AS transporter_name, '
                . 'MAX(vehicle_number) AS vehicle_number, MAX(document_number) AS docket_no');
        $billedAgg = DB::table('tally_invoices')->whereNotNull('order_no')->whereIn('order_no', $filteredOrderNos)->groupBy('order_no')
            ->selectRaw('order_no, MIN(invoice_date) AS first_invoice_date, COUNT(*) AS invoice_count');

        $withStatus = DB::query()->fromSub($lineRows, 'x')
            ->leftJoinSub($deliveredByItem, 'd', fn($j) => $j->on('d.order_no', '=', 'x.order_no')->on('d.item_name', '=', 'x.item_name'))
            ->leftJoinSub($billedAgg, 'b', 'b.order_no', '=', 'x.order_no')
            ->selectRaw('x.*, d.delivered_qty, d.last_delivery_date, d.delivery_notes, d.transporter_name, d.vehicle_number, d.docket_no, '
                . 'b.first_invoice_date, b.invoice_count, GREATEST(x.quantity - COALESCE(d.delivered_qty, 0), 0) AS pending_qty');

        $applyTab = fn($q, string $t) => match ($t) {
            'pending' => $q->whereRaw('GREATEST(x.quantity - COALESCE(d.delivered_qty, 0), 0) > 0.0001'),
            'dispatch' => $q->whereRaw('d.delivered_qty IS NOT NULL')->whereRaw('GREATEST(x.quantity - COALESCE(d.delivered_qty, 0), 0) <= 0.0001'),
            default => $q,
        };

        // ONE query for everything below (stats, trend, by-unit, by-dispatch, by-customer, lead time, AND
        // the table), not a separate re-run of the join per section -- see the historical note on this
        // pattern in financialData(). A filtered window is at most a few thousand lines, trivial in memory.
        $all = $applyTab($withStatus, $tab)->get();

        $total = $all->count();
        $rows = $this->sortRows($all, $sort, $dir)->values()
            ->slice(($page - 1) * $per, $per)->values();

        $orderNos = $rows->pluck('order_no')->unique();
        $trackingNotes = DB::table('work_order_tracking_notes')->whereIn('order_no', $orderNos)->get()->keyBy(fn($r) => $r->company . '|' . $r->order_no);
        foreach ($rows as $r) {
            $r->tracking = $trackingNotes[$r->company . '|' . $r->order_no] ?? null;
        }
        // Per-ORDER totals, not per-line -- explicit request: "for each work order total order qty
        // dispatched qty pending qty, and amount for pending order". See orderRollups()'s own doc comment.
        $this->attachOrderRollups($rows);

        $orderCount = $all->pluck('order_no')->unique()->count();
        $dispatchedAmount = $all->filter(fn($r) => $r->delivered_qty !== null && $r->pending_qty <= 0.0001)->sum('amount');
        $pendingAmount = $all->filter(fn($r) => $r->pending_qty > 0.0001)->sum('amount');
        $billedAmount = $all->filter(fn($r) => $r->invoice_count > 0)->sum('amount');
        // Quantity totals for the table's own totals row -- explicit request: "total order qty total
        // pending total sent". Sent = ordered minus whatever's still pending, clamped at 0 the same way
        // pending_qty itself already is (an over-delivery never shows as negative "sent").
        $qtyOrdered = $all->sum(fn($r) => (float) $r->quantity);
        $qtyPending = $all->sum(fn($r) => (float) $r->pending_qty);
        $qtySent = max(0, $qtyOrdered - $qtyPending);

        $trend = $all->groupBy('order_date')->map(fn($g, $d) => ['d' => $d, 'value' => $g->sum('amount'), 'n' => $g->pluck('order_no')->unique()->count()])
            ->sortKeys()->values();
        $byUnit = $all->groupBy('unit')->map(fn($g, $u) => ['unit' => $u, 'value' => $g->sum('amount'), 'n' => $g->pluck('order_no')->unique()->count()])
            ->sortByDesc('value')->values();
        $byGroup = $all->groupBy(fn($r) => $r->sales_group ?: 'Not set')
            ->map(fn($g, $sg) => ['group' => $sg, 'value' => $g->sum('amount'), 'n' => $g->pluck('order_no')->unique()->count()])
            ->sortByDesc('value')->values()->take(20);
        $byDispatch = $all->groupBy(function ($r) {
            if ($r->delivered_qty === null) return 'Not dispatched';
            return $r->pending_qty <= 0.0001 ? 'Dispatched' : 'Partially dispatched';
        })->map(fn($g, $s) => ['status' => $s, 'value' => $g->sum('amount'), 'n' => $g->pluck('order_no')->unique()->count()])->sortByDesc('value')->values();

        // Per-customer pending rollup -- explicit request: "per customer pending order". Only lines with
        // pending_qty > 0 count, regardless of which tab is active, so this card always answers "who is
        // owed what" even while looking at the All or Dispatch tab.
        $pendingLines = $all->filter(fn($r) => $r->pending_qty > 0.0001);
        $byParty = $pendingLines->groupBy('party_name')
            ->map(fn($g, $p) => ['party' => $p, 'value' => $g->sum('amount'), 'orders' => $g->pluck('order_no')->unique()->count(), 'lines' => $g->count()])
            ->sortByDesc('value')->values()->take(100);

        return response()->json([
            'stats' => [
                'orders' => $orderCount, 'lines' => $total, 'total_value' => (float) $all->sum('amount'),
                'dispatched_value' => (float) $dispatchedAmount, 'pending_value' => (float) $pendingAmount,
                'billed_value' => (float) $billedAmount, 'unit_count' => $all->pluck('unit')->unique()->count(),
                'qty_ordered' => $qtyOrdered, 'qty_pending' => $qtyPending, 'qty_sent' => $qtySent,
                'lead_time' => $this->leadTimeFromRows($all->unique('order_no')->values()),
            ],
            'trend' => $trend, 'by_unit' => $byUnit, 'by_group' => $byGroup, 'by_dispatch' => $byDispatch, 'by_party' => $byParty,
            'table' => ['rows' => $rows, 'total' => $total, 'page' => $page, 'per' => $per],
            'unit_labels' => self::UNIT_PREFIXES,
            'sales_groups' => $this->salesGroups(),
        ])->header('Cache-Control', 'private, max-age=30');
    }

    /** Distinct customer-ledger groups -- "Sundry Debtors" only, so the filter list is actual salesperson/
     *  territory/brand buckets (e.g. "ISHAN SIKKA", "PUNJAB", "Fillezy") and not unrelated accounting
     *  groups (Bank Accounts, Direct Expenses, ...) that also live in the same ledger_group column. */
    private function salesGroups()
    {
        return DB::table('tally_ledgers')->where('primary_group', 'Sundry Debtors')
            ->whereNotNull('ledger_group')->where('ledger_group', '!=', '')
            ->distinct()->orderBy('ledger_group')->pluck('ledger_group');
    }

    /** tally_ledgers deduped to one row per (company, normalized_name) -- 103 pairs repeat in the raw table
     *  (Tally itself has the odd same-named ledger entered twice), and joining the raw table on just
     *  normalized_name would silently multiply those customers' order lines in every query that joins it. */
    private function salesGroupLedgers()
    {
        return DB::table('tally_ledgers')->groupBy('company', 'normalized_name')
            ->selectRaw('company, normalized_name, MAX(ledger_group) AS ledger_group');
    }

    /**
     * Per-ORDER rollup (qty/amount/dispatched/pending), for the "Order qty / Order dispatched / Order
     * pending / Order amount / Order pending value" columns -- explicit request: every line of a
     * multi-item order should show the WHOLE order's totals, not just that one line's own numbers, even
     * for lines a tab filter hid (the Pending tab only lists an order's still-pending lines, but its
     * order-level totals still need to count the lines that already shipped).
     *
     * Matched per ITEM within the order (same as the line-level pending_qty everywhere else on this page),
     * not a crude whole-order quantity sum -- a multi-item order where one item shipped in full and
     * another didn't needs its pending VALUE to come from the unshipped item's own rate, not an average
     * across mismatched units. A line's pending share of its own amount is pending_qty/quantity * amount.
     *
     * @param  \Illuminate\Support\Collection<int,string>  $orderNos
     * @return \Illuminate\Support\Collection<string,array{qty:float,amount:float,dispatched_qty:float,pending_qty:float,pending_amount:float}>
     */
    private function orderRollups($orderNos)
    {
        if ($orderNos->isEmpty()) {
            return collect();
        }
        $lines = DB::table('tally_sale_orders')->whereIn('order_no', $orderNos)->get(['order_no', 'item_name', 'quantity', 'amount']);
        $delivered = DB::table('tally_deliveries')->whereIn('order_no', $orderNos)->groupBy('order_no', 'item_name')
            ->selectRaw('order_no, item_name, SUM(quantity) AS delivered_qty')->get()
            ->keyBy(fn($d) => $d->order_no . '|' . $d->item_name);

        return $lines->groupBy('order_no')->map(function ($group) use ($delivered) {
            $qty = 0.0; $amount = 0.0; $pendingQty = 0.0; $pendingAmount = 0.0;
            foreach ($group as $l) {
                $lineQty = (float) $l->quantity;
                $lineAmount = (float) $l->amount;
                $deliveredQty = (float) ($delivered[$l->order_no . '|' . $l->item_name]->delivered_qty ?? 0);
                $linePending = max(0, $lineQty - $deliveredQty);
                $qty += $lineQty;
                $amount += $lineAmount;
                $pendingQty += $linePending;
                $pendingAmount += $lineQty > 0 ? ($linePending / $lineQty) * $lineAmount : 0;
            }
            return [
                'qty' => $qty, 'amount' => $amount, 'dispatched_qty' => max(0, $qty - $pendingQty),
                'pending_qty' => $pendingQty, 'pending_amount' => $pendingAmount,
            ];
        });
    }

    /** Attaches the orderRollups() figures onto each row as order_qty/order_dispatched_qty/
     *  order_pending_qty/order_amount/order_pending_amount. */
    private function attachOrderRollups($rows): void
    {
        $rollups = $this->orderRollups($rows->pluck('order_no')->unique());
        foreach ($rows as $r) {
            $roll = $rollups[$r->order_no] ?? ['qty' => 0, 'amount' => 0, 'dispatched_qty' => 0, 'pending_qty' => 0, 'pending_amount' => 0];
            $r->order_qty = $roll['qty'];
            $r->order_amount = $roll['amount'];
            $r->order_dispatched_qty = $roll['dispatched_qty'];
            $r->order_pending_qty = $roll['pending_qty'];
            $r->order_pending_amount = $roll['pending_amount'];
        }
    }

    /** A LEAN version of the panelData()/financialData() filter -- only joins tally_stock_items when a
     *  category filter is actually active, and only joins the ledger-group subquery when a group filter is
     *  active, instead of always carrying both joins the way the main line-row query does. Used purely to
     *  get the list of matching order numbers so the delivery/invoice aggregates can be scoped to them
     *  instead of grouping the entire 46k/48k-row tables on every request; those two joins are the most
     *  expensive part of the main query, so skipping them here (usually both filters are empty) matters. */
    private function filteredOrderNumbers(?string $company, string $from, string $to, string $party, string $unit, string $category, string $group, string $unitCase, ?string $approval = null)
    {
        $q = DB::table('tally_sale_orders AS o');
        if ($category !== '') {
            $q->join('tally_stock_items AS si', 'si.name', '=', 'o.item_name');
        }
        if ($group !== '') {
            $q->joinSub($this->salesGroupLedgers(), 'tl', fn($j) => $j->on('tl.normalized_name', '=', 'o.normalized_name')->on('tl.company', '=', 'o.company'));
        }
        $q->when($company, fn($w) => $w->where('o.company', $company))
            ->whereBetween('o.order_date', [$from, $to])
            ->when($party !== '', fn($w) => $w->where(fn($w2) => $w2->where('o.party_name', 'like', "%{$party}%")->orWhere('o.order_no', 'like', "%{$party}%")))
            ->when($unit !== '', fn($w) => $w->whereRaw("{$unitCase} = ?", [$unit]))
            ->when($category !== '', fn($w) => $w->where('si.category', $category))
            ->when($group !== '', fn($w) => $w->where('tl.ledger_group', $group))
            ->when($approval !== null && $approval !== '', fn($w) => $w->where('o.financial_approval', $approval));
        return $q;
    }

    /** Avg days order-date -> first-invoice-date, and order-date -> last-delivery-date, over the SAME
     *  already-filtered/fetched row set every other stat on the panel uses -- this is why it now responds
     *  to the date/unit/category filters instead of being a fixed company-wide "last 6 months" number. */
    private function leadTimeFromRows($rows): array
    {
        $billingDays = $rows->filter(fn($r) => $r->first_invoice_date !== null)
            ->map(fn($r) => \Carbon\Carbon::parse($r->order_date)->diffInDays(\Carbon\Carbon::parse($r->first_invoice_date)));
        $deliveryDays = $rows->filter(fn($r) => $r->last_delivery_date !== null)
            ->map(fn($r) => \Carbon\Carbon::parse($r->order_date)->diffInDays(\Carbon\Carbon::parse($r->last_delivery_date)));

        return [
            'to_billing' => ['avg_days' => $billingDays->isEmpty() ? null : round($billingDays->avg(), 1), 'n' => $billingDays->count()],
            'to_delivery' => ['avg_days' => $deliveryDays->isEmpty() ? null : round($deliveryDays->avg(), 1), 'n' => $deliveryDays->count()],
        ];
    }

    /** Average days order-date -> first-invoice-date, and order-date -> first-delivery-date, over the last 6 months. */
    private function leadTimeStats(?string $company): array
    {
        $since = now()->subMonths(6)->toDateString();

        $toBilling = DB::table('tally_sale_orders AS o')
            ->join('tally_invoices AS i', 'i.order_no', '=', 'o.order_no')
            ->when($company, fn($w) => $w->where('o.company', $company))
            ->where('o.order_date', '>=', $since)
            ->selectRaw('AVG(DATEDIFF(i.invoice_date, o.order_date)) AS avg_days, COUNT(DISTINCT o.order_no) AS n')
            ->first();

        $toDelivery = DB::table('tally_sale_orders AS o')
            ->join('tally_deliveries AS d', 'd.order_no', '=', 'o.order_no')
            ->when($company, fn($w) => $w->where('o.company', $company))
            ->where('o.order_date', '>=', $since)
            ->selectRaw('AVG(DATEDIFF(d.delivery_date, o.order_date)) AS avg_days, COUNT(DISTINCT o.order_no) AS n')
            ->first();

        return [
            'to_billing' => ['avg_days' => $toBilling->avg_days !== null ? round((float) $toBilling->avg_days, 1) : null, 'n' => (int) $toBilling->n],
            'to_delivery' => ['avg_days' => $toDelivery->avg_days !== null ? round((float) $toDelivery->avg_days, 1) : null, 'n' => (int) $toDelivery->n],
        ];
    }

    /** Full detail for one order: every item line, matched deliveries, matched invoices, and the tracking note. */
    public function show(Request $request, string $orderNo)
    {
        $this->authorizeAccess();

        $lines = DB::table('tally_sale_orders')->where('order_no', $orderNo)
            ->orderBy('id')
            ->get(['company', 'order_date', 'party_name', 'normalized_name', 'item_name', 'spec', 'hsn', 'quantity', 'unit', 'rate', 'amount', 'pack_cases', 'pack_gross_qty', 'pack_net_wt_qty', 'pack_qty_number']);
        abort_if($lines->isEmpty(), 404);
        $company = $lines->first()->company;

        $deliveries = DB::table('tally_deliveries')->where('order_no', $orderNo)
            ->orderBy('delivery_date')->orderBy('delivery_no')
            ->get(['delivery_date', 'delivery_no', 'item_name', 'quantity', 'unit', 'transporter_name', 'vehicle_number', 'transport_mode', 'document_number', 'document_date', 'consignee_name', 'consignee_address', 'consignee_gstin']);

        $notes = $deliveries->groupBy('delivery_no')->map(function ($g) {
            $first = $g->first();
            return [
                'delivery_no' => $first->delivery_no, 'delivery_date' => $first->delivery_date,
                'transporter_name' => $first->transporter_name, 'vehicle_number' => $first->vehicle_number,
                'transport_mode' => $first->transport_mode, 'document_number' => $first->document_number, 'document_date' => $first->document_date,
                'consignee_name' => $first->consignee_name, 'consignee_address' => $first->consignee_address, 'consignee_gstin' => $first->consignee_gstin,
                'items' => $g->map(fn($l) => ['item' => $l->item_name, 'qty' => (float) $l->quantity, 'unit' => $l->unit])->values(),
            ];
        })->values();

        $invoices = DB::table('tally_invoices')->where('order_no', $orderNo)
            ->orderBy('invoice_date')->get(['invoice_no', 'invoice_date', 'amount']);

        $tracking = DB::table('work_order_tracking_notes')->where('company', $company)->where('order_no', $orderNo)->first();

        return response()->json([
            'order_no' => $orderNo, 'company' => $company,
            'lines' => $lines, 'deliveries' => $notes, 'invoices' => $invoices,
            'tracking' => $tracking,
        ]);
    }

    /**
     * Everything Tally has for one SKU across both sides -- every order line it was ever ordered on
     * (tally_sale_orders) and every invoice line it was ever actually billed on (tally_sale_lines, which
     * carries HSN/GST/rate/taxable+tax amount per line; invoice_lines is the lighter duplicate of the same
     * data), newest first. Matched by exact item_name text (Tally has no stable numeric item id in either
     * export), so a renamed item's older history would not show up under its new name.
     */
    public function itemHistory(Request $request)
    {
        $this->authorizeAccess();

        $item = trim((string) $request->input('item', ''));
        abort_if($item === '', 404);
        $company = $this->company($request);

        // The item's own master record -- category/group, base unit, and (the actual answer to "item specs")
        // its narration, which for this company holds the real packaging specification (e.g. "100 PCS/POLY
        // BAG + CORRUGATED SHEET..."), separate from the per-batch description seen on order/invoice lines.
        // hsn/gst_rate are blank on the master itself for this company (checked: 0 of 39,341 stock items have
        // either set) -- Tally only carries them per voucher line here, so the most recent invoiced line's
        // values are used as the effective current HSN/GST instead of leaving them blank.
        $master = DB::table('tally_stock_items')->where('name', $item)
            ->when($company, fn($w) => $w->where('company', $company))
            ->first(['category', 'stock_group', 'base_unit', 'item_alias', 'description', 'narration', 'closing_qty', 'closing_unit', 'closing_rate', 'sku_code', 'colour', 'size', 'lamination', 'printing']);
        $recentLine = DB::table('tally_sale_lines')->where('item_name', $item)
            ->when($company, fn($w) => $w->where('company', $company))
            ->whereNotNull('hsn')->orderByDesc('invoice_date')->first(['hsn', 'gst_rate']);

        $orders = DB::table('tally_sale_orders')->where('item_name', $item)
            ->when($company, fn($w) => $w->where('company', $company))
            ->orderByDesc('order_date')->limit(100)
            ->get(['order_date', 'order_no', 'party_name', 'quantity', 'unit', 'rate', 'amount']);

        $sales = DB::table('tally_sale_lines')->where('item_name', $item)
            ->when($company, fn($w) => $w->where('company', $company))
            ->orderByDesc('invoice_date')->limit(100)
            ->get(['invoice_date', 'invoice_no', 'party_name', 'quantity', 'unit', 'rate', 'taxable_amount', 'hsn']);

        $orderAgg = DB::table('tally_sale_orders')->where('item_name', $item)->when($company, fn($w) => $w->where('company', $company))
            ->selectRaw('SUM(quantity) AS qty, COUNT(DISTINCT order_no) AS n')->first();
        $saleAgg = DB::table('tally_sale_lines')->where('item_name', $item)->when($company, fn($w) => $w->where('company', $company))
            ->selectRaw('SUM(quantity) AS qty, COUNT(DISTINCT invoice_no) AS n, COUNT(DISTINCT normalized_name) AS customers, AVG(rate) AS avg_rate, MIN(rate) AS min_rate, MAX(rate) AS max_rate')->first();

        return response()->json([
            'item' => $item,
            'specs' => $master ? [
                'category' => $master->category, 'stock_group' => $master->stock_group, 'base_unit' => $master->base_unit,
                'item_alias' => $master->item_alias, 'description' => $master->description, 'narration' => $master->narration,
                'closing_qty' => $master->closing_qty !== null ? (float) $master->closing_qty : null, 'closing_unit' => $master->closing_unit,
                'closing_rate' => $master->closing_rate !== null ? (float) $master->closing_rate : null,
                'hsn' => $recentLine->hsn ?? null, 'gst_rate' => $recentLine->gst_rate ?? null,
                'sku_code' => $master->sku_code, 'colour' => $master->colour, 'size' => $master->size,
                'lamination' => $master->lamination === null ? null : (bool) $master->lamination,
                'printing' => $master->printing === null ? null : (bool) $master->printing,
            ] : null,
            'summary' => [
                'ordered_qty' => (float) ($orderAgg->qty ?? 0), 'order_count' => (int) ($orderAgg->n ?? 0),
                'invoiced_qty' => (float) ($saleAgg->qty ?? 0), 'invoice_count' => (int) ($saleAgg->n ?? 0),
                'customer_count' => (int) ($saleAgg->customers ?? 0),
                'avg_rate' => $saleAgg->avg_rate !== null ? round((float) $saleAgg->avg_rate, 2) : null,
                'min_rate' => $saleAgg->min_rate !== null ? (float) $saleAgg->min_rate : null,
                'max_rate' => $saleAgg->max_rate !== null ? (float) $saleAgg->max_rate : null,
            ],
            'orders' => $orders, 'sales' => $sales,
        ]);
    }

    /** The unit a prefix resolves to, as a single SQL CASE expression -- mirrors unitPrefix()/unitLabel()
     *  above exactly, so a filter picked from the same list always matches what the chart/table show. */
    private function unitCaseSql(): string
    {
        $cases = "WHEN order_no REGEXP '^M99W' THEN 'M99W' WHEN order_no REGEXP '^M18W' THEN 'M18W' "
            . "WHEN order_no LIKE 'W-RG%' THEN 'W-RG' WHEN order_no LIKE 'W-PG%' THEN 'W-PG' "
            . "WHEN order_no LIKE 'W-CH%' THEN 'W-CH' WHEN order_no LIKE 'W-LD%' THEN 'W-LD' "
            . "WHEN order_no LIKE 'W-GJ%' THEN 'W-GJ' WHEN order_no LIKE 'W-SL%' THEN 'W-SL' "
            . "WHEN order_no LIKE 'KRW%' THEN 'KRW' WHEN order_no LIKE 'KMW%' THEN 'KMW'";
        return "CASE {$cases} ELSE 'Other' END";
    }

    /**
     * The Financial tab's data: stats, a value-by-day trend, a value-by-unit breakdown (click-to-filter, same
     * UX as the Tally Analytics "Sales by invoice series" donut), a value-by-approval-status breakdown, a
     * per-customer outstanding rollup, and the filtered/paginated table. ITEM-LINE level (item name + stock
     * group per row), same change and same reason as panelData() -- explicit request 2026-10-19 to match the
     * Tally "Partywise Sale Order" report shape instead of one row per order.
     */
    public function financialData(Request $request)
    {
        $this->authorizeAccess();

        $company = $this->company($request);
        $from = $this->date($request->input('from')) ?? now()->subDays(29)->toDateString();
        $to = $this->date($request->input('to')) ?? now()->toDateString();
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        $party = trim((string) $request->input('party', ''));
        $unit = trim((string) $request->input('unit', ''));
        $category = trim((string) $request->input('category', ''));
        $group = trim((string) $request->input('group', ''));
        $approval = trim((string) $request->input('approval', ''));
        $per = $this->perPage($request);
        $page = max(1, (int) $request->input('page', 1));
        [$sort, $dir] = $this->sortParams($request, ['order_date', 'order_no', 'party_name', 'unit', 'stock_group', 'amount', 'item_name', 'quantity', 'sales_group',
            'credit_period', 'outstanding_days', 'total_outstanding', 'credit_limit', 'financial_approval'], 'order_date');
        $unitCase = $this->unitCaseSql();

        $base = DB::table('tally_sale_orders AS o')
            ->leftJoin('tally_stock_items AS si', 'si.name', '=', 'o.item_name')
            // See panelData()'s identical join for why ledger_group is here -- confirmed real salesperson/
            // territory/brand buckets, not a guess -- and why it's a deduped subquery, not the raw table.
            ->leftJoinSub($this->salesGroupLedgers(), 'tl', fn($j) => $j->on('tl.normalized_name', '=', 'o.normalized_name')->on('tl.company', '=', 'o.company'))
            ->when($company, fn($w) => $w->where('o.company', $company))
            ->whereBetween('o.order_date', [$from, $to])
            ->when($party !== '', fn($w) => $w->where(fn($w2) => $w2->where('o.party_name', 'like', "%{$party}%")->orWhere('o.order_no', 'like', "%{$party}%")))
            ->when($unit !== '', fn($w) => $w->whereRaw("{$unitCase} = ?", [$unit]))
            ->when($category !== '', fn($w) => $w->where('si.category', $category))
            ->when($group !== '', fn($w) => $w->where('tl.ledger_group', $group))
            ->when($approval !== '', fn($w) => $w->where('o.financial_approval', $approval));

        $lineRows = (clone $base)->selectRaw("o.id, o.order_no, o.company, o.order_date, o.party_name, o.normalized_name, o.item_name, o.quantity, o.amount, "
            . "o.financial_approval, o.remarks, si.category AS stock_group, tl.ledger_group AS sales_group, {$unitCase} AS unit");

        // See panelData()'s identical note: materialized arrays from the LEAN filteredOrderNumbers()
        // helper, not subquery builders cloned from $base -- a whereIn() subquery re-running $base (which
        // joins stock_items + the ledger-group subquery) per outer row made this slower, not faster.
        $filteredOrderNos = $this->filteredOrderNumbers($company, $from, $to, $party, $unit, $category, $group, $unitCase, $approval)->distinct()->pluck('o.order_no');
        $filteredNormalizedNames = $this->filteredOrderNumbers($company, $from, $to, $party, $unit, $category, $group, $unitCase, $approval)->distinct()->pluck('o.normalized_name');
        $outstandingAgg = DB::table('tally_outstanding')->whereIn('normalized_name', $filteredNormalizedNames)
            ->groupBy('normalized_name')
            ->selectRaw('normalized_name, SUM(amount) AS total_outstanding, MAX(credit_days) AS credit_period, MIN(bill_date) AS oldest_bill_date');

        // tally_sale_lines/tally_invoices carry the actual billing dates for lead-time, same as panelData().
        $billedAgg = DB::table('tally_invoices')->whereNotNull('order_no')->whereIn('order_no', $filteredOrderNos)->groupBy('order_no')
            ->selectRaw('order_no, MIN(invoice_date) AS first_invoice_date');
        $deliveredAgg = DB::table('tally_deliveries')->whereIn('order_no', $filteredOrderNos)->groupBy('order_no')
            ->selectRaw('order_no, MAX(delivery_date) AS last_delivery_date');

        $withCredit = DB::query()->fromSub($lineRows, 'x')
            ->leftJoinSub($outstandingAgg, 'os', 'os.normalized_name', '=', 'x.normalized_name')
            ->leftJoin('tally_parties AS p', 'p.normalized_name', '=', 'x.normalized_name')
            ->leftJoinSub($billedAgg, 'b', 'b.order_no', '=', 'x.order_no')
            ->leftJoinSub($deliveredAgg, 'd', 'd.order_no', '=', 'x.order_no')
            ->selectRaw('x.*, os.total_outstanding, os.credit_period, os.oldest_bill_date, p.credit_limit, '
                . 'DATEDIFF(CURDATE(), os.oldest_bill_date) AS outstanding_days, b.first_invoice_date, d.last_delivery_date');

        // ONE query for everything (stats, trend, by-unit, by-approval, by-customer, lead time, table) --
        // see panelData()'s identical note; this endpoint had the same six-re-runs-of-one-join problem.
        $all = $withCredit->get();

        $total = $all->count();
        $rows = $this->sortRows($all, $sort, $dir)->values()
            ->slice(($page - 1) * $per, $per)->values();

        // Per-ORDER totals -- see orderRollups()'s doc comment (panelData() uses the same helper).
        $this->attachOrderRollups($rows);

        $orderCount = $all->pluck('order_no')->unique()->count();
        $okValue = $all->filter(fn($r) => $r->financial_approval === 'Ok')->sum('amount');
        $holdRows = $all->filter(fn($r) => $r->financial_approval === 'Make and Hold');
        $qtyOrdered = $all->sum(fn($r) => (float) $r->quantity);

        $trend = $all->groupBy('order_date')->map(fn($g, $d) => ['d' => $d, 'value' => $g->sum('amount'), 'n' => $g->pluck('order_no')->unique()->count()])->sortKeys()->values();
        $byUnit = $all->groupBy('unit')->map(fn($g, $u) => ['unit' => $u, 'value' => $g->sum('amount'), 'n' => $g->pluck('order_no')->unique()->count()])->sortByDesc('value')->values();
        $byGroup = $all->groupBy(fn($r) => $r->sales_group ?: 'Not set')
            ->map(fn($g, $sg) => ['group' => $sg, 'value' => $g->sum('amount'), 'n' => $g->pluck('order_no')->unique()->count()])
            ->sortByDesc('value')->values()->take(20);
        $byApproval = $all->groupBy(fn($r) => $r->financial_approval ?? 'Not set')
            ->map(fn($g, $s) => ['status' => $s, 'value' => $g->sum('amount'), 'n' => $g->pluck('order_no')->unique()->count()])->sortByDesc('value')->values();

        // Per-customer rollup: pending-value-equivalent here is "on hold" value (the thing Finance actually
        // needs to chase), plus each party's own total outstanding (already a per-party figure from Tally's
        // ledger, not derived from this filtered window -- shown once per party, not summed across lines).
        $byParty = $all->groupBy('party_name')->map(function ($g, $p) {
            $first = $g->first();
            $hold = $g->filter(fn($r) => $r->financial_approval === 'Make and Hold');
            return [
                'party' => $p, 'value' => $g->sum('amount'), 'orders' => $g->pluck('order_no')->unique()->count(),
                'hold_value' => $hold->sum('amount'), 'hold_count' => $hold->pluck('order_no')->unique()->count(),
                'total_outstanding' => $first->total_outstanding !== null ? (float) $first->total_outstanding : null,
                'credit_limit' => $first->credit_limit !== null ? (float) $first->credit_limit : null,
            ];
        })->sortByDesc('hold_value')->values()->take(100);

        return response()->json([
            'stats' => [
                'orders' => $orderCount, 'lines' => $total, 'total_value' => (float) $all->sum('amount'),
                'ok_value' => (float) $okValue, 'hold_value' => (float) $holdRows->sum('amount'),
                'hold_count' => $holdRows->pluck('order_no')->unique()->count(), 'unit_count' => $all->pluck('unit')->unique()->count(),
                'qty_ordered' => $qtyOrdered,
                'lead_time' => $this->leadTimeFromRows($all->unique('order_no')->values()),
            ],
            'trend' => $trend, 'by_unit' => $byUnit, 'by_group' => $byGroup, 'by_approval' => $byApproval, 'by_party' => $byParty,
            'table' => ['rows' => $rows, 'total' => $total, 'page' => $page, 'per' => $per],
            'unit_labels' => self::UNIT_PREFIXES,
            'sales_groups' => $this->salesGroups(),
        ])->header('Cache-Control', 'private, max-age=30');
    }

    private function date(?string $v): ?string
    {
        if (!$v) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($v)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{0: string, 1: 'asc'|'desc'} */
    private function sortParams(Request $request, array $allowed, string $default): array
    {
        $sort = (string) $request->input('sort', $default);
        $sort = in_array($sort, $allowed, true) ? $sort : $default;
        $dir = $request->input('dir') === 'asc' ? 'asc' : 'desc';
        return [$sort, $dir];
    }

    /** Sorts an already-fetched Collection by one of its own scalar columns -- used instead of an ORDER BY
     *  because every panel now fetches its whole filtered set once and derives stats/charts/table from that
     *  same in-memory collection (see panelData()'s doc comment), so the table's sort has to happen here too.
     *  PHP's sort is stable (guaranteed since 8.0), so pre-sorting by order_no gives every other sort a
     *  predictable, repeatable tiebreaker for rows that share a value (e.g. many orders on the same date). */
    private function sortRows($rows, string $sort, string $dir)
    {
        $byOrderNo = $rows->sortBy(fn($r) => $r->order_no, SORT_REGULAR, $dir === 'desc');
        return $sort === 'order_no' ? $byOrderNo : $byOrderNo->sortBy(fn($r) => $r->$sort, SORT_REGULAR, $dir === 'desc');
    }

    public function updateTracking(Request $request, string $orderNo)
    {
        $this->authorizeAccess();

        $data = $request->validate([
            'company' => ['required', 'in:hitech,kif'],
            'carrier' => ['nullable', 'string', 'max:255'],
            'tracking_ref' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $exists = DB::table('tally_sale_orders')->where('company', $data['company'])->where('order_no', $orderNo)->exists();
        abort_unless($exists, 404);

        $existing = DB::table('work_order_tracking_notes')->where('company', $data['company'])->where('order_no', $orderNo)->first();
        $values = [
            'carrier' => $data['carrier'] ?? null, 'tracking_ref' => $data['tracking_ref'] ?? null, 'note' => $data['note'] ?? null,
            'updated_by_id' => Auth::id(), 'updated_at' => now(),
        ];
        if ($existing) {
            DB::table('work_order_tracking_notes')->where('id', $existing->id)->update($values);
        } else {
            DB::table('work_order_tracking_notes')->insert($values + ['company' => $data['company'], 'order_no' => $orderNo, 'created_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }
}
