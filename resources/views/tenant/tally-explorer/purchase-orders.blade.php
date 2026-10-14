@extends('tenant.tally-explorer.layout')

@section('tab')
<form method="GET" class="tx-card p-3 mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-12 col-md-5"><label class="form-label small fw-bold mb-1">Search</label>
      <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Order no, supplier or item"></div>
    <div class="col-6 col-md-2"><label class="form-label small fw-bold mb-1">From</label><input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm"></div>
    <div class="col-6 col-md-2"><label class="form-label small fw-bold mb-1">To</label><input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm"></div>
    <div class="col-12 col-md-3 d-flex gap-2">
      <button class="btn btn-primary btn-sm flex-grow-1"><i class="bx bx-search"></i> Apply</button>
      <a href="{{ route('tally-explorer.purchase-orders') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
    </div>
  </div>
</form>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-4"><div class="tx-stat"><div class="lbl">Order lines</div><div class="val">{{ number_format($stats['lines']) }}</div></div></div>
  <div class="col-6 col-lg-4"><div class="tx-stat"><div class="lbl">Distinct orders</div><div class="val">{{ number_format($stats['orders']) }}</div></div></div>
  <div class="col-6 col-lg-4"><div class="tx-stat"><div class="lbl">Total value</div><div class="val">₹{{ number_format($stats['amount'], 0) }}</div></div></div>
</div>

<div class="tx-card overflow-hidden mb-3">
  <div class="table-responsive">
    <table class="table table-sm table-hover tx-table">
      <thead class="table-light"><tr>
        <th>Date</th><th>Order No</th><th>Supplier</th><th>Item</th><th class="tx-num">Qty</th><th>Unit</th><th class="tx-num">Amount (₹)</th>
      </tr></thead>
      <tbody>
      @forelse($rows as $r)
        <tr>
          <td class="tx-sub">{{ $r->order_date ? \Illuminate\Support\Carbon::parse($r->order_date)->format('d M Y') : '—' }}</td>
          <td class="fw-semibold">{{ $r->order_no }}</td>
          <td>{{ $r->party_name ?: '—' }}</td>
          <td>{{ $r->item_name ?: '—' }}</td>
          <td class="tx-num">{{ $r->quantity !== null ? number_format((float) $r->quantity, 3) : '—' }}</td>
          <td class="tx-sub">{{ $r->unit }}</td>
          <td class="tx-num fw-semibold">{{ number_format((float) $r->amount, 2) }}</td>
        </tr>
      @empty
        <tr><td colspan="7" class="text-center text-muted py-5">No purchase orders match.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>
{{ $rows->links() }}
@endsection
