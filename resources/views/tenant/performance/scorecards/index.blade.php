@php $configData = Helper::appClasses(); @endphp
@extends('layouts/layoutMaster')

@section('title', 'Scorecards')

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6 mx-4">
    <div>
      <h3 class="fw-extrabold mb-1 text-dark">Scorecards</h3>
      <p class="text-muted mb-0 small">
        Automated sales performance appraisals, generated from Pipeline, MindSpeak, Travel &amp; the 90-Day Grid.
      </p>
    </div>
    @if(auth()->user()->hasRole(['admin', 'hr', 'manager']))
    <div class="d-flex gap-3 flex-wrap">
      <button type="button" class="btn-hitech shadow-sm rounded-pill px-5 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#generateScorecardModal">
        <i class="bx bx-plus-circle fs-5"></i><span class="fw-bold">Generate Scorecard</span>
      </button>
    </div>
    @endif
  </div>

  <div class="px-4">
    <div class="hitech-card mb-6" id="scorecardFilterCard">
      <div class="card-body p-4 border-bottom">
        <form method="GET" class="row align-items-end g-4">
          <div class="col-lg-3 col-md-6">
            <div class="search-wrapper-hitech mw-100">
              <i class="bx bx-search text-muted ms-3"></i>
              <input type="text" name="search" class="form-control" placeholder="Search employee name..." value="{{ request('search') }}">
              <button class="btn-search shadow-sm" type="submit"><i class="bx bx-search fs-5"></i><span>Search</span></button>
            </div>
          </div>
          <div class="col-lg-2 col-md-6">
            <label class="form-label fw-bold small text-muted text-uppercase mb-2">Department</label>
            <select class="form-select form-select-hitech" name="department_id" onchange="this.form.submit()">
              <option value="">All Departments</option>
              @foreach($departments as $d)
                <option value="{{ $d->id }}" {{ (string) request('department_id') === (string) $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-lg-2 col-md-6">
            <label class="form-label fw-bold small text-muted text-uppercase mb-2">Cycle</label>
            <select class="form-select form-select-hitech" name="cycle_id" onchange="this.form.submit()">
              <option value="">All Cycles</option>
              @foreach($cycles as $c)
                <option value="{{ $c->id }}" {{ (string) request('cycle_id') === (string) $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-lg-2 col-md-6">
            <label class="form-label fw-bold small text-muted text-uppercase mb-2">Status</label>
            <select class="form-select form-select-hitech" name="status" onchange="this.form.submit()">
              <option value="">All statuses</option>
              <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
              <option value="published" {{ request('status') === 'published' ? 'selected' : '' }}>Published</option>
            </select>
          </div>
          <div class="col-lg-2 col-md-6">
            <label class="form-label fw-bold small text-muted text-uppercase mb-2">Grade</label>
            <select class="form-select form-select-hitech" name="grade" onchange="this.form.submit()">
              <option value="">All grades</option>
              @foreach(\App\Http\Controllers\tenant\SalesAssessmentController::GRADES as $g)
                <option value="{{ $g }}" {{ request('grade') === $g ? 'selected' : '' }}>{{ $g }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-lg-1 col-md-6">
            <a href="{{ route('performance.scorecards.index') }}" class="btn btn-hitech-cancel w-100">Clear</a>
          </div>
        </form>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="min-width:180px">User</th>
              <th>Cycle</th>
              <th>Score</th>
              <th>Grade</th>
              <th>Status</th>
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
                  <div class="fw-medium">{{ $sc->user->first_name ?? '' }} {{ $sc->user->last_name ?? '' }}</div>
                  @if($sc->user?->department?->name)
                    <small class="text-muted">{{ $sc->user->department->name }}</small>
                  @endif
                </td>
                <td>{{ $sc->cycle->name ?? 'N/A' }}</td>
                <td class="fw-medium">{{ $sc->total_score }} / 100</td>
                <td><span class="badge-hitech {{ $gradeBadge }}">{{ $sc->grade }}</span></td>
                <td>
                  <span class="badge-hitech {{ $sc->status === 'published' ? 'badge-hitech-success' : 'badge-muted' }}">{{ ucfirst($sc->status) }}</span>
                </td>
                <td class="text-end">
                  <a href="{{ route('performance.scorecards.show', $sc->id) }}" class="btn btn-sm btn-hitech rounded-pill px-3"><i class="bx bx-show me-1"></i>View Report</a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="text-center text-muted py-5">No scorecards match these filters.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

@if(auth()->user()->hasRole(['admin', 'hr', 'manager']))
<!-- Generate Modal -->
<div class="modal fade" id="generateScorecardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="{{ route('performance.scorecards.generate') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel1">Generate End-of-Year Scorecard</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        Generating this scorecard will auto-fetch data from Product Orders, Pipeline, MindSpeak (Innovation), and the 90-Day Grid.
                    </div>
                    <div class="row">
                        <div class="col mb-3">
                            <label for="user_id" class="form-label">Employee</label>
                            <select id="user_id" name="user_id" class="form-select" required>
                                <option value="">Select Employee</option>
                                @foreach(\App\Models\User::where('tenant_id', \Illuminate\Support\Facades\Auth::user()->tenant_id)->get() as $u)
                                    <option value="{{ $u->id }}">{{ $u->first_name }} {{ $u->last_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col mb-3">
                            <label for="appraisal_cycle_id" class="form-label">Appraisal Cycle</label>
                            <select id="appraisal_cycle_id" name="appraisal_cycle_id" class="form-select" required>
                                <option value="">Select Cycle</option>
                                @foreach($cycles as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Generate</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif

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
