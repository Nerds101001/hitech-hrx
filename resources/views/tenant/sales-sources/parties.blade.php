@extends('tenant.sales-sources.layout')

@section('tab')
@php $inr = fn($v) => \App\Support\Fmt::inr($v, 0); $canEditContact = auth()->user()->hasRole(['admin', 'Admin', 'super_admin'])
    && \App\Http\Middleware\RestrictTallyAccess::permits(auth()->user())
    && (string) config('services.tally.write_gateway_url') !== '' && (string) config('services.tally.write_gateway_company') !== ''; // no pencil where writing to Tally is not set up (the live server can never reach Tally) @endphp

<form method="GET" action="{{ route('sales-sources.parties') }}" class="hitech-card-white p-3 mb-4">
  <div class="row g-2 align-items-end">
    <div class="col-12 col-md-4">
      <label class="form-label small fw-bold mb-1">Search</label>
      <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Customer name">
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label small fw-bold mb-1">Show</label>
      <select name="filter" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">All parties</option>
        <option value="credit" @selected($filter === 'credit')>With a credit limit</option>
        <option value="no_credit" @selected($filter === 'no_credit')>No credit limit set</option>
        <option value="gstin" @selected($filter === 'gstin')>With a GSTIN</option>
        <option value="no_gstin" @selected($filter === 'no_gstin')>Missing GSTIN</option>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label small fw-bold mb-1">Sort by</label>
      <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="credit_limit" @selected($sort !== 'name')>Credit limit</option>
        <option value="name" @selected($sort === 'name')>Name</option>
      </select>
    </div>
    <div class="col-12 col-md-3 d-flex gap-2">
      <button class="btn btn-primary btn-sm flex-grow-1"><i class="bx bx-search"></i> Apply</button>
      <a href="{{ route('sales-sources.parties') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
    </div>
  </div>
</form>

<div class="row g-3 mb-3">
  <div class="col-12 col-sm-6 col-lg-3"><div class="ss-stat"><div class="lbl">Customers synced</div><div class="val">{{ number_format($totals->n) }}</div><div class="ss-sub">from Tally's ledger master</div></div></div>
  <div class="col-12 col-sm-6 col-lg-3"><div class="ss-stat"><div class="lbl">Total credit extended</div><div class="val">₹{{ $inr($totals->credit_sum) }}</div><div class="ss-sub">{{ number_format($totals->with_credit) }} with a limit set</div></div></div>
  <div class="col-12 col-sm-6 col-lg-3"><div class="ss-stat"><div class="lbl">With GSTIN</div><div class="val">{{ number_format($totals->with_gstin) }}</div><div class="ss-sub">{{ $totals->n ? number_format(100 * ($totals->n - $totals->with_gstin) / $totals->n, 0) : 0 }}% missing</div></div></div>
  <div class="col-12 col-sm-6 col-lg-3"><div class="ss-stat"><div class="lbl">With a contact person</div><div class="val">{{ number_format($totals->with_contact) }}</div></div></div>
</div>

<div class="hitech-card-white overflow-hidden mb-3">
  <div class="table-responsive">
    <table class="table table-sm table-hover ss-table mb-0">
      <thead class="table-light">
        <tr>
          <th style="min-width:260px;">Customer</th><th class="ss-num">Credit limit (₹)</th>
          <th>GSTIN</th><th>PAN</th><th>Contact</th><th>Ledger group</th>@if($canEditContact)<th></th>@endif
        </tr>
      </thead>
      <tbody>
        @forelse($parties as $p)
          <tr>
            <td>
              <a href="{{ route('sales-sources.company', ['name' => $p->party_name]) }}" class="fw-semibold text-decoration-none">{{ $p->party_name }}</a>
              @if($codes[$p->normalized_name] ?? null)<div class="ss-sub">Tally code {{ $codes[$p->normalized_name] }}</div>@endif
            </td>
            <td class="ss-num fw-semibold">{{ $p->credit_limit !== null ? $inr($p->credit_limit) : '—' }}</td>
            <td>{{ $p->gstin ?: '—' }}</td>
            <td>{{ $p->pan ?: '—' }}</td>
            <td class="tc-contact-cell">
              @if($p->contact_person || $p->contact_mobile)
                {{ $p->contact_person ?: '—' }}
                @if($p->contact_mobile)<div class="ss-sub">{{ $p->contact_mobile }}</div>@endif
              @else
                —
              @endif
            </td>
            <td>{{ $p->ledger_group ?: '—' }}</td>
            @if($canEditContact)
              <td class="text-end">
                <button type="button" class="btn btn-sm btn-icon btn-outline-secondary tc-edit-btn" title="Update contact in Tally"
                  data-normalized-name="{{ $p->normalized_name }}" data-party-name="{{ $p->party_name }}"
                  data-contact-person="{{ $p->contact_person }}" data-contact-mobile="{{ $p->contact_mobile }}">
                  <i class="bx bx-edit-alt"></i>
                </button>
              </td>
            @endif
          </tr>
        @empty
          <tr><td colspan="{{ $canEditContact ? 7 : 6 }}" class="text-center text-muted py-5">No parties match these filters.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="p-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="small text-muted">Showing {{ $parties->firstItem() ?? 0 }}&ndash;{{ $parties->lastItem() ?? 0 }} of {{ number_format($parties->total()) }}</div>
    {{ $parties->links() }}
  </div>
</div>

<p class="small text-muted mb-4">
  Pulled straight from Tally's own ledger master (<code>hrx:tally-push-parties</code>), not derived from invoices &mdash; a customer can have Tally sales but no row here yet if this sync hasn't run since they were added, or a credit limit/GSTIN simply
  because it was never entered in Tally. Credit limit here is what Tally's ledger master currently holds; Tally does not keep a change history for it (checked directly), so no "last changed" or "who changed it" is shown.
  @if($canEditContact)The pencil icon is the one place this app writes back to Tally &mdash; it only ever changes that one customer's contact person and mobile, never anything financial, and always shows exactly what will change before it sends anything.@endif
</p>

@if($canEditContact)
  @include('tenant.sales-sources.partials.contact-update-modal')
@endif
@endsection
