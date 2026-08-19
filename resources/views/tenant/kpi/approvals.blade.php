@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')

@section('title', 'KPI Approvals')

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6 mx-4">
    <div>
      <h3 class="fw-extrabold mb-1 text-dark">KPI Approvals</h3>
      <p class="text-muted mb-0 small">
        Weekly figures staff have reported. Approving one adds it to that month's
        total and rescores the KPI; sending it back lets them correct the week.
      </p>
    </div>
    <div class="d-flex gap-3 flex-wrap">
      <a href="{{ route('tenant.kpi.templates') }}" class="btn btn-hitech-cancel shadow-sm rounded-pill px-4 d-flex align-items-center gap-2">
        <i class="bx bx-list-check fs-5"></i><span class="fw-bold">Templates</span>
      </a>
      <a href="{{ route('tenant.kpi.manage') }}" class="btn btn-hitech-cancel shadow-sm rounded-pill px-4 d-flex align-items-center gap-2">
        <i class="bx bx-user-detail fs-5"></i><span class="fw-bold">Per-employee view</span>
      </a>
    </div>
  </div>

  @php
    $byUser = $entries->groupBy('user_id');
    $totalValue = $entries->sum('value');
  @endphp

  <div class="row g-6 mb-6 mx-0 px-4">
    <div class="col-xl-3 col-md-6">
      <div class="hitech-stat-card dashboard-variant card-amber uniform-card">
        <div class="stat-card-header mb-2">
          <div class="stat-icon-wrap icon-amber" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-time-five"></i></div>
        </div>
        <div><h3 class="stat-value mb-0">{{ $entries->count() }}</h3><span class="stat-label">Awaiting approval</span></div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6">
      <div class="hitech-stat-card dashboard-variant card-teal uniform-card">
        <div class="stat-card-header mb-2">
          <div class="stat-icon-wrap icon-teal" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-group"></i></div>
        </div>
        <div><h3 class="stat-value mb-0">{{ $byUser->count() }}</h3><span class="stat-label">People waiting</span></div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6">
      <div class="hitech-stat-card dashboard-variant card-blue uniform-card">
        <div class="stat-card-header mb-2">
          <div class="stat-icon-wrap icon-blue" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-calendar-week"></i></div>
        </div>
        <div><h3 class="stat-value mb-0">{{ $entries->pluck('period_start')->unique()->count() }}</h3><span class="stat-label">Weeks covered</span></div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6">
      <div class="hitech-stat-card dashboard-variant card-red uniform-card">
        <div class="stat-card-header mb-2">
          <div class="stat-icon-wrap icon-red" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-hourglass"></i></div>
        </div>
        @php $oldest = $entries->sortBy('created_at')->first(); @endphp
        <div><h3 class="stat-value mb-0">{{ $oldest ? \Carbon\Carbon::parse($oldest->created_at)->diffInDays() : 0 }}</h3><span class="stat-label">Days oldest has waited</span></div>
      </div>
    </div>
  </div>

  <div class="px-4">
    <div class="hitech-card-white mb-6 overflow-hidden">
      <div class="card-body p-sm-5 p-4">
        <div class="row align-items-end g-4">
          <div class="col-lg-4">
            <label class="form-label fw-bold small text-muted text-uppercase mb-2">Search</label>
            <div class="search-wrapper-hitech mw-100">
              <i class="bx bx-search text-muted ms-3"></i>
              <input type="text" class="form-control" placeholder="Search person or KPI..." id="apSearch">
              <button class="btn-search shadow-sm" type="button"><i class="bx bx-search fs-5"></i><span>Search</span></button>
            </div>
          </div>
          <div class="col-lg-3 col-md-6">
            <label class="form-label fw-bold small text-muted text-uppercase mb-2">Department</label>
            <form method="GET" id="apDeptForm">
              <select class="form-select" name="department_id" onchange="document.getElementById('apDeptForm').submit()">
                <option value="">All Departments</option>
                @foreach($departments as $d)
                  <option value="{{ $d->id }}" {{ (string)$departmentId === (string)$d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                @endforeach
              </select>
            </form>
          </div>
          <div class="col-lg-5 d-flex align-items-center gap-3">
            <span class="text-muted fw-semibold small" id="apShowing"></span>
          </div>
        </div>
      </div>
    </div>

    <div class="hitech-card">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="min-width:190px">Employee</th>
              <th style="min-width:190px">KPI</th>
              <th>Week</th>
              <th class="text-end">Reported</th>
              <th>Note</th>
              <th class="text-end" style="min-width:200px">Decision</th>
            </tr>
          </thead>
          <tbody>
            @forelse($entries as $e)
              <tr class="ap-row" data-id="{{ $e->id }}"
                  data-search="{{ strtolower(optional($e->user)->getFullName() . ' ' . optional($e->assignment)->title) }}">
                <td>
                  <div class="fw-medium">{{ optional($e->user)->getFullName() ?? '—' }}</div>
                  <small class="text-muted">{{ optional(optional($e->user)->department)->name ?? '—' }}</small>
                </td>
                <td>
                  <div class="fw-medium">{{ optional($e->assignment)->title ?? '—' }}</div>
                  <small class="text-muted">{{ rtrim(rtrim(number_format((float) optional($e->assignment)->max_marks, 2, '.', ''), '0'), '.') }} marks</small>
                </td>
                <td class="text-nowrap">
                  {{ \Carbon\Carbon::parse($e->period_start)->format('d M') }} –
                  {{ \Carbon\Carbon::parse($e->period_end)->format('d M') }}
                  <small class="text-muted d-block">sent {{ \Carbon\Carbon::parse($e->created_at)->diffForHumans() }}</small>
                </td>
                <td class="text-end fw-semibold">{{ rtrim(rtrim(number_format((float) $e->value, 2, '.', ''), '0'), '.') }}</td>
                <td><small class="text-muted">{{ $e->note ?: '—' }}</small></td>
                <td class="text-end text-nowrap">
                  <input type="text" class="form-control form-control-sm d-inline-block ap-note" style="width:130px"
                         placeholder="note (optional)">
                  <button class="btn btn-sm btn-icon icon-sophisticated text-success ap-act" data-act="approve" title="Approve">
                    <i class="bx bx-check"></i>
                  </button>
                  <button class="btn btn-sm btn-icon icon-sophisticated text-danger ap-act" data-act="reject" title="Send back">
                    <i class="bx bx-undo"></i>
                  </button>
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted py-5">
                <i class="bx bx-check-circle fs-3 d-block mb-2 text-success"></i>
                Nothing waiting. Every submitted week has been dealt with.
              </td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
(function () {
  const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  const URL_REVIEW = "{{ route('tenant.kpi.entries.review', ['entryId' => '__ID__']) }}";

  function refreshCount() {
    const rows = document.querySelectorAll('.ap-row:not(.d-none)').length;
    const el = document.getElementById('apShowing');
    if (el) el.textContent = rows + ' waiting';
  }

  document.querySelectorAll('.ap-act').forEach(function (btn) {
    btn.addEventListener('click', async function () {
      const row = btn.closest('.ap-row');
      const act = btn.dataset.act;
      if (act === 'reject' && !confirm('Send this week back to the employee to correct?')) return;

      row.querySelectorAll('.ap-act').forEach(b => b.disabled = true);
      try {
        const res = await fetch(URL_REVIEW.replace('__ID__', row.dataset.id), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
          credentials: 'same-origin',
          body: JSON.stringify({ action: act, note: row.querySelector('.ap-note').value || null })
        });
        const data = await res.json().catch(() => ({}));
        if (res.ok && data.status === 'success') {
          row.classList.add('d-none');
          refreshCount();
        } else {
          alert(data.message || 'Could not save that.');
          row.querySelectorAll('.ap-act').forEach(b => b.disabled = false);
        }
      } catch (err) {
        alert('Could not save that: ' + err.message);
        row.querySelectorAll('.ap-act').forEach(b => b.disabled = false);
      }
    });
  });

  const search = document.getElementById('apSearch');
  if (search) {
    search.addEventListener('input', function () {
      const q = this.value.trim().toLowerCase();
      document.querySelectorAll('.ap-row').forEach(function (r) {
        r.classList.toggle('d-none', !!q && !(r.dataset.search || '').includes(q));
      });
      refreshCount();
    });
  }
  refreshCount();
})();
</script>
@endsection
