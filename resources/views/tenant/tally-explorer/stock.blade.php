@extends('tenant.tally-explorer.layout')

@section('tab')
<form method="GET" class="tx-card p-3 mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-12 col-md-4"><label class="form-label small fw-bold mb-1">Search item</label>
      <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Item name"></div>
    <div class="col-8 col-md-4"><label class="form-label small fw-bold mb-1">Stock group</label>
      <select name="group" class="form-select form-select-sm">
        <option value="">All groups</option>
        @foreach($groups as $g)<option value="{{ $g }}" @selected($group === $g)>{{ $g }}</option>@endforeach
      </select></div>
    <div class="col-4 col-md-2 pb-1"><div class="form-check mt-3"><input type="hidden" name="in_stock" value="0"><input class="form-check-input" type="checkbox" name="in_stock" value="1" id="inStock" @checked($onlyStock)><label class="form-check-label small" for="inStock">In stock only</label></div></div>
    <div class="col-12 col-md-2 d-flex gap-2">
      <button class="btn btn-primary btn-sm flex-grow-1"><i class="bx bx-search"></i> Apply</button>
      <a href="{{ route('tally-explorer.stock') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
    </div>
  </div>
</form>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-4"><div class="tx-stat"><div class="lbl">Items</div><div class="val">{{ number_format($stats['items']) }}</div></div></div>
  <div class="col-6 col-lg-4"><div class="tx-stat"><div class="lbl">With stock in hand</div><div class="val">{{ number_format($stats['with_stock']) }}</div></div></div>
  <div class="col-6 col-lg-4"><div class="tx-stat"><div class="lbl">Closing stock value</div><div class="val">₹{{ number_format($stats['value'], 0) }}</div></div></div>
</div>

<div class="tx-card overflow-hidden mb-3">
  <div class="table-responsive">
    <table class="table table-sm table-hover tx-table">
      <thead class="table-light"><tr>
        <th>Item</th><th>Group</th><th>Category</th><th>Unit</th><th class="tx-num">Closing qty</th><th class="tx-num">Rate</th><th class="tx-num">Value (₹)</th><th class="tx-num">GST%</th>
      </tr></thead>
      <tbody>
      @forelse($rows as $r)
        <tr>
          <td class="fw-semibold"><a href="{{ route('tally-explorer.items.show', ['name' => $r->name]) }}" class="text-decoration-none" style="color:#4338ca;">{{ $r->name }}</a></td>
          <td class="tx-sub">{{ $r->stock_group ?: '—' }}</td>
          <td class="tx-sub">{{ $r->category ?: '—' }}</td>
          <td class="tx-sub">{{ $r->base_unit ?: '—' }}</td>
          <td class="tx-num">{{ $r->closing_qty !== null ? number_format((float) $r->closing_qty, 3) : '—' }}<span class="tx-sub"> {{ $r->closing_unit }}</span></td>
          <td class="tx-num">{{ $r->closing_rate !== null ? number_format((float) $r->closing_rate, 2) : '—' }}</td>
          <td class="tx-num fw-semibold">{{ $r->closing_value !== null ? number_format((float) $r->closing_value, 0) : '—' }}</td>
          <td class="tx-num">{{ $r->gst_rate !== null ? number_format((float) $r->gst_rate, 1) : '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="8" class="text-center text-muted py-5">No stock items match.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>
{{ $rows->links() }}
@endsection
