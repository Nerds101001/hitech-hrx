@extends('tenant.sales-sources.layout')

@section('tab')
@php $inr = fn($v) => \App\Support\Fmt::inr($v); @endphp

<form method="GET" action="{{ route('sales-sources.orders') }}" class="hitech-card-white p-3 mb-4">
  <div class="row g-2 align-items-end">
    <div class="col-12 col-md-5">
      <label class="form-label small fw-bold mb-1">Search</label>
      <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Party, order no. or Tally party code">
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label small fw-bold mb-1">Status</label>
      <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">All</option>
        @foreach($statuses as $s)<option value="{{ $s }}" @selected($status === $s)>{{ $s }}</option>@endforeach
      </select>
    </div>
    <div class="col-6 col-md-4 d-flex gap-2">
      <button class="btn btn-primary btn-sm flex-grow-1"><i class="bx bx-search"></i> Apply</button>
      <a href="{{ route('sales-sources.orders') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
    </div>
  </div>
</form>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3"><div class="ss-stat"><div class="lbl">Orders</div><div class="val">{{ number_format($totals->orders) }}</div><div class="ss-sub">{{ number_format($totals->n) }} order lines</div></div></div>
  <div class="col-6 col-md-3"><div class="ss-stat"><div class="lbl">Order value</div><div class="val">₹{{ $inr($totals->amount) }}</div></div></div>
</div>

<div class="hitech-card-white overflow-hidden mb-3">
  <div class="table-responsive">
    <table class="table table-sm table-hover ss-table mb-0">
      <thead class="table-light">
        <tr><th>Order date</th><th>Order no.</th><th style="min-width:260px;">Party</th><th>Status</th><th class="ss-num">Qty</th><th class="ss-num">Amount (₹)</th></tr>
      </thead>
      <tbody>
        @forelse($orders as $o)
          <tr>
            <td class="text-nowrap">{{ $o->order_date?->format('d M Y') }}</td>
            <td class="text-nowrap fw-semibold">{{ $o->order_no }}</td>
            <td>{{ $o->party_name }}@if($o->party_code)<div class="ss-sub">Tally code {{ $o->party_code }}</div>@endif</td>
            <td>@if($o->status)<span class="ss-flag {{ strtolower($o->status) === 'ok' ? 'ok' : 'warn' }}">{{ $o->status }}</span>@endif</td>
            <td class="ss-num">{{ $o->quantity !== null ? rtrim(rtrim(number_format($o->quantity, 3), '0'), '.') : '' }} {{ $o->unit }}</td>
            <td class="ss-num fw-semibold">{{ $inr($o->amount) }}</td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted py-5">No sale orders match these filters.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="p-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="small text-muted">Showing {{ $orders->firstItem() ?? 0 }}&ndash;{{ $orders->lastItem() ?? 0 }} of {{ number_format($orders->total()) }}</div>
    {{ $orders->links() }}
  </div>
</div>
@endsection
