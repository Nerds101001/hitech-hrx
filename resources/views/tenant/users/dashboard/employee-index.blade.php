@extends('layouts/layoutMaster')

@section('title', 'Employee Hub')

@section('vendor-style')
@vite(['resources/assets/vendor/libs/animate-css/animate.scss'])
@vite(['resources/assets/vendor/scss/pages/hitech-portal.scss'])
@endsection

@section('content')



<div class="emp-hub-wrapper">

  {{-- ============================================================ --}}
  {{-- HERO SECTION                                                  --}}
  {{-- ============================================================ --}}
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
        <div class="stat-value">{{ auth()->user()->available_leave_count ?? 0 }}</div>
        <div class="stat-label">Leave Days</div>
      </div>
      <div class="hero-quick-stat">
        <div class="stat-value">{{ $myAttendanceCount }}</div>
        <div class="stat-label">Attendance</div>
      </div>
      <div class="hero-quick-stat">
        <div class="stat-value">{{ $settings->currency_symbol ?? '₹' }}{{ number_format($latestNetSalary, 0) }}</div>
        <div class="stat-label">Last Pay</div>
      </div>
    </div>
  </div>

  <div class="row g-4">

    {{-- ============================================================ --}}
    {{-- LEFT: STAT CARDS + PAY RUN                                    --}}
    {{-- ============================================================ --}}
    <div class="col-xl-8 col-lg-7">
      <div class="row g-4">

        {{-- Leave Balance --}}
        <div class="col-sm-6 animate__animated animate__fadeInUp" style="animation-delay:0.05s">
          <div class="hitech-stat-card dashboard-variant card-teal">
            <div class="stat-card-header">
              <div class="stat-icon-wrap icon-teal"><i class="bx bx-calendar-check"></i></div>
              <a href="{{ route('user.leaves.index') }}" class="stat-card-link"><i class="bx bx-right-arrow-alt"></i></a>
            </div>
            <div>
              <div class="stat-card-label">Leave Balance</div>
              <div class="stat-card-value">{{ auth()->user()->available_leave_count ?? 0 }}</div>
              <div class="stat-card-sub">Days Available</div>
            </div>
          </div>
        </div>

        {{-- Attendance --}}
        <div class="col-sm-6 animate__animated animate__fadeInUp" style="animation-delay:0.1s">
          <div class="hitech-stat-card dashboard-variant card-blue">
            <div class="stat-card-header">
              <div class="stat-icon-wrap icon-blue"><i class="bx bx-time-five"></i></div>
              <a href="{{ route('user.attendance.index') }}" class="stat-card-link"><i class="bx bx-right-arrow-alt"></i></a>
            </div>
            <div>
              <div class="stat-card-label">Attendance</div>
              <div class="stat-card-value">{{ $myAttendanceCount }}</div>
              <div class="stat-card-sub">Records This Month</div>
            </div>
          </div>
        </div>

        {{-- Expenses --}}
        <div class="col-sm-6 animate__animated animate__fadeInUp" style="animation-delay:0.15s">
          <div class="hitech-stat-card dashboard-variant card-amber">
            <div class="stat-card-header">
              <div class="stat-icon-wrap icon-amber"><i class="bx bx-receipt"></i></div>
              <a href="{{ route('user.expenses.index') }}" class="stat-card-link"><i class="bx bx-right-arrow-alt"></i></a>
            </div>
            <div>
              <div class="stat-card-label">My Expenses</div>
              <div class="stat-card-value">{{ $myExpensesCount }}</div>
              <div class="stat-card-sub">Requests Submitted</div>
            </div>
          </div>
        </div>

        {{-- SOS Alerts --}}
        <div class="col-sm-6 animate__animated animate__fadeInUp" style="animation-delay:0.2s">
          <div class="hitech-stat-card dashboard-variant card-red">
            <div class="stat-card-header">
              <div class="stat-icon-wrap icon-red"><i class="bx bx-error-circle"></i></div>
              <a href="{{ route('user.sos.index') }}" class="stat-card-link"><i class="bx bx-right-arrow-alt"></i></a>
            </div>
            <div>
              <div class="stat-card-label">SOS Alerts</div>
              <div class="stat-card-value" style="color:#dc2626;">{{ $mySOSCount }}</div>
              <div class="stat-card-sub">Logs Sent</div>
            </div>
          </div>
        </div>

        {{-- Last Pay Run --}}
        <div class="col-12 animate__animated animate__fadeInUp" style="animation-delay:0.25s">
          <div class="payrun-card">
            <div>
              <div class="payrun-label">Last Pay Run</div>
              <div class="payrun-value">
                {{ $settings->currency_symbol ?? '₹' }}{{ number_format($latestNetSalary, 2) }}
              </div>
              @if($payrollTrend != 0)
                <span class="badge mt-1 {{ $payrollTrend > 0 ? 'bg-success' : 'bg-danger' }} rounded-pill" style="font-size:0.7rem;">
                  <i class="bx {{ $payrollTrend > 0 ? 'bx-trending-up' : 'bx-trending-down' }} me-1"></i>
                  {{ abs(round($payrollTrend, 1)) }}% vs last month
                </span>
              @endif
            </div>
            <a href="{{ route('user.payroll.index') }}" class="btn-hitech">
              <i class="bx bx-download"></i> View Payslips
            </a>
          </div>
        </div>

      </div>
    </div>

    {{-- ============================================================ --}}
    {{-- RIGHT SIDEBAR: HOLIDAY + ANNOUNCEMENTS                        --}}
    {{-- ============================================================ --}}
    <div class="col-xl-4 col-lg-5">
      <div class="d-flex flex-column gap-4">

        {{-- Upcoming Holiday --}}
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

        {{-- Announcements --}}
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
                  <div class="announce-desc">{{ $notice->description }}</div>
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
