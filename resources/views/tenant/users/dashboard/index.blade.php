@php
  $quotes = [
      ['text' => 'Success is the result of hard work, determination, and the courage to pursue greatness.'],
      ['text' => 'Teamwork divides the task and multiplies the success.'],
      ['text' => 'You don’t have to be perfect to make an impact; every small effort counts.'],
      ['text' => 'Productivity is not about doing more; it’s about focusing on what truly matters.'],
  ];
  $quote = $quotes[array_rand($quotes)];
  $quoteText = $quote['text'];
@endphp
@extends('layouts/layoutMaster')

@section('title', 'Strategic Command Center')

@section('vendor-style')
  @vite(['resources/assets/vendor/libs/apex-charts/apex-charts.scss'])
  @vite(['resources/assets/vendor/scss/pages/hitech-portal.scss'])
  <style>
    .admin-hero-new {
      background: linear-gradient(135deg, #004D4D 0%, #008080 100%);
      border-radius: 24px;
      padding: 3rem;
      position: relative;
      overflow: hidden;
      color: white;
      box-shadow: 0 20px 40px rgba(0, 77, 77, 0.2);
    }
    .hero-glass-overlay {
      position: absolute;
      top: 0; right: 0; bottom: 0; left: 0;
      background: radial-gradient(circle at 70% 20%, rgba(255,255,255,0.1) 0%, transparent 40%);
    }
    .keka-stat-card {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        border: 1px solid rgba(0, 77, 84, 0.05);
        transition: all 0.3s ease;
        text-align: left;
        box-shadow: 0 10px 30px -10px rgba(0,0,0,0.1);
    }
    .keka-stat-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 25px 50px -12px rgba(0, 77, 84, 0.15);
    }
    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 1.25rem;
    }
    .icon-teal { background: rgba(0, 128, 128, 0.1); color: #008080; }
    .icon-blue { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
    .icon-amber { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
    .icon-red { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

    .request-bubble {
        background: #f8fafc;
        border-radius: 16px;
        padding: 1.5rem;
        border: 1px solid #f1f5f9;
        text-align: center;
        transition: all 0.2s;
        text-decoration: none;
        display: block;
    }
    .request-bubble:hover {
        background: #004D4D;
        color: white !important;
        transform: scale(1.05);
    }
    .request-bubble:hover * { color: white !important; }
  </style>
@endsection

@section('content')
<div class="px-4">
  
  <!-- Hero Section -->
  <div class="row mb-6 animate__animated animate__fadeIn">
    <div class="col-12">
      <div class="admin-hero-new">
        <div class="hero-glass-overlay"></div>
        <div class="row align-items-center position-relative">
          <div class="col-lg-8">
            <span class="badge rounded-pill px-3 py-2 mb-4" style="background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.2); letter-spacing: 1px; font-weight: 700;">
              SYSTEM CONTROL CENTER : ADMIN ACCESS
            </span>
            <h1 class="display-5 fw-bold text-white mb-2">Welcome Back, {{ explode(' ', auth()->user()->full_name)[0] }}!</h1>
            <p class="fs-5 opacity-75 mb-0" style="max-width: 600px;">"{{$quoteText}}"</p>
          </div>
          <div class="col-lg-4 text-end d-none d-lg-block">
             <div class="date-badge-premium">
                <div class="fw-bold fs-3">{{ now()->format('d') }}</div>
                <div class="small fw-extrabold text-uppercase opacity-75">{{ now()->format('M Y') }}</div>
             </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Global Metrics -->
  <div class="row g-4 mb-6 animate__animated animate__fadeInUp">
    <div class="col-xl-3 col-md-6">
      <div class="keka-stat-card">
        <div class="stat-icon icon-teal"><i class="bx bx-group"></i></div>
        <h2 class="fw-extrabold text-dark mb-0">{{ $totalUser }}</h2>
        <div class="text-muted small fw-bold text-uppercase">Active Workforce</div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6">
      <div class="keka-stat-card">
        <div class="stat-icon icon-blue"><i class="bx bx-user-check"></i></div>
        <h2 class="fw-extrabold text-dark mb-0">{{ $todayPresentUsers }}</h2>
        <div class="text-muted small fw-bold text-uppercase">Present Today ({{ $totalUser > 0 ? round(($todayPresentUsers/$totalUser)*100) : 0 }}%)</div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6">
      <div class="keka-stat-card">
        <div class="stat-icon icon-amber"><i class="bx bx-calendar-event"></i></div>
        <h2 class="fw-extrabold text-dark mb-0">{{ $onLeaveUsersCount }}</h2>
        <div class="text-muted small fw-bold text-uppercase">On Leave Today</div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6">
      <div class="keka-stat-card">
        <div class="stat-icon icon-red"><i class="bx bx-user-x"></i></div>
        <h2 class="fw-extrabold text-danger mb-0">{{ $todayAbsentUsers }}</h2>
        <div class="text-muted small fw-bold text-uppercase">Absent / Unaccounted</div>
      </div>
    </div>
  </div>

  <div class="row g-6">
    <!-- Main Command Board -->
    <div class="col-xl-8">
      
      <!-- Productivity Hub -->
      <div class="hitech-card mb-6 animate__animated animate__fadeInLeft">
        <div class="hitech-card-header border-bottom">
           <h5 class="title mb-0">Productivity Analysis</h5>
           <div class="dropdown">
             <button class="btn btn-sm btn-label-teal dropdown-toggle" data-bs-toggle="dropdown">Last 7 Days</button>
           </div>
        </div>
        <div class="card-body p-5">
           <div class="row align-items-center">
             <div class="col-md-4 text-center border-end">
                <div class="text-muted small fw-bold mb-1">CUMULATIVE HOURS</div>
                <h1 class="display-4 fw-black text-teal mb-2">{{ $thisWeekWorkingHours }}h</h1>
                <div class="badge bg-label-success rounded-pill px-3">CONSOLIDATED GROWTH</div>
             </div>
             <div class="col-md-8 ps-md-5">
                <div id="weeklyReportChart" style="min-height: 200px;"></div>
             </div>
           </div>
        </div>
      </div>

      <!-- Quick Approvals Grid -->
      <div class="hitech-card animate__animated animate__fadeInUp">
        <div class="hitech-card-header h-auto py-4">
           <h5 class="title mb-0">Command Queue (Pending Approvals)</h5>
        </div>
        <div class="card-body">
            <div class="row g-4">
               <div class="col-md-3 col-6">
                  <a href="{{ route('leaveRequests.index') }}" class="request-bubble">
                     <h3 class="{{ $pendingLeaveRequests > 0 ? 'text-danger' : 'text-muted' }} fw-black mb-1">{{ $pendingLeaveRequests }}</h3>
                     <div class="small fw-bold text-uppercase text-muted">Leaves</div>
                  </a>
               </div>
               <div class="col-md-3 col-6">
                  <a href="{{ route('expenseRequests.index') }}" class="request-bubble">
                     <h3 class="{{ $pendingExpenseRequests > 0 ? 'text-warning' : 'text-muted' }} fw-black mb-1">{{ $pendingExpenseRequests }}</h3>
                     <div class="small fw-bold text-uppercase text-muted">Expenses</div>
                  </a>
               </div>
               <div class="col-md-3 col-6">
                  <a href="{{ route('documentmanagement.index') }}" class="request-bubble">
                     <h3 class="{{ $pendingDocumentRequests > 0 ? 'text-primary' : 'text-muted' }} fw-black mb-1">{{ $pendingDocumentRequests }}</h3>
                     <div class="small fw-bold text-uppercase text-muted">Documents</div>
                  </a>
               </div>
               <div class="col-md-3 col-6">
                  <a href="{{ route('loan.index') }}" class="request-bubble">
                     <h3 class="{{ $pendingLoanRequests > 0 ? 'text-info' : 'text-muted' }} fw-black mb-1">{{ $pendingLoanRequests }}</h3>
                     <div class="small fw-bold text-uppercase text-muted">Loans</div>
                  </a>
               </div>
            </div>
        </div>
      </div>

    </div>

    <!-- Operations Stream (Right) -->
    <div class="col-xl-4 flex-column d-flex gap-6">
      
      <!-- Holiday Alert -->
      <div class="holiday-card animate__animated animate__fadeInRight shadow-lg">
          <div class="d-flex align-items-center justify-content-between mb-4">
             <i class="bx bx-map-pin fs-3 text-white"></i>
             <div class="badge bg-white text-teal rounded-pill fw-bold" style="font-size: 0.65rem;">CALENDAR SYSTEM</div>
          </div>
          @if($nextHoliday)
            <h3 class="text-white fw-black mb-1">{{ $nextHoliday->name }}</h3>
            <p class="text-white opacity-75 small mb-4">{{ $nextHoliday->date->format('l, F d, Y') }}</p>
            <div class="d-flex align-items-center gap-3">
               <div class="flex-grow-1 bg-white bg-opacity-20 rounded-pill" style="height: 6px;">
                  <div class="bg-white rounded-pill" style="height: 6px; width: 45%;"></div>
               </div>
               <span class="text-white small fw-bold">{{ now()->diffInDays($nextHoliday->date) }}d</span>
            </div>
          @else
            <p class="text-white opacity-75">No upcoming company-wide holidays.</p>
          @endif
      </div>

      <!-- Activity Stream -->
      <div class="hitech-card flex-grow-1 animate__animated animate__fadeInUp">
          <div class="hitech-card-header border-bottom">
             <h6 class="title mb-0">Operations Stream</h6>
             <i class="bx bx-pulse text-teal animate__animated animate__heartBeat animate__infinite"></i>
          </div>
          <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
             <ul id="activityList" class="list-group list-group-flush">
                <li class="list-group-item text-center py-5">
                   <div class="spinner-border text-teal spinner-border-sm" role="status"></div>
                   <div class="text-muted small mt-2 fw-bold">Synchronizing Logs...</div>
                </li>
             </ul>
          </div>
      </div>

    </div>
  </div>

</div>
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/apex-charts/apexcharts.js'])
@endsection

@section('page-script')
  @vite(['resources/assets/js/app/dashboard-index.js'])
@endsection
