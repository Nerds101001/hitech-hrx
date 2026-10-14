@extends('layouts/layoutMaster')

@section('title', 'DWM Daily Work Management - Dashboard')

@section('vendor-style')
@vite([
  'resources/assets/vendor/scss/pages/hitech-portal.scss'
])
@endsection

@section('page-style')
<style>
  /* ===================== Hero Header ===================== */
  .sv-hero {
    background: linear-gradient(135deg, #0d7377 0%, #14a085 40%, #0a9396 70%, #00b4d8 100%);
    border-radius: 18px;
    padding: 2.5rem 2.8rem;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(13, 115, 119, 0.30);
  }
  .sv-hero::before {
    content: '';
    position: absolute;
    top: -60px; right: -60px;
    width: 260px; height: 260px;
    background: rgba(255,255,255,0.07);
    border-radius: 50%;
  }
  .sv-hero::after {
    content: '';
    position: absolute;
    bottom: -80px; left: -40px;
    width: 200px; height: 200px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
  }
  .sv-hero-title {
    font-size: 1.85rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: -0.5px;
    margin-bottom: 0.3rem;
  }
  .sv-hero-subtitle {
    color: rgba(255,255,255,0.80);
    font-size: 0.97rem;
    margin-bottom: 0;
  }
  .btn-new-visit {
    background: #fff;
    color: #0d7377;
    border: none;
    font-weight: 700;
    border-radius: 10px;
    padding: 0.65rem 1.4rem;
    font-size: 0.92rem;
    box-shadow: 0 4px 18px rgba(0,0,0,0.12);
    transition: all 0.22s ease;
  }
  .btn-new-visit:hover {
    background: #0d7377;
    color: #fff;
    box-shadow: 0 6px 22px rgba(13,115,119,0.35);
    transform: translateY(-2px);
  }

  /* ===================== Filter Bar ===================== */
  .sv-filter-bar {
    background: #fff;
    border-radius: 14px;
    padding: 1.2rem 1.6rem;
    box-shadow: 0 2px 16px rgba(0,0,0,0.07);
    margin-bottom: 1.5rem;
  }
  .sv-filter-bar .form-label {
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #5a5f71;
    margin-bottom: 0.3rem;
  }
  .sv-filter-bar .form-select,
  .sv-filter-bar .form-control {
    border-radius: 9px;
    font-size: 0.88rem;
    border-color: #e3e6ef;
    height: 40px;
  }

  /* ===================== Table ===================== */
  .sv-table-card {
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(0,0,0,0.07);
    border: none;
  }
  .sv-table-card .card-header {
    background: linear-gradient(90deg, #f8fffe, #edf9f9);
    border-bottom: 2px solid #c7eaea;
    padding: 1rem 1.6rem;
    font-weight: 700;
    color: #0d7377;
    font-size: 0.95rem;
  }
  .sv-client-name {
    font-weight: 700;
    color: #1A1A2E;
    display: block;
    font-size: 0.9rem;
  }
  .sv-client-city {
    font-size: 0.78rem;
    color: #8A90A2;
  }

  /* The capacity-footer text and status badge are inline style="font-size:
     0.65rem" (~10.4px) with no class to target -- !important on the same
     property, scoped by the ancestor the table itself already uses, is
     enough to win over an inline style without !important. */
  @media (max-width: 480px) {
    [style*="font-size: 0.65rem"],
    [style*="font-size:0.65rem"] {
      font-size: 11px !important;
    }
  }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y animate__animated animate__fadeIn px-4">
    
    <div class="sv-hero mb-4">
        <div class="d-flex justify-content-between align-items-center" style="position:relative; z-index:2;">
            <div>
                <h1 class="sv-hero-title">⏱️ Daily Work Management</h1>
                <p class="sv-hero-subtitle">Track capacity load, view recurring tasks, and monitor headroom.</p>
            </div>
            <div>
                <a href="{{ route('tenant.work-control.export') }}" class="btn btn-outline-light me-2" style="border: 2px solid rgba(255,255,255,0.4); font-weight: 700;">
                    <i class="bx bx-download me-1"></i> Export
                </a>
                <a href="{{ route('tenant.work-control.my-routines') }}" class="btn btn-new-visit">
                    <i class="bx bx-plus me-1"></i> Log My Tasks
                </a>
            </div>
        </div>
    </div>

    <div class="row g-6 mb-5">
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="hitech-stat-card dashboard-variant card-teal h-100">
                <div class="stat-card-header">
                    <div class="stat-icon-wrap icon-teal"><i class="bx bx-group"></i></div>
                </div>
                <div>
                    <h3 class="stat-value mb-1">{{ $users->count() }}</h3>
                    <small class="stat-label">Team Tracked</small>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="hitech-stat-card dashboard-variant card-blue h-100">
                <div class="stat-card-header">
                    <div class="stat-icon-wrap icon-blue"><i class="bx bx-task"></i></div>
                </div>
                <div>
                    <h3 class="stat-value mb-1">{{ $users->sum(function($u) { return $u->workControlRoutines->count(); }) }}</h3>
                    <small class="stat-label">Recurring Tasks</small>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="hitech-stat-card dashboard-variant card-amber h-100">
                <div class="stat-card-header">
                    <div class="stat-icon-wrap icon-amber"><i class="bx bx-time-five"></i></div>
                </div>
                <div>
                    @php
                        $totalMins = $users->sum(function($u) {
                            return $u->workControlRoutines->sum('daily_equivalent_minutes');
                        });
                        $totalHours = floor($totalMins / 60);
                    @endphp
                    <h3 class="stat-value mb-1">{{ $totalHours }}h</h3>
                    <small class="stat-label">Daily Committed</small>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Filter Bar ===== --}}
    <div class="sv-filter-bar">
        <form method="GET" action="{{ route('tenant.work-control.index') }}" id="filterForm">
            <div class="row g-3 align-items-end">
                <div class="col-sm-6 col-lg-4">
                    <label class="form-label">Search Employee</label>
                    <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Search by name...">
                </div>
                <div class="col-sm-6 col-lg-4">
                    <label class="form-label">Department</label>
                    <select name="department_id" class="form-select">
                        <option value="">All Departments</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-lg-4 text-end text-lg-start">
                    <button type="submit" class="btn btn-primary" style="border-radius: 9px; font-weight: 600; padding: 0.55rem 1.4rem;">
                        <i class="bx bx-search me-1"></i> Search
                    </button>
                    <a href="{{ route('tenant.work-control.index') }}" class="btn btn-icon btn-outline-secondary ms-2" style="border-radius: 9px;" title="Reset Filters">
                        <i class="bx bx-reset"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <div class="card sv-table-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bx bx-list-ul me-1"></i> Team Capacity List</span>
            <span class="badge bg-label-primary rounded-pill">Showing {{ $users->count() }} records</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive text-nowrap">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-muted text-uppercase" style="width: 25%">Team Member</th>
                            <th class="text-muted text-uppercase" style="width: 55%">Capacity Load</th>
                            <th class="text-muted text-uppercase" style="width: 20%">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                            @php
                                // Round to whole minutes first: the raw sum is a float, and using
                                // % on a float raises an implicit-conversion deprecation per row
                                // (PHP 8.1+).
                                $dailyMins = (int) round($user->workControlRoutines->sum('daily_equivalent_minutes'));
                                $hours = intdiv($dailyMins, 60);
                                $mins = $dailyMins % 60;
                                
                                // Max visualization is 10 hours (600 mins)
                                $percent = min(100, ($dailyMins / 600) * 100);
                                
                                $statusClass = 'success';
                                $statusText = 'HEADROOM';
                                if ($dailyMins > 480) {
                                    $statusClass = 'danger';
                                    $statusText = 'OVER SHIFT';
                                } else if ($dailyMins >= 420) {
                                    $statusClass = 'warning';
                                    $statusText = 'NEAR LIMIT';
                                }
                                
                                $deptName = $user->department ? $user->department->name : ($user->designation && $user->designation->department ? $user->designation->department->name : 'N/A');
                            @endphp
                            <tr class="border-bottom">
                                <td class="py-3">
                                    <div class="d-flex align-items-center">
                                        <div class="d-flex flex-column">
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#userModal{{ $user->id }}" class="sv-client-name text-primary text-decoration-none">{{ $user->name }}</a>
                                            <span class="sv-client-city text-uppercase">{{ $deptName }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <div class="d-flex flex-column w-100">
                                        <div class="progress rounded-pill mt-2" style="height: 10px; background-color: #f1f1f1; position: relative; overflow: visible;">
                                            <div class="progress-bar rounded-pill bg-{{ $statusClass }}" role="progressbar" style="width: {{ $percent }}%;" aria-valuenow="{{ $dailyMins }}" aria-valuemin="0" aria-valuemax="600"></div>
                                            <!-- 8 Hour Marker -->
                                            <div style="position: absolute; left: 80%; top: -4px; bottom: -4px; width: 2px; background-color: #222; z-index: 10;"></div>
                                        </div>
                                        <div class="d-flex justify-content-between mt-2 text-muted" style="font-size: 0.65rem;">
                                            <span>0h</span>
                                            <span>2h</span>
                                            <span>4h</span>
                                            <span>6h</span>
                                            <span class="text-dark fw-bold">8h</span>
                                            <span>10h</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3">
                                    <h6 class="mb-1 text-dark">{{ $hours }}h {{ sprintf('%02d', $mins) }}m</h6>
                                    <span class="badge bg-label-{{ $statusClass }} rounded-pill" style="font-size: 0.65rem;">{{ $statusText }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3">
                                    <div class="sv-empty">
                                        <i class="bx bx-group empty-icon"></i>
                                        <p>No team members found matching your criteria.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- User Details Modals -->
    @foreach($users as $user)
    <div class="modal fade" id="userModal{{ $user->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="border-radius: 16px;">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold text-dark">
                        <i class="bx bx-user me-2 text-primary"></i> {{ $user->name }}'s Routines
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-uppercase fw-bold text-muted">Task Description</th>
                                    <th class="text-uppercase fw-bold text-muted">Frequency</th>
                                    <th class="text-uppercase fw-bold text-muted">Duration</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($user->workControlRoutines as $routine)
                                    <tr>
                                        <td class="text-dark fw-medium">{{ $routine->title }}</td>
                                        <td><span class="badge bg-label-info text-capitalize">{{ $routine->frequency }}</span></td>
                                        <td class="text-dark fw-bold">
                                            {{ floor($routine->duration_minutes / 60) }}h {{ sprintf('%02d', $routine->duration_minutes % 60) }}m
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">No tasks logged yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endforeach

</div>
@endsection
