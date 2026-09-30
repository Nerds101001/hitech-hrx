@extends('tenant.sales-sources.layout')

@php
    $L = fn($v) => \App\Support\Fmt::lakh($v);
    $monthLabel = \Carbon\Carbon::createFromFormat('!Y-m', $month)->format('F Y');
    $setuHasMonth = in_array($month, $setuMonthsCached, true);
    $qs = fn(array $extra) => route('sales-sources.index', array_merge(request()->except('page'), $extra));
    $chips = [
        'all' => ['All customers', $totals['customers']],
        'differs' => ['Sources disagree', $totals['differs']],
        'no_manual' => ['Not in Sales Report', $totals['no_manual']],
        'no_setu' => ['Not in Setu', $totals['no_setu']],
        'no_tally' => ['Not in Tally', $totals['no_tally']],
        'agree' => ['All three agree', $totals['agree']],
    ];
@endphp

@section('page-style')
<style>
    #ssPage .ss-card { background: #fff; border: 1px solid #edf1f5; border-radius: 16px; box-shadow: var(--ss-shadow); }

    /* filters */
    #ssPage .ss-toolbar { margin-bottom: 1rem; padding: 1rem 1.1rem; animation: ssRise .5s ease .05s backwards; }
    #ssPage .ss-toolbar .form-label { margin-bottom: .3rem !important; font-size: .66rem; font-weight: 600 !important; letter-spacing: .05em; text-transform: uppercase; color: var(--ss-muted); }
    #ssPage .ss-compare .form-control, #ssPage .ss-compare .form-select { padding: .5rem .8rem !important; border: 1px solid #d5dde6 !important; border-radius: 10px !important; background-color: #fff !important; box-shadow: none !important; font-size: .85rem; color: var(--ss-ink); }
    #ssPage .ss-compare .form-control:focus, #ssPage .ss-compare .form-select:focus { border-color: var(--ss-brand) !important; box-shadow: 0 0 0 3px rgba(18,116,100,.14) !important; }
    #ssPage .ss-compare .btn:not(.btn-close) { min-height: 38px !important; padding: .45rem 1rem !important; gap: .4rem !important; border-width: 1px !important; border-style: solid !important; border-radius: 10px !important; font-size: .83rem !important; font-weight: 600 !important; background-image: none !important; box-shadow: none !important; transform: none !important; transition: background-color .15s ease, border-color .15s ease !important; }
    #ssPage .ss-compare .btn-primary { background-color: var(--ss-brand) !important; border-color: var(--ss-brand) !important; color: #fff !important; }
    #ssPage .ss-compare .btn-primary:hover { background-color: var(--ss-brand-dark) !important; border-color: var(--ss-brand-dark) !important; }
    #ssPage .ss-compare .btn-outline-secondary { background-color: #fff !important; border-color: #d3dbe5 !important; color: #334155 !important; }
    #ssPage .ss-compare .btn-outline-secondary:hover { background-color: var(--ss-soft) !important; border-color: #c2ccd8 !important; color: var(--ss-ink) !important; }

    /* quick filters */
    #ssPage .ss-chips { display: flex; gap: .5rem; margin-bottom: 1rem; padding-bottom: 2px; overflow-x: auto; scrollbar-width: none; -webkit-overflow-scrolling: touch; animation: ssRise .5s ease .1s backwards; }
    #ssPage .ss-chips::-webkit-scrollbar { display: none; }
    #ssPage .ss-pill { flex-shrink: 0; display: inline-flex; align-items: center; gap: .45rem; padding: .42rem .85rem; border: 1px solid #e3e9f0; border-radius: 999px; background: #fff; color: #475569; font-size: .8rem; font-weight: 500; text-decoration: none; white-space: nowrap; transition: border-color .15s ease, background-color .15s ease, color .15s ease; }
    #ssPage .ss-pill b { padding: 0 7px; border-radius: 999px; background: #f1f5f9; color: #64748b; font-size: .72rem; font-weight: 600; }
    #ssPage .ss-pill:hover { border-color: #c9d3df; color: var(--ss-ink); }
    #ssPage .ss-pill.active { border-color: var(--ss-brand); background: var(--ss-brand); color: #fff; }
    #ssPage .ss-pill.active b { background: rgba(255,255,255,.22); color: #fff; }

    #ssPage .ss-compare .alert { margin-bottom: 1rem; padding: .65rem .9rem; border: 1px solid #fedf89; border-radius: 12px; background: #fffaeb; color: #93370d; box-shadow: none; }

    /* the comparison table */
    #ssPage .ss-table-card { overflow: hidden; margin-bottom: 1rem; animation: ssRise .55s ease .15s backwards; }
    #ssPage .ss-cmp th { padding: .7rem 1rem; background: var(--ss-soft); border-bottom: 1px solid var(--ss-line); font-size: .68rem; font-weight: 600; letter-spacing: .05em; color: var(--ss-muted); }
    #ssPage .ss-cmp td { padding: .75rem 1rem; border-bottom: 1px solid #f1f4f8; font-size: .85rem; color: #334155; }
    #ssPage .ss-cmp tbody tr:last-child td { border-bottom: 0; }
    #ssPage .ss-cmp tbody tr { transition: background-color .15s ease; }
    #ssPage .ss-cmp tbody tr:hover { background: #f8fafc; }
    #ssPage .ss-cmp .ss-row-differs { background: #fffcf3; }
    #ssPage .ss-cmp .ss-row-differs:hover { background: #fff8e5; }
    #ssPage .ss-cmp .ss-row-differs td:first-child { box-shadow: inset 3px 0 0 #f59e0b; }
    #ssPage .ss-cmp .ss-cust { font-weight: 600; color: var(--ss-ink); }
    #ssPage .ss-cmp .ss-cust:hover { color: var(--ss-brand); }
    #ssPage .ss-cmp .ss-sub { margin-top: 1px; font-size: .72rem; color: var(--ss-muted); }
    #ssPage .ss-cmp .ss-flag { padding: 3px 9px; border: 1px solid transparent; font-size: .68rem; font-weight: 600; }
    #ssPage .ss-cmp .ss-flag.warn { border-color: #fedf89; background: #fffaeb; color: #b54708; }
    #ssPage .ss-cmp .ss-flag.bad  { border-color: #fbd0cc; background: #fef3f2; color: #b42318; }
    #ssPage .ss-cmp .ss-flag.ok   { border-color: #bfe9d2; background: #ecfdf3; color: #067647; }
    #ssPage .ss-cmp .ss-flag.info { border-color: #cfe9fb; background: #f0f9ff; color: #075985; }
    #ssPage .ss-pager { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .6rem; padding: .8rem 1rem; border-top: 1px solid var(--ss-line); }
    #ssPage .ss-pager nav { max-width: 100%; }
    #ssPage .ss-pager .pagination { flex-wrap: wrap; justify-content: center; margin: 0; gap: .25rem; }
    #ssPage .ss-pager .page-link { min-width: 36px; padding: .4rem .6rem; border: 1px solid #e3e9f0; border-radius: 10px !important; color: #334155; font-size: .82rem; text-align: center; box-shadow: none; }
    #ssPage .ss-pager .page-item.active .page-link { border-color: var(--ss-brand); background: var(--ss-brand); color: #fff; }
    #ssPage .ss-pager .page-item.disabled .page-link { opacity: .5; }
    #ssPage .ss-notes { display: flex; gap: .7rem; margin-bottom: 1.5rem; padding: 1rem 1.2rem; }
    #ssPage .ss-notes > i { flex-shrink: 0; margin-top: 2px; font-size: 1.2rem; color: #94a3b8; }
    #ssPage .ss-notes p { margin: 0 0 .6rem; font-size: .8rem; line-height: 1.6; color: var(--ss-muted); }
    #ssPage .ss-notes p:last-child { margin-bottom: 0; }

    @media (min-width: 768px) { #ssPage .ss-toggle-filters { display: none !important; } }   /* the portal's own .btn rule forces display, so d-md-none alone doesn't hide it */
    @media (max-width: 767px) {
        #ssPage .ss-toolbar:not(.open) .ss-more { display: none; }
        #ssPage .ss-toggle-filters { min-width: 42px; }
        #ssPage .ss-pill { padding: .5rem .9rem; }
    }
    @media (max-width: 991px) {   /* tablets and phones: each customer becomes a small card instead of a wide table */
        #ssPage .ss-cmp .ss-cust { display: inline-block; padding: .25rem 0; }
        #ssPage .ss-cmp thead { display: none; }
        #ssPage .ss-cmp, #ssPage .ss-cmp tbody, #ssPage .ss-cmp tr, #ssPage .ss-cmp td { display: block; width: 100%; }
        #ssPage .ss-cmp tbody tr { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .3rem .5rem; padding: .85rem 1rem; border-bottom: 1px solid #f1f4f8; }
        #ssPage .ss-cmp .ss-row-differs { box-shadow: inset 3px 0 0 #f59e0b; }
        #ssPage .ss-cmp .ss-row-differs td:first-child { box-shadow: none; }
        #ssPage .ss-cmp td { min-width: 0 !important; padding: 0 !important; border: 0 !important; }
        #ssPage .ss-cmp td.ss-cell-cust, #ssPage .ss-cmp td.ss-cell-notes { grid-column: 1 / -1; }
        #ssPage .ss-cmp td.ss-num { text-align: left; font-size: .95rem; }
        #ssPage .ss-cmp td.ss-num::before { content: attr(data-label); display: block; font-size: .62rem; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: var(--ss-muted); }
        #ssPage .ss-cmp td.ss-cell-notes:empty { display: none; }
        #ssPage .ss-cmp .ss-sub { font-size: .75rem; }
    }
    @media (max-width: 575px) {
        #ssPage .ss-toolbar { padding: .85rem; }
        #ssPage .ss-compare .form-control, #ssPage .ss-compare .form-select { font-size: 16px !important; }
        #ssPage .ss-compare .btn:not(.btn-close) { min-height: 42px !important; }
        #ssPage .ss-pager { justify-content: center; text-align: center; }
        #ssPage .ss-notes { padding: .9rem; }
    }
</style>
@endsection

@section('hero-strip')
  <div class="ss-kpi">
    <div class="ss-kpi-icon tone-violet"><i class="bx bx-spreadsheet"></i></div>
    <div class="ss-kpi-body">
      <div class="ss-kpi-value">₹<span data-count="{{ round($totals['manual'], 2) }}" data-decimals="2">{{ $L($totals['manual']) }}</span> <small>L</small></div>
      <div class="ss-kpi-label">Manual Sales Report &middot; {{ $monthLabel }}</div>
      <div class="ss-kpi-sub">Sales Report rows linked to a customer</div>
    </div>
  </div>
  <div class="ss-kpi">
    <div class="ss-kpi-icon tone-cyan"><i class="bx bx-data"></i></div>
    <div class="ss-kpi-body">
      <div class="ss-kpi-value">@if($setuHasMonth)₹<span data-count="{{ round($totals['setu'], 2) }}" data-decimals="2">{{ $L($totals['setu']) }}</span> <small>L</small>@else <span style="font-size:1.05rem;">not cached</span>@endif</div>
      <div class="ss-kpi-label">Setu &middot; {{ $monthLabel }}</div>
      <div class="ss-kpi-sub">Cache holds {{ count($setuMonthsCached) ? implode(', ', array_map(fn($m) => \Carbon\Carbon::createFromFormat('!Y-m', $m)->format('M y'), $setuMonthsCached)) : 'nothing' }}@if($setuSyncedAt) &middot; synced {{ \Carbon\Carbon::parse($setuSyncedAt)->format('d M') }}@endif</div>
    </div>
  </div>
  <div class="ss-kpi">
    <div class="ss-kpi-icon tone-blue"><i class="bx bx-receipt"></i></div>
    <div class="ss-kpi-body">
      <div class="ss-kpi-value">₹<span data-count="{{ round($totals['tally'], 2) }}" data-decimals="2">{{ $L($totals['tally']) }}</span> <small>L</small></div>
      <div class="ss-kpi-label">Tally &middot; {{ $monthLabel }}</div>
      <div class="ss-kpi-sub">Invoice value incl. GST, branch transfers excluded</div>
    </div>
  </div>
  <div class="ss-kpi">
    <div class="ss-kpi-icon tone-amber"><i class="bx bx-group"></i></div>
    <div class="ss-kpi-body">
      <div class="ss-kpi-value"><span data-count="{{ $totals['customers'] }}" data-decimals="0">{{ number_format($totals['customers']) }}</span> <small>/ {{ number_format($totals['differs']) }}</small></div>
      <div class="ss-kpi-label">Customers &middot; disagreeing</div>
      <div class="ss-kpi-sub">
        <a href="{{ $qs(['status' => 'no_manual']) }}">{{ $totals['no_manual'] }} not in Sales Report</a> &middot;
        <a href="{{ $qs(['status' => 'no_setu']) }}">{{ $totals['no_setu'] }} not in Setu</a> &middot;
        <a href="{{ $qs(['status' => 'no_tally']) }}">{{ $totals['no_tally'] }} not in Tally</a>
      </div>
    </div>
  </div>
@endsection

@section('tab')
<div class="ss-compare">

<form method="GET" action="{{ route('sales-sources.index') }}" class="ss-card ss-toolbar">
  <div class="row g-2 align-items-end">
    <div class="ss-more col-6 col-md-2">
      <label class="form-label small fw-bold mb-1">Month</label>
      <select name="m" class="form-select form-select-sm" onchange="this.form.submit()">
        @foreach($months as $m)
          <option value="{{ $m }}" @selected($m === $month)>{{ \Carbon\Carbon::createFromFormat('!Y-m', $m)->format('M Y') }}</option>
        @endforeach
      </select>
    </div>
    <div class="ss-more col-6 col-md-2">
      <label class="form-label small fw-bold mb-1">Show</label>
      <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="all" @selected($status === 'all')>All customers</option>
        <option value="differs" @selected($status === 'differs')>Sources disagree</option>
        <option value="no_manual" @selected($status === 'no_manual')>In Tally, not in Sales Report</option>
        <option value="no_setu" @selected($status === 'no_setu')>In Tally, not in Setu</option>
        <option value="no_tally" @selected($status === 'no_tally')>Not in Tally</option>
        <option value="agree" @selected($status === 'agree')>All three agree</option>
      </select>
    </div>
    <div class="col-12 col-md-3">
      <label class="form-label small fw-bold mb-1">Search</label>
      <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Customer name or Tally party code">
    </div>
    <div class="ss-more col-6 col-md-2">
      <label class="form-label small fw-bold mb-1">Sort by</label>
      <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="amount" @selected($sort === 'amount')>Largest sale</option>
        <option value="gap" @selected($sort === 'gap')>Biggest gap</option>
        <option value="name" @selected($sort === 'name')>Name</option>
      </select>
    </div>
    <div class="ss-more col-6 col-md-1">
      <label class="form-label small fw-bold mb-1">Rows</label>
      <select name="per" class="form-select form-select-sm" onchange="this.form.submit()">
        @foreach([25, 50, 100, 200] as $n)<option value="{{ $n }}" @selected($per === $n)>{{ $n }}</option>@endforeach
      </select>
    </div>
    <div class="col-12 col-xl-2 d-flex gap-2">
      <button class="btn btn-primary btn-sm flex-grow-1"><i class="bx bx-search"></i> Apply</button>
      <a href="{{ route('sales-sources.index', ['m' => $month]) }}" class="btn btn-outline-secondary btn-sm">Reset</a>
      <button type="button" class="btn btn-outline-secondary btn-sm d-md-none ss-toggle-filters" aria-expanded="false" title="Month, status, sort and rows"><i class="bx bx-slider-alt"></i></button>
    </div>
  </div>
</form>

<div class="ss-chips">
  @foreach($chips as $key => [$label, $count])
    <a href="{{ $qs(['status' => $key]) }}" class="ss-pill {{ $status === $key ? 'active' : '' }}">{{ $label }} <b>{{ number_format($count) }}</b></a>
  @endforeach
</div>

@if($setuBehindDays > 0)
  <div class="alert py-2 small">
    <i class="bx bx-time-five me-1"></i>
    Setu was last synced on <strong>{{ \Carbon\Carbon::parse($setuSyncedAt)->format('d M') }}</strong> but the Tally data runs to
    <strong>{{ \Carbon\Carbon::parse($tallyLast)->format('d M') }}</strong>. {{ $monthLabel }} is still in progress, so Setu will read
    low here until it is re-synced.
  </div>
@endif

<div class="ss-card ss-table-card">
  <div class="table-responsive">
    <table class="table ss-table ss-cmp mb-0">
      <thead>
        <tr>
          <th style="min-width:280px;">Customer</th>
          <th class="ss-num">Manual (₹ L)</th>
          <th class="ss-num">Setu (₹ L)</th>
          <th class="ss-num">Tally (₹ L)</th>
          <th class="ss-num">Gap (₹ L)</th>
          <th>Notes</th>
        </tr>
      </thead>
      <tbody>
        @forelse($paginator as $r)
          <tr class="{{ $r['differs'] ? 'ss-row-differs' : '' }}">
            <td class="ss-cell-cust">
              <div>
                @if(isset($profileIds[$r['key']]))
                  <a href="{{ route('crm-clients.profile', $profileIds[$r['key']]) }}" class="ss-cust" title="Open this customer's profile">{{ $r['name'] }}</a>
                @else
                  <a href="{{ route('sales-sources.company', ['name' => $r['name']]) }}" class="ss-cust ss-cust-find" title="Open this company's profile">{{ $r['name'] }}</a>
                @endif
              </div>
              <div class="ss-sub">
                @if($r['party_code'])Tally code {{ $r['party_code'] }}@endif
                @if($r['sps_tally']) &middot; {{ $r['sps_tally'] }}@elseif($r['sps_manual']) &middot; {{ $r['sps_manual'] }}@endif
                @if($r['invoices']) &middot; {{ $r['invoices'] }} invoice{{ $r['invoices'] > 1 ? 's' : '' }}@endif
              </div>
            </td>
            <td class="ss-num" data-label="Manual">{{ $L($r['manual']) }}</td>
            <td class="ss-num" data-label="Setu">{{ $L($r['setu']) }}</td>
            <td class="ss-num fw-semibold" data-label="Tally">{{ $L($r['tally']) }}</td>
            <td class="ss-num {{ $r['differs'] ? 'text-danger fw-bold' : 'text-muted' }}" data-label="Gap">{{ $r['gap'] > 0 ? $L($r['gap']) : '—' }}</td>
            <td class="ss-cell-notes">
              @if($r['differs'])<span class="ss-flag warn">Sources disagree</span>@endif
              @if($r['tally'] !== null && $r['manual'] === null)<span class="ss-flag bad">Not in Sales Report</span>@endif
              @if($r['tally'] !== null && $r['setu'] === null && $setuHasMonth)<span class="ss-flag bad">Not in Setu</span>@endif
              @if($r['tally'] === null)<span class="ss-flag info">Not in Tally</span>@endif
              @if(!$r['differs'] && $r['tally'] !== null && $r['manual'] !== null && ($r['setu'] !== null || !$setuHasMonth))<span class="ss-flag ok">Agree</span>@endif
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted py-5">No customers match these filters for {{ $monthLabel }}.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="ss-pager">
    <div class="small text-muted">Showing {{ $paginator->firstItem() ?? 0 }}&ndash;{{ $paginator->lastItem() ?? 0 }} of {{ number_format($paginator->total()) }} customers</div>
    {{ $paginator->links() }}
  </div>
</div>

<div class="ss-card ss-notes">
  <i class="bx bx-info-circle"></i>
  <div>
    <p>
      All amounts are ₹ Lakhs. Customers are matched across the three sources by their normalised name (the same rule as the Verified / Similar Match badges).
      A gap under ₹{{ number_format($tolerance * 100000) }} is treated as rounding. Tally's invoice export carries no credit notes or returns, so a customer with returns will show Tally
      higher than Setu, which nets them off.
    </p>
    <p>
      <strong>Our own branches:</strong> stock moved between Hi Tech's own branches is not a customer sale. The Tally export flags those invoices as branch transfers and they are left out
      of the Tally column, but Setu lists the branch ledgers (e.g. <em>Hi Tech International (Plot No. 18/6)</em>) as customers with sales &mdash; which is why Setu reads higher than
      both Tally and the manual Sales Report. <strong>Export customers</strong> are billed in Tally to "TO THE ORDER OF &hellip;" (a bank or consignee), not to the buyer, so they cannot be
      matched to the Sales Report by name yet.
    </p>
  </div>
</div>

</div>

<script>
// Phones: month / status / sort / rows sit behind the slider button.
(function () {
    var t = document.querySelector('#ssPage .ss-toggle-filters');
    if (t) t.addEventListener('click', function () { var on = t.closest('.ss-toolbar').classList.toggle('open'); t.setAttribute('aria-expanded', on ? 'true' : 'false'); });
})();

// The banner figures count up once (the printed value is the real one and is put back at the end).
(function () {
    if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    document.querySelectorAll('#ssPage .ss-kpi [data-count]').forEach(function (el) {
        const end = parseFloat(el.dataset.count), d = parseInt(el.dataset.decimals || '0', 10), final = el.textContent;
        if (!isFinite(end) || end === 0) return;
        const t0 = Date.now(), dur = 900;
        (function step() {
            const p = Math.min(1, (Date.now() - t0) / dur), e = 1 - Math.pow(1 - p, 3);
            el.textContent = p < 1 ? (end * e).toLocaleString('en-IN', { minimumFractionDigits: d, maximumFractionDigits: d }) : final;
            if (p < 1) setTimeout(step, 30);
        })();
    });
})();
</script>
@endsection
