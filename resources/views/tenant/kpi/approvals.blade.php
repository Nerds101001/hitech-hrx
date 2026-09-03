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
    $byUser = $pendingEntries->groupBy('user_id');
    $tabLabels = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
    $tabStatusConst = [
        'pending'  => \App\Models\KpiEntry::SUBMITTED,
        'approved' => \App\Models\KpiEntry::APPROVED,
        'rejected' => \App\Models\KpiEntry::REJECTED,
    ];
    $qsBase = array_filter(['department_id' => $departmentId, 'employee_id' => $employeeId]);
  @endphp

  <div class="row g-6 mb-6 mx-0 px-4">
    <div class="col-xl-3 col-md-6">
      <div class="hitech-stat-card dashboard-variant card-amber uniform-card">
        <div class="stat-card-header mb-2">
          <div class="stat-icon-wrap icon-amber" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-time-five"></i></div>
        </div>
        <div><h3 class="stat-value mb-0">{{ $pendingEntries->count() }}</h3><span class="stat-label">Awaiting approval</span></div>
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
        <div><h3 class="stat-value mb-0">{{ $pendingEntries->pluck('period_start')->unique()->count() }}</h3><span class="stat-label">Weeks covered</span></div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6">
      <div class="hitech-stat-card dashboard-variant card-red uniform-card">
        <div class="stat-card-header mb-2">
          <div class="stat-icon-wrap icon-red" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-hourglass"></i></div>
        </div>
        @php $oldest = $pendingEntries->sortBy('created_at')->first(); @endphp
        {{-- diffInDays() returns a float here, so an entry made minutes ago
             rendered as 0.00022 rather than 0. --}}
        <div><h3 class="stat-value mb-0">{{ $oldest ? (int) floor(abs(\Carbon\Carbon::parse($oldest->created_at)->diffInDays(now()))) : 0 }}</h3><span class="stat-label">Days oldest has waited</span></div>
      </div>
    </div>
  </div>

  <div class="row g-6 mb-6 mx-0 px-4">
    <div class="col-12">
      {{-- One unified card — tabs, filters and table together, same shell
           Leave Approvals uses (hitech-card > segmented control > card-header
           border-bottom > filter row > table with no card of its own). Three
           separately-rounded cards stacked on top of each other is exactly
           what read as "too much radius" here before. --}}
      <div class="hitech-card animate__animated animate__fadeInUp" style="animation-delay: 0.1s">
        <div class="hitech-card-header border-bottom">
          {{-- Pending / Approved / Rejected + Export, one row. Full page
               reload per tab (this screen is server-rendered, no
               DataTables/ajax layer), so each tab is a plain link carrying
               tab= and the current department_id. --}}
          <div class="hitech-segmented-control bg-light-soft rounded-pill p-1 d-flex" style="border: 1px solid rgba(0, 90, 90, 0.1); width: fit-content;">
            @foreach($tabLabels as $key => $label)
              <a href="{{ route('tenant.kpi.approvals', $qsBase + ['tab' => $key]) }}"
                 class="btn btn-sm rounded-pill px-4 status-toggle-btn {{ $tab === $key ? 'active' : '' }}">
                @if($key === 'pending') <i class="bx bx-time-five me-1"></i>
                @elseif($key === 'approved') <i class="bx bx-check-double me-1"></i>
                @else <i class="bx bx-block me-1"></i> @endif
                {{ $label }} ({{ (int) ($tabCounts[$tabStatusConst[$key]] ?? 0) }})
              </a>
            @endforeach
          </div>
          <a href="{{ route('tenant.kpi.approvals.export', $qsBase + ['tab' => $tab]) }}" class="btn btn-sm btn-icon icon-sophisticated" title="Export {{ $tabLabels[$tab] }} to Excel">
            <i class="bx bx-download fs-5"></i>
          </a>
        </div>

        {{-- Integrated filter row --}}
        <div class="card-body p-4 border-bottom">
          <div class="d-flex flex-wrap align-items-center gap-3">
            <div class="search-wrapper-hitech" style="width: 360px;">
              <i class="bx bx-search text-muted ms-3 fs-5"></i>
              <input type="text" class="form-control" placeholder="Search person, department or KPI..." id="apSearch">
              <button class="btn-search shadow-sm" type="button"><i class="bx bx-search fs-5"></i><span>Search</span></button>
            </div>
            <form method="GET" id="apFilterForm" class="d-flex flex-wrap gap-3">
              <input type="hidden" name="tab" value="{{ $tab }}">
              <div class="compact-select" style="min-width: 200px;">
                <select class="form-select filter-item-hitech ap-pill-select" name="department_id" onchange="document.getElementById('apFilterForm').submit()">
                  <option value="">Department: All</option>
                  @foreach($departments as $d)
                    <option value="{{ $d->id }}" {{ (string)$departmentId === (string)$d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                  @endforeach
                </select>
              </div>
              <div class="compact-select" style="min-width: 200px;">
                <select class="form-select filter-item-hitech ap-pill-select" name="employee_id" onchange="document.getElementById('apFilterForm').submit()">
                  <option value="">Name: All</option>
                  @foreach($employees as $emp)
                    <option value="{{ $emp->id }}" {{ (string)$employeeId === (string)$emp->id ? 'selected' : '' }}>{{ $emp->first_name }} {{ $emp->last_name }}</option>
                  @endforeach
                </select>
              </div>
            </form>
          </div>
        </div>

        <div class="table-responsive p-0">
          <table class="table table-hover border-top mb-0" id="apTable">
          <thead class="table-light">
            <tr>
              <th class="ap-sortable" data-sort="text" style="min-width:190px">Employee <i class="bx bx-sort text-muted"></i></th>
              <th class="ap-sortable" data-sort="text" style="min-width:150px">Department <i class="bx bx-sort text-muted"></i></th>
              <th class="ap-sortable" data-sort="text" style="min-width:190px">KPI <i class="bx bx-sort text-muted"></i></th>
              <th class="ap-sortable" data-sort="text">Week <i class="bx bx-sort text-muted"></i></th>
              <th class="ap-sortable text-end" data-sort="num">Reported <i class="bx bx-sort text-muted"></i></th>
              <th>Customers</th>
              @if($tab === 'pending')
                <th class="text-end" style="min-width:200px">Decision</th>
              @else
                <th class="ap-sortable" data-sort="text">Reviewed by <i class="bx bx-sort text-muted"></i></th>
              @endif
            </tr>
          </thead>
          <tbody>
            @forelse($entries as $e)
              @php $dept = optional(optional($e->user)->department)->name ?? '—'; @endphp
              <tr class="ap-row" data-id="{{ $e->id }}"
                  data-search="{{ strtolower(optional($e->user)->getFullName() . ' ' . $dept . ' ' . optional($e->assignment)->title) }}">
                <td data-val="{{ strtolower(optional($e->user)->getFullName() ?? '') }}">{{ optional($e->user)->getFullName() ?? '—' }}</td>
                <td data-val="{{ strtolower($dept) }}">{{ $dept }}</td>
                <td data-val="{{ strtolower(optional($e->assignment)->title ?? '') }}">
                  <div class="fw-medium">{{ optional($e->assignment)->title ?? '—' }}</div>
                  <small class="text-muted">{{ rtrim(rtrim(number_format((float) optional($e->assignment)->max_marks, 2, '.', ''), '0'), '.') }} marks</small>
                </td>
                <td class="text-nowrap" data-val="{{ \Carbon\Carbon::parse($e->period_start)->format('Y-m-d') }}">
                  {{ \Carbon\Carbon::parse($e->period_start)->format('d M') }} –
                  {{ \Carbon\Carbon::parse($e->period_end)->format('d M') }}
                  <small class="text-muted d-block">sent {{ \Carbon\Carbon::parse($e->created_at)->diffForHumans() }}</small>
                </td>
                <td class="text-end fw-semibold" data-val="{{ (float) $e->value }}">{{ rtrim(rtrim(number_format((float) $e->value, 2, '.', ''), '0'), '.') }}</td>
                <td><small class="text-muted">{{ $e->note ?: '—' }}</small></td>
                @if($tab === 'pending')
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
                @else
                  <td data-val="{{ strtolower(optional($e->reviewer)->getFullName() ?? '') }}">
                    {{ optional($e->reviewer)->getFullName() ?? '—' }}
                    <small class="text-muted d-block">{{ $e->reviewed_at ? \Carbon\Carbon::parse($e->reviewed_at)->diffForHumans() : '' }}</small>
                    @if($tab === 'rejected' && $e->review_note)
                      <small class="text-danger d-block">HR: {{ $e->review_note }}</small>
                    @endif
                  </td>
                @endif
              </tr>
            @empty
              <tr><td colspan="7" class="text-center text-muted py-5">
                <i class="bx bx-check-circle fs-3 d-block mb-2 text-success"></i>
                @if($tab === 'pending')
                  Nothing waiting. Every submitted week has been dealt with.
                @else
                  No {{ strtolower($tabLabels[$tab]) }} entries{{ $departmentId ? ' for this department' : '' }} yet.
                @endif
              </td></tr>
            @endforelse
          </tbody>
        </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-style')
<style>
  /* .filter-item-hitech's own radius (10px) reads as a different shape than
     the pill-rounded search box sitting right next to it. Scoped to this
     page's two filter selects rather than the shared class, which other
     pages (Leave, Attendance, ...) also use as-is. */
  .ap-pill-select {
    border-radius: 50px !important;
    border: 1px solid #e2e8f0 !important;
  }
</style>
@endsection

@section('page-script')
<script>
(function () {
  const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  const URL_REVIEW = "{{ route('tenant.kpi.entries.review', ['entryId' => '__ID__']) }}";

  function refreshCount() {
    const rows = document.querySelectorAll('.ap-row:not(.d-none)').length;
    const el = document.getElementById('apShowing');
    if (el) el.textContent = rows + ' shown';
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

  // Click-to-sort — the whole table is already server-rendered (one tab's
  // worth of rows, no pagination), so this just reorders the existing <tr>s
  // client-side rather than round-tripping for something this small.
  document.querySelectorAll('#apTable th.ap-sortable').forEach(function (th, idx) {
    let dir = 1;
    th.style.cursor = 'pointer';
    th.addEventListener('click', function () {
      const tbody = document.querySelector('#apTable tbody');
      const rows = Array.from(tbody.querySelectorAll('tr.ap-row'));
      const type = th.dataset.sort;
      const cellIndex = Array.from(th.parentElement.children).indexOf(th);
      rows.sort(function (a, b) {
        const av = a.children[cellIndex].dataset.val ?? '';
        const bv = b.children[cellIndex].dataset.val ?? '';
        if (type === 'num') return (parseFloat(av) - parseFloat(bv)) * dir;
        return av.localeCompare(bv) * dir;
      });
      rows.forEach(r => tbody.appendChild(r));
      dir *= -1;
      document.querySelectorAll('#apTable th.ap-sortable i').forEach(i => i.className = 'bx bx-sort text-muted');
      th.querySelector('i').className = 'bx bx-sort-' + (dir === 1 ? 'down' : 'up') + ' text-dark';
    });
  });

  refreshCount();
})();
</script>
@endsection
