@extends('layouts/layoutMaster')

@section('title', 'Ledger · ' . \Illuminate\Support\Str::limit($ledger->name, 40))

@section('vendor-style')
  @vite(['resources/assets/vendor/scss/pages/hitech-portal.scss', 'resources/assets/vendor/libs/apex-charts/apex-charts.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/apex-charts/apex-charts.min.js'])
@endsection

@section('content')
@php
  $c = fn ($v, $d = 0) => '₹' . number_format((float) $v, $d);
@endphp
<div id="lgPage" class="layout-full-width">
  <div class="lg-inner">
    <a href="{{ route('tally-explorer.ledgers') }}" class="lg-back"><i class="bx bx-chevron-left"></i> Back to Ledgers</a>

    <section class="lg-hero">
      <div class="lg-hero-bg" aria-hidden="true"></div>
      <div class="lg-hero-top">
        <div class="lg-hero-icon"><i class="bx bx-book-content"></i></div>
        <div class="lg-hero-info">
          <div class="lg-hero-eyebrow">
            {{ $ledger->primary_group ?: 'LEDGER' }}@if($ledger->ledger_group && $ledger->ledger_group !== $ledger->primary_group) · {{ $ledger->ledger_group }}@endif
            @if($isCustomer) · <span class="lg-role">Customer</span>@endif @if($isSupplier) · <span class="lg-role">Supplier</span>@endif
          </div>
          <h1 class="lg-hero-title">{{ $ledger->name }}</h1>
          <div class="lg-hero-chips">
            @if($ledger->gstin)<span class="lg-chip"><i class="bx bx-id-card"></i>{{ $ledger->gstin }}</span>@endif
            @if($ledger->pan)<span class="lg-chip"><i class="bx bx-credit-card-front"></i>PAN {{ $ledger->pan }}</span>@endif
            @if($ledger->contact_person)<span class="lg-chip"><i class="bx bx-user"></i>{{ $ledger->contact_person }}</span>@endif
            @if($ledger->contact_mobile)<span class="lg-chip"><i class="bx bx-phone"></i>{{ $ledger->contact_mobile }}</span>@endif
          </div>
        </div>
        <div class="lg-hero-amount">
          <div class="lg-hero-amt-lbl">Closing balance</div>
          <div class="lg-hero-amt-val {{ (float)$ledger->closing_balance < 0 ? 'neg' : '' }}">{{ $ledger->closing_balance !== null ? $c($ledger->closing_balance) : '—' }}</div>
        </div>
      </div>
    </section>

    {{-- Terms & master --}}
    <div class="row g-3 mb-4">
      <div class="col-12 col-lg-5">
        <div class="lg-card h-100"><div class="lg-card-body">
          <h6 class="fw-bold mb-3"><i class="bx bx-receipt me-1"></i> Terms &amp; credit</h6>
          <div class="lg-terms">
            <div><span class="lg-t-lbl">Credit limit</span><span class="lg-t-val">{{ $terms['credit_limit'] !== null ? $c($terms['credit_limit']) : 'not set' }}</span></div>
            <div><span class="lg-t-lbl">Outstanding now</span><span class="lg-t-val {{ $kpi['outstanding'] > 0 ? 'warn' : '' }}">{{ $c($kpi['outstanding']) }}</span></div>
            <div><span class="lg-t-lbl">Credit used</span><span class="lg-t-val">{{ $terms['utilisation'] !== null ? $terms['utilisation'].'%' : '—' }}</span></div>
            <div><span class="lg-t-lbl">Payment terms (typical)</span><span class="lg-t-val">{{ $terms['credit_days_typical'] !== null ? $terms['credit_days_typical'].' days' : '—' }}@if($terms['credit_days_max'] !== null && $terms['credit_days_max'] !== $terms['credit_days_typical'])<span class="lg-t-sub"> (up to {{ $terms['credit_days_max'] }})</span>@endif</span></div>
            <div><span class="lg-t-lbl">Open bills</span><span class="lg-t-val">{{ number_format($kpi['bills_open']) }}</span></div>
            <div><span class="lg-t-lbl">Group</span><span class="lg-t-val sm">{{ $ledger->ledger_group ?: '—' }}</span></div>
          </div>
          @if($terms['utilisation'] !== null)
            <div class="lg-util mt-3"><div class="lg-util-bar"><span style="width:{{ min(100, $terms['utilisation']) }}%; background:{{ $terms['utilisation'] > 100 ? '#b91c1c' : ($terms['utilisation'] > 80 ? '#ea580c' : '#0f6b78') }};"></span></div>
            <div class="lg-t-sub mt-1">{{ $c($kpi['outstanding']) }} of {{ $c($terms['credit_limit']) }} credit limit used</div></div>
          @endif
        </div></div>
      </div>
      <div class="col-12 col-lg-7">
        <div class="row g-3 h-100">
          @if($isCustomer)
            <div class="col-6"><div class="lg-stat"><div class="lbl">Total sales</div><div class="val">{{ $c($kpi['sales_total']) }}</div><div class="lg-stat-sub">{{ number_format($kpi['sales_count']) }} invoices</div></div></div>
            <div class="col-6"><div class="lg-stat"><div class="lbl">Received</div><div class="val">{{ $c($kpi['received']) }}</div><div class="lg-stat-sub">payments in</div></div></div>
          @endif
          @if($isSupplier)
            <div class="col-6"><div class="lg-stat"><div class="lbl">Total purchases</div><div class="val">{{ $c($kpi['purchase_total']) }}</div><div class="lg-stat-sub">{{ number_format($kpi['purchase_count']) }} bills</div></div></div>
            <div class="col-6"><div class="lg-stat"><div class="lbl">Paid</div><div class="val">{{ $c($kpi['paid']) }}</div><div class="lg-stat-sub">payments out</div></div></div>
          @endif
          @unless($isCustomer || $isSupplier)
            <div class="col-12"><div class="lg-stat"><div class="lbl">Activity</div><div class="val sm text-muted">No sales or purchases on record for this ledger in the synced data.</div></div></div>
          @endunless
        </div>
      </div>
    </div>

    {{-- Trend chart --}}
    @if($trend->isNotEmpty())
    <div class="lg-card mb-4">
      <div class="lg-card-head"><h6 class="mb-0"><i class="bx bx-bar-chart-alt-2 me-1"></i> Monthly activity</h6><span class="lg-sub">sales &amp; purchases by month</span></div>
      <div class="lg-card-body"><div id="lgTrend" style="min-height:300px;"></div></div>
    </div>
    @endif

    <div class="row g-3 mb-4">
      {{-- Outstanding bills + ageing --}}
      @if($outstanding->isNotEmpty())
      <div class="col-12 col-xl-7">
        <div class="lg-card h-100">
          <div class="lg-card-head"><h6 class="mb-0"><i class="bx bx-time-five me-1"></i> Outstanding ({{ $outstanding->count() }} bill{{ $outstanding->count() === 1 ? '' : 's' }})</h6>
            <span class="lg-sub">
              @foreach($ageing as $b => $amt)@if($amt != 0)<span class="lg-age">{{ $b }}d: {{ $c($amt) }}</span>@endif @endforeach
            </span>
          </div>
          <div class="table-responsive" style="max-height:360px;overflow:auto;">
            <table class="table table-sm align-middle mb-0 lg-table">
              <thead class="table-light" style="position:sticky;top:0;"><tr><th>Bill</th><th>Date</th><th class="lg-num">Age (d)</th><th class="lg-num">Terms</th><th class="lg-num">Amount (₹)</th></tr></thead>
              <tbody>
              @foreach($outstanding as $b)
                @php $age = $b->bill_date ? (int) \Illuminate\Support\Carbon::parse($b->bill_date)->startOfDay()->diffInDays(now()->startOfDay()) : null; @endphp
                <tr>
                  <td class="fw-semibold">{{ $b->bill_ref ?: '—' }}</td>
                  <td class="lg-sub">{{ $b->bill_date ? \Illuminate\Support\Carbon::parse($b->bill_date)->format('d M Y') : '—' }}</td>
                  <td class="lg-num {{ $age !== null && $age > 90 ? 'text-danger fw-semibold' : '' }}">{{ $age ?? '—' }}</td>
                  <td class="lg-num lg-sub">{{ $b->credit_days !== null ? $b->credit_days : '—' }}</td>
                  <td class="lg-num fw-semibold">{{ number_format((float)$b->amount, 0) }}</td>
                </tr>
              @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
      @endif

      {{-- Recent activity feed --}}
      <div class="col-12 {{ $outstanding->isNotEmpty() ? 'col-xl-5' : '' }}">
        <div class="lg-card h-100">
          <div class="lg-card-head"><h6 class="mb-0"><i class="bx bx-history me-1"></i> Recent activity</h6></div>
          <div class="lg-card-body">
            @forelse($feed as $f)
              <div class="lg-feed">
                <span class="lg-feed-dot {{ $f['dir'] }}"></span>
                <div class="lg-feed-main">
                  <div class="lg-feed-top"><span class="lg-feed-type">{{ $f['type'] }}</span> <span class="lg-feed-ref">{{ $f['ref'] }}</span></div>
                  <div class="lg-sub">{{ $f['date'] ? \Illuminate\Support\Carbon::parse($f['date'])->format('d M Y') : '—' }}@if(!empty($f['note'])) · {{ \Illuminate\Support\Str::limit($f['note'], 24) }}@endif</div>
                </div>
                <div class="lg-feed-amt {{ $f['dir'] }}">{{ $f['dir'] === 'in' ? '+' : '−' }}{{ number_format($f['amount'], 0) }}</div>
              </div>
            @empty
              <div class="text-center text-muted py-4">No transactions on record.</div>
            @endforelse
          </div>
        </div>
      </div>
    </div>

    {{-- Delivery activity --}}
    @if($deliveries->isNotEmpty())
    <div class="lg-card mb-4">
      <div class="lg-card-head"><h6 class="mb-0"><i class="bx bx-package me-1"></i> Recent deliveries</h6><span class="lg-sub">how goods shipped</span></div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 lg-table">
          <thead class="table-light"><tr><th>Date</th><th>Delivery No</th><th>Item</th><th class="lg-num">Qty</th><th>Transporter</th><th>Vehicle</th><th>Consignee</th></tr></thead>
          <tbody>
          @foreach($deliveries as $d)
            <tr>
              <td class="lg-sub">{{ $d->delivery_date ? \Illuminate\Support\Carbon::parse($d->delivery_date)->format('d M Y') : '—' }}</td>
              <td class="fw-semibold">{{ $d->delivery_no }}</td>
              <td>{{ \Illuminate\Support\Str::limit($d->item_name, 30) ?: '—' }}</td>
              <td class="lg-num">{{ $d->quantity !== null ? number_format((float)$d->quantity, 2) : '—' }} <span class="lg-sub">{{ $d->unit }}</span></td>
              <td class="lg-sub">{{ $d->transporter_name ?: '—' }}</td>
              <td class="lg-sub">{{ $d->vehicle_number ?: '—' }}</td>
              <td class="lg-sub">{{ \Illuminate\Support\Str::limit($d->consignee_name, 20) ?: '—' }}</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
    @endif

  </div>
</div>

<script>
  window.LG_TREND = @json($trend->values());
</script>
<script>
(function () {
  function ready(fn){ if (document.readyState !== 'loading') fn(); else document.addEventListener('DOMContentLoaded', fn); }
  const inr = v => '₹' + Number(v||0).toLocaleString('en-IN', {maximumFractionDigits:0});
  const monthLabel = ym => { const [y,m] = ym.split('-'); return new Date(y, m-1, 1).toLocaleDateString('en-GB',{month:'short',year:'2-digit'}); };
  ready(function () {
    if (typeof ApexCharts === 'undefined') return;
    const t = window.LG_TREND || [];
    const el = document.querySelector('#lgTrend');
    if (!el || !t.length) return;
    const hasSales = t.some(r => r.sales), hasPurch = t.some(r => r.purchases);
    const series = [];
    if (hasSales) series.push({ name: 'Sales', data: t.map(r => Math.round(r.sales)) });
    if (hasPurch) series.push({ name: 'Purchases', data: t.map(r => Math.round(r.purchases)) });
    new ApexCharts(el, {
      chart: { type: 'bar', height: 300, toolbar: { show: false }, fontFamily: 'inherit' },
      series: series,
      xaxis: { categories: t.map(r => monthLabel(r.ym)), labels: { rotate: -45, style: { fontSize: '11px' } } },
      yaxis: { labels: { formatter: v => inr(v) } },
      colors: ['#0f6b78', '#6366f1'],
      plotOptions: { bar: { borderRadius: 4, columnWidth: '58%' } },
      dataLabels: { enabled: false },
      legend: { position: 'top' },
      tooltip: { y: { formatter: v => inr(v) } },
      grid: { borderColor: '#eef2f6' },
    }).render();
  });
})();
</script>

<style>
  #lgPage .lg-inner { padding:.5rem 1rem 2rem; } @media (min-width:768px){ #lgPage .lg-inner { padding:.5rem 1.5rem 2rem; } }
  #lgPage .lg-back { display:inline-flex; align-items:center; gap:.2rem; margin:.25rem 0 .9rem; font-size:.84rem; font-weight:600; color:#475569; text-decoration:none; } #lgPage .lg-back:hover { color:#0f6b78; }
  #lgPage .lg-hero { position:relative; overflow:hidden; border-radius:20px; color:#fff; background:#0a2740; box-shadow:0 12px 30px rgba(10,39,64,.16); margin-bottom:1.25rem; }
  #lgPage .lg-hero-bg { position:absolute; inset:0; z-index:0; background: radial-gradient(760px 320px at 90% -20%, rgba(45,212,191,.35), transparent 60%), linear-gradient(115deg,#0a2740,#0d4266 55%,#0f6b78); }
  #lgPage .lg-hero-top { position:relative; z-index:1; display:flex; align-items:center; gap:1.2rem; padding:1.5rem 1.8rem; }
  #lgPage .lg-hero-icon { width:60px; height:60px; flex-shrink:0; display:flex; align-items:center; justify-content:center; border-radius:16px; background:#fff; color:#0f4c75; font-size:1.7rem; }
  #lgPage .lg-hero-info { flex:1; min-width:0; } #lgPage .lg-hero-eyebrow { font-size:.72rem; letter-spacing:.03em; color:rgba(255,255,255,.75); font-weight:600; } #lgPage .lg-role { background:rgba(255,255,255,.18); padding:1px 8px; border-radius:20px; }
  #lgPage .lg-hero-title { margin:.2rem 0 .5rem; font-size:1.5rem; font-weight:700; color:#fff !important; letter-spacing:-.02em; line-height:1.2; }
  #lgPage .lg-hero-chips { display:flex; flex-wrap:wrap; gap:.5rem; } #lgPage .lg-chip { display:inline-flex; align-items:center; gap:.35rem; padding:3px 11px; border-radius:999px; font-size:.74rem; color:#fff; background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.18); }
  #lgPage .lg-hero-amount { flex-shrink:0; text-align:right; } #lgPage .lg-hero-amt-lbl { font-size:.72rem; color:rgba(255,255,255,.7); } #lgPage .lg-hero-amt-val { font-size:1.5rem; font-weight:800; color:#fff; } #lgPage .lg-hero-amt-val.neg { color:#fecaca; }
  #lgPage .lg-card { border-radius:14px; background:#fff; box-shadow:0 1px 2px rgba(15,23,42,.04),0 4px 14px rgba(15,23,42,.05); overflow:hidden; }
  #lgPage .lg-card-head { display:flex; align-items:baseline; justify-content:space-between; gap:.5rem; padding:1rem 1.15rem .4rem; flex-wrap:wrap; } #lgPage .lg-card-head h6 { font-weight:700; } #lgPage .lg-sub { font-size:.72rem; color:#94a3b8; } #lgPage .lg-card-body { padding:.5rem 1.15rem 1.1rem; }
  #lgPage .lg-stat { border-radius:14px; padding:.9rem 1.1rem; background:#fff; box-shadow:0 1px 2px rgba(15,23,42,.04),0 4px 14px rgba(15,23,42,.05); height:100%; }
  #lgPage .lg-stat .lbl { font-size:.64rem; text-transform:uppercase; letter-spacing:.04em; color:#94a3b8; font-weight:700; } #lgPage .lg-stat .val { font-size:1.25rem; font-weight:800; color:#1e293b; } #lgPage .lg-stat .val.sm { font-size:.9rem; } #lgPage .lg-stat-sub { font-size:.7rem; font-weight:600; color:#94a3b8; }
  #lgPage .lg-terms > div { display:flex; justify-content:space-between; gap:.5rem; padding:.42rem 0; border-bottom:1px dashed #eef2f6; } #lgPage .lg-terms > div:last-child { border-bottom:0; }
  #lgPage .lg-t-lbl { font-size:.78rem; color:#64748b; } #lgPage .lg-t-val { font-size:.9rem; font-weight:700; color:#1e293b; text-align:right; } #lgPage .lg-t-val.sm { font-size:.8rem; font-weight:600; } #lgPage .lg-t-val.warn { color:#b45309; } #lgPage .lg-t-sub { font-size:.72rem; font-weight:500; color:#94a3b8; }
  #lgPage .lg-util-bar { height:8px; border-radius:20px; background:#eef2f6; overflow:hidden; } #lgPage .lg-util-bar span { display:block; height:100%; border-radius:20px; }
  #lgPage .lg-age { display:inline-block; margin-left:.4rem; font-size:.68rem; background:#f1f5f9; color:#475569; padding:1px 8px; border-radius:20px; }
  #lgPage .lg-table th { font-size:.66rem; text-transform:uppercase; letter-spacing:.04em; color:#64748b; white-space:nowrap; } #lgPage .lg-table td { font-size:.82rem; } #lgPage .lg-num { text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; } #lgPage .lg-sub { font-size:.72rem; color:#94a3b8; }
  #lgPage .lg-feed { display:flex; align-items:center; gap:.7rem; padding:.5rem 0; border-bottom:1px solid #f4f6f9; } #lgPage .lg-feed:last-child { border-bottom:0; }
  #lgPage .lg-feed-dot { width:9px; height:9px; border-radius:50%; flex-shrink:0; } #lgPage .lg-feed-dot.in { background:#16a34a; } #lgPage .lg-feed-dot.out { background:#6366f1; }
  #lgPage .lg-feed-main { flex:1; min-width:0; } #lgPage .lg-feed-type { font-size:.8rem; font-weight:700; color:#1e293b; } #lgPage .lg-feed-ref { font-size:.74rem; color:#64748b; }
  #lgPage .lg-feed-amt { font-weight:700; font-size:.86rem; font-variant-numeric:tabular-nums; } #lgPage .lg-feed-amt.in { color:#16a34a; } #lgPage .lg-feed-amt.out { color:#4338ca; }
</style>
@endsection
