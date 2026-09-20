@extends('tenant.sales-sources.layout')

@section('page-style')
<style>
  .an-card { border-radius: 16px; background: #fff; box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 4px 14px rgba(15,23,42,.05); padding: 1.1rem 1.2rem; height: 100%; }
  .an-card h6 { font-size: .8rem; font-weight: 800; color: #1e293b; margin: 0; }
  .an-card .an-sub { font-size: .7rem; color: #94a3b8; margin-top: 2px; }
  .an-filters .btn-group .btn.active { background: #127464; border-color: #127464; color: #fff; }
  .an-filters .form-control, .an-filters .form-select { min-width: 0; }

  .an-kpi { border-radius: 16px; background: #fff; box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 4px 14px rgba(15,23,42,.05); padding: .95rem 1.1rem; height: 100%; position: relative; overflow: hidden; }
  .an-kpi::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: var(--an-accent, #127464); }
  .an-kpi .lbl { font-size: .66rem; text-transform: uppercase; letter-spacing: .05em; color: #94a3b8; font-weight: 700; display: flex; align-items: center; gap: 6px; }
  .an-kpi .val { font-size: 1.55rem; font-weight: 800; color: #0f172a; line-height: 1.25; font-variant-numeric: tabular-nums; white-space: nowrap; }
  .an-kpi .foot { font-size: .7rem; color: #64748b; margin-top: 2px; display: flex; flex-wrap: wrap; gap: 4px 8px; align-items: center; }
  .an-chip { font-size: .66rem; font-weight: 700; padding: 1px 7px; border-radius: 20px; display: inline-flex; align-items: center; gap: 2px; white-space: nowrap; }
  .an-chip.up { background: #dcfce7; color: #15803d; }
  .an-chip.down { background: #fee2e2; color: #b91c1c; }
  .an-chip.flat { background: #f1f5f9; color: #64748b; }
  .an-chip.warn { background: #fef3c7; color: #92400e; }

  .an-skel { background: linear-gradient(90deg, #eef2f6 25%, #f8fafc 37%, #eef2f6 63%); background-size: 400% 100%; animation: an-shimmer 1.3s ease infinite; border-radius: 8px; }
  @keyframes an-shimmer { 0% { background-position: 100% 50%; } 100% { background-position: 0 50%; } }
  .an-chart { min-height: 300px; }
  #anRoot.an-busy .an-kpi, #anRoot.an-busy .an-chart { opacity: .5; transition: opacity .15s; }
  .an-chart .an-skel { height: 300px; }

  .an-notice { border-radius: 12px; padding: .7rem 1rem; font-size: .8rem; margin-bottom: .75rem; display: flex; gap: .7rem; align-items: flex-start; }
  .an-notice i { font-size: 1.25rem; line-height: 1.2; }
  .an-notice.warn { background: #fffbeb; border: 1px solid #fde68a; color: #78350f; }
  .an-notice.info { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e3a8a; }
  .an-notice.bad { background: #fef2f2; border: 1px solid #fecaca; color: #7f1d1d; }

  .an-tabs { border-bottom: 1px solid #e2e8f0; flex-wrap: nowrap; overflow-x: auto; }
  .an-tabs .nav-link { border: none; border-bottom: 2px solid transparent; color: #64748b; font-weight: 700; font-size: .82rem; padding: .6rem 1rem; white-space: nowrap; background: transparent; }
  .an-tabs .nav-link.active { color: #127464; border-bottom-color: #127464; background: transparent; }
  .an-table-wrap { overflow: auto; max-height: 640px; border: 1px solid #eef2f6; border-radius: 12px; transition: opacity .15s; }
  .an-table-wrap.an-loading { opacity: .45; pointer-events: none; }
  .an-table { margin: 0; font-size: .8rem; }
  .an-table thead th { position: sticky; top: 0; z-index: 2; background: #f8fafc; font-size: .66rem; text-transform: uppercase; letter-spacing: .04em; color: #64748b; white-space: nowrap; border-bottom: 1px solid #e2e8f0; }
  .an-table thead th.sortable { cursor: pointer; user-select: none; }
  .an-table thead th.sortable:hover { color: #127464; }
  .an-table td { vertical-align: middle; white-space: nowrap; }
  .an-table td.wrap { white-space: normal; min-width: 220px; max-width: 380px; }
  .an-num { text-align: right; font-variant-numeric: tabular-nums; }
  .an-link { color: #127464; font-weight: 600; text-decoration: none; cursor: pointer; }
  .an-link:hover { text-decoration: underline; }
  .an-muted { color: #94a3b8; }
  .an-share { display: inline-flex; align-items: center; gap: 6px; min-width: 88px; justify-content: flex-end; }
  .an-share .bar { width: 44px; height: 5px; background: #e2e8f0; border-radius: 4px; overflow: hidden; }
  .an-share .bar > i { display: block; height: 100%; background: #127464; }
  .an-toolbar { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; margin-bottom: .75rem; }
  .an-toolbar .form-control, .an-toolbar .form-select { max-width: 240px; }
  .an-seg .btn { font-size: .75rem; padding: .25rem .7rem; }
  .an-seg .btn.active { background: #127464; border-color: #127464; color: #fff; }
  .an-pager { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .5rem; margin-top: .75rem; font-size: .78rem; color: #64748b; }
  .an-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-right: 6px; }
  .an-mini { border-radius: 12px; background: #f8fafc; padding: .6rem .8rem; }
  .an-mini .lbl { font-size: .62rem; text-transform: uppercase; letter-spacing: .04em; color: #94a3b8; font-weight: 700; }
  .an-mini .val { font-size: 1.05rem; font-weight: 800; color: #0f172a; font-variant-numeric: tabular-nums; }
  .an-kv { display: grid; grid-template-columns: max-content 1fr; gap: 3px 14px; font-size: .8rem; }
  .an-kv dt { color: #94a3b8; font-weight: 600; margin: 0; }
  .an-kv dd { margin: 0; color: #1e293b; }
  #anBillModal .modal-body, #anItemModal .modal-body, #anCustModal .modal-body { background: #fff; }
  @media (max-width: 575.98px) {
    .an-kpi .val { font-size: 1.25rem; }
    .an-filters #anPresets { flex-wrap: nowrap; overflow-x: auto; width: 100%; scrollbar-width: none; }
    .an-filters #anPresets::-webkit-scrollbar { display: none; }
    .an-filters #anPresets .btn { flex: 0 0 auto; white-space: nowrap; }
    .an-pager { justify-content: center; }
    .an-pager > div { flex-wrap: wrap; justify-content: center; }
    .an-filters .an-dates { width: 100%; }
    .an-filters .an-dates .form-control { flex: 1 1 0; width: auto !important; min-width: 0; font-size: .74rem; padding-left: .45rem; padding-right: .25rem; }
    .an-filters .form-select { flex: 1 1 45%; max-width: none !important; }
    .an-toolbar .form-control, .an-toolbar .form-select { max-width: none; flex: 1 1 100%; }
  }
</style>
@endsection

@section('tab')
<div id="anRoot">

  {{-- ------------------------------------------------------------------ filters --}}
  <div class="an-card mb-3 an-filters" style="height:auto;">
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <div class="btn-group btn-group-sm" role="group" id="anPresets" aria-label="Quick date ranges">
        @foreach($presets as $p)
          <button type="button" class="btn btn-outline-secondary" data-preset="{{ $p['key'] }}" data-from="{{ $p['from'] }}" data-to="{{ $p['to'] }}">{{ $p['label'] }}</button>
        @endforeach
        <button type="button" class="btn btn-outline-secondary" data-preset="custom">Custom</button>
      </div>
      <div class="d-flex align-items-center gap-1 an-dates">
        <input type="date" id="anFrom" class="form-control form-control-sm" min="{{ $bounds[0] }}" max="{{ $bounds[1] }}" style="width:9.2rem;">
        <span class="small text-muted">to</span>
        <input type="date" id="anTo" class="form-control form-control-sm" min="{{ $bounds[0] }}" max="{{ $bounds[1] }}" style="width:9.2rem;">
      </div>
      <select id="anType" class="form-select form-select-sm" style="width:auto;" aria-label="Type">
        <option value="customer">Customer sales</option>
        <option value="branch">Branch transfers</option>
        <option value="all">Everything</option>
      </select>
      <select id="anSp" class="form-select form-select-sm" style="width:auto;max-width:210px;" aria-label="Salesperson">
        <option value="">All salespeople</option>
        @foreach($salespersons as $name)<option value="{{ $name }}">{{ $name }}</option>@endforeach
      </select>
      <select id="anSeries" class="form-select form-select-sm" style="width:auto;" aria-label="Invoice series">
        <option value="">All series</option>
        @foreach($seriesChoices as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach
      </select>
      <button type="button" id="anReset" class="btn btn-sm btn-outline-secondary"><i class="bx bx-reset"></i> Reset</button>
      <span class="ms-auto small text-muted" id="anMeta"></span>
    </div>
    <div class="small text-muted mt-2" id="anPeriodLine"></div>
  </div>

  <div id="anAlert"></div>
  <div id="anNotices"></div>

  {{-- ------------------------------------------------------------------ KPI cards --}}
  <div class="row g-3 mb-4" id="anKpis">
    @for($i = 0; $i < 8; $i++)
      <div class="col-6 col-lg-3"><div class="an-kpi"><div class="an-skel" style="height:14px;width:55%;"></div><div class="an-skel mt-2" style="height:28px;width:75%;"></div><div class="an-skel mt-2" style="height:12px;width:45%;"></div></div></div>
    @endfor
  </div>

  {{-- ------------------------------------------------------------------ charts --}}
  <div class="row g-4 mb-4">
    <div class="col-lg-8">
      <div class="an-card">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
          <div><h6>Sales trend</h6><div class="an-sub" id="anTrendSub">Invoice value incl. GST, and number of bills</div></div>
          <div class="btn-group btn-group-sm an-seg" id="anGrain"><button type="button" class="btn btn-outline-secondary" data-grain="day">Day</button><button type="button" class="btn btn-outline-secondary" data-grain="week">Week</button><button type="button" class="btn btn-outline-secondary" data-grain="month">Month</button></div>
        </div>
        <div id="chTrend" class="an-chart"><div class="an-skel"></div></div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="an-card"><h6>Sales by invoice series</h6><div class="an-sub">Channel / plant &middot; click a slice to filter</div><div id="chSeries" class="an-chart mt-2"><div class="an-skel"></div></div></div>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-lg-4"><div class="an-card"><h6>Top 10 customers</h6><div class="an-sub">By invoice value &middot; click a bar to open the customer</div><div id="chCustomers" class="an-chart mt-2"><div class="an-skel"></div></div></div></div>
    <div class="col-lg-4"><div class="an-card"><h6>Salespeople</h6><div class="an-sub">By invoice value &middot; click a bar to filter</div><div id="chSalespersons" class="an-chart mt-2"><div class="an-skel"></div></div></div></div>
    <div class="col-lg-4"><div class="an-card"><h6>Product families</h6><div class="an-sub" id="anFamSub">Taxable value &middot; click a bar to see its items</div><div id="chFamilies" class="an-chart mt-2"><div class="an-skel"></div></div></div></div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-lg-4"><div class="an-card"><h6>GST slabs</h6><div class="an-sub" id="anGstSub">Taxable value by GST rate</div><div id="chGst" class="an-chart mt-2"><div class="an-skel"></div></div></div></div>
    <div class="col-lg-4"><div class="an-card"><h6>Bill size</h6><div class="an-sub">Invoice value by size of bill</div><div id="chSizes" class="an-chart mt-2"><div class="an-skel"></div></div></div></div>
    <div class="col-lg-4"><div class="an-card"><h6>Receivables ageing</h6><div class="an-sub" id="anAgeSub">All customers, as of today (not affected by the filters)</div><div id="chAgeing" class="an-chart mt-2"><div class="an-skel"></div></div></div></div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-lg-6"><div class="an-card"><h6>Dispatched from</h6><div class="an-sub">Plant on the invoice &middot; not recorded on every invoice</div><div id="chPlants" class="an-chart mt-2"><div class="an-skel"></div></div></div></div>
    <div class="col-lg-6"><div class="an-card"><h6>Top customer cities</h6><div class="an-sub" id="anCitySub">Taxable value &middot; invoices with item detail only</div><div id="chCities" class="an-chart mt-2"><div class="an-skel"></div></div></div></div>
  </div>

  {{-- ------------------------------------------------------------------ drill-down tables --}}
  <div class="an-card mb-4" style="height:auto;">
    <ul class="nav an-tabs mb-3" id="anTabs" role="tablist">
      <li class="nav-item"><button type="button" class="nav-link" data-tab="bills"><i class="bx bx-receipt me-1"></i>Bill-wise</button></li>
      <li class="nav-item"><button type="button" class="nav-link" data-tab="items"><i class="bx bx-package me-1"></i>Item-wise</button></li>
      <li class="nav-item"><button type="button" class="nav-link" data-tab="customers"><i class="bx bx-buildings me-1"></i>Customer-wise</button></li>
      <li class="nav-item"><button type="button" class="nav-link" data-tab="salespersons"><i class="bx bx-user-pin me-1"></i>Salesperson-wise</button></li>
      <li class="nav-item"><button type="button" class="nav-link" data-tab="gst"><i class="bx bx-purchase-tag-alt me-1"></i>GST &amp; HSN</button></li>
      <li class="nav-item"><button type="button" class="nav-link" data-tab="price"><i class="bx bx-trending-up me-1"></i>Price analysis</button></li>
    </ul>
    <div id="anTabBody"></div>
  </div>

  <p class="small text-muted mb-4">
    <strong>How to read this.</strong> Bill value is the invoice total <em>including GST</em>. Item, HSN, GST, rate and taxable figures come from a separate Tally report that lists
    only some invoices &mdash; every card and table built on it says how much it covers. &ldquo;Still open&rdquo; means the bill number is still on Tally's outstanding list, not that it is overdue.
    Nothing on this page changes any data.
  </p>
</div>
@endsection

@section('tab-modals')
<div class="modal fade" id="anBillModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h6 class="modal-title mb-0" id="anBillTitle">Invoice</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body" id="anBillBody"></div>
</div></div></div>

<div class="modal fade" id="anItemModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h6 class="modal-title mb-0" id="anItemTitle">Item</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body" id="anItemBody"></div>
</div></div></div>

<div class="modal fade" id="anCustModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h6 class="modal-title mb-0" id="anCustTitle">Customer</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body" id="anCustBody"></div>
</div></div></div>
@endsection

@section('page-script')
@php
    $anConfig = [
        'routes' => [
            'data' => route('sales-sources.analytics.data'),
            'bill' => route('sales-sources.analytics.bill'),
            'item' => route('sales-sources.analytics.item'),
            'customer' => route('sales-sources.analytics.customer'),
            'export' => route('sales-sources.analytics.export'),
        ],
        'filters' => $filters,
        'overview' => $overview,
        'presets' => $presets,
        'palette' => $palette,
        'importId' => $importId,
    ];
@endphp
<script>
window.ANCFG = @json($anConfig);
</script>
@include('tenant.sales-sources.analytics-script')
@endsection
