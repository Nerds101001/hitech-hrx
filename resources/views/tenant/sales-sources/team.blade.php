@extends('tenant.sales-sources.layout')

@section('tab')

<form method="GET" action="{{ route('sales-sources.team') }}" class="hitech-card-white p-3 mb-4">
  <div class="row g-2 align-items-end">
    <div class="col-12 col-md-8">
      <label class="form-label small fw-bold mb-1">Search</label>
      <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Employee name or email">
    </div>
    <div class="col-12 col-md-4 d-flex gap-2">
      <button class="btn btn-primary btn-sm flex-grow-1"><i class="bx bx-search"></i> Apply</button>
      <a href="{{ route('sales-sources.team') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
    </div>
  </div>
</form>

<div class="row g-3 mb-3">
  <div class="col-12 col-sm-6 col-lg-4"><div class="ss-stat"><div class="lbl">Employees with accounts assigned</div><div class="val">{{ number_format($totals['employees']) }}</div></div></div>
  <div class="col-12 col-sm-6 col-lg-4"><div class="ss-stat"><div class="lbl">CRM accounts with someone assigned</div><div class="val">{{ number_format($totals['accounts_with_any_role']) }}</div><div class="ss-sub">of {{ number_format($totals['accounts_total']) }} CRM records</div></div></div>
  <div class="col-12 col-sm-6 col-lg-4"><div class="ss-stat"><div class="lbl">Unassigned CRM accounts</div><div class="val text-danger">{{ number_format($totals['accounts_total'] - $totals['accounts_with_any_role']) }}</div><div class="ss-sub">no salesperson, ccare, new biz, billing or finance</div></div></div>
</div>

<div class="hitech-card-white overflow-hidden mb-3">
  <div class="table-responsive">
    <table class="table table-sm table-hover ss-table mb-0">
      <thead class="table-light">
        <tr>
          <th style="min-width:220px;">Employee</th>
          @foreach($roleCols as $label)<th class="ss-num">{{ $label }}</th>@endforeach
          <th class="ss-num">Total accounts</th><th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $r)
          <tr>
            <td>
              <div class="fw-semibold">{{ $r['name'] }}</div>
              <div class="ss-sub">{{ $r['email'] }}</div>
            </td>
            @foreach($r['counts'] as $n)<td class="ss-num">{{ $n ?: '—' }}</td>@endforeach
            <td class="ss-num fw-bold">{{ $r['total'] }}</td>
            <td class="text-end"><a href="{{ route('crm-clients.employee', $r['id']) }}" class="btn btn-sm btn-outline-primary">View accounts</a></td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-center text-muted py-5">No employee matches this search.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<p class="small text-muted mb-4">
  Only employees who hold at least one of these roles on a CRM account are listed &mdash; not the full staff directory.
  "Unassigned CRM accounts" is not the same figure as the <a href="{{ route('sales-sources.unmapped') }}">Not in CRM</a> list:
  this counts CRM records with nobody assigned; that one counts real Tally customers with no CRM record at all.
</p>
@endsection
