@extends('tenant.sales-sources.layout')

@section('tab')
@php $inr = fn($v) => \App\Support\Fmt::inr($v, 0); @endphp

<form method="GET" action="{{ route('sales-sources.finance') }}" class="hitech-card-white p-3 mb-4">
  <div class="row g-2 align-items-end">
    <div class="col-12 col-md-6">
      <label class="form-label small fw-bold mb-1">Search</label>
      <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Company name or finance person">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label small fw-bold mb-1">Interest rate (% p.a.)</label>
      <input type="number" name="rate" value="{{ rtrim(rtrim(number_format($rate, 2), '0'), '.') }}" step="0.5" min="0" max="100" class="form-control form-control-sm">
    </div>
    <div class="col-12 col-md-4 d-flex gap-2">
      <button class="btn btn-primary btn-sm flex-grow-1"><i class="bx bx-search"></i> Apply</button>
      <a href="{{ route('sales-sources.finance') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
    </div>
  </div>
</form>

<div class="alert alert-warning py-2 small mb-3">
  <i class="bx bx-info-circle me-1"></i>
  The interest figure is an <strong>estimate</strong> at the rate above ({{ rtrim(rtrim(number_format($rate, 2), '0'), '.') }}% p.a., simple interest on each bill's own outstanding days) &mdash; Tally and HRX do not store an actual cost-of-capital rate anywhere, so this is not an accounted cost, just what carrying this receivable is costing at whatever rate you enter.
</div>

<div class="row g-3 mb-3">
  <div class="col-12 col-sm-6 col-lg-3"><div class="ss-stat"><div class="lbl">Total due</div><div class="val">₹{{ $inr($totals['due']) }}</div><div class="ss-sub">{{ number_format($totals['parties']) }} customers</div></div></div>
  <div class="col-12 col-sm-6 col-lg-3"><div class="ss-stat"><div class="lbl">Estimated interest cost</div><div class="val text-danger">₹{{ $inr($totals['interest']) }}</div><div class="ss-sub">at {{ rtrim(rtrim(number_format($rate, 2), '0'), '.') }}% p.a.</div></div></div>
  <div class="col-12 col-sm-6 col-lg-3"><div class="ss-stat"><div class="lbl">Over 90 days</div><div class="val">₹{{ $inr($totals['over90']) }}</div></div></div>
  <div class="col-12 col-sm-6 col-lg-3"><div class="ss-stat"><div class="lbl">No finance person assigned</div><div class="val text-danger">{{ number_format($totals['unassigned']) }}</div><div class="ss-sub">of {{ number_format($totals['parties']) }} customers</div></div></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-12 col-sm-6 col-lg-3"><div class="ss-stat"><div class="lbl">Collected, last 30 days</div><div class="val text-success">₹{{ $inr($totals['collected_30d']) }}</div><div class="ss-sub">actual Receipt vouchers from Tally</div></div></div>
  <div class="col-12 col-sm-6 col-lg-3"><div class="ss-stat"><div class="lbl">Collected this month</div><div class="val text-success">₹{{ $inr($totals['collected_mtd']) }}</div></div></div>
</div>

<div class="hitech-card-white overflow-hidden mb-3">
  <div class="table-responsive">
    <table class="table table-sm table-hover ss-table mb-0">
      <thead class="table-light">
        <tr>
          <th style="min-width:240px;">Company</th><th>Finance person</th>
          <th class="ss-num">0&ndash;30 d</th><th class="ss-num">31&ndash;60 d</th><th class="ss-num">61&ndash;90 d</th><th class="ss-num">&gt; 90 d</th>
          <th class="ss-num">Total due (₹)</th><th class="ss-num">Oldest (days)</th><th class="ss-num">Interest (₹)</th><th>Last payment</th>
        </tr>
      </thead>
      <tbody>
        @forelse($paged as $r)
          <tr>
            <td>
              <a href="{{ route('sales-sources.company', ['name' => $r['name']]) }}" class="fw-semibold text-decoration-none">{{ $r['name'] }}</a>
              @if($r['party_code'])<div class="ss-sub">Tally code {{ $r['party_code'] }}</div>@endif
            </td>
            <td>
              @if($r['finance_name'])
                {{ $r['finance_name'] }}
              @else
                <span class="ss-flag warn">Unassigned</span>
              @endif
            </td>
            <td class="ss-num">{{ $r['b0'] ? $inr($r['b0']) : '—' }}</td>
            <td class="ss-num">{{ $r['b1'] ? $inr($r['b1']) : '—' }}</td>
            <td class="ss-num">{{ $r['b2'] ? $inr($r['b2']) : '—' }}</td>
            <td class="ss-num {{ $r['b3'] > 0 ? 'text-danger fw-bold' : '' }}">{{ $r['b3'] ? $inr($r['b3']) : '—' }}</td>
            <td class="ss-num fw-bold">{{ $inr($r['due']) }}</td>
            <td class="ss-num">{{ $r['oldest'] }}</td>
            <td class="ss-num text-danger">{{ $inr($r['interest']) }}</td>
            <td>
              @if($r['last_payment_date'])
                {{ \Carbon\Carbon::parse($r['last_payment_date'])->format('d M Y') }}
                <div class="ss-sub {{ $r['days_since_payment'] > 60 ? 'text-danger' : '' }}">₹{{ $inr($r['last_payment_amount']) }} &middot; {{ $r['days_since_payment'] }}d ago</div>
              @else
                <span class="ss-flag warn">No receipt on record</span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="10" class="text-center text-muted py-5">Nothing outstanding for this search.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="p-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="small text-muted">Showing {{ $paged->firstItem() ?? 0 }}&ndash;{{ $paged->lastItem() ?? 0 }} of {{ number_format($paged->total()) }}, ranked by amount due</div>
    {{ $paged->links() }}
  </div>
</div>

<p class="small text-muted mb-4">
  Ageing is counted from each bill's date to today. "Finance person" comes from the CRM record's Finance role (Team tab), matched by company name &mdash; a customer with Tally dues but no CRM record at all won't show one either way.
  Only ledger group "Sundry Debtors" is shown (customer receivables), not payables to suppliers.
  "Last payment" is the most recent Receipt voucher Tally has recorded for that customer (pulled via the Tally API sync, not the older file import) &mdash; "No receipt on record" means none has been synced yet, not necessarily that the customer has never paid.
</p>
@endsection
