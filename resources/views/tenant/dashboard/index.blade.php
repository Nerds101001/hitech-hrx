@php
  $quotes = [
      ['text' => 'Success is the result of hard work, determination, and the courage to pursue greatness.'],
      ['text' => 'Teamwork divides the task and multiplies the success.'],
      ['text' => 'Productivity is not about doing more; it\'s about focusing on what truly matters.'],
      ['text' => 'The secret to great teamwork is trust, communication, and a shared goal.'],
      ['text' => 'Every accomplishment starts with the decision to try.'],
      ['text' => 'Productivity is never an accident. It is always the result of commitment to excellence.'],
      ['text' => 'Believe in your ability to shape the future with the work you do today.'],
      ['text' => 'Efficiency is doing things right; effectiveness is doing the right things.'],
      ['text' => 'Dream big, work hard, stay focused, and surround yourself with good people.'],
      ['text' => 'The best way to predict the future is to create it.'],
  ];
  $quote = $quotes[array_rand($quotes)];
  $quoteText = $quote['text'];
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Dashboard')

@section('vendor-style')
  @vite(['resources/assets/vendor/libs/apex-charts/apex-charts.scss'])
  @vite(['resources/assets/vendor/scss/pages/hitech-portal.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/apex-charts/apexcharts.js'])
@endsection

@section('page-script')
  @vite(['resources/assets/js/app/dashboard-index.js'])
@endsection

@section('content')
<div class="row g-6">

  {{-- ===== HERO BANNER ===== --}}
  <div class="col-lg-12">
    <div class="admin-hero animate__animated animate__fadeIn">
      <div class="admin-hero-text">
        <div class="greeting">System Control Center</div>
        <div class="sub-text">Welcome back, {{ auth()->user()->first_name }}! Here's what's happening today.</div>
      </div>
      <div class="d-none d-md-block text-end" style="max-width: 420px; position:relative; z-index:1;">
        <small class="text-white opacity-75 fst-italic">"{{ $quoteText }}"</small>
      </div>
    </div>
  </div>

  {{-- ===== STAT CARDS ===== --}}
  <div class="col-xl-3 col-md-6 col-sm-6 animate__animated animate__fadeInUp" style="animation-delay: 0.05s">
    <div class="hitech-stat-card dashboard-variant card-teal h-100">
      <div class="stat-card-header">
        <div class="stat-icon-wrap icon-teal"><i class="bx bx-group"></i></div>
        <div class="dropdown">
          <button class="btn p-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bx bx-dots-vertical-rounded text-muted"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="{{ route('employees.index') }}"><i class="bx bx-group me-2"></i>View All</a></li>
          </ul>
        </div>
      </div>
      <div>
        <h3 class="stat-value mb-1">{{ $totalUser }}</h3>
        <small class="text-success fw-bold"><i class="bx bx-check-double me-1"></i>Active Staff</small>
      </div>
    </div>
  </div>

  <div class="col-xl-3 col-md-6 col-sm-6 animate__animated animate__fadeInUp" style="animation-delay: 0.1s">
    <div class="hitech-stat-card dashboard-variant card-blue h-100">
      <div class="stat-card-header">
        <div class="stat-icon-wrap icon-blue"><i class="bx bx-user-check"></i></div>
      </div>
      <div>
        <h3 class="stat-value mb-1">{{ $todayPresentUsers }}</h3>
        <small class="text-primary fw-bold">
          @if($totalUser > 0)
            {{ round(($todayPresentUsers / $totalUser) * 100, 1) }}% Attendance
          @else
            0% Attendance
          @endif
        </small>
      </div>
    </div>
  </div>

  <div class="col-xl-3 col-md-6 col-sm-6 animate__animated animate__fadeInUp" style="animation-delay: 0.15s">
    <div class="hitech-stat-card dashboard-variant card-amber h-100">
      <div class="stat-card-header">
        <div class="stat-icon-wrap icon-amber"><i class="bx bx-calendar-minus"></i></div>
      </div>
      <div>
        <h3 class="stat-value mb-1">{{ $onLeaveUsersCount }}</h3>
        <small class="text-warning fw-bold">On Leave Today</small>
      </div>
    </div>
  </div>

  <div class="col-xl-3 col-md-6 col-sm-6 animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
    <div class="hitech-stat-card dashboard-variant card-red h-100">
      <div class="stat-card-header">
        <div class="stat-icon-wrap icon-red"><i class="bx bx-user-x"></i></div>
      </div>
      <div>
        <h3 class="stat-value mb-1" style="color:#dc2626;">{{ $todayAbsentUsers }}</h3>
        <small class="text-danger fw-bold">Absent / Unaccounted</small>
      </div>
    </div>
  </div>

  {{-- ===== LEFT: Main Charts & Tables (col-xl-8) ===== --}}
  <div class="col-xl-8">
    <div class="row g-6">

      {{-- Productivity / Weekly Hours --}}
      <div class="col-12">
        <div class="hitech-card h-100">
          <div class="hitech-card-header">
            <h5 class="title mb-0">Productivity Overview</h5>
            <span class="badge bg-label-primary rounded-pill">Weekly</span>
          </div>
          <div class="card-body">
            <div class="row align-items-center">
              <div class="col-md-4 text-center text-md-start">
                <h2 class="mb-1" style="color:#005a5a;">{{ $thisWeekWorkingHours }}<span class="text-body">h</span></h2>
                <p class="mb-4 text-muted">Total hours logged this week</p>
                <div class="badge bg-label-success rounded-pill p-2 px-3">
                  <i class="bx bx-trending-up me-1"></i> Consistent Growth
                </div>
              </div>
              <div class="col-md-8">
                <div id="weeklyReportChart" style="min-height: 150px;"></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Pending Requests --}}
      <div class="col-12">
        <div class="hitech-card">
          <div class="hitech-card-header">
            <h5 class="title mb-0">Pending Requests Queue</h5>
          </div>
          <div class="card-body p-0">
            <div class="row g-0 text-center">
              <div class="col-6 col-md-3 p-4 border-end border-bottom">
                @if(\Illuminate\Support\Facades\Route::has('leaveRequests.index'))
                  <a href="{{ route('leaveRequests.index') }}" class="text-body d-block text-decoration-none">
                    <h3 class="mb-1 {{ $pendingLeaveRequests > 0 ? 'text-danger' : 'text-muted' }} fw-bold">{{ $pendingLeaveRequests }}</h3>
                    <p class="mb-0 small text-uppercase fw-bold text-muted">Leaves</p>
                  </a>
                @endif
              </div>
              <div class="col-6 col-md-3 p-4 border-end border-bottom">
                @if(\Illuminate\Support\Facades\Route::has('expenseRequests.index'))
                  <a href="{{ route('expenseRequests.index') }}" class="text-body d-block text-decoration-none">
                    <h3 class="mb-1 {{ $pendingExpenseRequests > 0 ? 'text-warning' : 'text-muted' }} fw-bold">{{ $pendingExpenseRequests }}</h3>
                    <p class="mb-0 small text-uppercase fw-bold text-muted">Expenses</p>
                  </a>
                @endif
              </div>
              <div class="col-6 col-md-3 p-4 border-end border-bottom">
                @if(\Illuminate\Support\Facades\Route::has('documentmanagement.index'))
                  <a href="{{ route('documentmanagement.index') }}" class="text-body d-block text-decoration-none">
                    <h3 class="mb-1 {{ $pendingDocumentRequests > 0 ? 'text-primary' : 'text-muted' }} fw-bold">{{ $pendingDocumentRequests }}</h3>
                    <p class="mb-0 small text-uppercase fw-bold text-muted">Documents</p>
                  </a>
                @endif
              </div>
              <div class="col-6 col-md-3 p-4 border-bottom">
                @if(\Illuminate\Support\Facades\Route::has('loan.index'))
                  <a href="{{ route('loan.index') }}" class="text-body d-block text-decoration-none">
                    <h3 class="mb-1 {{ $pendingLoanRequests > 0 ? 'text-info' : 'text-muted' }} fw-bold">{{ $pendingLoanRequests }}</h3>
                    <p class="mb-0 small text-uppercase fw-bold text-muted">Loans</p>
                  </a>
                @endif
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Company Availability (Who's Out Today) --}}
      <div class="col-12">
        <div class="hitech-card">
          <div class="hitech-card-header">
            <h5 class="title mb-0">Company Availability</h5>
            <span class="badge bg-label-secondary rounded-pill">{{ $teamOutToday->count() }} Out Today</span>
          </div>
          <div class="table-responsive">
            <table class="table table-borderless mb-0">
              <thead>
                <tr>
                  <th>Employee</th>
                  <th>Reason</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                @forelse($teamOutToday->take(5) as $request)
                  <tr>
                    <td>
                      <div class="d-flex align-items-center">
                        <div class="avatar avatar-xs me-2">
                          <span class="avatar-initial rounded-circle" style="background:rgba(0,90,90,0.1); color:#005a5a;">
                            {{ $request->user->getInitials() }}
                          </span>
                        </div>
                        <span class="fw-bold">{{ $request->user->full_name }}</span>
                      </div>
                    </td>
                    <td><span class="badge bg-label-secondary badge-hitech">{{ $request->leaveType->name ?? 'General' }}</span></td>
                    <td><span class="badge bg-label-success badge-hitech">Approved</span></td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="3" class="text-center text-muted py-4">Nobody is out today. Good coverage! 🎉</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </div>

  {{-- ===== RIGHT: Sidebar (col-xl-4) ===== --}}
  <div class="col-xl-4">
    <div class="row g-6">

      {{-- Holiday Card --}}
      <div class="col-12">
        <div class="holiday-card animate__animated animate__fadeInRight" style="animation-delay:0.05s">
          <i class="bx bx-party holiday-icon"></i>
          <div style="font-size:0.68rem; font-weight:700; color:rgba(255,255,255,0.55); text-transform:uppercase; letter-spacing:0.1em; margin-bottom:0.6rem;">Upcoming Holiday</div>
          @if($nextHoliday)
            <div style="font-size:1.15rem; font-weight:800; color:#fff; margin-bottom:4px;">{{ $nextHoliday->name }}</div>
            <div style="font-size:0.8rem; color:rgba(255,255,255,0.65);">{{ $nextHoliday->date->format('l, F jS') }}</div>
            <div class="holiday-chip mt-2">In {{ now()->diffInDays($nextHoliday->date) }} Days</div>
          @else
            <div style="font-size:0.875rem; color:rgba(255,255,255,0.6);">No upcoming holidays scheduled.</div>
          @endif
        </div>
      </div>

      {{-- Recent Activities --}}
      <div class="col-12">
        <div class="announce-card animate__animated animate__fadeInRight" style="animation-delay:0.12s">
          <div class="announce-header">
            <h6 class="mb-0">Recent Activities</h6>
          </div>
          <div class="announce-body p-0" style="max-height: 400px; overflow-y: auto;">
            <ul id="activityList" class="list-group list-group-flush border-0">
              <li class="list-group-item text-center py-4 text-muted small border-0">Loading activity log...</li>
            </ul>
          </div>
        </div>
      </div>

      {{-- Department Performance Chart --}}
      <div class="col-12">
        <div class="hitech-card">
          <div class="hitech-card-header">
            <h5 class="title mb-0">Department Performance</h5>
          </div>
          <div class="card-body">
            <div id="topDepartmentsChart"></div>
          </div>
        </div>
      </div>

    </div>
  </div>

</div>
@endsection
