@extends('tenant.sales-sources.layout')

@section('tab')
@php $inr = fn($v) => \App\Support\Fmt::inr($v, 0); @endphp

<form method="GET" action="{{ route('sales-sources.unmapped') }}" class="hitech-card-white p-3 mb-4">
  <div class="row g-2 align-items-end">
    <div class="col-12 col-md-8">
      <label class="form-label small fw-bold mb-1">Search</label>
      <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Customer name, as Tally has it">
    </div>
    <div class="col-12 col-md-4 d-flex gap-2">
      <button class="btn btn-primary btn-sm flex-grow-1"><i class="bx bx-search"></i> Apply</button>
      <a href="{{ route('sales-sources.unmapped') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
    </div>
  </div>
</form>

<div class="row g-3 mb-3">
  <div class="col-12 col-sm-6 col-lg-3"><div class="ss-stat"><div class="lbl">Not in CRM</div><div class="val text-danger">{{ number_format($rows->total()) }}</div><div class="ss-sub">of {{ number_format($universe) }} real customers ({{ number_format($mapped) }} matched)</div></div></div>
  <div class="col-12 col-sm-6 col-lg-3"><div class="ss-stat"><div class="lbl">Sales value unmapped</div><div class="val">₹{{ $inr($unmappedValue) }}</div><div class="ss-sub">no salesperson / customer-care / finance contact on file</div></div></div>
</div>

<div class="hitech-card-white overflow-hidden mb-3">
  <div class="table-responsive">
    <table class="table table-sm table-hover ss-table mb-0">
      <thead class="table-light">
        <tr>
          <th style="min-width:260px;">Customer (as Tally has it)</th><th class="ss-num">Invoices</th>
          <th>First sale</th><th>Last sale</th><th class="ss-num">Total (₹)</th><th style="min-width:220px;">Possible CRM match</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $r)
          <tr>
            <td class="fw-medium">{{ $r['name'] }}</td>
            <td class="ss-num">{{ number_format($r['invoices']) }}</td>
            <td class="text-nowrap">{{ \Carbon\Carbon::parse($r['first_date'])->format('d M Y') }}</td>
            <td class="text-nowrap">{{ \Carbon\Carbon::parse($r['last_date'])->format('d M Y') }}</td>
            <td class="ss-num fw-bold">{{ $inr($r['total']) }}</td>
            <td>
              @if($r['near'])
                <a href="{{ route('crm-clients.show', $r['near']['id']) }}" class="ss-flag info text-decoration-none">{{ $r['near']['score'] }}% &middot; {{ \Illuminate\Support\Str::limit($r['near']['name'], 32) }}</a>
              @else
                <span class="text-muted small">No close match &mdash; needs a new CRM record</span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted py-5">Every invoiced customer for this search is matched to a CRM record.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="p-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="small text-muted">Showing {{ $rows->firstItem() ?? 0 }}&ndash;{{ $rows->lastItem() ?? 0 }} of {{ number_format($rows->total()) }}, ranked by sales value</div>
    {{ $rows->links() }}
  </div>
</div>

<p class="small text-muted mb-4">
  "Real customers" excludes branch transfers to your own locations. A match here means the customer's name, cleaned up the same way everywhere else in this app,
  is either identical to a CRM record or linked via the Sales Report &mdash; anything else is listed as unmapped, even if a similarly-named CRM record exists.
  The "possible CRM match" column is a suggestion to check, never applied automatically. Creating a new CRM record needs a CRM company code from the CRM system itself &mdash; this page can't generate one.
</p>
@endsection
