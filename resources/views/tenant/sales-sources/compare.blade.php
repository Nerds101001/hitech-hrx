@extends('tenant.sales-sources.layout')

@section('tab')
@php
    $L = fn($v) => \App\Support\Fmt::lakh($v);
    $monthLabel = \Carbon\Carbon::createFromFormat('!Y-m', $month)->format('F Y');
    $setuHasMonth = in_array($month, $setuMonthsCached, true);
    $qs = fn(array $extra) => route('sales-sources.index', array_merge(request()->except('page'), $extra));
@endphp

<form method="GET" action="{{ route('sales-sources.index') }}" class="hitech-card-white p-3 mb-4">
  <div class="row g-2 align-items-end">
    <div class="col-6 col-md-2">
      <label class="form-label small fw-bold mb-1">Month</label>
      <select name="m" class="form-select form-select-sm" onchange="this.form.submit()">
        @foreach($months as $m)
          <option value="{{ $m }}" @selected($m === $month)>{{ \Carbon\Carbon::createFromFormat('!Y-m', $m)->format('M Y') }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-6 col-md-2">
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
    <div class="col-6 col-md-2">
      <label class="form-label small fw-bold mb-1">Sort by</label>
      <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="amount" @selected($sort === 'amount')>Largest sale</option>
        <option value="gap" @selected($sort === 'gap')>Biggest gap</option>
        <option value="name" @selected($sort === 'name')>Name</option>
      </select>
    </div>
    <div class="col-6 col-md-1">
      <label class="form-label small fw-bold mb-1">Rows</label>
      <select name="per" class="form-select form-select-sm" onchange="this.form.submit()">
        @foreach([25, 50, 100, 200] as $n)<option value="{{ $n }}" @selected($per === $n)>{{ $n }}</option>@endforeach
      </select>
    </div>
    <div class="col-12 col-md-2 d-flex gap-2">
      <button class="btn btn-primary btn-sm flex-grow-1"><i class="bx bx-search"></i> Apply</button>
      <a href="{{ route('sales-sources.index', ['m' => $month]) }}" class="btn btn-outline-secondary btn-sm">Reset</a>
    </div>
  </div>
</form>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3">
    <div class="ss-stat">
      <div class="lbl">Manual Sales Report &middot; {{ $monthLabel }}</div>
      <div class="val">₹{{ $L($totals['manual']) }} L</div>
      <div class="ss-sub">Sales Report rows linked to a customer</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="ss-stat">
      <div class="lbl">Setu &middot; {{ $monthLabel }}</div>
      <div class="val">@if($setuHasMonth)₹{{ $L($totals['setu']) }} L @else <span class="text-muted">not cached</span>@endif</div>
      <div class="ss-sub">Cache holds {{ count($setuMonthsCached) ? implode(', ', array_map(fn($m) => \Carbon\Carbon::createFromFormat('!Y-m', $m)->format('M y'), $setuMonthsCached)) : 'nothing' }}@if($setuSyncedAt) &middot; synced {{ \Carbon\Carbon::parse($setuSyncedAt)->format('d M') }}@endif</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="ss-stat">
      <div class="lbl">Tally &middot; {{ $monthLabel }}</div>
      <div class="val">₹{{ $L($totals['tally']) }} L</div>
      <div class="ss-sub">Invoice value incl. GST, branch transfers excluded</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="ss-stat">
      <div class="lbl">Customers &middot; disagreeing</div>
      <div class="val">{{ number_format($totals['customers']) }} <span class="text-warning" style="font-size:1rem;">/ {{ number_format($totals['differs']) }}</span></div>
      <div class="ss-sub">
        <a href="{{ $qs(['status' => 'no_manual']) }}">{{ $totals['no_manual'] }} not in Sales Report</a> &middot;
        <a href="{{ $qs(['status' => 'no_setu']) }}">{{ $totals['no_setu'] }} not in Setu</a> &middot;
        <a href="{{ $qs(['status' => 'no_tally']) }}">{{ $totals['no_tally'] }} not in Tally</a>
      </div>
    </div>
  </div>
</div>

@if($setuBehindDays > 0)
  <div class="alert alert-warning py-2 small mb-3">
    <i class="bx bx-time-five me-1"></i>
    Setu was last synced on <strong>{{ \Carbon\Carbon::parse($setuSyncedAt)->format('d M') }}</strong> but the Tally data runs to
    <strong>{{ \Carbon\Carbon::parse($tallyLast)->format('d M') }}</strong>. {{ $monthLabel }} is still in progress, so Setu will read
    low here until it is re-synced.
  </div>
@endif

<div class="hitech-card-white overflow-hidden mb-3">
  <div class="table-responsive">
    <table class="table table-sm table-hover ss-table mb-0">
      <thead class="table-light">
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
            <td>
              <div class="fw-semibold">{{ $r['name'] }}</div>
              <div class="ss-sub">
                @if($r['party_code'])Tally code {{ $r['party_code'] }}@endif
                @if($r['sps_tally']) &middot; {{ $r['sps_tally'] }}@elseif($r['sps_manual']) &middot; {{ $r['sps_manual'] }}@endif
                @if($r['invoices']) &middot; {{ $r['invoices'] }} invoice{{ $r['invoices'] > 1 ? 's' : '' }}@endif
              </div>
            </td>
            <td class="ss-num">{{ $L($r['manual']) }}</td>
            <td class="ss-num">{{ $L($r['setu']) }}</td>
            <td class="ss-num fw-semibold">{{ $L($r['tally']) }}</td>
            <td class="ss-num {{ $r['differs'] ? 'text-danger fw-bold' : 'text-muted' }}">{{ $r['gap'] > 0 ? $L($r['gap']) : '—' }}</td>
            <td>
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
  <div class="p-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="small text-muted">Showing {{ $paginator->firstItem() ?? 0 }}&ndash;{{ $paginator->lastItem() ?? 0 }} of {{ number_format($paginator->total()) }} customers</div>
    {{ $paginator->links() }}
  </div>
</div>

<p class="small text-muted mb-4">
  All amounts are ₹ Lakhs. Customers are matched across the three sources by their normalised name (the same rule as the Verified / Similar Match badges).
  A gap under ₹{{ number_format($tolerance * 100000) }} is treated as rounding. Tally's invoice export carries no credit notes or returns, so a customer with returns will show Tally
  higher than Setu, which nets them off.
</p>
<p class="small text-muted mb-4">
  <strong>Our own branches:</strong> stock moved between Hi Tech's own branches is not a customer sale. The Tally export flags those invoices as branch transfers and they are left out
  of the Tally column, but Setu lists the branch ledgers (e.g. <em>Hi Tech International (Plot No. 18/6)</em>) as customers with sales &mdash; which is why Setu reads higher than
  both Tally and the manual Sales Report. <strong>Export customers</strong> are billed in Tally to "TO THE ORDER OF &hellip;" (a bank or consignee), not to the buyer, so they cannot be
  matched to the Sales Report by name yet.
</p>
@endsection
