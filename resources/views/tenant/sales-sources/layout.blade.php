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
      .btn, .form-control, .form-select { border-radius: 10px !important; }
      .ss-tabs { border-bottom: 1px solid #e2e8f0; flex-wrap: nowrap; overflow-x: auto; }
      .ss-tabs .nav-item { flex-shrink: 0; }
      .ss-tabs .nav-link { font-weight: 600; font-size: .85rem; border: none; border-bottom: 2px solid transparent; color: #64748b; padding: .65rem 1rem; white-space: nowrap; }
      .ss-tabs .nav-link.active { color: #127464; border-bottom-color: #127464; background: transparent; }
      .ss-stat { border-radius: 14px; padding: 1rem 1.15rem; background: #fff; box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 4px 14px rgba(15,23,42,.05); height: 100%; }
      .ss-stat .lbl { font-size: .68rem; text-transform: uppercase; letter-spacing: .04em; color: #94a3b8; font-weight: 700; }
      .ss-stat .val { font-size: 1.35rem; font-weight: 800; color: #1e293b; line-height: 1.3; }
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
    </style>
@endsection

@section('vendor-script')
    @if(!empty($charts))
      @vite(['resources/assets/vendor/libs/apex-charts/apex-charts.min.js'])
    @endif
@endsection

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3 mx-4 py-2">
    <div>
      <h3 class="fw-extrabold mb-0 text-dark">Sales Sources</h3>
      <p class="text-muted mb-0 small">The same sales as the manual Sales Report, Setu and Tally each report them. Read-only &mdash; for comparing the sources before any of them is trusted to sync the Sales Report.</p>
    </div>
    <a href="{{ route('sales-visits.pipeline.index') }}" class="btn btn-outline-primary btn-sm">
      <i class="bx bx-table me-1"></i> Open the manual Sales Report
    </a>
  </div>

  <div class="px-4">
    @if($lastImport)
      @php $s = $lastImport->summary ?? []; @endphp
      <div class="small text-muted mb-3">
        <i class="bx bx-data me-1"></i>
        Tally data imported <strong>{{ $lastImport->finished_at->diffForHumans() }}</strong>
        ({{ $lastImport->finished_at->format('d M Y, h:i A') }})
        &middot; invoices {{ isset($s['invoices']['from']) ? \Carbon\Carbon::parse($s['invoices']['from'])->format('d M Y') : '—' }}
        &rarr; {{ isset($s['invoices']['to']) ? \Carbon\Carbon::parse($s['invoices']['to'])->format('d M Y') : '—' }}
        @if($lastImport->status !== 'ok')
          &middot; <span class="text-warning fw-bold">partial import &mdash; some files were missing or rejected</span>
        @endif
      </div>
    @else
      <div class="alert alert-warning py-2 small">
        No Tally data has been imported yet. Run <code>php artisan hrx:import-tally</code> to load the daily exports.
      </div>
    @endif

    <ul class="nav ss-tabs mb-4" role="tablist">
      @foreach([
        'analytics'   => ['sales-sources.analytics',   'bx-bar-chart-alt-2', 'Tally Analytics'],
        'compare'     => ['sales-sources.index',       'bx-git-compare',   'Compare'],
        'outstanding' => ['sales-sources.outstanding', 'bx-time-five',     'Outstanding'],
        'orders'      => ['sales-sources.orders',      'bx-cart',          'Sale Orders'],
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
