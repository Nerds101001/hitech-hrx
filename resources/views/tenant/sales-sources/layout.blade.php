@extends('layouts/layoutMaster')

@section('title', 'Sales Sources')

@section('vendor-style')
    @vite([
      'resources/assets/vendor/libs/animate-css/animate.scss',
      'resources/assets/vendor/scss/pages/hitech-portal.scss'
    ])
    @if(!empty($charts))
      @vite(['resources/assets/vendor/libs/apex-charts/apex-charts.scss'])
    @endif
    <style>
      /* ── shared bits the individual tabs use ─────────────────────────── */
      .btn, .form-control, .form-select { border-radius: 10px !important; }
      .ss-stat { border-radius: 14px; padding: 1rem 1.15rem; background: #fff; box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 4px 14px rgba(15,23,42,.05); height: 100%; }
      .ss-stat .lbl { font-size: .68rem; text-transform: uppercase; letter-spacing: .04em; color: #94a3b8; font-weight: 700; }
      .ss-stat { min-width: 0; }
      .ss-stat .val { font-size: 1.35rem; font-weight: 800; color: #1e293b; line-height: 1.3; overflow-wrap: anywhere; }
      .ss-table th { font-size: .68rem; text-transform: uppercase; letter-spacing: .04em; color: #64748b; white-space: nowrap; }
      .ss-table td { vertical-align: middle; font-size: .82rem; }
      .ss-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
      .ss-row-differs { background: #fffbeb; }
      .ss-flag { font-size: .62rem; font-weight: 700; padding: 2px 8px; border-radius: 20px; display: inline-block; margin: 1px 2px 1px 0; }
      .ss-flag.warn { background: #fef3c7; color: #92400e; }
      .ss-flag.bad  { background: #fee2e2; color: #b91c1c; }
      .ss-flag.ok   { background: #dcfce7; color: #15803d; }
      .ss-flag.info { background: #dbeafe; color: #1d4ed8; }
      .ss-sub { font-size: .68rem; color: #94a3b8; }
      .ss-cust { color: inherit; text-decoration: none; }
      .ss-cust:hover { color: #127464; text-decoration: underline; }
      .ss-cust-find { text-decoration: underline dotted #94a3b8; text-underline-offset: 3px; }

      /* ── the page frame: banner, then tabs ───────────────────────────── */
      #ssPage { --ss-ink: #0f172a; --ss-text: #334155; --ss-muted: #64748b; --ss-line: #e8edf3; --ss-soft: #f6f8fb;
                --ss-brand: #127464; --ss-brand-dark: #0e5f52; --ss-brand-soft: #eaf6f3; --ss-brand-line: #cfe8e1;
                --ss-shadow: 0 1px 2px rgba(16,24,40,.04), 0 6px 20px rgba(16,24,40,.05); }
      #ssPage .ss-inner { padding: 0 1rem; }
      @media (min-width: 768px) { #ssPage .ss-inner { padding: 0 1.5rem; } }

      #ssPage .ss-hero { position: relative; isolation: isolate; margin: .5rem 0 1.25rem; overflow: hidden; border-radius: 22px; color: #fff; background: #0a2740; box-shadow: 0 14px 34px rgba(10,39,64,.20); animation: ssRise .5s ease backwards; }
      #ssPage .ss-hero-bg { position: absolute; inset: 0; z-index: -1;
        background: radial-gradient(900px 380px at 88% -12%, rgba(45,212,191,.40), transparent 62%), radial-gradient(720px 420px at 8% 118%, rgba(59,130,246,.44), transparent 62%),
                    radial-gradient(520px 300px at 52% -8%, rgba(99,102,241,.30), transparent 66%), linear-gradient(115deg, #0a2740 0%, #0d4266 48%, #0f6b78 100%); }
      #ssPage .ss-hero-bg::before { content: ''; position: absolute; inset: 0; opacity: .55;
        background-image: linear-gradient(rgba(255,255,255,.07) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.07) 1px, transparent 1px); background-size: 46px 46px;
        -webkit-mask-image: linear-gradient(180deg, #000 0%, transparent 82%); mask-image: linear-gradient(180deg, #000 0%, transparent 82%); }
      #ssPage .ss-hero-bg::after { content: ''; position: absolute; right: -140px; top: -230px; width: 560px; height: 560px; border-radius: 50%; background: radial-gradient(circle, rgba(94,234,212,.30), transparent 66%); filter: blur(8px); animation: ssDrift 16s ease-in-out infinite alternate; }
      @keyframes ssDrift { from { transform: translate(0,0) scale(1); } to { transform: translate(-70px,46px) scale(1.14); } }
      @keyframes ssRise { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }

      #ssPage .ss-hero-top { position: relative; display: flex; align-items: center; gap: 1.4rem; padding: 1.7rem 2rem 1.4rem; }
      #ssPage .ss-hero-icon { width: 72px; height: 72px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; border-radius: 20px; background: #fff; color: #0f4c75; font-size: 2rem; box-shadow: 0 10px 28px rgba(0,0,0,.20); }
      #ssPage .ss-hero-info { min-width: 0; flex: 1; }
      #ssPage .ss-hero-title { margin: 0 0 .4rem; font-size: 1.9rem; font-weight: 700; line-height: 1.15; letter-spacing: -.025em; color: #fff !important; }
      #ssPage .ss-hero-sub { margin: 0; max-width: 62rem; font-size: .86rem; line-height: 1.5; color: rgba(255,255,255,.80); }
      #ssPage .ss-hero-chips { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: .7rem; }
      #ssPage .ss-chip { display: inline-flex; align-items: center; gap: .4rem; padding: 4px 12px; border-radius: 999px; font-size: .76rem; font-weight: 500; color: #fff; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.18); -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px); }
      #ssPage .ss-chip i { font-size: .95rem; opacity: .85; }
      #ssPage .ss-chip.warn { background: rgba(245,158,11,.22); border-color: rgba(252,211,77,.45); color: #fde68a; }
      #ssPage .ss-hero-alert { margin-bottom: .7rem; padding: .5rem .85rem; border-radius: 10px; background: rgba(245,158,11,.20); border: 1px solid rgba(252,211,77,.4); color: #fef3c7; font-size: .82rem; }
      #ssPage .ss-hero-alert code { color: #fff; background: rgba(255,255,255,.14); padding: 1px 6px; border-radius: 6px; }
      #ssPage .ss-hero-actions { flex-shrink: 0; }
      #ssPage .ss-hero .btn { min-height: 38px !important; padding: .45rem 1rem !important; border-radius: 10px !important; border: 1px solid transparent !important; font-size: .83rem !important; font-weight: 600 !important; background-image: none !important; box-shadow: none !important; transform: none !important; background-color: #fff !important; color: #0f172a !important; white-space: nowrap; }
      #ssPage .ss-hero .btn:hover { background-color: #f1f5f9 !important; }

      /* key figures a tab may put inside the banner (a glass strip, like the customer profile) */
      #ssPage .ss-kpis { position: relative; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); margin: 0 1.6rem 1.6rem; padding: 1.1rem .3rem; border-radius: 18px; background: rgba(255,255,255,.10); border: 1px solid rgba(255,255,255,.16); -webkit-backdrop-filter: blur(14px) saturate(140%); backdrop-filter: blur(14px) saturate(140%); }
      #ssPage .ss-kpi { display: flex; align-items: center; gap: .9rem; min-width: 0; padding: .2rem 1.25rem; }
      #ssPage .ss-kpi + .ss-kpi { border-left: 1px solid rgba(255,255,255,.15); }
      #ssPage .ss-kpi-icon { width: 46px; height: 46px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; border-radius: 13px; font-size: 1.4rem; }
      #ssPage .tone-blue { background: #e3edff; color: #2563eb; } #ssPage .tone-violet { background: #ece8ff; color: #7c3aed; }
      #ssPage .tone-cyan { background: #d5f5fb; color: #0891b2; } #ssPage .tone-amber { background: #ffead6; color: #ea580c; } #ssPage .tone-green { background: #dcfce9; color: #16a34a; }
      #ssPage .ss-kpi-body { min-width: 0; }
      #ssPage .ss-kpi-value { font-size: 1.5rem; font-weight: 700; line-height: 1.15; letter-spacing: -.02em; color: #fff; font-variant-numeric: tabular-nums; }
      #ssPage .ss-kpi-value small { font-size: .95rem; font-weight: 600; color: rgba(255,255,255,.7); }
      #ssPage .ss-kpi-label { margin-top: 2px; font-size: .8rem; color: rgba(255,255,255,.9); }
      #ssPage .ss-kpi-sub { font-size: .72rem; line-height: 1.4; color: rgba(255,255,255,.66); }
      #ssPage .ss-kpi-sub a { color: #fff; text-decoration: underline; text-decoration-color: rgba(255,255,255,.4); text-underline-offset: 2px; }
      #ssPage .ss-kpi-sub a:hover { text-decoration-color: #fff; }

      /* tabs: plain text, green underline */
      #ssPage .ss-tabs { gap: .35rem; margin-bottom: 1.25rem !important; border-bottom: 1px solid var(--ss-line); flex-wrap: nowrap; overflow-x: auto; scrollbar-width: none; -webkit-overflow-scrolling: touch; scroll-snap-type: x proximity; }
      #ssPage .ss-tabs::-webkit-scrollbar { display: none; }
      #ssPage .ss-tabs .nav-item { flex-shrink: 0; scroll-snap-align: center; }
      #ssPage .ss-tabs .nav-link { margin-bottom: -1px; padding: .8rem 1rem; border: 0; border-bottom: 2px solid transparent; border-radius: 0; background: transparent !important; font-size: .88rem; font-weight: 500; color: var(--ss-muted); white-space: nowrap; transition: color .15s ease, border-color .15s ease; }
      #ssPage .ss-tabs .nav-link i { font-size: 1rem; vertical-align: -2px; opacity: .7; }
      #ssPage .ss-tabs .nav-link:hover { color: var(--ss-ink); border-bottom-color: #d3dbe5; }
      #ssPage .ss-tabs .nav-link.active { color: var(--ss-brand); font-weight: 600; border-bottom-color: var(--ss-brand); }
      #ssPage .ss-tabs .nav-link.active i, #ssPage .ss-tabs .nav-link:hover i { opacity: 1; }

      @media (max-width: 1199px) { #ssPage .ss-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); row-gap: 1rem; } #ssPage .ss-kpi:nth-child(odd) { border-left: 0; } }
      @media (max-width: 991px) {
        #ssPage .ss-hero-top { flex-wrap: wrap; padding: 1.4rem 1.3rem 1.1rem; }
        #ssPage .ss-hero-actions { width: 100%; }
        #ssPage .ss-hero-actions .btn { width: 100%; justify-content: center; }
        #ssPage .ss-hero-title { font-size: 1.6rem; }
        #ssPage .ss-kpis { margin: 0 1rem 1rem; }
      }
      @media (max-width: 767px) {
        #ssPage .ss-hero-icon { width: 60px; height: 60px; border-radius: 17px; font-size: 1.7rem; }
        #ssPage .ss-kpis { display: flex; gap: .6rem; padding: .6rem; overflow-x: auto; scroll-snap-type: x proximity; scrollbar-width: none; -webkit-overflow-scrolling: touch; }
        #ssPage .ss-kpis::-webkit-scrollbar { display: none; }
        #ssPage .ss-kpi, #ssPage .ss-kpi:nth-child(n) { flex: 0 0 auto; width: min(46%, 270px); gap: .7rem; padding: .6rem .8rem; border: 1px solid rgba(255,255,255,.14); border-radius: 13px; background: rgba(255,255,255,.07); scroll-snap-align: start; }
        #ssPage .ss-kpi-icon { width: 38px; height: 38px; border-radius: 11px; font-size: 1.15rem; }
        #ssPage .ss-kpi-value { font-size: 1.3rem; }
        #ssPage .ss-kpi-sub a { display: inline-block; padding: .3rem 0; }
        #ssPage .ss-tabs .nav-link { padding: .85rem 1rem; }
      }
      @media (max-width: 575px) {
        #ssPage .ss-hero { margin-top: .25rem; border-radius: 18px; }
        #ssPage .ss-hero-top { display: grid; grid-template-columns: 52px minmax(0, 1fr); align-items: center; gap: .55rem .8rem; padding: 1rem 1rem .8rem; }
        #ssPage .ss-hero-info { display: contents; }
        #ssPage .ss-hero-icon { grid-row: 1 / span 2; width: 52px; height: 52px; border-radius: 14px; font-size: 1.5rem; }
        #ssPage .ss-hero-chips { grid-column: 1 / -1; grid-row: 3; margin: 0; gap: .4rem; }
        #ssPage .ss-hero-alert { grid-column: 1 / -1; grid-row: 3; margin: 0; }
        #ssPage .ss-hero-title { grid-column: 2; grid-row: 1; margin: 0; font-size: 1.3rem; align-self: end; }
        #ssPage .ss-hero-sub { grid-column: 2; grid-row: 2; align-self: start; font-size: .76rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        #ssPage .ss-chip { max-width: 100%; padding: 4px 10px; font-size: .74rem; }
        #ssPage .ss-hero-actions { grid-column: 1 / -1; grid-row: 4; }
        #ssPage .ss-kpi, #ssPage .ss-kpi:nth-child(n) { width: min(78%, 260px); }
        #ssPage .ss-kpis { margin: 0 .75rem .8rem; }
      }
      @media (prefers-reduced-motion: reduce) { #ssPage *, #ssPage *::before, #ssPage *::after { animation: none !important; transition: none !important; } }
    </style>
@endsection

@section('vendor-script')
    @if(!empty($charts))
      @vite(['resources/assets/vendor/libs/apex-charts/apex-charts.min.js'])
    @endif
@endsection

@section('content')
<div id="ssPage" class="layout-full-width ss-page animate__animated animate__fadeIn">
  <div class="ss-inner">

    <section class="ss-hero">
      <div class="ss-hero-bg" aria-hidden="true"></div>
      <div class="ss-hero-top">
        <div class="ss-hero-icon"><i class="bx bx-git-compare"></i></div>
        <div class="ss-hero-info">
          @if($lastSync)
            <div class="ss-hero-chips">
              <span class="ss-chip"><i class="bx bx-data"></i>{{ $lastSync['label'] }} <b>{{ $lastSync['at']->diffForHumans() }}</b> ({{ $lastSync['at']->format('d M Y, h:i A') }})</span>
              @if($lastImport)
                @php $s = $lastImport->summary ?? []; @endphp
                <span class="ss-chip"><i class="bx bx-calendar"></i>last full file import covered {{ isset($s['invoices']['from']) ? \Carbon\Carbon::parse($s['invoices']['from'])->format('d M Y') : '—' }} &rarr; {{ isset($s['invoices']['to']) ? \Carbon\Carbon::parse($s['invoices']['to'])->format('d M Y') : '—' }}</span>
              @endif
              @if($lastSync['partial'])
                <span class="ss-chip warn"><i class="bx bx-error"></i>{{ $lastSync['source'] === 'push' ? 'partial sync — recomputing invoice totals failed, will self-correct next run' : 'partial import — some files were missing or rejected' }}</span>
              @endif
            </div>
          @else
            <div class="ss-hero-alert">No Tally data has been imported yet. Run <code>php artisan hrx:import-tally</code> to load the daily exports.</div>
          @endif
          <h1 class="ss-hero-title">Sales Sources</h1>
          <p class="ss-hero-sub">The same sales as the manual Sales Report, Setu and Tally each report them. Read-only &mdash; for comparing the sources before any of them is trusted to sync the Sales Report.</p>
        </div>
        <div class="ss-hero-actions">
          <a href="{{ route('sales-visits.pipeline.index') }}" class="btn btn-light"><i class="bx bx-table me-1"></i> Open the manual Sales Report</a>
        </div>
      </div>
      @hasSection('hero-strip')
        <div class="ss-kpis">@yield('hero-strip')</div>
      @endif
    </section>

    <ul class="nav ss-tabs" role="tablist">
      @foreach([
        'analytics'   => ['sales-sources.analytics',   'bx-bar-chart-alt-2', 'Tally Analytics'],
        'compare'     => ['sales-sources.index',       'bx-git-compare',   'Compare'],
        'outstanding' => ['sales-sources.outstanding', 'bx-time-five',     'Outstanding'],
        'orders'      => ['sales-sources.orders',      'bx-cart',          'Sale Orders'],
        'work-orders' => ['sales-sources.work-orders', 'bx-task',          'Work Orders'],
        'unmapped'    => ['sales-sources.unmapped',    'bx-unlink',        'Not in CRM'],
        'parties'     => ['sales-sources.parties',     'bx-credit-card',   'Credit & Contacts'],
        'team'        => ['sales-sources.team',        'bx-group',         'Team'],
        'finance'     => ['sales-sources.finance',      'bx-money',         'Finance'],
        'setu'        => ['sales-sources.setu',        'bx-data',          'Setu'],
      ] as $key => [$route, $icon, $label])
        <li class="nav-item">
          <a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ route($route) }}"><i class="bx {{ $icon }} me-1"></i>{{ $label }}</a>
        </li>
      @endforeach
    </ul>

    @yield('tab')
  </div>
</div>

{{-- Popups go OUTSIDE the animated wrapper above. That wrapper keeps a stacking
     layer alive (animate__fadeIn, fill-mode: both), and a modal trapped inside it
     ends up UNDER the page-level backdrop -- dimmed and unclickable. --}}
@yield('tab-modals')
@endsection
