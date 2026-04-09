@extends('layouts/layoutMaster')

@section('title', 'Team Manager View')

@section('vendor-style')
@vite(['resources/assets/vendor/libs/animate-css/animate.scss'])
@vite(['resources/assets/vendor/scss/pages/hitech-portal.scss'])
@endsection
@section('content')
<div class="emp-hub-wrapper">
  <!-- Welcome Section -->
  <div class="emp-hero animate__animated animate__fadeIn">
    <div class="emp-hero-text">
      <div class="greeting">
        Good {{ now()->hour < 12 ? 'Morning' : (now()->hour < 17 ? 'Afternoon' : 'Evening') }},
        {{ auth()->user()->first_name }}! 👋
      </div>
      <div class="date-badge mt-2">
        <i class="bx bx-calendar" style="font-size:0.85rem;"></i>
        {{ now()->format('l, F jS') }}
      </div>
    </div>
    <div class="emp-hero-meta">
      <div class="hero-quick-stat">
        <div class="stat-value text-danger">{{ $pendingLeaveRequests }}</div>
        <div class="stat-label">Pending Leaves</div>
      </div>
      <div class="hero-quick-stat">
        <div class="stat-value text-warning">{{ $pendingExpenseRequests }}</div>
        <div class="stat-label">Pending Expenses</div>
      </div>
      <div class="hero-quick-stat">
        <div class="stat-value">{{ $teamOutToday->count() }}</div>
        <div class="stat-label">Team Out Today</div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-xl-8 col-lg-7">
      <div class="row g-4 mb-4">

    <!-- Team Availability Section -->
    <div class="hitech-card mb-6">
      <div class="hitech-card-header">
        <h5 class="title mb-0">Team Availability (Out Today)</h5>
        <span class="badge bg-label-secondary rounded-pill">{{ $teamOutToday->count() }} Out</span>
      </div>
      <div class="table-responsive text-nowrap">
        <table class="table table-borderless mb-0">
          <thead>
            <tr>
              <th>Employee</th>
              <th>Leave Type</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            @forelse($teamOutToday as $request)
              <tr>
                <td>
                  <div class="d-flex align-items-center">
                    <div class="avatar avatar-sm me-3">
                      @if($request->user->profile_picture)
                        <img src="{{ $request->user->getProfilePicture() }}" alt="Avatar" class="rounded-circle">
                      @else
                        <span class="avatar-initial rounded-circle bg-label-teal" style="background:rgba(0,90,90,0.1); color:#005a5a;">{{ $request->user->getInitials() }}</span>
                      @endif
                    </div>
                    <div>
                      <h6 class="mb-0 fw-bold">{{ $request->user->full_name }}</h6>
                      <small class="text-muted">{{ $request->user->designation->name ?? 'Staff' }}</small>
                    </div>
                  </div>
                </td>
                <td>
                  <span class="badge bg-label-secondary badge-hitech">{{ $request->leaveType->name ?? 'General' }}</span>
                </td>
                <td>
                  <span class="badge bg-label-success badge-hitech">Approved</span>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="3" class="text-center py-4 text-muted">Everyone is in today! 🚀</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <!-- Personal Stats Row -->
    <div class="row g-4 mt-2">
      <div class="col-12">
        <h5 class="mb-2 ms-1 fw-bold text-dark opacity-75">My Personal Stats</h5>
      </div>
      <div class="col-md-4 col-sm-6 animate__animated animate__fadeInUp" style="animation-delay: 0.15s">
        <div class="hitech-stat-card dashboard-variant card-teal pt-3 pb-3 text-center align-items-center h-100">
          <div class="stat-card-header justify-content-center w-100 mb-2">
             <div class="stat-icon-wrap icon-teal"><i class="bx bx-calendar-check"></i></div>
          </div>
          <div>
            <h3 class="stat-value mb-0">{{ $myLeavesCount }}</h3>
            <small class="stat-label text-muted">My Leaves</small>
          </div>
        </div>
      </div>
      <div class="col-md-4 col-sm-6 animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
        <div class="hitech-stat-card dashboard-variant card-amber pt-3 pb-3 text-center align-items-center h-100">
          <div class="stat-card-header justify-content-center w-100 mb-2">
             <div class="stat-icon-wrap icon-amber"><i class="bx bx-receipt"></i></div>
          </div>
          <div>
            <h3 class="stat-value mb-0">{{ $myExpensesCount }}</h3>
            <small class="stat-label text-muted">My Expenses</small>
          </div>
        </div>
      </div>
      <div class="col-md-4 col-sm-12 animate__animated animate__fadeInUp" style="animation-delay: 0.25s">
        <div class="hitech-stat-card dashboard-variant card-red pt-3 pb-3 text-center align-items-center h-100">
          <div class="stat-card-header justify-content-center w-100 mb-2">
             <div class="stat-icon-wrap icon-red"><i class="bx bx-error-circle"></i></div>
          </div>
          <div>
            <h3 class="stat-value mb-0 text-danger">{{ $mySOSCount }}</h3>
            <small class="stat-label text-muted">My SOS Alerts</small>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Sidebar: Holidays & News -->
  <div class="col-xl-4 col-lg-5">
    <div class="d-flex flex-column gap-4">
      <div class="col-12">
        <!-- Next Holiday -->
        <div class="holiday-card animate__animated animate__fadeInRight" style="animation-delay:0.05s">
          <i class="bx bx-party holiday-icon"></i>
          <div style="font-size:0.68rem; font-weight:700; color:rgba(255,255,255,0.55); text-transform:uppercase; letter-spacing:0.1em; margin-bottom:0.6rem;">Upcoming Holiday</div>
          @if($nextHoliday)
            <div style="font-size:1.15rem; font-weight:800; color:#fff; margin-bottom:4px;">{{ $nextHoliday->name }}</div>
            <div style="font-size:0.8rem; color:rgba(255,255,255,0.65);">{{ $nextHoliday->date->format('l, F jS') }}</div>
            <div class="holiday-chip mt-2">In {{ now()->diffInDays($nextHoliday->date) }} Days</div>
          @else
            <div style="font-size:0.875rem; color:rgba(255,255,255,0.6);">No upcoming holidays.</div>
          @endif
        </div>

        <!-- Announcements -->
        <div class="announce-card animate__animated animate__fadeInRight" style="animation-delay:0.12s">
          <div class="announce-header">
            <h6>Announcements</h6>
            <i class="bx bx-news" style="color:#94a3b8;"></i>
          </div>
          <div class="announce-body">
            @forelse($recentNotices as $notice)
              <div class="announce-item">
                <div class="announce-dot"></div>
                <div>
                  <div class="announce-title">{{ $notice->title }}</div>
                  <div class="announce-desc">{{ \Illuminate\Support\Str::limit($notice->description, 100) }}</div>
                </div>
              </div>
            @empty
              <div style="text-align:center; padding:2rem 1rem; color:#94a3b8; font-size:0.82rem;">
                <i class="bx bx-inbox" style="font-size:2rem; display:block; margin-bottom:0.5rem; opacity:0.4;"></i>
                No recent announcements.
              </div>
            @endforelse
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
@endsection
