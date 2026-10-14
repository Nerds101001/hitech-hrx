@extends('layouts/layoutMaster')

@section('title', 'Item · ' . \Illuminate\Support\Str::limit($name, 40))

@section('vendor-style')
  @vite(['resources/assets/vendor/scss/pages/hitech-portal.scss', 'resources/assets/vendor/libs/apex-charts/apex-charts.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/apex-charts/apex-charts.min.js'])
@endsection

@section('content')
<div id="idPage" class="layout-full-width">
  <div class="id-inner">
    <a href="{{ url()->previous() }}" class="id-back"><i class="bx bx-chevron-left"></i> Back</a>

    <section class="id-hero">
      <div class="id-hero-bg" aria-hidden="true"></div>
      <div class="id-hero-top">
        <div class="id-hero-icon"><i class="bx bx-box"></i></div>
        <div class="id-hero-info">
          <div class="id-hero-eyebrow">STOCK ITEM</div>
          <h1 class="id-hero-title">{{ $name }}</h1>
          <div class="id-hero-chips">
            @if($stock && $stock->stock_group)<span class="id-chip"><i class="bx bx-category"></i>{{ $stock->stock_group }}</span>@endif
            @if($stock && $stock->category)<span class="id-chip"><i class="bx bx-purchase-tag"></i>{{ $stock->category }}</span>@endif
            @if($stock && $stock->gst_rate !== null)<span class="id-chip"><i class="bx bx-receipt"></i>GST {{ number_format((float)$stock->gst_rate,1) }}%</span>@endif
            @if($stock && $stock->closing_qty !== null)<span class="id-chip"><i class="bx bx-cube"></i>In stock: {{ number_format((float)$stock->closing_qty,2) }} {{ $stock->closing_unit }}</span>@endif
          </div>
        </div>
      </div>
    </section>

    <div class="row g-3 mb-4">
      <div class="col-6 col-lg-3"><div class="id-stat"><div class="lbl">Last purchase rate</div><div class="val">{{ $stats['last_buy_rate'] !== null ? '₹'.number_format($stats['last_buy_rate'],2) : '—' }}</div><div class="id-stat-sub">range ₹{{ $stats['min_buy'] !== null ? number_format($stats['min_buy'],2) : '—' }} – ₹{{ $stats['max_buy'] !== null ? number_format($stats['max_buy'],2) : '—' }}</div></div></div>
      <div class="col-6 col-lg-3"><div class="id-stat"><div class="lbl">Last sale rate</div><div class="val">{{ $stats['last_sell_rate'] !== null ? '₹'.number_format($stats['last_sell_rate'],2) : '—' }}</div>@if($stats['last_buy_rate'] && $stats['last_sell_rate'])<div class="id-stat-sub">margin ₹{{ number_format($stats['last_sell_rate'] - $stats['last_buy_rate'],2) }}</div>@endif</div></div>
      <div class="col-6 col-lg-3"><div class="id-stat"><div class="lbl">Purchased</div><div class="val sm">{{ number_format($stats['buy_qty'],0) }}<span class="id-stat-sub"> units · ₹{{ number_format($stats['buy_value'],0) }}</span></div><div class="id-stat-sub">{{ $stats['buys'] }} buys · {{ $stats['suppliers'] }} supplier(s)</div></div></div>
      <div class="col-6 col-lg-3"><div class="id-stat"><div class="lbl">Sold</div><div class="val sm">{{ number_format($stats['sell_qty'],0) }}<span class="id-stat-sub"> units · ₹{{ number_format($stats['sell_value'],0) }}</span></div><div class="id-stat-sub">{{ $stats['sells'] }} sale lines</div></div></div>
    </div>

    <div class="id-card mb-4">
      <div class="id-card-head"><h6 class="mb-0"><i class="bx bx-line-chart me-1"></i> Price over time</h6><span class="id-sub">purchase rate vs sale rate</span></div>
      <div class="id-card-body"><div id="idPrice" style="min-height:320px;"></div></div>
    </div>

    <div class="id-card mb-4">
      <div class="id-card-head"><h6 class="mb-0"><i class="bx bx-store me-1"></i> Suppliers — cheapest first</h6></div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 id-table">
          <thead class="table-light"><tr><th>Supplier</th><th class="id-num">Latest rate</th><th class="id-num">Min</th><th class="id-num">Avg</th><th class="id-num">Buys</th><th class="id-num">Qty</th><th class="id-num">Value (₹)</th><th>Last bought</th></tr></thead>
          <tbody>
          @forelse($bySupplier as $i => $s)
            <tr>
              <td class="fw-semibold"><a href="{{ route('tally-explorer.ledgers.show', ['name' => $s['supplier']]) }}" class="text-decoration-none" style="color:#0f6b78;">{{ $s['supplier'] }}</a>@if($i === 0)<span class="id-badge-best ms-1">cheapest</span>@endif</td>
              <td class="id-num fw-semibold">{{ number_format($s['last_rate'],2) }}</td>
              <td class="id-num id-sub">{{ number_format($s['min_rate'],2) }}</td>
              <td class="id-num id-sub">{{ number_format($s['avg_rate'],2) }}</td>
              <td class="id-num">{{ $s['buys'] }}</td>
              <td class="id-num">{{ number_format($s['qty'],0) }}</td>
              <td class="id-num">{{ number_format($s['value'],0) }}</td>
              <td class="id-sub">{{ $s['last_date'] ? \Illuminate\Support\Carbon::parse($s['last_date'])->format('d M Y') : '—' }}</td>
            </tr>
          @empty
            <tr><td colspan="8" class="text-center text-muted py-4">No purchase price history for this item.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="id-card mb-4">
      <div class="id-card-head"><h6 class="mb-0"><i class="bx bx-history me-1"></i> Purchase history ({{ $pLines->count() }})</h6></div>
      <div class="table-responsive" style="max-height:420px;overflow:auto;">
        <table class="table table-sm align-middle mb-0 id-table">
          <thead class="table-light" style="position:sticky;top:0;"><tr><th>Date</th><th>Supplier</th><th class="id-num">Qty</th><th>Unit</th><th class="id-num">Rate</th><th class="id-num">GST%</th><th class="id-num">Taxable (₹)</th></tr></thead>
          <tbody>
          @forelse($pLines->sortByDesc('purchase_date') as $l)
            <tr>
              <td class="id-sub">{{ $l->purchase_date ? \Illuminate\Support\Carbon::parse($l->purchase_date)->format('d M Y') : '—' }}</td>
              <td>{{ $l->party_name ?: '—' }}</td>
              <td class="id-num">{{ $l->quantity !== null ? number_format((float)$l->quantity,3) : '—' }}</td>
              <td class="id-sub">{{ $l->unit }}</td>
              <td class="id-num fw-semibold">{{ $l->rate !== null ? number_format((float)$l->rate,2) : '—' }}</td>
              <td class="id-num id-sub">{{ $l->gst_rate !== null ? number_format((float)$l->gst_rate,1) : '—' }}</td>
              <td class="id-num">{{ number_format((float)$l->taxable_amount,2) }}</td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted py-4">No purchases on record.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<script>
  window.ID_BUY = @json($buyTrend);
  window.ID_SELL = @json($sellTrend);
</script>
<script>
(function () {
  function ready(fn){ if (document.readyState !== 'loading') fn(); else document.addEventListener('DOMContentLoaded', fn); }
  ready(function () {
    if (typeof ApexCharts === 'undefined') return;
    const buy = window.ID_BUY || [], sell = window.ID_SELL || [];
    const series = [];
    if (buy.length)  series.push({ name: 'Purchase rate', data: buy.map(p => ({ x: p.date, y: p.rate })) });
    if (sell.length) series.push({ name: 'Sale rate',     data: sell.map(p => ({ x: p.date, y: p.rate })) });
    const el = document.querySelector('#idPrice');
    if (!series.length) { el.innerHTML = '<div class="text-center text-muted py-5">No rate history to chart.</div>'; return; }
    new ApexCharts(el, {
      chart: { type: 'line', height: 320, toolbar: { show: false }, fontFamily: 'inherit', zoom: { enabled: false } },
      series: series,
      xaxis: { type: 'datetime', labels: { datetimeFormatter: { month: "MMM 'yy" } } },
      yaxis: { labels: { formatter: v => '₹' + Number(v).toLocaleString('en-IN', { maximumFractionDigits: 0 }) } },
      stroke: { width: 2, curve: 'straight' },
      markers: { size: 3 },
      colors: ['#6366f1', '#0f6b78'],
      legend: { position: 'top' },
      tooltip: { x: { format: 'dd MMM yyyy' }, y: { formatter: v => '₹' + Number(v).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) } },
      grid: { borderColor: '#eef2f6' },
    }).render();
  });
})();
</script>

<style>
  #idPage .id-inner { padding:.5rem 1rem 2rem; } @media (min-width:768px){ #idPage .id-inner { padding:.5rem 1.5rem 2rem; } }
  #idPage .id-back { display:inline-flex; align-items:center; gap:.2rem; margin:.25rem 0 .9rem; font-size:.84rem; font-weight:600; color:#475569; text-decoration:none; } #idPage .id-back:hover { color:#0f6b78; }
  #idPage .id-hero { position:relative; overflow:hidden; border-radius:20px; color:#fff; background:#0a2740; box-shadow:0 12px 30px rgba(10,39,64,.16); margin-bottom:1.25rem; }
  #idPage .id-hero-bg { position:absolute; inset:0; z-index:0; background: radial-gradient(760px 320px at 90% -20%, rgba(99,102,241,.4), transparent 60%), linear-gradient(115deg,#0a2740,#223b6b 55%,#4338ca); }
  #idPage .id-hero-top { position:relative; z-index:1; display:flex; align-items:center; gap:1.2rem; padding:1.5rem 1.8rem; }
  #idPage .id-hero-icon { width:60px; height:60px; flex-shrink:0; display:flex; align-items:center; justify-content:center; border-radius:16px; background:#fff; color:#4338ca; font-size:1.7rem; }
  #idPage .id-hero-eyebrow { font-size:.72rem; letter-spacing:.04em; color:rgba(255,255,255,.72); font-weight:600; }
  #idPage .id-hero-title { margin:.2rem 0 .5rem; font-size:1.5rem; font-weight:700; color:#fff !important; letter-spacing:-.02em; line-height:1.25; }
  #idPage .id-hero-chips { display:flex; flex-wrap:wrap; gap:.5rem; } #idPage .id-chip { display:inline-flex; align-items:center; gap:.35rem; padding:3px 11px; border-radius:999px; font-size:.74rem; color:#fff; background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.18); }
  #idPage .id-stat { border-radius:14px; padding:.9rem 1.1rem; background:#fff; box-shadow:0 1px 2px rgba(15,23,42,.04),0 4px 14px rgba(15,23,42,.05); height:100%; }
  #idPage .id-stat .lbl { font-size:.66rem; text-transform:uppercase; letter-spacing:.04em; color:#94a3b8; font-weight:700; } #idPage .id-stat .val { font-size:1.3rem; font-weight:800; color:#1e293b; } #idPage .id-stat .val.sm { font-size:1.05rem; } #idPage .id-stat-sub { font-size:.7rem; font-weight:600; color:#94a3b8; }
  #idPage .id-card { border-radius:14px; background:#fff; box-shadow:0 1px 2px rgba(15,23,42,.04),0 4px 14px rgba(15,23,42,.05); overflow:hidden; }
  #idPage .id-card-head { display:flex; align-items:baseline; justify-content:space-between; gap:.5rem; padding:1rem 1.15rem .4rem; } #idPage .id-card-head h6 { font-weight:700; } #idPage .id-sub { font-size:.72rem; color:#94a3b8; } #idPage .id-card-body { padding:.4rem 1.15rem 1.1rem; }
  #idPage .id-table th { font-size:.66rem; text-transform:uppercase; letter-spacing:.04em; color:#64748b; white-space:nowrap; } #idPage .id-table td { font-size:.82rem; } #idPage .id-num { text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; } #idPage .id-sub { font-size:.72rem; color:#94a3b8; }
  #idPage .id-badge-best { font-size:.6rem; font-weight:700; padding:1px 7px; border-radius:20px; background:#dcfce7; color:#15803d; }
</style>
@endsection
