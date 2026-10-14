<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tally Explorer -- browse the raw Tally data synced into HRX: purchases (bills + lines), purchase orders, payments,
 * the stock-item master and the full ledger master. Every tab is a searchable, filterable, paginated table with a
 * per-record drill-down, so every field that came across from Tally is visible. Read-only.
 */
class TallyExplorerController extends Controller
{
    private const PER_PAGE = 50;

    public function index()
    {
        return redirect()->route('tally-explorer.trend');
    }

    /** Purchase bills (header), with a drill-down to their item lines. */
    public function purchases(Request $request): View
    {
        $q = trim((string) $request->get('q', ''));
        $from = $request->get('from');
        $to = $request->get('to');

        $base = DB::table('tally_purchases')
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('purchase_no', 'like', "%{$q}%")->orWhere('party_name', 'like', "%{$q}%")))
            ->when($from, fn ($w) => $w->whereDate('purchase_date', '>=', $from))
            ->when($to, fn ($w) => $w->whereDate('purchase_date', '<=', $to));

        $stats = [
            'bills' => (clone $base)->count(),
            'amount' => (float) (clone $base)->sum('amount'),
            'suppliers' => (clone $base)->distinct()->count('normalized_name'),
        ];
        $rows = (clone $base)->orderByDesc('purchase_date')->orderByDesc('id')->paginate(self::PER_PAGE)->withQueryString();

        return view('tenant.tally-explorer.purchases', $this->common('purchases') + compact('rows', 'stats', 'q', 'from', 'to'));
    }

    /** The item lines of one purchase bill -- the drill-down, keyed by the voucher's purchase_key. */
    public function purchaseDetail(Request $request): JsonResponse
    {
        $key = (string) $request->get('key', '');
        $header = DB::table('tally_purchases')->where('purchase_key', $key)->first();
        $lines = DB::table('tally_purchase_lines')->where('purchase_key', $key)
            ->orderBy('id')->get();

        return response()->json([
            'header' => $header,
            'extra' => $header && $header->extra ? json_decode($header->extra, true) : null,
            'lines' => $lines,
        ]);
    }

    /**
     * Full analytics view for one purchase bill: the bill itself, this supplier's purchase trend over time, and --
     * the headline -- a price-hike analysis of every item on the bill (its rate history across all purchases, and
     * how this bill's rate compares to the last time it was bought).
     */
    public function purchaseShow(Request $request): View
    {
        $key = (string) $request->get('key', '');
        $header = DB::table('tally_purchases')->where('purchase_key', $key)->first();
        abort_unless($header, 404);
        $extra = $header->extra ? json_decode($header->extra, true) : [];
        $lines = DB::table('tally_purchase_lines')->where('purchase_key', $key)->orderBy('id')->get();
        $supplier = $header->normalized_name;

        // This supplier's monthly purchase value (the trend bar chart).
        $trend = $supplier
            ? DB::table('tally_purchases')->where('normalized_name', $supplier)
                ->selectRaw("DATE_FORMAT(purchase_date, '%Y-%m') ym, COUNT(*) bills, SUM(amount) amt")
                ->groupBy('ym')->orderBy('ym')->get()
            : collect();

        // Supplier KPIs.
        $sup = $supplier
            ? DB::table('tally_purchases')->where('normalized_name', $supplier)
                ->selectRaw('SUM(amount) total, COUNT(*) bills, MIN(purchase_date) first_date, MAX(purchase_date) last_date, AVG(amount) avg_bill')->first()
            : null;

        // Per-item price history + hike vs the previous purchase of the same item.
        $items = [];
        foreach ($lines->pluck('item_name')->filter()->unique() as $item) {
            $hist = DB::table('tally_purchase_lines')->where('item_name', $item)
                ->whereNotNull('rate')->where('rate', '>', 0)
                ->orderBy('purchase_date')->orderBy('id')
                ->get(['purchase_date', 'rate', 'rate_unit', 'party_name', 'quantity']);
            $thisLine = $lines->firstWhere('item_name', $item);
            $current = $thisLine && $thisLine->rate !== null ? (float) $thisLine->rate : null;
            $previous = DB::table('tally_purchase_lines')->where('item_name', $item)
                ->whereNotNull('rate')->where('rate', '>', 0)
                ->whereDate('purchase_date', '<', $header->purchase_date)
                ->orderByDesc('purchase_date')->orderByDesc('id')->value('rate');
            $previous = $previous !== null ? (float) $previous : null;
            $items[] = [
                'name' => $item,
                'rate_unit' => optional($hist->last())->rate_unit,
                'current' => $current,
                'previous' => $previous,
                'change_pct' => ($current !== null && $previous) ? round(($current - $previous) / $previous * 100, 1) : null,
                'min' => $hist->count() ? (float) $hist->min('rate') : null,
                'max' => $hist->count() ? (float) $hist->max('rate') : null,
                'avg' => $hist->count() ? round((float) $hist->avg('rate'), 2) : null,
                'points' => $hist->count(),
                'history' => $hist->map(fn ($h) => [
                    'date' => $h->purchase_date, 'rate' => (float) $h->rate, 'party' => $h->party_name, 'qty' => (float) $h->quantity,
                ])->values(),
            ];
        }

        return view('tenant.tally-explorer.purchase-detail', compact('header', 'extra', 'lines', 'trend', 'sup', 'items')
            + ['pageConfigs' => ['contentLayout' => 'wide']]);
    }

    /**
     * One item's full price history: its purchase rate over time across ALL suppliers (who's cheapest), its selling
     * rate over time, quantities, and where it stands in stock. The cross-supplier price-hike view.
     */
    public function itemShow(Request $request): View
    {
        $name = (string) $request->get('name', '');
        $pLines = DB::table('tally_purchase_lines')->where('item_name', $name)
            ->orderBy('purchase_date')->orderBy('id')
            ->get(['purchase_date', 'rate', 'rate_unit', 'party_name', 'quantity', 'unit', 'taxable_amount', 'hsn', 'gst_rate']);
        $sLines = DB::table('tally_sale_lines')->where('item_name', $name)
            ->orderBy('invoice_date')->orderBy('id')
            ->get(['invoice_date', 'rate', 'rate_unit', 'party_name', 'quantity', 'taxable_amount']);
        $stock = DB::table('tally_stock_items')->where('name', $name)->first();

        if ($pLines->isEmpty() && $sLines->isEmpty() && !$stock) {
            abort(404);
        }

        // Who we buy it from, cheapest first (by latest rate).
        $bySupplier = $pLines->whereNotNull('rate')->where('rate', '>', 0)->groupBy('party_name')->map(function ($g, $supplier) {
            $latest = $g->sortBy('purchase_date')->last();
            return [
                'supplier' => $supplier,
                'last_rate' => (float) $latest->rate,
                'last_date' => $latest->purchase_date,
                'min_rate' => (float) $g->min('rate'),
                'avg_rate' => round((float) $g->avg('rate'), 2),
                'buys' => $g->count(),
                'qty' => (float) $g->sum('quantity'),
                'value' => (float) $g->sum('taxable_amount'),
            ];
        })->sortBy('last_rate')->values();

        $purchaseRates = $pLines->whereNotNull('rate')->where('rate', '>', 0);
        $saleRates = $sLines->whereNotNull('rate')->where('rate', '>', 0);

        $stats = [
            'buy_qty' => (float) $pLines->sum('quantity'),
            'buy_value' => (float) $pLines->sum('taxable_amount'),
            'buys' => $pLines->count(),
            'sell_qty' => (float) $sLines->sum('quantity'),
            'sell_value' => (float) $sLines->sum('taxable_amount'),
            'sells' => $sLines->count(),
            'last_buy_rate' => $purchaseRates->count() ? (float) $purchaseRates->sortBy('purchase_date')->last()->rate : null,
            'last_sell_rate' => $saleRates->count() ? (float) $saleRates->sortBy('invoice_date')->last()->rate : null,
            'min_buy' => $purchaseRates->count() ? (float) $purchaseRates->min('rate') : null,
            'max_buy' => $purchaseRates->count() ? (float) $purchaseRates->max('rate') : null,
            'suppliers' => $bySupplier->count(),
        ];

        $buyTrend = $purchaseRates->map(fn ($r) => ['date' => $r->purchase_date, 'rate' => (float) $r->rate, 'party' => $r->party_name])->values();
        $sellTrend = $saleRates->map(fn ($r) => ['date' => $r->invoice_date, 'rate' => (float) $r->rate])->values();

        return view('tenant.tally-explorer.item-detail', [
            'name' => $name, 'stock' => $stock, 'bySupplier' => $bySupplier, 'stats' => $stats,
            'buyTrend' => $buyTrend, 'sellTrend' => $sellTrend, 'pLines' => $pLines,
            'pageConfigs' => ['contentLayout' => 'wide'],
        ]);
    }

    public function purchaseOrders(Request $request): View
    {
        $q = trim((string) $request->get('q', ''));
        $from = $request->get('from');
        $to = $request->get('to');

        $base = DB::table('tally_purchase_orders')
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('order_no', 'like', "%{$q}%")->orWhere('party_name', 'like', "%{$q}%")->orWhere('item_name', 'like', "%{$q}%")))
            ->when($from, fn ($w) => $w->whereDate('order_date', '>=', $from))
            ->when($to, fn ($w) => $w->whereDate('order_date', '<=', $to));

        $stats = [
            'lines' => (clone $base)->count(),
            'orders' => (clone $base)->distinct()->count('order_no'),
            'amount' => (float) (clone $base)->sum('amount'),
        ];
        $rows = (clone $base)->orderByDesc('order_date')->orderByDesc('id')->paginate(self::PER_PAGE)->withQueryString();

        return view('tenant.tally-explorer.purchase-orders', $this->common('purchase-orders') + compact('rows', 'stats', 'q', 'from', 'to'));
    }

    public function payments(Request $request): View
    {
        $q = trim((string) $request->get('q', ''));
        $from = $request->get('from');
        $to = $request->get('to');

        $base = DB::table('tally_payments')
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('payment_no', 'like', "%{$q}%")->orWhere('party_name', 'like', "%{$q}%")->orWhere('bank_ledger', 'like', "%{$q}%")))
            ->when($from, fn ($w) => $w->whereDate('payment_date', '>=', $from))
            ->when($to, fn ($w) => $w->whereDate('payment_date', '<=', $to));

        $stats = [
            'count' => (clone $base)->count(),
            'amount' => (float) (clone $base)->sum('amount'),
            'parties' => (clone $base)->whereNotNull('normalized_name')->distinct()->count('normalized_name'),
        ];
        $rows = (clone $base)->orderByDesc('payment_date')->orderByDesc('id')->paginate(self::PER_PAGE)->withQueryString();

        return view('tenant.tally-explorer.payments', $this->common('payments') + compact('rows', 'stats', 'q', 'from', 'to'));
    }

    public function stock(Request $request): View
    {
        $q = trim((string) $request->get('q', ''));
        $group = trim((string) $request->get('group', ''));
        $onlyStock = $request->boolean('in_stock');

        $base = DB::table('tally_stock_items')
            ->when($q !== '', fn ($w) => $w->where('name', 'like', "%{$q}%"))
            ->when($group !== '', fn ($w) => $w->where('stock_group', $group))
            ->when($onlyStock, fn ($w) => $w->where('closing_qty', '!=', 0));

        $stats = [
            'items' => (clone $base)->count(),
            'with_stock' => (clone $base)->where('closing_qty', '!=', 0)->count(),
            'value' => (float) (clone $base)->sum('closing_value'),
        ];
        $groups = DB::table('tally_stock_items')->select('stock_group')->whereNotNull('stock_group')
            ->groupBy('stock_group')->orderBy('stock_group')->pluck('stock_group');
        $rows = (clone $base)->orderBy('name')->paginate(self::PER_PAGE)->withQueryString();

        return view('tenant.tally-explorer.stock', $this->common('stock') + compact('rows', 'stats', 'q', 'group', 'groups', 'onlyStock'));
    }

    /**
     * A full 360 for one ledger (customer and/or supplier): the master (group, credit limit, GSTIN/PAN, contact),
     * the payment terms it actually runs on (credit period observed on its bills), its sales & purchase history,
     * money received & paid, bill-wise outstanding with ageing, and recent delivery activity.
     */
    public function ledgerShow(Request $request): View
    {
        $name = (string) $request->get('name', '');
        $ledger = DB::table('tally_ledgers')->where('name', $name)->first();
        abort_unless($ledger, 404);
        $norm = $ledger->normalized_name;

        $sales = fn () => DB::table('tally_invoices')->where('normalized_name', $norm);
        $purch = fn () => DB::table('tally_purchases')->where('normalized_name', $norm);
        $rcpt = fn () => DB::table('tally_receipts')->where('normalized_name', $norm);
        $paid = fn () => DB::table('tally_payments')->where('normalized_name', $norm);
        $outstanding = $norm ? DB::table('tally_outstanding')->where('normalized_name', $norm)->orderByDesc('amount')->get() : collect();

        $kpi = [
            'sales_total' => $norm ? (float) $sales()->sum('amount') : 0.0,
            'sales_count' => $norm ? $sales()->count() : 0,
            'purchase_total' => $norm ? (float) $purch()->sum('amount') : 0.0,
            'purchase_count' => $norm ? $purch()->count() : 0,
            'received' => $norm ? (float) $rcpt()->sum('amount') : 0.0,
            'paid' => $norm ? (float) $paid()->sum('amount') : 0.0,
            'outstanding' => (float) $outstanding->sum('amount'),
            'bills_open' => $outstanding->count(),
        ];
        $isCustomer = $kpi['sales_count'] > 0 || $kpi['bills_open'] > 0;
        $isSupplier = $kpi['purchase_count'] > 0;

        // Payment terms the ledger actually runs on: the credit period seen on its outstanding bills.
        $cd = $outstanding->whereNotNull('credit_days')->pluck('credit_days');
        $terms = [
            'credit_limit' => $ledger->credit_limit !== null ? (float) $ledger->credit_limit : null,
            'credit_days_typical' => $cd->isNotEmpty() ? (int) $cd->mode()[0] : null,
            'credit_days_max' => $cd->isNotEmpty() ? (int) $cd->max() : null,
            'utilisation' => ($ledger->credit_limit && $ledger->credit_limit > 0) ? round($kpi['outstanding'] / $ledger->credit_limit * 100, 0) : null,
        ];

        // Outstanding ageing buckets (bill date -> today).
        $today = now();
        $ageing = ['0-30' => 0.0, '31-60' => 0.0, '61-90' => 0.0, '90+' => 0.0];
        foreach ($outstanding as $b) {
            $days = $b->bill_date ? \Illuminate\Support\Carbon::parse($b->bill_date)->diffInDays($today) : 0;
            $bucket = $days <= 30 ? '0-30' : ($days <= 60 ? '31-60' : ($days <= 90 ? '61-90' : '90+'));
            $ageing[$bucket] += (float) $b->amount;
        }

        // Monthly trend (sales + purchases).
        $salesTrend = $norm ? $sales()->selectRaw("DATE_FORMAT(invoice_date, '%Y-%m') ym, SUM(amount) amt")->groupBy('ym')->orderBy('ym')->get() : collect();
        $purchaseTrend = $norm ? $purch()->selectRaw("DATE_FORMAT(purchase_date, '%Y-%m') ym, SUM(amount) amt")->groupBy('ym')->orderBy('ym')->get() : collect();
        $months = collect($salesTrend->pluck('ym'))->merge($purchaseTrend->pluck('ym'))->unique()->sort()->values();
        $trend = $months->map(fn ($m) => [
            'ym' => $m,
            'sales' => (float) (optional($salesTrend->firstWhere('ym', $m))->amt ?? 0),
            'purchases' => (float) (optional($purchaseTrend->firstWhere('ym', $m))->amt ?? 0),
        ]);

        // Recent activity feed: invoices, purchases, receipts, payments merged.
        $feed = collect();
        if ($norm) {
            foreach ($sales()->orderByDesc('invoice_date')->limit(12)->get(['invoice_no', 'invoice_date', 'amount']) as $r) {
                $feed->push(['date' => $r->invoice_date, 'type' => 'Sale', 'ref' => $r->invoice_no, 'amount' => (float) $r->amount, 'dir' => 'in']);
            }
            foreach ($purch()->orderByDesc('purchase_date')->limit(12)->get(['purchase_no', 'purchase_date', 'amount']) as $r) {
                $feed->push(['date' => $r->purchase_date, 'type' => 'Purchase', 'ref' => $r->purchase_no, 'amount' => (float) $r->amount, 'dir' => 'out']);
            }
            foreach ($rcpt()->orderByDesc('receipt_date')->limit(12)->get(['receipt_no', 'receipt_date', 'amount', 'bank_ledger']) as $r) {
                $feed->push(['date' => $r->receipt_date, 'type' => 'Receipt', 'ref' => $r->receipt_no, 'amount' => (float) $r->amount, 'dir' => 'in', 'note' => $r->bank_ledger]);
            }
            foreach ($paid()->orderByDesc('payment_date')->limit(12)->get(['payment_no', 'payment_date', 'amount', 'bank_ledger']) as $r) {
                $feed->push(['date' => $r->payment_date, 'type' => 'Payment', 'ref' => $r->payment_no, 'amount' => (float) $r->amount, 'dir' => 'out', 'note' => $r->bank_ledger]);
            }
        }
        $feed = $feed->sortByDesc('date')->take(20)->values();

        // Delivery activity (how goods actually ship to them) -- the practical "delivery terms".
        $deliveries = $norm ? DB::table('tally_deliveries')->where('normalized_name', $norm)
            ->orderByDesc('delivery_date')->limit(10)
            ->get(['delivery_date', 'delivery_no', 'item_name', 'quantity', 'unit', 'transporter_name', 'vehicle_number', 'consignee_name']) : collect();

        return view('tenant.tally-explorer.ledger-detail', compact(
            'ledger', 'kpi', 'terms', 'ageing', 'trend', 'outstanding', 'feed', 'deliveries', 'isCustomer', 'isSupplier'
        ) + ['pageConfigs' => ['contentLayout' => 'wide']]);
    }

    public function ledgers(Request $request): View
    {
        $q = trim((string) $request->get('q', ''));
        $group = trim((string) $request->get('group', ''));

        $base = DB::table('tally_ledgers')
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('gstin', 'like', "%{$q}%")))
            ->when($group !== '', fn ($w) => $w->where('primary_group', $group));

        $stats = [
            'ledgers' => (clone $base)->count(),
            'with_gstin' => (clone $base)->whereNotNull('gstin')->count(),
            'balance' => (float) (clone $base)->sum('closing_balance'),
        ];
        $groups = DB::table('tally_ledgers')->select('primary_group')->whereNotNull('primary_group')
            ->groupBy('primary_group')->orderBy('primary_group')->pluck('primary_group');
        $rows = (clone $base)->orderBy('name')->paginate(self::PER_PAGE)->withQueryString();

        return view('tenant.tally-explorer.ledgers', $this->common('ledgers') + compact('rows', 'stats', 'q', 'group', 'groups'));
    }

    /**
     * Trend pivot (Magenta-style): a MEASURE (sales/purchases/receipts/payments) broken down by a DIMENSION
     * (party/item/product group) across the months of a financial year, colour-coded month-on-month, with each
     * row linking into its detail page. Built entirely from the synced tally_* tables.
     */
    public function trend(Request $request): View
    {
        $measures = $this->trendMeasures();
        $measure = isset($measures[$request->get('measure')]) ? (string) $request->get('measure') : 'sales';
        $parts = $measures[$measure]['parts'];
        $isCombined = count($parts) > 1;

        $dims = $this->trendDims();
        $avail = $this->availableDims($parts);
        $dim = in_array($request->get('dim'), $avail, true) ? (string) $request->get('dim') : $avail[0];

        $filterDim = in_array($request->get('filter_dim'), array_keys($dims), true) ? (string) $request->get('filter_dim') : '';
        $filterVals = array_values(array_filter(array_map('trim', (array) $request->get('filter_vals', []))));

        $fy = $this->fyStartYear($request->get('fy'));
        $start = \Illuminate\Support\Carbon::create($fy, 4, 1)->startOfDay();
        $end = \Illuminate\Support\Carbon::create($fy + 1, 3, 31)->endOfDay();
        $months = [];
        for ($m = $start->copy(); $m->lte($end); $m->addMonth()) {
            $months[] = $m->format('Y-m');
        }

        $pivot = [];
        $colTotals = [];
        $partDefs = $this->trendParts();
        foreach ($parts as $partKey) {
            $part = $partDefs[$partKey];
            foreach ($this->partPivot($part, $dim, $filterDim, $filterVals, $start, $end) as $k => $d) {
                $pivot[$k]['label'] ??= $d['label'];
                $cells = $d['cells'] + array_fill_keys($months, null);
                $pivot[$k]['rows'][$part['label']] = $cells;
                foreach ($cells as $ym => $v) {
                    if ($v !== null) {
                        $colTotals[$part['label']][$ym] = ($colTotals[$part['label']][$ym] ?? 0) + $v;
                    }
                }
            }
        }

        $sortPartLabel = $partDefs[$parts[0]]['label'];
        $sortBy = (string) $request->get('sort', 'total');
        $include0 = $request->boolean('include0');
        $q = trim((string) $request->get('q', ''));

        $all = collect($pivot)->map(function ($r, $k) use ($sortPartLabel, $sortBy) {
            $primary = $r['rows'][$sortPartLabel] ?? [];
            $r['key'] = $k;
            $r['total'] = array_sum(array_filter($primary, fn ($v) => $v !== null));
            $r['sort'] = $sortBy === 'total' ? $r['total'] : ($primary[$sortBy] ?? 0);
            return $r;
        })->values();

        if (!$include0) {
            $all = $all->filter(fn ($r) => abs($r['total']) > 0.005)->values();
        }
        if ($q !== '') {
            $all = $all->filter(fn ($r) => stripos((string) $r['label'], $q) !== false)->values();
        }
        $all = $all->sortByDesc('sort')->values();

        $grand = [];
        foreach ($parts as $pk) {
            $lbl = $partDefs[$pk]['label'];
            $grand[$lbl] = array_sum($colTotals[$lbl] ?? []);
        }

        $perPage = 60;
        $page = max(1, (int) $request->get('page', 1));
        $paged = new \Illuminate\Pagination\LengthAwarePaginator(
            $all->forPage($page, $perPage)->values(), $all->count(), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('tenant.tally-explorer.trend', $this->common('trend') + [
            'measures' => array_map(fn ($m) => $m['label'], $measures),
            'measure' => $measure, 'dim' => $dim, 'fy' => $fy, 'q' => $q,
            'dimLabels' => $this->dimLabelsFor($avail), 'allDimLabels' => $this->dimLabelsFor(array_keys($dims)),
            'link' => $dims[$dim][3] ?? null,
            'filterDim' => $filterDim, 'filterVals' => $filterVals,
            'isCombined' => $isCombined, 'partLabels' => array_map(fn ($pk) => $partDefs[$pk]['label'], $parts),
            'sortBy' => $sortBy, 'include0' => $include0,
            'months' => $months, 'rows' => $paged, 'colTotals' => $colTotals, 'grand' => $grand,
            'rowCount' => $all->count(),
        ]);
    }

    /** Distinct values of a filter dimension, searchable -- feeds the multi-select value picker. */
    public function trendValues(Request $request): JsonResponse
    {
        $dims = $this->trendDims();
        $dim = (string) $request->get('dim', '');
        if (!isset($dims[$dim])) {
            return response()->json(['values' => []]);
        }
        $d = $dims[$dim];
        $col = $d[2];
        if ($d[1] === 'ledger') {
            $query = DB::table('tally_ledgers')->whereNotNull($col)->where($col, '!=', '');
        } else {
            $query = DB::table($d[4] ? 'tally_sale_lines' : 'tally_invoices')->whereNotNull($col)->where($col, '!=', '');
        }
        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $query->where($col, 'like', "%{$q}%");
        }

        return response()->json(['values' => $query->select($col . ' as v')->distinct()->orderBy('v')->limit(200)->pluck('v')]);
    }

    public function trendExport(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $measures = $this->trendMeasures();
        $measure = isset($measures[$request->get('measure')]) ? (string) $request->get('measure') : 'sales';
        $parts = $measures[$measure]['parts'];
        $avail = $this->availableDims($parts);
        $dim = in_array($request->get('dim'), $avail, true) ? (string) $request->get('dim') : $avail[0];
        $dims = $this->trendDims();
        $filterDim = in_array($request->get('filter_dim'), array_keys($dims), true) ? (string) $request->get('filter_dim') : '';
        $filterVals = array_values(array_filter(array_map('trim', (array) $request->get('filter_vals', []))));

        $fy = $this->fyStartYear($request->get('fy'));
        $start = \Illuminate\Support\Carbon::create($fy, 4, 1)->startOfDay();
        $end = \Illuminate\Support\Carbon::create($fy + 1, 3, 31)->endOfDay();
        $months = [];
        for ($m = $start->copy(); $m->lte($end); $m->addMonth()) {
            $months[] = $m->format('Y-m');
        }

        $partDefs = $this->trendParts();
        $partLabels = array_map(fn ($pk) => $partDefs[$pk]['label'], $parts);
        $merged = [];
        foreach ($parts as $pk) {
            foreach ($this->partPivot($partDefs[$pk], $dim, $filterDim, $filterVals, $start, $end) as $k => $d) {
                $merged[$k]['label'] ??= $d['label'];
                $merged[$k][$partDefs[$pk]['label']] = $d['cells'];
            }
        }

        $filename = "trend-{$measure}-by-{$dim}-fy{$fy}.csv";
        return response()->streamDownload(function () use ($merged, $months, $partLabels) {
            $out = fopen('php://output', 'w');
            $head = array_merge(['Breakdown'], count($partLabels) > 1 ? ['Report Type'] : [],
                array_map(fn ($m) => \Illuminate\Support\Carbon::parse($m . '-01')->format('M Y'), $months), ['Total']);
            fputcsv($out, $head);
            foreach ($merged as $row) {
                foreach ($partLabels as $pl) {
                    if (count($partLabels) > 1 && !isset($row[$pl])) {
                        continue;
                    }
                    $cells = $row[$pl] ?? [];
                    $line = array_merge([$row['label'] ?? ''], count($partLabels) > 1 ? [$pl] : []);
                    $tot = 0.0;
                    foreach ($months as $m) {
                        $v = $cells[$m] ?? null;
                        $line[] = $v === null ? '' : round($v, 2);
                        $tot += (float) $v;
                    }
                    $line[] = round($tot, 2);
                    fputcsv($out, $line);
                }
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** Pivot one base part by one dimension. @return array<string,array{label:string,cells:array<string,?float>}> */
    private function partPivot(array $part, string $dim, string $filterDim, array $filterVals, $start, $end): array
    {
        $dims = $this->trendDims();
        $d = $dims[$dim];
        $table = $part['table'];
        if (!empty($d[4]) && empty($part['line'])) {
            return [];
        }

        $groupExpr = $d[1] === 'ledger' ? "l.{$d[2]}" : "{$table}.{$d[2]}";
        $labelExpr = ($d[1] === 'self' && $d[2] === 'normalized_name') ? "MAX({$table}.party_name)" : $groupExpr;
        $sign = $part['sign'] ?? 1;
        $valExpr = "{$part['agg']}({$table}.{$part['value']}) * {$sign}";

        $needJoin = $d[1] === 'ledger' || ($filterDim !== '' && ($dims[$filterDim][1] ?? '') === 'ledger');
        $base = DB::table($table);
        if ($needJoin) {
            $base->join('tally_ledgers as l', "{$table}.normalized_name", '=', 'l.normalized_name');
        }
        $base->whereBetween("{$table}.{$part['date']}", [$start, $end])
            ->whereNotNull($groupExpr)->where($groupExpr, '!=', '');

        if ($filterDim !== '' && $filterVals) {
            $fd = $dims[$filterDim];
            if (empty($fd[4]) || !empty($part['line'])) {
                $base->whereIn($fd[1] === 'ledger' ? "l.{$fd[2]}" : "{$table}.{$fd[2]}", $filterVals);
            }
        }

        $rows = $base->selectRaw("$groupExpr dimkey, $labelExpr dimlabel, DATE_FORMAT({$table}.{$part['date']}, '%Y-%m') ym, $valExpr amt")
            ->groupBy(DB::raw($groupExpr), DB::raw("DATE_FORMAT({$table}.{$part['date']}, '%Y-%m')"))->get();

        $out = [];
        foreach ($rows as $r) {
            $out[$r->dimkey]['label'] ??= $r->dimlabel;
            $out[$r->dimkey]['cells'][$r->ym] = (float) $r->amt;
        }
        return $out;
    }

    /** Dimensions valid for a measure -- line-only dims (item/city/group/hsn) only when every part is a line table. */
    private function availableDims(array $parts): array
    {
        $partDefs = $this->trendParts();
        $allLine = true;
        foreach ($parts as $pk) {
            if (empty($partDefs[$pk]['line'])) {
                $allLine = false;
            }
        }
        $dims = $this->trendDims();
        return array_values(array_filter(array_keys($dims), fn ($k) => empty($dims[$k][4]) || $allLine));
    }

    private function dimLabelsFor(array $keys): array
    {
        $dims = $this->trendDims();
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = $dims[$k][0];
        }
        return $out;
    }

    /** key => [label, src(self|ledger), col, link, lineOnly]. */
    private function trendDims(): array
    {
        return [
            'party' => ['Customer / Supplier', 'self', 'normalized_name', 'ledger', false],
            'salesperson' => ['Salesperson (sales group)', 'ledger', 'ledger_group', null, false],
            'state' => ['State', 'ledger', 'state', null, false],
            'country' => ['Country', 'ledger', 'country', null, false],
            'company' => ['Company', 'ledger', 'mailing_name', null, false],
            'pincode' => ['Pincode', 'ledger', 'pincode', null, false],
            'city' => ['City', 'self', 'city', null, true],
            'item' => ['Item', 'self', 'item_name', 'item', true],
            'group' => ['Product group', 'self', 'product_group', null, true],
            'hsn' => ['HSN', 'self', 'hsn', null, true],
        ];
    }

    /** key => [label, table, date, value, agg, line, sign?]. */
    private function trendParts(): array
    {
        return [
            'sales' => ['label' => 'Sales', 'table' => 'tally_sale_lines', 'date' => 'invoice_date', 'value' => 'taxable_amount', 'agg' => 'SUM', 'line' => true],
            'purchases' => ['label' => 'Purchase', 'table' => 'tally_purchase_lines', 'date' => 'purchase_date', 'value' => 'taxable_amount', 'agg' => 'SUM', 'line' => true],
            'payments' => ['label' => 'Payments', 'table' => 'tally_payments', 'date' => 'payment_date', 'value' => 'amount', 'agg' => 'SUM', 'line' => false, 'sign' => -1],
            'receipts' => ['label' => 'Receipts', 'table' => 'tally_receipts', 'date' => 'receipt_date', 'value' => 'amount', 'agg' => 'SUM', 'line' => false],
            'outstanding' => ['label' => 'Outstanding', 'table' => 'tally_outstanding', 'date' => 'bill_date', 'value' => 'amount', 'agg' => 'SUM', 'line' => false],
            'purchase_rate' => ['label' => 'Avg buy rate', 'table' => 'tally_purchase_lines', 'date' => 'purchase_date', 'value' => 'rate', 'agg' => 'AVG', 'line' => true],
        ];
    }

    /** key => [label, parts[]]. */
    private function trendMeasures(): array
    {
        return [
            'sales' => ['label' => 'Sales', 'parts' => ['sales']],
            'purchases' => ['label' => 'Purchases', 'parts' => ['purchases']],
            'receipts' => ['label' => 'Receipts', 'parts' => ['receipts']],
            'payments' => ['label' => 'Payments', 'parts' => ['payments']],
            'outstanding' => ['label' => 'Outstanding', 'parts' => ['outstanding']],
            'purchase_rate' => ['label' => 'Purchase Rate', 'parts' => ['purchase_rate']],
            'purchase_vs_payment' => ['label' => 'Purchase v/s Payment', 'parts' => ['purchases', 'payments']],
            'receipts_vs_payment' => ['label' => 'Receipts v/s Payment', 'parts' => ['receipts', 'payments']],
        ];
    }

    private function fyStartYear($fy): int
    {
        if (is_numeric($fy)) {
            return (int) $fy;
        }
        $t = now();
        return $t->month >= 4 ? $t->year : $t->year - 1;
    }

    /** Shared layout data: which tab is active, and a "last synced" hint per dataset. */
    private function common(string $tab): array
    {
        return [
            'tab' => $tab,
            'counts' => [
                'purchases' => DB::table('tally_purchases')->count(),
                'purchase-orders' => DB::table('tally_purchase_orders')->count(),
                'payments' => DB::table('tally_payments')->count(),
                'stock' => DB::table('tally_stock_items')->count(),
                'ledgers' => DB::table('tally_ledgers')->count(),
            ],
            'pageConfigs' => ['contentLayout' => 'wide'],
        ];
    }
}
