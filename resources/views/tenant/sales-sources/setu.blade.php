@extends('tenant.sales-sources.layout')

@section('tab')
@php
    $L = fn($v) => \App\Support\Fmt::lakh($v);
    $mLabel = fn($m) => \Carbon\Carbon::createFromFormat('!Y-m', $m)->format('M y');
@endphp

<form method="GET" action="{{ route('sales-sources.setu') }}" class="hitech-card-white p-3 mb-4">
  <div class="row g-2 align-items-end">
    <div class="col-12 col-md-6">
      <label class="form-label small fw-bold mb-1">Search</label>
      <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Customer name (as Setu / Tally names it)">
    </div>
    <div class="col-12 col-md-3 d-flex gap-2">
      <button class="btn btn-primary btn-sm flex-grow-1"><i class="bx bx-search"></i> Apply</button>
      <a href="{{ route('sales-sources.setu') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
    </div>
  </div>
</form>

<div class="hitech-card-white overflow-hidden mb-3">
  <div class="table-responsive">
    <table class="table table-sm table-hover ss-table mb-0">
      <thead class="table-light">
        <tr>
          <th style="min-width:280px;">Customer (as Setu names it)</th>
          @foreach($months as $m)<th class="ss-num">{{ $mLabel($m) }} (₹ L)</th>@endforeach
          <th class="ss-num">Total (₹ L)</th><th>Also in</th><th>Last synced</th>
        </tr>
      </thead>
      <tbody>
        @forelse($customers as $c)
          @php $h = $hrx[$c->normalized_name] ?? null; $code = $inTally[$c->normalized_name] ?? null; @endphp
          <tr>
            <td class="fw-semibold">{{ $c->name }}</td>
            @foreach($months as $m)
              @php $v = ($c->recent_months ?? [])[$m] ?? null; @endphp
              <td class="ss-num {{ $v === null ? 'text-muted' : '' }}">{{ $v === null ? '—' : $L((float) $v) }}</td>
            @endforeach
            <td class="ss-num fw-bold">{{ $L((float) $c->recent_total) }}</td>
            <td>
              @if($code)<span class="ss-flag ok">Tally {{ $code }}</span>@else<span class="ss-flag warn">Not in Tally</span>@endif
              @if($h)<span class="ss-flag {{ $h->setu_match_status === 'verified' ? 'ok' : 'info' }}">{{ $h->setu_match_status === 'verified' ? 'Verified' : 'Sales Report' }}</span>@endif
            </td>
            <td class="small">{{ $c->last_synced_at ? \Carbon\Carbon::parse($c->last_synced_at)->format('d M Y') : '—' }}</td>
          </tr>
        @empty
          <tr><td colspan="{{ 4 + count($months) }}" class="text-center text-muted py-5">No Setu customers match.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="p-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="small text-muted">Showing {{ $customers->firstItem() ?? 0 }}&ndash;{{ $customers->lastItem() ?? 0 }} of {{ number_format($customers->total()) }} customers</div>
    {{ $customers->links() }}
  </div>
</div>

<p class="small text-muted mb-4">
  This is the local Setu cache (<code>hrx:sync-setu-customers</code>), which only keeps the last few months and only customers with sales. Amounts are ₹ Lakhs, GST included.
</p>
@endsection
