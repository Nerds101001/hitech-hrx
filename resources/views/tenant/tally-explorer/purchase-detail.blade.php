@extends('layouts/layoutMaster')

@section('title', 'Purchase ' . $header->purchase_no)

@section('vendor-style')
  @vite(['resources/assets/vendor/scss/pages/hitech-portal.scss', 'resources/assets/vendor/libs/apex-charts/apex-charts.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/apex-charts/apex-charts.min.js'])
@endsection

@section('content')
@php
  $hikes = collect($items)->filter(fn ($i) => $i['change_pct'] !== null && $i['change_pct'] > 0);
  $drops = collect($items)->filter(fn ($i) => $i['change_pct'] !== null && $i['change_pct'] < 0);
@endphp
<div id="pdPage" class="layout-full-width">
  <div class="pd-inner">

    <a href="{{ route('tally-explorer.purchases') }}" class="pd-back"><i class="bx bx-chevron-left"></i> Back to Purchases</a>

    {{-- Bill header --}}
    <section class="pd-hero">
      <div class="pd-hero-bg" aria-hidden="true"></div>
      <div class="pd-hero-top">
        <div class="pd-hero-icon"><i class="bx bx-cart-download"></i></div>
        <div class="pd-hero-info">
          <div class="pd-hero-eyebrow">PURCHASE BILL {{ $header->purchase_no }}@if($header->series) · {{ $header->series }}@endif · {{ $header->purchase_date ? \Illuminate\Support\Carbon::parse($header->purchase_date)->format('d M Y') : '—' }}</div>
          <h1 class="pd-hero-title">{{ $header->party_name ?: '—' }}</h1>
          <div class="pd-hero-chips">
            @if(!empty($extra['party_gstin']))<span class="pd-chip"><i class="bx bx-id-card"></i>{{ $extra['party_gstin'] }}</span>@endif
            @if(!empty($extra['place_of_supply']))<span class="pd-chip"><i class="bx bx-map"></i>{{ $extra['place_of_supply'] }}</span>@endif
            @if(!empty($extra['reference']))<span class="pd-chip"><i class="bx bx-hash"></i>Ref {{ $extra['reference'] }}</span>@endif
          </div>
        </div>
        <div class="pd-hero-amount">
          <div class="pd-hero-amt-lbl">Bill value</div>
          <div class="pd-hero-amt-val">₹{{ number_format((float) $header->amount, 2) }}</div>
        </div>
      </div>
    </section>

    {{-- Supplier KPIs --}}
    <div class="row g-3 mb-4">
      <div class="col-6 col-lg-3"><div class="pd-stat"><div class="lbl">Total bought (supplier)</div><div class="val">₹{{ number_format((float) ($sup->total ?? 0), 0) }}</div></div></div>
      <div class="col-6 col-lg-3"><div class="pd-stat"><div class="lbl">Bills</div><div class="val">{{ number_format($sup->bills ?? 0) }}<span class="pd-stat-sub"> · avg ₹{{ number_format((float) ($sup->avg_bill ?? 0), 0) }}</span></div></div></div>
      <div class="col-6 col-lg-3"><div class="pd-stat"><div class="lbl">First purchase</div><div class="val sm">{{ ($sup->first_date ?? null) ? \Illuminate\Support\Carbon::parse($sup->first_date)->format('d M Y') : '—' }}</div></div></div>
      <div class="col-6 col-lg-3"><div class="pd-stat"><div class="lbl">Last purchase</div><div class="val sm">{{ ($sup->last_date ?? null) ? \Illuminate\Support\Carbon::parse($sup->last_date)->format('d M Y') : '—' }}</div></div></div>
    </div>

    {{-- Supplier purchase trend --}}
    <div class="pd-card mb-4">
      <div class="pd-card-head"><h6 class="mb-0"><i class="bx bx-bar-chart-alt-2 me-1"></i> Purchases from {{ \Illuminate\Support\Str::limit($header->party_name, 48) }} over time</h6>
        <span class="pd-sub">monthly bill value</span></div>
      <div class="pd-card-body"><div id="pdTrend" style="min-height:300px;"></div></div>
    </div>

    {{-- Price-hike analysis --}}
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
      <h5 class="fw-bold mb-0"><i class="bx bx-trending-up me-1"></i> Price trend &amp; hikes</h5>
      @if($hikes->count())<span class="pd-flag up">{{ $hikes->count() }} item(s) up vs last buy</span>@endif
      @if($drops->count())<span class="pd-flag down">{{ $drops->count() }} item(s) down</span>@endif
    </div>
    <p class="text-muted small mb-3">For every item on this bill: the rate you paid now vs the previous time it was purchased, and its rate across all purchases on record.</p>

    <div class="row g-3 mb-4">
      @forelse($items as $i)
        <div class="col-12 col-xl-6">
          <div class="pd-card h-100">
            <div class="pd-card-body">
              <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                <a href="{{ route('tally-explorer.items.show', ['name' => $i['name']]) }}" class="fw-semibold text-decoration-none" style="min-width:0;color:#4338ca;">{{ $i['name'] }} <i class="bx bx-link-external" style="font-size:.7rem;"></i></a></div>
                @if($i['change_pct'] !== null)
                  <span class="pd-delta {{ $i['change_pct'] > 0 ? 'up' : ($i['change_pct'] < 0 ? 'down' : 'flat') }}">
                    <i class="bx {{ $i['change_pct'] > 0 ? 'bx-up-arrow-alt' : ($i['change_pct'] < 0 ? 'bx-down-arrow-alt' : 'bx-minus') }}"></i>{{ $i['change_pct'] > 0 ? '+' : '' }}{{ $i['change_pct'] }}%
                  </span>
                @else
                  <span class="pd-delta flat">first buy</span>
                @endif
              </div>
              <div class="pd-price-row">
                <div><span class="pd-price-lbl">This bill</span><span class="pd-price-val">₹{{ $i['current'] !== null ? number_format($i['current'], 2) : '—' }}</span><span class="pd-price-unit">{{ $i['rate_unit'] ? '/'.$i['rate_unit'] : '' }}</span></div>
                <div><span class="pd-price-lbl">Previous</span><span class="pd-price-val muted">₹{{ $i['previous'] !== null ? number_format($i['previous'], 2) : '—' }}</span></div>
                <div><span class="pd-price-lbl">Range</span><span class="pd-price-val muted">₹{{ $i['min'] !== null ? number_format($i['min'], 2) : '—' }} – ₹{{ $i['max'] !== null ? number_format($i['max'], 2) : '—' }}</span></div>
                <div><span class="pd-price-lbl">Avg · points</span><span class="pd-price-val muted">₹{{ $i['avg'] !== null ? number_format($i['avg'], 2) : '—' }} · {{ $i['points'] }}</span></div>
              </div>
              <div class="pd-spark" id="pdItem{{ $loop->index }}"></div>
            </div>
          </div>
        </div>
      @empty
        <div class="col-12"><div class="pd-card"><div class="pd-card-body text-center text-muted py-4">This bill has no inventory item lines (a service/freight charge).</div></div></div>
      @endforelse
    </div>

    {{-- This bill's lines --}}
    <div class="pd-card mb-4">
      <div class="pd-card-head"><h6 class="mb-0"><i class="bx bx-list-ul me-1"></i> This bill ({{ $lines->count() }} line{{ $lines->count() === 1 ? '' : 's' }})</h6></div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 pd-table">
          <thead class="table-light"><tr><th>Item</th><th>HSN</th><th class="pd-num">GST%</th><th class="pd-num">Qty</th><th>Unit</th><th class="pd-num">Rate</th><th class="pd-num">Taxable</th><th class="pd-num">Tax</th></tr></thead>
          <tbody>
          @forelse($lines as $l)
            <tr>
              <td class="fw-semibold">{{ $l->item_name }}</td>
              <td class="pd-sub">{{ $l->hsn ?: '—' }}</td>
              <td class="pd-num">{{ $l->gst_rate !== null ? number_format((float) $l->gst_rate, 2) : '—' }}</td>
              <td class="pd-num">{{ $l->quantity !== null ? number_format((float) $l->quantity, 3) : '—' }}</td>
              <td class="pd-sub">{{ $l->unit }}</td>
              <td class="pd-num">{{ $l->rate !== null ? number_format((float) $l->rate, 2) : '—' }}</td>
              <td class="pd-num">{{ number_format((float) $l->taxable_amount, 2) }}</td>
              <td class="pd-num">{{ $l->tax_amount !== null ? number_format((float) $l->tax_amount, 2) : '—' }}</td>
            </tr>
          @empty
            <tr><td colspan="8" class="text-center text-muted py-3">No item lines.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<script>
  window.PD_TREND = @json($trend);
  window.PD_ITEMS = @json(collect($items)->map(fn ($i) => ['history' => $i['history']])->values());
</script>
<script>
(function () {
  function ready(fn){ if (document.readyState !== 'loading') fn(); else document.addEventListener('DOMContentLoaded', fn); }
  const inr = v => '₹' + Number(v||0).toLocaleString('en-IN', {maximumFractionDigits:0});
  const monthLabel = ym => { const [y,m] = ym.split('-'); return new Date(y, m-1, 1).toLocaleDateString('en-GB',{month:'short',year:'2-digit'}); };

  ready(function () {
    if (typeof ApexCharts === 'undefined') return;

    // Supplier monthly purchase trend -- the bar graph.
    const t = window.PD_TREND || [];
    if (t.length) {
      new ApexCharts(document.querySelector('#pdTrend'), {
        chart: { type: 'bar', height: 300, toolbar: { show: false }, fontFamily: 'inherit' },
        series: [{ name: 'Purchased', data: t.map(r => Math.round(r.amt)) }],
        xaxis: { categories: t.map(r => monthLabel(r.ym)), labels: { rotate: -45, style: { fontSize: '11px' } } },
        yaxis: { labels: { formatter: v => inr(v) } },
        colors: ['#0f6b78'],
        plotOptions: { bar: { borderRadius: 5, columnWidth: t.length > 18 ? '70%' : '48%' } },
        dataLabels: { enabled: false },
        tooltip: { y: { formatter: (v, o) => inr(v) + '  ·  ' + (t[o.dataPointIndex]?.bills || 0) + ' bill(s)' } },
        grid: { borderColor: '#eef2f6' },
      }).render();
    } else {
      document.querySelector('#pdTrend').innerHTML = '<div class="text-center text-muted py-5">No purchase history for this supplier.</div>';
    }

    // Per-item price trend sparklines.
    (window.PD_ITEMS || []).forEach((it, idx) => {
      const el = document.querySelector('#pdItem' + idx);
      if (!el) return;
      const h = it.history || [];
      if (h.length < 2) { el.innerHTML = '<div class="pd-spark-empty">Only one price point on record.</div>'; return; }
      new ApexCharts(el, {
        chart: { type: 'line', height: 90, sparkline: { enabled: true }, fontFamily: 'inherit' },
        series: [{ name: 'Rate', data: h.map(p => p.rate) }],
        xaxis: { categories: h.map(p => p.date) },
        stroke: { width: 2, curve: 'straight' },
        markers: { size: 3 },
        colors: ['#6366f1'],
        tooltip: { x: { show: true }, y: { formatter: v => '₹' + Number(v).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2}) },
                   custom: undefined },
      }).render();
    });
  });
})();
</script>

<style>
  #pdPage { --pd-ink:#0f172a; --pd-muted:#64748b; --pd-line:#e8edf3; }
  #pdPage .pd-inner { padding: .5rem 1rem 2rem; }
  @media (min-width:768px){ #pdPage .pd-inner { padding: .5rem 1.5rem 2rem; } }
  #pdPage .pd-back { display:inline-flex; align-items:center; gap:.2rem; margin:.25rem 0 .9rem; font-size:.84rem; font-weight:600; color:#475569; text-decoration:none; }
  #pdPage .pd-back:hover { color:#0f6b78; }

  #pdPage .pd-hero { position:relative; overflow:hidden; border-radius:20px; color:#fff; background:#0a2740; box-shadow:0 12px 30px rgba(10,39,64,.16); margin-bottom:1.25rem; }
  #pdPage .pd-hero-bg { position:absolute; inset:0; z-index:0; background: radial-gradient(760px 320px at 90% -20%, rgba(45,212,191,.35), transparent 60%), linear-gradient(115deg,#0a2740,#0d4266 55%,#0f6b78); }
  #pdPage .pd-hero-top { position:relative; z-index:1; display:flex; align-items:center; gap:1.2rem; padding:1.5rem 1.8rem; }
  #pdPage .pd-hero-icon { width:60px; height:60px; flex-shrink:0; display:flex; align-items:center; justify-content:center; border-radius:16px; background:#fff; color:#0f4c75; font-size:1.7rem; }
  #pdPage .pd-hero-info { flex:1; min-width:0; }
  #pdPage .pd-hero-eyebrow { font-size:.72rem; letter-spacing:.04em; color:rgba(255,255,255,.72); font-weight:600; margin-bottom:.25rem; }
  #pdPage .pd-hero-title { margin:0 0 .5rem; font-size:1.6rem; font-weight:700; color:#fff !important; letter-spacing:-.02em; line-height:1.2; }
  #pdPage .pd-hero-chips { display:flex; flex-wrap:wrap; gap:.5rem; }
  #pdPage .pd-chip { display:inline-flex; align-items:center; gap:.35rem; padding:3px 11px; border-radius:999px; font-size:.74rem; color:#fff; background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.18); }
  #pdPage .pd-hero-amount { flex-shrink:0; text-align:right; }
  #pdPage .pd-hero-amt-lbl { font-size:.72rem; color:rgba(255,255,255,.7); }
  #pdPage .pd-hero-amt-val { font-size:1.7rem; font-weight:800; color:#fff; }

  #pdPage .pd-stat { border-radius:14px; padding:.9rem 1.1rem; background:#fff; box-shadow:0 1px 2px rgba(15,23,42,.04),0 4px 14px rgba(15,23,42,.05); height:100%; }
  #pdPage .pd-stat .lbl { font-size:.66rem; text-transform:uppercase; letter-spacing:.04em; color:#94a3b8; font-weight:700; }
  #pdPage .pd-stat .val { font-size:1.3rem; font-weight:800; color:#1e293b; }
  #pdPage .pd-stat .val.sm { font-size:1.0rem; }
  #pdPage .pd-stat-sub { font-size:.72rem; font-weight:600; color:#94a3b8; }

  #pdPage .pd-card { border-radius:14px; background:#fff; box-shadow:0 1px 2px rgba(15,23,42,.04),0 4px 14px rgba(15,23,42,.05); overflow:hidden; }
  #pdPage .pd-card-head { display:flex; align-items:baseline; justify-content:space-between; gap:.5rem; padding:1rem 1.15rem .4rem; }
  #pdPage .pd-card-head h6 { font-weight:700; }
  #pdPage .pd-sub { font-size:.72rem; color:#94a3b8; }
  #pdPage .pd-card-body { padding: .4rem 1.15rem 1.1rem; }

  #pdPage .pd-flag { font-size:.68rem; font-weight:700; padding:3px 10px; border-radius:20px; }
  #pdPage .pd-flag.up { background:#fee2e2; color:#b91c1c; } #pdPage .pd-flag.down { background:#dcfce7; color:#15803d; }
  #pdPage .pd-delta { flex-shrink:0; display:inline-flex; align-items:center; gap:.1rem; font-size:.78rem; font-weight:800; padding:2px 9px; border-radius:20px; white-space:nowrap; }
  #pdPage .pd-delta.up { background:#fee2e2; color:#b91c1c; } #pdPage .pd-delta.down { background:#dcfce7; color:#15803d; } #pdPage .pd-delta.flat { background:#eef2f6; color:#64748b; }
  #pdPage .pd-price-row { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:.4rem .8rem; margin:.4rem 0 .2rem; }
  #pdPage .pd-price-lbl { display:block; font-size:.64rem; text-transform:uppercase; letter-spacing:.03em; color:#94a3b8; font-weight:700; }
  #pdPage .pd-price-val { font-size:1.0rem; font-weight:700; color:#1e293b; } #pdPage .pd-price-val.muted { font-weight:600; color:#64748b; font-size:.9rem; }
  #pdPage .pd-price-unit { font-size:.72rem; color:#94a3b8; }
  #pdPage .pd-spark { margin-top:.3rem; } #pdPage .pd-spark-empty { font-size:.72rem; color:#cbd5e1; padding:.6rem 0; }

  #pdPage .pd-table th { font-size:.66rem; text-transform:uppercase; letter-spacing:.04em; color:#64748b; white-space:nowrap; }
  #pdPage .pd-table td { font-size:.82rem; } #pdPage .pd-num { text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; } #pdPage .pd-sub { font-size:.72rem; color:#94a3b8; }
  @media (max-width:575px){ #pdPage .pd-hero-top { flex-wrap:wrap; } #pdPage .pd-hero-amount { text-align:left; } }
</style>
@endsection
