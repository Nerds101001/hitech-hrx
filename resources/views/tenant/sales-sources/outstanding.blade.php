@extends('tenant.sales-sources.layout')

@section('tab')
@php $inr = fn($v) => \App\Support\Fmt::inr($v, 0); $dash = fn($v) => ((float) $v) == 0.0 ? '—' : \App\Support\Fmt::inr((float) $v, 0); @endphp

<form method="GET" action="{{ route('sales-sources.outstanding') }}" class="hitech-card-white p-3 mb-4">
  <div class="row g-2 align-items-end">
    <div class="col-12 col-md-3">
      <label class="form-label small fw-bold mb-1">Ledger group</label>
      <select name="group" class="form-select form-select-sm" onchange="this.form.submit()">
        @foreach($groups as $name => $count)<option value="{{ $name }}" @selected($group === $name)>{{ $name }} ({{ number_format($count) }})</option>@endforeach
      </select>
    </div>
    <div class="col-12 col-md-5">
      <label class="form-label small fw-bold mb-1">Search</label>
      <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Party name or Tally party code">
    </div>
    <div class="col-12 col-md-4 d-flex gap-2">
      <button class="btn btn-primary btn-sm flex-grow-1"><i class="bx bx-search"></i> Apply</button>
      <a href="{{ route('sales-sources.outstanding') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
    </div>
  </div>
</form>

<div class="row g-3 mb-3">
  <div class="col-12 col-sm-6 col-lg-3"><div class="ss-stat"><div class="lbl">Total outstanding</div><div class="val">₹{{ $inr($totals->total) }}</div><div class="ss-sub">{{ number_format($totals->bills) }} open bills</div></div></div>
  <div class="col-12 col-sm-6 col-lg-3"><div class="ss-stat"><div class="lbl">Older than 90 days</div><div class="val text-danger">₹{{ $inr($totals->over90) }}</div><div class="ss-sub">{{ $totals->total != 0 ? round(100 * $totals->over90 / $totals->total, 1) : 0 }}% of the total</div></div></div>
  <div class="col-12 col-sm-6 col-lg-3"><div class="ss-stat"><div class="lbl">Parties</div><div class="val">{{ number_format($totals->parties) }}</div></div></div>
</div>

<div class="hitech-card-white overflow-hidden mb-3">
  <div class="table-responsive">
    <table class="table table-sm table-hover ss-table mb-0">
      <thead class="table-light">
        <tr>
          <th style="min-width:260px;">Party</th><th class="ss-num">Bills</th>
          <th class="ss-num">0&ndash;30 d</th><th class="ss-num">31&ndash;60 d</th><th class="ss-num">61&ndash;90 d</th><th class="ss-num">&gt; 90 d</th>
          <th class="ss-num">Total (₹)</th><th class="ss-num">Oldest (days)</th><th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($parties as $p)
          <tr>
            <td>{{ $p->party_name }}@if($p->party_code)<div class="ss-sub">Tally code {{ $p->party_code }}</div>@endif</td>
            <td class="ss-num">{{ $p->bills }}</td>
            <td class="ss-num">{{ $dash($p->a0) }}</td>
            <td class="ss-num">{{ $dash($p->a1) }}</td>
            <td class="ss-num">{{ $dash($p->a2) }}</td>
            <td class="ss-num {{ $p->a3 > 0 ? 'text-danger fw-bold' : '' }}">{{ $dash($p->a3) }}</td>
            <td class="ss-num fw-bold">{{ $inr($p->total) }}</td>
            <td class="ss-num">{{ $p->oldest }}</td>
            <td><button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 js-bills" data-key="{{ $p->pkey }}" data-name="{{ $p->party_name }}"><i class="bx bx-list-ul"></i> Bills</button></td>
          </tr>
        @empty
          <tr><td colspan="9" class="text-center text-muted py-5">Nothing outstanding for these filters.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="p-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="small text-muted">Showing {{ $parties->firstItem() ?? 0 }}&ndash;{{ $parties->lastItem() ?? 0 }} of {{ number_format($parties->total()) }} parties</div>
    {{ $parties->links() }}
  </div>
</div>

<p class="small text-muted mb-4">
  Ageing is counted from each bill's date to <strong>today</strong>, not to the date of the export. A negative amount is Tally's "(-)" &mdash; the opposite side of the ledger
  (for a customer, an advance or a credit balance) &mdash; and reduces that party's total. Credit periods are only recorded on some bills, so they are shown in the bill list rather than used here.
</p>
@endsection

@section('tab-modals')
<div class="modal fade" id="billsModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header"><h6 class="modal-title mb-0" id="billsTitle">Open bills</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" id="billsBody"></div>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.js-bills');
    if (!btn) return;
    const body = document.getElementById('billsBody');
    document.getElementById('billsTitle').textContent = btn.dataset.name;
    body.innerHTML = '<div class="text-muted small">Loading…</div>';
    new bootstrap.Modal(document.getElementById('billsModal')).show();
    const url = @json(route('sales-sources.outstanding-bills')) + '?group=' + encodeURIComponent(@json($group)) + '&key=' + encodeURIComponent(btn.dataset.key);
    fetch(url, { headers: { 'Accept': 'application/json' } }).then(r => r.json()).then(d => {
        const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
        const fmt = n => n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        if (!d.bills.length) { body.innerHTML = '<div class="text-muted small">No bills.</div>'; return; }
        let html = '<table class="table table-sm"><thead><tr><th>Bill</th><th>Date</th><th class="text-end">Age (days)</th><th>Credit period</th><th class="text-end">Amount (₹)</th></tr></thead><tbody>';
        d.bills.forEach(b => { html += '<tr><td>' + esc(b.bill_ref) + '</td><td>' + esc(b.date) + '</td><td class="text-end' + (b.age > 90 ? ' text-danger fw-bold' : '') + '">' + b.age + '</td><td>' + (b.credit_days ? b.credit_days + ' days' : '—') + '</td><td class="text-end">' + fmt(b.amount) + '</td></tr>'; });
        body.innerHTML = html + '</tbody></table>';
    }).catch(() => { body.innerHTML = '<div class="text-danger small">Could not load the bills.</div>'; });
});
</script>
@endsection
