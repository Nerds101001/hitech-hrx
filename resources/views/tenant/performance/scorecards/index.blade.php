@php $configData = Helper::appClasses(); @endphp
@extends('layouts/layoutMaster')

@section('title', 'Scorecards')

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6 mx-4">
    <div>
      <h3 class="fw-extrabold mb-1 text-dark">Scorecards</h3>
      <p class="text-muted mb-0 small">
        Every employee's live KPI/KRA score - the same figures as their own My Scorecard, tracked continuously.
      </p>
    </div>
    {{-- Month-wise PDF. The month is chosen once here and applied to the row links and the bulk button by the script below. --}}
    <div class="d-flex flex-wrap align-items-center gap-2">
      <select id="pdfMonth" class="form-select form-select-hitech" style="min-width:160px" aria-label="Month for PDF">
        @foreach($pdfMonths as $pm)
          <option value="{{ $pm['key'] }}">{{ $pm['label'] }}</option>
        @endforeach
      </select>
      <a href="#" id="pdfAll" class="btn btn-hitech rounded-pill px-4 d-flex align-items-center gap-2"
         data-base="{{ route('performance.scorecards.index', array_filter(['department_id' => request('department_id'), 'search' => request('search')])) }}"
         title="One PDF, a page per person, for the department and search currently selected">
        <i class="bx bx-download fs-5"></i><span class="fw-bold">Download all</span>
      </a>
    </div>
  </div>

  <div class="px-4">
    <div class="hitech-card mb-6" id="scorecardFilterCard">
      <div class="card-body p-4 border-bottom">
        <form method="GET" class="row align-items-end g-4">
          <div class="col-lg-4 col-md-6">
            <div class="search-wrapper-hitech mw-100">
              <i class="bx bx-search text-muted ms-3"></i>
              <input type="text" name="search" class="form-control" placeholder="Search employee name..." value="{{ request('search') }}">
              <button class="btn-search shadow-sm" type="submit"><i class="bx bx-search fs-5"></i><span>Search</span></button>
            </div>
          </div>
          <div class="col-lg-3 col-md-6">
            <label class="form-label fw-bold small text-muted text-uppercase mb-2">Department</label>
            <select class="form-select form-select-hitech" name="department_id" onchange="this.form.submit()">
              <option value="">All Departments</option>
              @foreach($departments as $d)
                <option value="{{ $d->id }}" {{ (string) request('department_id') === (string) $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-lg-3 col-md-6">
            <label class="form-label fw-bold small text-muted text-uppercase mb-2">Grade</label>
            <select class="form-select form-select-hitech" name="grade" onchange="this.form.submit()">
              <option value="">All grades</option>
              @foreach(\App\Http\Controllers\tenant\SalesAssessmentController::GRADES as $g)
                <option value="{{ $g }}" {{ request('grade') === $g ? 'selected' : '' }}>{{ $g }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-lg-2 col-md-6">
            <a href="{{ route('performance.scorecards.index') }}" class="btn btn-hitech-cancel w-100">Clear</a>
          </div>
        </form>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="min-width:180px">Employee</th>
              <th>Score</th>
              <th>Grade</th>
              <th class="text-end" style="min-width:120px">Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse($scorecards as $sc)
              @php
                $gradeLetter = strtoupper(substr($sc->grade ?? '', 0, 1));
                $gradeBadge = ['A' => 'badge-hitech-success', 'B' => 'badge-brand-soft', 'C' => 'badge-status-grade-c', 'D' => 'badge-hitech-danger'][$gradeLetter] ?? 'badge-muted';
              @endphp
              <tr>
                <td>
                  <div class="fw-medium">{{ $sc->user->first_name }} {{ $sc->user->last_name }}</div>
                  @if($sc->user->department?->name)
                    <small class="text-muted">{{ $sc->user->department->name }}</small>
                  @endif
                </td>
                <td class="fw-medium">
                  @if($sc->has_assignments)
                    {{ rtrim(rtrim(number_format($sc->earned, 1), '0'), '.') }} / {{ rtrim(rtrim(number_format($sc->possible, 1), '0'), '.') }}
                    <span class="text-muted small">({{ $sc->pct }}%)</span>
                  @else
                    <span class="text-muted">No KPIs assigned</span>
                  @endif
                </td>
                <td>
                  @if($sc->grade)
                    <span class="badge-hitech {{ $gradeBadge }}">{{ $sc->grade }}</span>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td class="text-end text-nowrap">
                  @if($sc->has_assignments)
                    <a href="#" class="btn btn-sm btn-hitech-cancel rounded-pill px-3 js-pdf-row"
                       data-base="{{ route('performance.scorecards.index', ['user_id' => $sc->user->id]) }}" title="Download this employee's scorecard for the month chosen above"><i class="bx bx-download me-1"></i>PDF</a>
                  @endif
                  <a href="{{ route('performance.scorecards.show', $sc->user->id) }}" class="btn btn-sm btn-hitech rounded-pill px-3"><i class="bx bx-show me-1"></i>View Report</a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="text-center text-muted py-5">No employees match these filters.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
  // Plain DOM only: nothing here may depend on jQuery/bootstrap, which load deferred.
  document.addEventListener('DOMContentLoaded', function () {
    var select = document.getElementById('pdfMonth');
    function link(base) {
      return base + (base.indexOf('?') === -1 ? '?' : '&') + 'pdf=' + encodeURIComponent(select.value);
    }
    document.querySelectorAll('.js-pdf-row, #pdfAll').forEach(function (a) {
      a.addEventListener('click', function (e) { e.preventDefault(); window.location.href = link(a.dataset.base); });
    });
  });
</script>

<style>
  /* Grade C has no ready-made semantic badge elsewhere in the theme
     (success/danger cover A/D, brand-soft reads as "informational" for
     B) - a distinct amber tone for the middle grade so all four read
     apart from each other at a glance. */
  .badge-status-grade-c {
    background: rgba(217, 119, 6, 0.1);
    color: #d97706;
    border: 1px solid rgba(217, 119, 6, 0.18);
  }
</style>
@endsection
