<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\CrmClientMapping;
use App\Support\NameMatcher;
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
    /** ai@rustx.com/mukul@rustx.com reach any ledger; everyone else only one of THEIR OWN customers (see ledgerShow()). */
    private const LEDGER_FULL_ACCESS_EMAILS = ['ai@rustx.com', 'mukul@rustx.com'];

    /**
     * Resolve + authorize the ledger a request names, shared by ledgerShow() and invoicePdf() so the
     * per-customer ownership rule (SalesScope, same as the mapping popup) can never drift between the two
     * entry points. Aborts (404/403) rather than returning a failure state.
     */
    private function isFullLedgerAccess(Request $request): bool
    {
        $user = $request->user();
        return $user && ($user->hasRole(['admin', 'Admin', 'super_admin', 'hr', 'HR'])
            || in_array(strtolower((string) $user->email), array_map('strtolower', self::LEDGER_FULL_ACCESS_EMAILS), true));
    }

    private function resolveLedgerForRequest(Request $request): object
    {
        $user = $request->user();
        $fullAccess = $this->isFullLedgerAccess($request);

        $name = (string) $request->get('name', '');
        $customerId = $request->get('customer_id') ?? $request->route('customer');

        if ($fullAccess && $name !== '') {
            // Full Tally Explorer access (unchanged): any ledger by its exact Tally name.
            $ledger = DB::table('tally_ledgers')->where('name', $name)->first();
        } else {
            // Reached from the Sales Report grid's customer-name link (everyone else, and admin
            // too when that's how they got here) -- resolve via the mapped Customer, not a raw
            // ledger name, so ownership can actually be checked against it below.
            abort_unless($customerId, 404);
            $customer = \App\Models\Customer::find($customerId);
            abort_unless($customer && $customer->tally_matched_name, 404);

            if (!$fullAccess) {
                // Same ownership rule CustomerTallyMappingController::scopedCustomerQuery() already
                // enforces for the mapping popup -- a non-admin only reaches a ledger that is one of
                // THEIR OWN customers on the Sales Report grid. Per explicit request (2026-10-29):
                // Geet Kaur (and anyone else) should only be able to drill into her own customers.
                $ids = \App\Support\SalesScope::allowedUserIds($user);
                if ($ids !== null) {
                    $owns = DB::table('sales_pipelines')
                        ->where('customer_id', $customer->id)
                        ->whereNull('deleted_at')
                        ->where(function ($q) use ($ids) {
                            $q->whereIn('salesperson_id', $ids)
                                ->orWhereIn('ccare_id', $ids)
                                ->orWhereIn('new_biz_id', $ids)
                                ->orWhereIn('new_biz_2_id', $ids)
                                ->orWhereIn('former_new_biz_id', $ids)
                                ->orWhereExists(function ($s) use ($ids) {
                                    $s->selectRaw('1')->from('sales_pipeline_extra_reps as er')
                                        ->whereColumn('er.sales_pipeline_id', 'sales_pipelines.id')
                                        ->whereIn('er.user_id', $ids);
                                });
                        })
                        ->exists();
                    abort_unless($owns, 403);
                }
            }

            $ledger = DB::table('tally_ledgers')->where('normalized_name', $customer->tally_matched_name)->first();
        }
        abort_unless($ledger, 404);

        return $ledger;
    }

    public function ledgerShow(Request $request): View
    {
        $ledger = $this->resolveLedgerForRequest($request);
        $norm = $ledger->normalized_name;

        // Same two entry points this whole page supports (a route {customer} id, or ?name= for full
        // Tally Explorer access) carried through to the invoice-items modal and its PDF link, so either
        // path re-authorizes through resolveLedgerForRequest() exactly the way this page itself just did.
        $customerIdForLinks = $request->route('customer') ?? $request->get('customer_id');
        [$billRouteName, $pdfRouteName] = $customerIdForLinks
            ? ['sales-visits.pipeline.customer.tally-ledger.invoice', 'sales-visits.pipeline.customer.tally-ledger.invoice.pdf']
            : ['tally-explorer.ledgers.invoice', 'tally-explorer.ledgers.invoice.pdf'];
        $linkParams = $customerIdForLinks ? ['customer' => $customerIdForLinks] : ['name' => $ledger->name];
        // {customer} is consumed as a path segment (no query string yet, so start one with '?'); 'name'
        // has no route placeholder on the Tally Explorer path, so Laravel already appends it as '?name=...'.
        $sep = $customerIdForLinks ? '?' : '&';
        $billUrl = route($billRouteName, $linkParams) . $sep . 'invoice_no=';
        $pdfUrlBase = route($pdfRouteName, $linkParams) . $sep . 'invoice_no=';
        $itemRouteName = $customerIdForLinks ? 'sales-visits.pipeline.customer.tally-ledger.item' : 'tally-explorer.ledgers.item';
        $itemUrlBase = route($itemRouteName, $linkParams) . $sep . 'item=';
        $deliveryRouteName = $customerIdForLinks ? 'sales-visits.pipeline.customer.tally-ledger.delivery' : 'tally-explorer.ledgers.delivery';
        $deliveryUrlBase = route($deliveryRouteName, $linkParams) . $sep . 'delivery_no=';

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

        // Full, uncapped lists -- superseded the old 20-row merged "Recent activity" feed (removed
        // 2026-10-29 along with its dedicated layout slot): these are every row Tally has for this ledger,
        // shown in full in their own cards further down the page, each
        // in its own scrollable table below, so nothing is hidden behind a cap.
        $allInvoices = $norm ? $sales()->orderByDesc('invoice_date')
            ->get(['invoice_no', 'invoice_date', 'series', 'salesperson', 'amount', 'taxable_total', 'tax_total', 'lr_no', 'transporter', 'destination', 'po_number', 'extra']) : collect();

        // Item names per invoice, for the All Invoices table -- grouped in PHP from one bulk query (an
        // invoice can have several lines) rather than a GROUP_CONCAT, so a very long item list can still be
        // truncated for display without truncating the underlying data. Sourced from tally_sale_lines: the
        // live gateway push (hrx:tally-push) writes this for every invoice line it sees, not just the
        // roughly-quarter-of-invoices the old file-based SaleReportCRM import covered, so this is
        // effectively complete for anything pushed since that pipeline went live.
        $itemsByInvoice = [];
        if ($norm) {
            foreach (DB::table('tally_sale_lines')->where('normalized_name', $norm)->get(['invoice_no', 'item_name']) as $line) {
                $itemsByInvoice[$line->invoice_no][] = $line->item_name;
            }
        }
        $allPurchases = $norm ? $purch()->orderByDesc('purchase_date')
            ->get(['purchase_no', 'purchase_date', 'series', 'amount']) : collect();
        $allReceipts = $norm ? $rcpt()->orderByDesc('receipt_date')
            ->get(['receipt_no', 'receipt_date', 'voucher_type', 'bank_ledger', 'reference', 'amount']) : collect();
        $allPayments = $norm ? $paid()->orderByDesc('payment_date')
            ->get(['payment_no', 'payment_date', 'voucher_type', 'bank_ledger', 'reference', 'amount']) : collect();

        // Pending (open, not yet billed/received) sale orders for a customer, purchase orders for a
        // supplier -- PartyWiseSaleOrder.txt's own 'status' column, not derived.
        $pendingSaleOrders = $norm ? DB::table('tally_sale_orders')->where('normalized_name', $norm)
            ->orderByDesc('order_date')->get(['order_no', 'order_date', 'status', 'quantity', 'unit', 'amount']) : collect();
        $pendingPurchaseOrders = $norm ? DB::table('tally_purchase_orders')->where('normalized_name', $norm)
            ->orderByDesc('order_date')->get(['order_no', 'order_date', 'item_name', 'quantity', 'unit', 'amount']) : collect();

        // GSTIN "at any cost": the ledger master's own field is sometimes blank even when every invoice
        // billed to this party carries its GSTIN (captured straight off the voucher, PARTYGSTIN, at the
        // time of billing - see TallyGateway::parseVouchers()/TallyPushCommand). Fall back to whatever
        // GSTIN the invoices themselves show when the master has none, and surface every distinct value
        // seen (a mismatch between them is itself worth knowing, not something to silently pick one of).
        $invoiceGstins = $norm ? DB::table('tally_invoices')
            ->where('normalized_name', $norm)
            ->whereNotNull('extra')
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(extra, '$.party_gstin')) as gstin, MAX(invoice_date) as last_seen, COUNT(*) as invoice_count")
            ->groupBy('gstin')
            ->havingRaw("gstin IS NOT NULL AND gstin <> ''")
            ->orderByDesc('last_seen')
            ->get() : collect();
        $displayGstin = $ledger->gstin ?: optional($invoiceGstins->first())->gstin;
        $gstinFromInvoicesOnly = !$ledger->gstin && $displayGstin;

        // Delivery / transport activity (how goods actually ship to them) -- full list, not capped, per
        // explicit request (2026-10-29): transport details for every dispatch on this same page. No
        // expected/target dispatch date exists anywhere in Tally for this company (confirmed directly on
        // both Sales Order and Delivery Note vouchers when this table was first built) -- delivery_date is
        // always the ACTUAL dispatch date, never an ETA.
        $deliveries = $norm ? DB::table('tally_deliveries')->where('normalized_name', $norm)
            ->orderByDesc('delivery_date')
            ->get(['delivery_date', 'delivery_no', 'order_no', 'item_name', 'quantity', 'unit', 'transporter_name', 'vehicle_number', 'transport_mode', 'document_number', 'consignee_name']) : collect();

        // This ledger's matching CRM client record, if one exists -- same normalized-name equality
        // CrmCustomerResolver uses to connect a CrmClientMapping to Tally data (see its keysFor()),
        // just run in the opposite direction (Tally ledger -> CRM mapping). Loads the whole mapping
        // table's id+name only (cheap, a few hundred rows) to find the match in PHP, since the
        // normalization itself isn't expressible as a SQL predicate.
        $crmMapping = null;
        $crmContacts = collect();
        $crmActivities = collect();
        $crmFollowUps = collect();
        $crmTeam = collect();
        if ($norm) {
            $crmMapping = CrmClientMapping::query()->get(['id', 'crm_company_name', 'crm_city', 'crm_state', 'crm_stage', 'created_at'])
                ->first(fn ($m) => NameMatcher::normalize($m->crm_company_name) === $norm);
            if ($crmMapping) {
                $crmContacts = $crmMapping->contacts()->orderByDesc('is_primary')->get();
                $crmActivities = $crmMapping->activities()->with(['createdBy'])->limit(15)->get();
                $crmFollowUps = $crmMapping->activities()->where('needs_follow_up', true)->whereNull('follow_up_completed_at')->orderBy('follow_up_due_at')->get();
                $crmTeam = $crmMapping->teamMembersByRole();
            }
        }

        // Which Tally company this ledger lives in (Hi Tech vs Keep It Fresh) -- the human label from
        // config/services.php tally.companies, falling back to the raw stored code if it's ever not one
        // of the configured ones.
        $companyLabel = config("services.tally.companies.{$ledger->company}.label") ?: $ledger->company;

        return view('tenant.tally-explorer.ledger-detail', compact(
            'ledger', 'kpi', 'terms', 'ageing', 'trend', 'outstanding', 'deliveries', 'isCustomer', 'isSupplier',
            'crmMapping', 'crmContacts', 'crmActivities', 'crmFollowUps', 'crmTeam', 'companyLabel',
            'allInvoices', 'allPurchases', 'allReceipts', 'allPayments', 'itemsByInvoice', 'billUrl', 'pdfUrlBase',
            'customerIdForLinks', 'itemUrlBase', 'deliveryUrlBase',
            'pendingSaleOrders', 'pendingPurchaseOrders', 'displayGstin', 'gstinFromInvoicesOnly', 'invoiceGstins'
        ) + ['pageConfigs' => ['contentLayout' => 'wide']]);
    }

    /**
     * JSON for the invoice-items modal (resources/views/tenant/sales-sources/partials/invoice-modal.blade.php
     * -- the same modal the CRM Customer Profile and Sales Sources pages already use), scoped to THIS
     * ledger the same way CustomerProfileController::invoice() scopes it to a CRM mapping: reuses
     * resolveLedgerForRequest()'s ownership check, then refuses an invoice_no that doesn't actually belong
     * to this ledger's normalized_name, so a customer-scoped viewer can never pull up an invoice outside
     * what they're allowed to see just by guessing a number. The payload shape itself
     * (TallyAnalytics::bill()) is shared verbatim with those admin-only pages -- same item lines, GST,
     * reconciliation note and outstanding status.
     */
    public function invoiceDetail(Request $request): \Illuminate\Http\JsonResponse
    {
        $ledger = $this->resolveLedgerForRequest($request);
        $norm = $ledger->normalized_name;

        $no = trim((string) $request->query('invoice_no'));
        abort_unless($no !== '' && $norm && DB::table('tally_invoices')->where('invoice_no', $no)->where('normalized_name', $norm)->exists(), 404);

        $bill = (new \App\Services\TallyAnalytics(\App\Services\TallyAnalytics::filters([])))->bill($no);
        abort_if($bill === null, 404);

        return response()->json($bill)->header('Cache-Control', 'private, max-age=60');
    }

    /**
     * JSON for the item price-analysis modal (clicking an item inside the invoice-items modal) -- the
     * same data the Sales Sources > Analytics "Bill-wise" page's own item drill-down uses
     * (TallyAnalytics::item()), company-wide pricing stats and history, BUT with the cross-customer parts
     * removed for anyone who isn't full-access: 'customers' (who else buys it) and 'recent' (their recent
     * purchases) name OTHER customers, which is outside a per-customer-scoped viewer's ownership rights --
     * the same boundary resolveLedgerForRequest() already enforces for the ledger page itself. 'recent' is
     * filtered down to this ledger's OWN purchases of the item rather than dropped outright, so the price
     * history a salesperson actually needs (what has WE been charged) still shows. The aggregate KPIs
     * (avg/min/max rate, totals) are never customer-identifying, so those are untouched either way.
     */
    public function itemDetail(Request $request): \Illuminate\Http\JsonResponse
    {
        $ledger = $this->resolveLedgerForRequest($request);
        $norm = $ledger->normalized_name;

        $name = (string) $request->query('item');
        abort_if($name === '', 404);
        $unit = (string) $request->query('unit', '');
        $scope = $request->query('scope') === 'all' ? 'all' : 'period';

        $data = (new \App\Services\TallyAnalytics(\App\Services\TallyAnalytics::filters($request->query())))->item($name, $unit, $scope);

        if (!$this->isFullLedgerAccess($request)) {
            $data['customers'] = [];
            $data['recent'] = collect($data['recent'] ?? [])
                ->filter(fn ($r) => $norm && NameMatcher::normalize((string) ($r['party'] ?? '')) === $norm)
                ->values()->all();
            $data['restricted'] = true;
        }

        return response()->json($data)->header('Cache-Control', 'private, max-age=60');
    }

    /**
     * JSON for the delivery-detail modal (clicking a row in "Recent deliveries") -- every field Tally
     * carries for that Delivery Note: transporter name, vehicle number, transport mode, document no/date,
     * distance, the consignee (who goods were actually sent to, which can differ from the billed party),
     * and every item line on it (a Delivery Note is one row per SKU, same shape as an invoice). Scoped to
     * this ledger's own normalized_name via resolveLedgerForRequest(), the same ownership rule as
     * invoiceDetail()/itemDetail().
     */
    public function deliveryDetail(Request $request): \Illuminate\Http\JsonResponse
    {
        $ledger = $this->resolveLedgerForRequest($request);
        $norm = $ledger->normalized_name;

        $no = trim((string) $request->query('delivery_no'));
        abort_if($no === '', 404);

        $rows = DB::table('tally_deliveries')->where('delivery_no', $no)->where('normalized_name', $norm)
            ->orderBy('id')->get();
        abort_if($rows->isEmpty(), 404);

        $first = $rows->first();

        return response()->json([
            'delivery_no' => $first->delivery_no,
            'delivery_date' => $first->delivery_date,
            'order_no' => $first->order_no,
            'transporter_name' => $first->transporter_name,
            'vehicle_number' => $first->vehicle_number,
            'transport_mode' => $first->transport_mode,
            'document_number' => $first->document_number,
            'document_date' => $first->document_date,
            'distance_km' => $first->distance_km !== null ? (float) $first->distance_km : null,
            'consignee_name' => $first->consignee_name,
            'consignee_address' => $first->consignee_address,
            'consignee_gstin' => $first->consignee_gstin,
            'lines' => $rows->map(fn ($r) => [
                'item' => $r->item_name,
                'qty' => $r->quantity !== null ? (float) $r->quantity : null,
                'unit' => $r->unit,
            ])->values(),
        ])->header('Cache-Control', 'private, max-age=60');
    }

    /**
     * A quick-reference "Tax Invoice" PDF for one invoice of this ledger -- built entirely from our own
     * synced data (tally_invoices + tally_sale_lines' item/HSN/GST breakdown), NOT a replica of Tally's own
     * statutory print (which also carries our bank details, signatory and legal boilerplate that live only
     * inside Tally itself). Useful for a quick look or to forward without opening Tally; the authoritative
     * document for filing/dispute purposes is still Tally's own print.
     *
     * Reuses ledgerShow()'s own ownership check via resolveLedgerForRequest() so this can never be used to
     * read an invoice belonging to a customer outside the requester's own scope.
     */
    public function invoicePdf(Request $request)
    {
        $ledger = $this->resolveLedgerForRequest($request);
        $norm = $ledger->normalized_name;

        $invoiceNo = trim((string) $request->query('invoice_no'));
        abort_if($invoiceNo === '', 404);

        $invoice = DB::table('tally_invoices')->where('normalized_name', $norm)->where('invoice_no', $invoiceNo)->first();
        abort_unless($invoice, 404);

        $lines = DB::table('tally_sale_lines')->where('invoice_no', $invoiceNo)
            ->orderBy('id')
            ->get(['item_name', 'hsn', 'gst_rate', 'quantity', 'unit', 'rate', 'taxable_amount', 'tax_amount']);

        $extra = $invoice->extra ? json_decode($invoice->extra, true) : [];
        $settings = \App\Models\Settings::first();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('tenant.tally-explorer.invoice-pdf', [
            'invoice' => $invoice,
            'ledger' => $ledger,
            'lines' => $lines,
            'partyGstin' => $extra['party_gstin'] ?? $ledger->gstin,
            'placeOfSupply' => $extra['place_of_supply'] ?? $ledger->state,
            'settings' => $settings,
        ]);

        return $pdf->stream('invoice-' . str_replace(['/', '\\'], '-', $invoiceNo) . '.pdf');
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

    /** doc-no column name per trend part table -- the one field trendParts() doesn't already carry, since it differs per table. */
    private function partDocNoColumn(string $table): string
    {
        return match ($table) {
            'tally_sale_lines' => 'invoice_no',
            'tally_purchase_lines' => 'purchase_no',
            'tally_payments' => 'payment_no',
            'tally_receipts' => 'receipt_no',
            'tally_outstanding' => 'bill_ref',
            default => 'id',
        };
    }

    /**
     * Underlying transaction-level rows behind one trend cell (one dimension
     * value x one month) -- the drill-down a click on any cell opens. Shared
     * by trendCellDetail() (JSON, for the popup) and trendCellExport() (CSV).
     * Applies the exact same dimension/filter logic as partPivot() so the
     * popup's total always reconciles with the cell that was clicked.
     */
    private function buildCellData(Request $request): array
    {
        $measures = $this->trendMeasures();
        $measure = isset($measures[$request->get('measure')]) ? (string) $request->get('measure') : 'sales';
        $parts = $measures[$measure]['parts'];
        $partDefs = $this->trendParts();

        $dims = $this->trendDims();
        $dim = isset($dims[$request->get('dim')]) ? (string) $request->get('dim') : 'party';
        $dimkey = (string) $request->get('dimkey', '');
        $ym = (string) $request->get('ym', '');

        $filterDim = in_array($request->get('filter_dim'), array_keys($dims), true) ? (string) $request->get('filter_dim') : '';
        $filterVals = array_values(array_filter(array_map('trim', (array) $request->get('filter_vals', []))));

        $label = null;
        $sections = [];
        foreach ($parts as $pk) {
            $part = $partDefs[$pk];
            $section = $this->cellRowsForPart($part, $dim, $dimkey, $ym, $filterDim, $filterVals);
            $label ??= $section['label'];
            $sections[] = ['part' => $part['label'], 'columns' => $section['columns'], 'rows' => $section['rows'], 'total' => $section['total']];
        }

        // Credit/Debit Notes aren't one of the trend measures (Tally books them as their own voucher type, not
        // sales/purchases), but they adjust what a customer/supplier actually owes -- worth surfacing alongside
        // whatever the cell was already showing for that party and month, not hidden behind a separate screen.
        if ($dim === 'party' && $dimkey !== '' && $ym !== '') {
            $noteSection = $this->creditDebitNoteSection($dimkey, $ym);
            if ($noteSection['rows']) {
                $sections[] = $noteSection;
            }
        }

        // A cell grouped by Salesperson covers MANY customers' transactions at once -- a flat line-item dump
        // alone leaves "how did this person do this month" buried. Surface the roll-up a sales manager actually
        // wants (total, how many distinct customers, vs last month) above the raw rows.
        $summary = null;
        if ($dim === 'salesperson' && $dimkey !== '' && $ym !== '') {
            $summary = $this->salespersonMonthSummary($parts, $partDefs, $dimkey, $ym);
        }

        return ['label' => $label ?? $dimkey, 'measure' => $measures[$measure]['label'], 'ym' => $ym, 'sections' => $sections, 'summary' => $summary];
    }

    /**
     * Total sales, distinct customers billed, and the month-on-month change for one Salesperson (sales group)
     * cell -- the roll-up the popup shows above the raw transaction rows when the cell is grouped by salesperson.
     */
    private function salespersonMonthSummary(array $parts, array $partDefs, string $salesperson, string $ym): array
    {
        $monthTotals = function (string $month) use ($parts, $partDefs, $salesperson) {
            $total = 0.0;
            $customers = collect();
            foreach ($parts as $pk) {
                $part = $partDefs[$pk];
                $table = $part['table'];
                $base = DB::table($table)
                    ->join('tally_ledgers as l', "{$table}.normalized_name", '=', 'l.normalized_name')
                    ->where('l.ledger_group', $salesperson)
                    ->whereRaw("DATE_FORMAT({$table}.{$part['date']}, '%Y-%m') = ?", [$month]);
                $sign = $part['sign'] ?? 1;
                $valueExpr = $part['value_sql'] ?? "{$table}.{$part['value']}";
                $total += ((float) (clone $base)->selectRaw("SUM({$valueExpr}) as t")->value('t')) * $sign;
                (clone $base)->whereNotNull("{$table}.normalized_name")->distinct()
                    ->pluck("{$table}.normalized_name")->each(fn ($n) => $customers->push($n));

                // Same Credit/Debit Note netting as partPivot() -- keeps this summary's total in sync with
                // the grid cell it's opened from, instead of a "Sales" figure that silently excludes returns.
                if (!empty($part['note_side'])) {
                    $side = $part['note_side'];
                    $signedSum = "SUM(CASE WHEN note_type = 'credit_{$side}' THEN -(amount + IFNULL(tax_amount, 0)) ELSE (amount + IFNULL(tax_amount, 0)) END)";
                    $total += (float) DB::table('tally_credit_debit_note_lines')
                        ->join('tally_ledgers as l', 'tally_credit_debit_note_lines.normalized_name', '=', 'l.normalized_name')
                        ->where('l.ledger_group', $salesperson)
                        ->whereIn('note_type', ["credit_{$side}", "debit_{$side}"])
                        ->whereRaw("DATE_FORMAT(note_date, '%Y-%m') = ?", [$month])
                        ->selectRaw("{$signedSum} as t")->value('t');
                }
            }
            return ['total' => round($total, 2), 'customers' => $customers->unique()->count()];
        };

        $this_ = $monthTotals($ym);
        $prevYm = date('Y-m', strtotime($ym . '-01 -1 month'));
        $prev = $monthTotals($prevYm);

        $pctChange = $prev['total'] != 0.0 ? round((($this_['total'] - $prev['total']) / abs($prev['total'])) * 100, 1) : null;

        return [
            'salesperson' => $salesperson,
            'month' => $ym,
            'total' => $this_['total'],
            'customers' => $this_['customers'],
            'prev_month' => $prevYm,
            'prev_total' => $prev['total'],
            'pct_change' => $pctChange,
        ];
    }

    /** Credit/Debit Note lines for one party x month, regardless of which trend measure the cell belongs to. */
    private function creditDebitNoteSection(string $normalizedName, string $ym): array
    {
        $labels = [
            'credit_sale' => 'Credit Note (Sale)', 'debit_sale' => 'Debit Note (Sale)',
            'credit_purchase' => 'Credit Note (Purchase)', 'debit_purchase' => 'Debit Note (Purchase)',
        ];
        $dbRows = DB::table('tally_credit_debit_note_lines')
            ->where('normalized_name', $normalizedName)
            ->whereRaw("DATE_FORMAT(note_date, '%Y-%m') = ?", [$ym])
            ->orderBy('note_date')->orderBy('note_no')
            ->get(['note_type', 'note_date', 'note_no', 'party_name', 'item_name', 'hsn', 'gst_rate', 'quantity', 'unit', 'rate', 'amount', 'tax_amount']);

        $rows = $dbRows->map(fn ($r) => [
            'type' => $labels[$r->note_type] ?? $r->note_type,
            'date' => $r->note_date ? \Illuminate\Support\Carbon::parse($r->note_date)->format('d-M-Y') : '',
            'doc_no' => $r->note_no, 'party' => $r->party_name, 'item' => $r->item_name, 'hsn' => $r->hsn,
            'gst' => $r->gst_rate, 'qty' => $r->quantity, 'unit' => $r->unit, 'rate' => $r->rate,
            'taxable' => round((float) $r->amount, 2), 'tax' => round((float) $r->tax_amount, 2),
            'total' => round((float) $r->amount + (float) $r->tax_amount, 2),
        ])->values()->all();

        // Signed so this figure matches what actually got netted into the Sales/Purchase total above -- a
        // credit note SUBTRACTS (what it's for), a debit note adds.
        $signedTotal = $dbRows->sum(fn ($r) => (str_starts_with($r->note_type, 'credit') ? -1 : 1) * ((float) $r->amount + (float) $r->tax_amount));

        return [
            'part' => 'Credit/Debit Notes',
            'columns' => ['Type', 'Date', 'Doc No', 'Customer', 'Item', 'HSN', 'GST %', 'Qty', 'Unit', 'Rate', 'Taxable Amount', 'Tax Amount', 'Total'],
            'rows' => $rows,
            'total' => round($signedTotal, 2),
        ];
    }

    /** One part's raw rows for buildCellData() -- line-item detail for sales/purchases, header-level rows otherwise. */
    private function cellRowsForPart(array $part, string $dim, string $dimkey, string $ym, string $filterDim, array $filterVals): array
    {
        $dims = $this->trendDims();
        $d = $dims[$dim] ?? null;
        $table = $part['table'];
        if (!$d || $dimkey === '' || $ym === '' || (!empty($d[4]) && empty($part['line']))) {
            return ['label' => null, 'columns' => [], 'rows' => [], 'total' => 0];
        }

        $groupExpr = $d[1] === 'ledger' ? "l.{$d[2]}" : "{$table}.{$d[2]}";
        $needJoin = $d[1] === 'ledger' || ($filterDim !== '' && ($dims[$filterDim][1] ?? '') === 'ledger');

        $base = DB::table($table);
        if ($needJoin) {
            $base->join('tally_ledgers as l', "{$table}.normalized_name", '=', 'l.normalized_name');
        }
        $base->where($groupExpr, $dimkey)
            ->whereRaw("DATE_FORMAT({$table}.{$part['date']}, '%Y-%m') = ?", [$ym]);

        if ($filterDim !== '' && $filterVals) {
            $fd = $dims[$filterDim];
            if (empty($fd[4]) || !empty($part['line'])) {
                $base->whereIn($fd[1] === 'ledger' ? "l.{$fd[2]}" : "{$table}.{$fd[2]}", $filterVals);
            }
        }

        $docNoCol = $this->partDocNoColumn($table);
        $sign = $part['sign'] ?? 1;

        if (!empty($part['line'])) {
            $columns = ['Date', 'Bill No', 'Customer', 'Item', 'HSN', 'GST %', 'Qty', 'Unit', 'Rate', 'Taxable Amount', 'Tax Amount', 'Total'];
            $rows = (clone $base)
                ->orderBy("{$table}.{$part['date']}")->orderBy("{$table}.{$docNoCol}")
                ->get(["{$table}.{$part['date']} as date", "{$table}.{$docNoCol} as doc_no", "{$table}.party_name",
                       "{$table}.item_name", "{$table}.hsn", "{$table}.gst_rate", "{$table}.quantity", "{$table}.unit",
                       "{$table}.rate", "{$table}.taxable_amount", "{$table}.tax_amount"])
                ->map(fn ($r) => [
                    'date' => $r->date ? \Illuminate\Support\Carbon::parse($r->date)->format('d-M-Y') : '',
                    'doc_no' => $r->doc_no, 'party' => $r->party_name, 'item' => $r->item_name, 'hsn' => $r->hsn,
                    'gst' => $r->gst_rate, 'qty' => $r->quantity, 'unit' => $r->unit, 'rate' => $r->rate,
                    'taxable' => round((float) $r->taxable_amount, 2), 'tax' => round((float) $r->tax_amount, 2),
                    'total' => round((float) $r->taxable_amount + (float) $r->tax_amount, 2),
                ])->values()->all();
            // Matches partPivot()'s own column choice: tax-inclusive total when this part aggregates on
            // value_sql (Sales/Purchases), the per-line rate for Purchase Rate (an average, not a sum of money).
            $totalCol = $part['value'] === 'rate' ? 'rate' : (isset($part['value_sql']) ? 'total' : 'taxable');
            $total = round(array_sum(array_column($rows, $totalCol)) * $sign, 2);
        } else {
            $columns = ['Date', 'Doc No', 'Party', 'Amount'];
            $rows = (clone $base)
                ->orderBy("{$table}.{$part['date']}")
                ->get(["{$table}.{$part['date']} as date", "{$table}.{$docNoCol} as doc_no", "{$table}.party_name", "{$table}.{$part['value']} as amount"])
                ->map(fn ($r) => [
                    'date' => $r->date ? \Illuminate\Support\Carbon::parse($r->date)->format('d-M-Y') : '',
                    'doc_no' => $r->doc_no, 'party' => $r->party_name, 'amount' => round((float) $r->amount, 2),
                ])->values()->all();
            $total = round(array_sum(array_column($rows, 'amount')) * $sign, 2);
        }

        // Only meaningful when grouped by Customer/Supplier itself (dimkey IS the party there). For every other
        // dimension (salesperson, city, state, ...) this table's party_name is just whichever row happened to
        // come back first -- an arbitrary customer, not the thing the popup is titled after -- so fall back to
        // the dimension value the cell was actually clicked for.
        $label = $dim === 'party' ? (clone $base)->value("{$table}.party_name") : $dimkey;

        return ['label' => $label, 'columns' => $columns, 'rows' => $rows, 'total' => $total];
    }

    public function trendCellDetail(Request $request): JsonResponse
    {
        return response()->json($this->buildCellData($request));
    }

    public function trendCellExport(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $data = $this->buildCellData($request);
        $filename = 'tally-' . str_replace(' ', '-', strtolower($data['measure'])) . '-' . ($data['label'] ?: 'detail') . '-' . $data['ym'] . '.csv';
        $filename = preg_replace('/[^A-Za-z0-9\-\.]+/', '_', $filename);

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            foreach ($data['sections'] as $section) {
                if (count($data['sections']) > 1) {
                    fputcsv($out, [$section['part']]);
                }
                fputcsv($out, $section['columns']);
                foreach ($section['rows'] as $row) {
                    fputcsv($out, array_values($row));
                }
                fputcsv($out, []);
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
        $valExpr = "{$part['agg']}(" . ($part['value_sql'] ?? "{$table}.{$part['value']}") . ") * {$sign}";

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

        if (!empty($part['note_side'])) {
            foreach ($this->creditDebitNoteAdjustmentPivot($part['note_side'], $dim, $filterDim, $filterVals, $start, $end) as $k => $d) {
                $out[$k]['label'] ??= $d['label'];
                foreach ($d['cells'] as $ym => $adj) {
                    $out[$k]['cells'][$ym] = ($out[$k]['cells'][$ym] ?? 0.0) + $adj;
                }
            }
        }

        return $out;
    }

    /**
     * Credit/Debit Note adjustment for one side ('sale' or 'purchase'), same (dimkey => [label, cells]) shape
     * partPivot() itself returns, so it merges straight in. A Credit Note reduces what the party owes
     * (subtracted); a Debit Note increases it (added) -- matches how Setu's own "Sales" figure already nets
     * these, confirmed against a real month where the gap to Setu was accounted for almost exactly by that
     * month's Credit (Sale) Notes. tally_credit_debit_note_lines has no `city` column (unlike the sale/purchase
     * line tables it otherwise mirrors), so the 'city' dim is skipped here -- a known, narrow gap, not a bug.
     */
    private function creditDebitNoteAdjustmentPivot(string $side, string $dim, string $filterDim, array $filterVals, $start, $end): array
    {
        if ($dim === 'city') {
            return [];
        }
        $dims = $this->trendDims();
        $d = $dims[$dim];
        $table = 'tally_credit_debit_note_lines';

        $groupExpr = $d[1] === 'ledger' ? "l.{$d[2]}" : "{$table}.{$d[2]}";
        $labelExpr = ($d[1] === 'self' && $d[2] === 'normalized_name') ? "MAX({$table}.party_name)" : $groupExpr;

        $needJoin = $d[1] === 'ledger' || ($filterDim !== '' && ($dims[$filterDim][1] ?? '') === 'ledger');
        $base = DB::table($table)->whereIn('note_type', ["credit_{$side}", "debit_{$side}"]);
        if ($needJoin) {
            $base->join('tally_ledgers as l', "{$table}.normalized_name", '=', 'l.normalized_name');
        }
        $base->whereBetween("{$table}.note_date", [$start, $end])
            ->whereNotNull($groupExpr)->where($groupExpr, '!=', '');

        if ($filterDim !== '' && $filterVals) {
            $fd = $dims[$filterDim];
            $base->whereIn($fd[1] === 'ledger' ? "l.{$fd[2]}" : "{$table}.{$fd[2]}", $filterVals);
        }

        // credit_* subtracts, debit_* adds -- the CASE expression does that without a second query per sign.
        $signedSum = "SUM(CASE WHEN {$table}.note_type = 'credit_{$side}' THEN -({$table}.amount + IFNULL({$table}.tax_amount, 0)) ELSE ({$table}.amount + IFNULL({$table}.tax_amount, 0)) END)";
        $rows = $base->selectRaw("$groupExpr dimkey, $labelExpr dimlabel, DATE_FORMAT({$table}.note_date, '%Y-%m') ym, $signedSum adj")
            ->groupBy(DB::raw($groupExpr), DB::raw("DATE_FORMAT({$table}.note_date, '%Y-%m')"))->get();

        $out = [];
        foreach ($rows as $r) {
            $out[$r->dimkey]['label'] ??= $r->dimlabel;
            $out[$r->dimkey]['cells'][$r->ym] = (float) $r->adj;
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
            // 'value' stays taxable_amount (used for the Purchase Rate measure's own math and as the CSV/line
            // fallback); 'value_sql' is what Sales/Purchase actually AGGREGATE with -- taxable + GST, so the
            // Trend totals match an invoice's/Setu's own tax-inclusive total instead of silently under-reporting
            // by the GST portion (confirmed against a real customer: taxable-only was short by exactly the GST).
            // 'note_side' nets Credit/Debit Notes into this part's totals (see creditDebitNoteAdjustmentPivot):
            // a Credit Note reduces what the party owes (subtracted), a Debit Note increases it (added) -- same
            // convention Setu's own "Sales" figure already uses, confirmed against a real month where the gap
            // was accounted for almost exactly by that month's Credit (Sale) Notes.
            'sales' => ['label' => 'Sales', 'table' => 'tally_sale_lines', 'date' => 'invoice_date', 'value' => 'taxable_amount',
                        'value_sql' => 'tally_sale_lines.taxable_amount + IFNULL(tally_sale_lines.tax_amount, 0)', 'agg' => 'SUM', 'line' => true, 'note_side' => 'sale'],
            'purchases' => ['label' => 'Purchase', 'table' => 'tally_purchase_lines', 'date' => 'purchase_date', 'value' => 'taxable_amount',
                        'value_sql' => 'tally_purchase_lines.taxable_amount + IFNULL(tally_purchase_lines.tax_amount, 0)', 'agg' => 'SUM', 'line' => true, 'note_side' => 'purchase'],
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
    /**
     * Web-triggered "quick sync" for a short, recent date range -- a fast way for anyone to pull just today's
     * (or the last few days') Tally data without waiting on a full-month backfill. Capped at 7 days so a click
     * here always stays "quick"; a longer backfill belongs to the existing TallySyncJob/poller pipeline
     * (app/Console/Commands/TallyJobsPollCommand.php), not this synchronous action.
     *
     * target-url is hardcoded to the LOCAL dev server, never read from config/env -- config('tally_push.outbound
     * .target_url') defaults to the PRODUCTION push endpoint (TALLY_PUSH_TARGET_URL in .env points at
     * hrx.hitechgroup.in), and this project's standing rule is local-only, never deploy/push unasked. A
     * web-triggered sync is exactly the kind of action an accidental click could fire, so the guard against
     * ever hitting prod has to be unconditional here, not just "the default happens to be safe right now."
     */
    public function quickSync(Request $request): \Illuminate\Http\RedirectResponse
    {
        // The Tally gateway (192.168.2.195:9080) is a LAN address only reachable from the office PC --
        // production (Hostinger, a remote host) has no network path to it, same reason the scheduled sync
        // tasks run on the office PC and push TO production rather than production pulling from Tally. The
        // button itself is hidden outside local (see layout.blade.php); this is the server-side backstop.
        if (!app()->environment('local')) {
            abort(404);
        }

        $data = $request->validate([
            'company' => ['required', 'string', 'in:hitech,kif'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $from = \Illuminate\Support\Carbon::parse($data['from'])->startOfDay();
        $to = \Illuminate\Support\Carbon::parse($data['to'])->startOfDay();
        if ($from->diffInDays($to) > 6) {
            return back()->with('error', 'Quick Sync is capped at 7 days -- use the full backfill job for a longer range.');
        }
        if ($to->gt(now())) {
            return back()->with('error', 'The "to" date cannot be in the future.');
        }

        $companyCode = $data['company'];
        $gatewayUrl = (string) config('services.tally.write_gateway_url');
        $gatewayCompany = $companyCode === 'kif'
            ? (string) config('services.tally.companies.kif.name')
            : (string) config('services.tally.write_gateway_company');

        if ($gatewayUrl === '' || $gatewayCompany === '') {
            return back()->with('error', "No gateway configured for company \"{$companyCode}\" -- check TALLY_WRITE_GATEWAY_URL / TALLY_COMPANY_KIF_NAME in .env.");
        }

        $exitCode = \Illuminate\Support\Facades\Artisan::call('hrx:tally-push', [
            '--from' => $from->format('Y-m-d'),
            '--to' => $to->format('Y-m-d'),
            '--company' => $companyCode,
            '--gateway-url' => $gatewayUrl,
            '--gateway-company' => $gatewayCompany,
            '--target-url' => 'http://127.0.0.1:8003/api/tally-push/sync',
            '--emit-totals' => true,
        ]);

        $output = \Illuminate\Support\Facades\Artisan::output();
        preg_match('/TALLY_TOTALS\s+(\{.*\})/s', $output, $m);
        $totals = isset($m[1]) ? json_decode($m[1], true) : null;

        if ($exitCode !== 0) {
            // The command's own "FAILED -- ..." line names the exact day and reason (usually a Tally gateway
            // timeout) -- surface that instead of a bare non-zero exit code.
            preg_match('/FAILED.*$/m', $output, $fm);
            return back()->with('error', 'Quick Sync stopped partway: ' . ($fm[0] ?? 'see server logs for details.'));
        }

        $summary = $totals
            ? sprintf('invoices %s · sale lines %s · credit/debit notes %s · purchases %s',
                number_format((int) ($totals['invoices'] ?? 0)), number_format((int) ($totals['sale_lines'] ?? 0)),
                number_format((int) ($totals['credit_debit_note_lines'] ?? 0)), number_format((int) ($totals['purchases'] ?? 0)))
            : 'done';

        return back()->with('success', sprintf(
            'Quick Sync complete: %s (%s) %s to %s — %s',
            config("services.tally.companies.{$companyCode}.label", $companyCode), $companyCode,
            $from->format('d-M-Y'), $to->format('d-M-Y'), $summary
        ));
    }

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
