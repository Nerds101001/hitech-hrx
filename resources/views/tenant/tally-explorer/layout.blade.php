@extends('layouts/layoutMaster')

@section('title', 'Tally Explorer')

@section('vendor-style')
  @vite(['resources/assets/vendor/scss/pages/hitech-portal.scss'])
@endsection

@section('content')
<div id="txPage" class="layout-full-width">
  <div class="tx-inner">

    <section class="tx-hero">
      <div class="tx-hero-bg" aria-hidden="true"></div>
      <div class="tx-hero-top">
        <div class="tx-hero-icon"><i class="bx bx-data"></i></div>
        <div class="tx-hero-info">
          <h1 class="tx-hero-title">Tally Explorer</h1>
          <p class="tx-hero-sub">Everything synced from Tally — purchases, purchase orders, payments, the stock-item master and the full ledger master — each one searchable, with a drill-down to every field. Read-only.</p>
        </div>
      </div>
    </section>

    <ul class="nav tx-tabs" role="tablist">
      @foreach([
        'trend'           => ['tally-explorer.trend',           'bx-trending-up',   'Trend'],
        'purchases'       => ['tally-explorer.purchases',       'bx-cart-download', 'Purchases'],
        'purchase-orders' => ['tally-explorer.purchase-orders', 'bx-receipt',       'Purchase Orders'],
        'payments'        => ['tally-explorer.payments',        'bx-money-withdraw','Payments'],
        'stock'           => ['tally-explorer.stock',           'bx-box',           'Stock Items'],
        'ledgers'         => ['tally-explorer.ledgers',         'bx-book-content',  'Ledgers (master)'],
      ] as $key => [$route, $icon, $label])
        <li class="nav-item">
          <a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ route($route) }}">
            <i class="bx {{ $icon }} me-1"></i>{{ $label }}
            @if(($counts[$key] ?? 0) > 0)<span class="tx-count">{{ number_format($counts[$key]) }}</span>@endif
          </a>
        </li>
      @endforeach
    </ul>

    @yield('tab')
  </div>
</div>

@yield('tab-modals')

<style>
  #txPage { --tx-ink:#0f172a; --tx-muted:#64748b; --tx-line:#e8edf3; --tx-brand:#127464; }
  #txPage .tx-inner { padding: 0 1rem; }
  @media (min-width:768px){ #txPage .tx-inner { padding: 0 1.5rem; } }

  #txPage .tx-hero { position:relative; overflow:hidden; margin:.5rem 0 1.25rem; border-radius:22px; color:#fff; background:#0a2740; box-shadow:0 14px 34px rgba(10,39,64,.18); }
  #txPage .tx-hero-bg { position:absolute; inset:0; z-index:0;
    background: radial-gradient(900px 380px at 88% -12%, rgba(45,212,191,.38), transparent 62%),
                radial-gradient(720px 420px at 8% 118%, rgba(59,130,246,.40), transparent 62%),
                linear-gradient(115deg,#0a2740 0%,#0d4266 48%,#0f6b78 100%); }
  #txPage .tx-hero-top { position:relative; z-index:1; display:flex; align-items:center; gap:1.3rem; padding:1.6rem 2rem 1.4rem; }
  #txPage .tx-hero-icon { width:66px; height:66px; flex-shrink:0; display:flex; align-items:center; justify-content:center; border-radius:18px; background:#fff; color:#0f4c75; font-size:1.9rem; box-shadow:0 10px 28px rgba(0,0,0,.18); }
  #txPage .tx-hero-title { margin:0 0 .35rem; font-size:1.8rem; font-weight:700; letter-spacing:-.025em; color:#fff !important; }
  #txPage .tx-hero-sub { margin:0; max-width:64rem; font-size:.85rem; line-height:1.5; color:rgba(255,255,255,.80); }

  #txPage .tx-tabs { gap:.35rem; margin-bottom:1.25rem; border-bottom:1px solid var(--tx-line); flex-wrap:nowrap; overflow-x:auto; scrollbar-width:none; }
  #txPage .tx-tabs::-webkit-scrollbar { display:none; }
  #txPage .tx-tabs .nav-item { flex-shrink:0; }
  #txPage .tx-tabs .nav-link { margin-bottom:-1px; padding:.8rem 1rem; border:0; border-bottom:2px solid transparent; border-radius:0; background:transparent !important; font-size:.88rem; font-weight:500; color:var(--tx-muted); white-space:nowrap; }
  #txPage .tx-tabs .nav-link i { font-size:1rem; vertical-align:-2px; opacity:.7; }
  #txPage .tx-tabs .nav-link:hover { color:var(--tx-ink); border-bottom-color:#d3dbe5; }
  #txPage .tx-tabs .nav-link.active { color:var(--tx-brand); font-weight:600; border-bottom-color:var(--tx-brand); }
  #txPage .tx-count { display:inline-block; margin-left:.35rem; padding:1px 7px; border-radius:999px; background:#eef2f6; color:#64748b; font-size:.68rem; font-weight:700; vertical-align:1px; }
  #txPage .tx-tabs .nav-link.active .tx-count { background:#d8efe9; color:#127464; }

  #txPage .btn, #txPage .form-control, #txPage .form-select { border-radius:10px !important; }
  #txPage .tx-card { border-radius:14px; background:#fff; box-shadow:0 1px 2px rgba(15,23,42,.04),0 4px 14px rgba(15,23,42,.05); }
  #txPage .tx-stat { border-radius:14px; padding:1rem 1.15rem; background:#fff; box-shadow:0 1px 2px rgba(15,23,42,.04),0 4px 14px rgba(15,23,42,.05); height:100%; }
  #txPage .tx-stat .lbl { font-size:.68rem; text-transform:uppercase; letter-spacing:.04em; color:#94a3b8; font-weight:700; }
  #txPage .tx-stat .val { font-size:1.35rem; font-weight:800; color:#1e293b; line-height:1.3; overflow-wrap:anywhere; }
  #txPage .tx-table { margin:0; }
  #txPage .tx-table th { font-size:.68rem; text-transform:uppercase; letter-spacing:.04em; color:#64748b; white-space:nowrap; border-top:0; }
  #txPage .tx-table td { vertical-align:middle; font-size:.82rem; }
  #txPage .tx-num { text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }
  #txPage .tx-sub { font-size:.7rem; color:#94a3b8; }
  #txPage .tx-badge { font-size:.62rem; font-weight:700; padding:2px 8px; border-radius:20px; background:#eef2f6; color:#475569; }
</style>
@endsection
