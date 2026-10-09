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
            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" onclick="openEmployeeAccounts({{ $r['id'] }}, @js($r['name']))">View accounts</button></td>
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

{{-- Accounts handled by an employee — fetched from the same JSON the employee profile uses; each row opens the customer 360. --}}
<div class="modal fade" id="empAccountsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title mb-0"><i class="bx bx-folder-open me-1"></i> Accounts handled by <span id="empAccName" class="fw-bold"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="empAccLoading" class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2"></span> Loading…</div>
        <div id="empAccEmpty" class="text-center text-muted py-5" style="display:none;">No accounts found for this employee.</div>
        <div id="empAccContent" style="display:none;">
          <div class="small text-muted mb-2" id="empAccCount"></div>
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light"><tr><th>Company</th><th>Stage</th><th>Role on account</th><th>Location</th><th>Contact</th><th></th></tr></thead>
              <tbody id="empAccRows"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  const EMP_URL = "{{ url('crm-clients/employee') }}";
  const PROFILE_BASE = "{{ url('crm-clients') }}";
  const STAGE = { ACTIVE:['#dcfce7','#16a34a'], DROPPED:['#fee2e2','#dc2626'], PROSPECT:['#fef3c7','#d97706'], INTRODUCTION:['#dbeafe','#2563eb'] };
  const esc = s => (s == null ? '' : String(s)).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  let modal;

  window.openEmployeeAccounts = function (id, name) {
    document.getElementById('empAccName').textContent = name || '';
    document.getElementById('empAccLoading').style.display = 'block';
    document.getElementById('empAccEmpty').style.display = 'none';
    document.getElementById('empAccContent').style.display = 'none';
    document.getElementById('empAccRows').innerHTML = '';
    modal = modal || new bootstrap.Modal(document.getElementById('empAccountsModal'));
    modal.show();

    fetch(EMP_URL + '/' + id, { headers: { 'Accept': 'application/json' } })
      .then(r => r.json())
      .then(data => {
        document.getElementById('empAccLoading').style.display = 'none';
        if (!Array.isArray(data) || data.length === 0) { document.getElementById('empAccEmpty').style.display = 'block'; return; }
        document.getElementById('empAccCount').textContent = data.length + ' account' + (data.length > 1 ? 's' : '');
        document.getElementById('empAccRows').innerHTML = data.map(c => {
          const col = STAGE[(c.crm_stage || '').toUpperCase()] || ['#f1f5f9', '#64748b'];
          const badge = c.crm_stage ? `<span style="background:${col[0]};color:${col[1]};font-size:.62rem;font-weight:700;padding:2px 8px;border-radius:20px;text-transform:uppercase;">${esc(c.crm_stage)}</span>` : '<span class="text-muted">—</span>';
          const loc = [c.crm_city, c.crm_state].filter(Boolean).map(esc).join(', ') || '—';
          const contact = [c.email, c.phone].filter(Boolean).map(esc).join('<br>') || '—';
          return `<tr>
            <td><div class="fw-semibold">${esc(c.crm_company_name)}</div><div class="text-muted" style="font-size:.72rem;">CRM #${esc(c.crm_company_code)}</div></td>
            <td>${badge}</td>
            <td class="small">${esc(c.role) || '—'}</td>
            <td class="small">${loc}</td>
            <td class="small">${contact}</td>
            <td class="text-end"><a href="${PROFILE_BASE}/${c.id}/profile" class="btn btn-sm btn-outline-primary">Open profile</a></td>
          </tr>`;
        }).join('');
        document.getElementById('empAccContent').style.display = 'block';
      })
      .catch(() => { document.getElementById('empAccLoading').style.display = 'none'; document.getElementById('empAccEmpty').style.display = 'block'; });
  };
})();
</script>
@endsection
