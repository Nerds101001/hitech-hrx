@extends('tenant.tally-explorer.layout')

@section('tab')
<form method="GET" class="tx-card p-3 mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-12 col-md-5"><label class="form-label small fw-bold mb-1">Search</label>
      <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Ledger name or GSTIN"></div>
    <div class="col-8 col-md-4"><label class="form-label small fw-bold mb-1">Group</label>
      <select name="group" class="form-select form-select-sm">
        <option value="">All groups</option>
        @foreach($groups as $g)<option value="{{ $g }}" @selected($group === $g)>{{ $g }}</option>@endforeach
      </select></div>
    <div class="col-4 col-md-3 d-flex gap-2">
      <button class="btn btn-primary btn-sm flex-grow-1"><i class="bx bx-search"></i> Apply</button>
      <a href="{{ route('tally-explorer.ledgers') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
    </div>
  </div>
</form>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-4"><div class="tx-stat"><div class="lbl">Ledgers</div><div class="val">{{ number_format($stats['ledgers']) }}</div></div></div>
  <div class="col-6 col-lg-4"><div class="tx-stat"><div class="lbl">With GSTIN</div><div class="val">{{ number_format($stats['with_gstin']) }}</div></div></div>
  <div class="col-6 col-lg-4"><div class="tx-stat"><div class="lbl">Net closing balance</div><div class="val">₹{{ number_format($stats['balance'], 0) }}</div></div></div>
</div>

<div class="tx-card overflow-hidden mb-3">
  <div class="table-responsive">
    <table class="table table-sm table-hover tx-table">
      <thead class="table-light"><tr>
        <th>Ledger</th><th>Group</th><th>Primary group</th><th class="tx-num">Closing (₹)</th><th class="tx-num">Credit limit</th><th>GSTIN</th><th>PAN</th><th>Contact</th>
      </tr></thead>
      <tbody>
      @forelse($rows as $r)
        <tr>
          <td class="fw-semibold"><a href="{{ route('tally-explorer.ledgers.show', ['name' => $r->name]) }}" class="text-decoration-none" style="color:#0f6b78;">{{ $r->name }}</a></td>
          <td class="tx-sub">{{ $r->ledger_group ?: '—' }}</td>
          <td><span class="tx-badge">{{ $r->primary_group ?: '—' }}</span></td>
          <td class="tx-num {{ (float) $r->closing_balance < 0 ? 'text-danger' : '' }}">{{ $r->closing_balance !== null ? number_format((float) $r->closing_balance, 0) : '—' }}</td>
          <td class="tx-num tx-sub">{{ $r->credit_limit ? number_format((float) $r->credit_limit, 0) : '—' }}</td>
          <td class="tx-sub font-monospace">{{ $r->gstin ?: '—' }}</td>
          <td class="tx-sub font-monospace">{{ $r->pan ?: '—' }}</td>
          <td class="tx-sub">{{ $r->contact_person ?: '' }}@if($r->contact_mobile)<br><span class="font-monospace">{{ $r->contact_mobile }}</span>@endif</td>
        </tr>
      @empty
        <tr><td colspan="8" class="text-center text-muted py-5">No ledgers match.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>
{{ $rows->links() }}
@endsection
