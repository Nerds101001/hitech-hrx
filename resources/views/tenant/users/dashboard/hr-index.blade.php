@extends('layouts/layoutMaster')

@section('title', __('HR Management Hub'))

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/select2/select2.scss',
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/libs/apex-charts/apex-charts.scss'
  ])
  @vite(['resources/assets/vendor/scss/pages/hitech-portal.scss'])
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap">
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/select2/select2.full.min.js',
    'resources/assets/vendor/libs/apex-charts/apex-charts.min.js',
  ])
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
  // 1. Hiring Trends
  const hiringTrendsChart = new ApexCharts(document.querySelector("#hiringTrendsChart"), {
    series: [{
      name: 'Hires',
      data: {!! json_encode($hiringTrend['hires'] ?? []) !!}
    }, {
      name: 'Attrition',
      data: {!! json_encode($hiringTrend['attrition'] ?? []) !!}
    }],
    chart: { type: 'area', height: 350, toolbar: { show: false }, animations: { enabled: true, easing: 'linear', speed: 800 } },
    colors: ['#00897b', '#dc2626'],
    stroke: { curve: 'smooth', width: 3 },
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.05 } },
    xaxis: { categories: {!! json_encode($hiringTrend['labels'] ?? []) !!}, labels: { style: { colors: '#94a3b8' } } },
    yaxis: { labels: { style: { colors: '#94a3b8' } } },
    grid: { borderColor: '#f1f5f9' },
    dataLabels: { enabled: false }
  });
  if(document.querySelector("#hiringTrendsChart")) hiringTrendsChart.render();

  // 2. Department Distribution
  const deptData = {!! json_encode($departmentData) !!};
  const departmentChart = new ApexCharts(document.querySelector("#departmentChart"), {
    series: deptData.map(d => d.count || 0),
    chart: { type: 'donut', height: 280 },
    labels: deptData.map(d => d.name),
    colors: ['#004D4D', '#00897b', '#00D2D2', '#D1FAE5'],
    legend: { position: 'bottom', horizontalAlign: 'center' },
    plotOptions: {
      pie: {
        donut: {
          size: '75%',
          labels: {
            show: true,
            total: {
              show: true,
              label: 'UNITS',
              formatter: () => '{{ $departmentData->count() }}',
              fontSize: '14px',
              fontWeight: 800
            }
          }
        }
      }
    }
  });
  if(document.querySelector("#departmentChart")) departmentChart.render();
});
</script>
@endsection

@section('content')

<div class="row g-6">

  {{-- ===== ROW 1: HERO & CORE STATS ===== --}}
  <div class="col-lg-12">
    <div class="admin-hero animate__animated animate__fadeIn">
      <div class="admin-hero-text">
        <div class="greeting">Strategic Command Center</div>
        <div class="sub-text">Welcome back, {{ auth()->user()->first_name }}! Monitoring real-time operations for {{ $activeEmployees }} staff.</div>
      </div>
      <div class="d-none d-md-block text-end">
        <div class="date-badge" style="background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.15); color: #fff; padding: 6px 16px; border-radius: 50px; font-size: 0.85rem; font-weight: 600; backdrop-filter: blur(8px);">
          <i class="bx bx-calendar me-2"></i>{{ now()->format('l, M j, Y') }}
        </div>
      </div>
    </div>
  </div>

  {{-- TOP 4 CARDS --}}
  <div class="col-xl-3 col-md-6 col-sm-6 animate__animated animate__fadeInUp" style="animation-delay: 0.05s">
    <div class="hitech-stat-card dashboard-variant card-teal h-100 uniform-card shadow-sm">
      <div class="stat-card-header">
        <div class="stat-icon-wrap icon-teal"><i class="bx bx-group"></i></div>
        <div class="trend-indicator text-success"><i class="bx bx-trending-up me-1"></i>{{ $trends['totalStaff']['value'] }}</div>
      </div>
      <div>
        <h3 class="stat-value mb-1">{{ $totalUser }}</h3>
        <div class="d-flex justify-content-between align-items-center">
            <small class="text-muted fw-bold">Total Staff</small>
            <span class="badge bg-label-success rounded-pill" style="font-size: 0.6rem;">+{{ $newHiresThisMonth }} New</span>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-3 col-md-6 col-sm-6 animate__animated animate__fadeInUp" style="animation-delay: 0.1s">
    <div class="hitech-stat-card dashboard-variant card-blue h-100 uniform-card shadow-sm">
      <div class="stat-card-header">
        <div class="stat-icon-wrap icon-blue"><i class="bx bx-user-check"></i></div>
        <div class="trend-indicator text-primary"><i class="bx bx-check-circle me-1"></i>{{ round(($todayPresentUsers/max($active, 1))*100) }}%</div>
      </div>
      <div>
        <h3 class="stat-value mb-1">{{ $todayPresentUsers }}</h3>
        <div class="d-flex justify-content-between align-items-center">
            <small class="text-muted fw-bold">Present Today</small>
            <span class="text-danger fw-bold" style="font-size: 0.75rem;">{{ $absentCount }} Absent</span>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-3 col-md-6 col-sm-6 animate__animated animate__fadeInUp" style="animation-delay: 0.15s">
    <div class="hitech-stat-card dashboard-variant card-amber h-100 uniform-card shadow-sm">
      <div class="stat-card-header">
        <div class="stat-icon-wrap icon-amber"><i class="bx bx-calendar-minus"></i></div>
        <div class="trend-indicator text-warning">{{ $trends['leaves']['value'] }} Today</div>
      </div>
      <div>
        <h3 class="stat-value mb-1">{{ $onLeaveUsersCount }}</h3>
        <small class="text-muted fw-bold">On Leave Today</small>
      </div>
    </div>
  </div>

  <div class="col-xl-3 col-md-6 col-sm-6 animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
    <div class="hitech-stat-card dashboard-variant card-red h-100 uniform-card shadow-sm">
      <div class="stat-card-header">
        <div class="stat-icon-wrap icon-red"><i class="bx bx-briefcase"></i></div>
        <div class="trend-indicator text-danger"><i class="bx bx-plus-circle me-1"></i>{{ $trends['openings']['value'] }}</div>
      </div>
      <div>
        <h3 class="stat-value mb-1" style="color:#005a5a;">{{ $activeJobsCount }}</h3>
        <small class="text-muted fw-bold">Active Openings</small>
      </div>
    </div>
  </div>

  {{-- ===== ROW 2: CELEBRATIONS & PENDING ===== --}}
  <div class="col-xl-4 col-md-6">
    <div class="hitech-stat-card dashboard-variant card-teal h-100 uniform-card shadow-sm animate__animated animate__fadeInUp" style="animation-delay: 0.25s">
      <div class="hitech-card-header-inner mb-4 d-flex justify-content-between">
        <div class="d-flex align-items-center gap-3">
          <div class="icon-wrap-sm bg-pink-light text-pink"><i class="bx bx-cake"></i></div>
          <h5 class="title mb-0">Birthdays</h5>
        </div>
        <a href="javascript:void(0);" class="small fw-bold text-teal">View All</a>
      </div>
      <div class="scroll-container-fixed">
        @php
            $todayBirthdays = $upcomingBirthdays->filter(fn($u) => $u->is_today);
            $nextBirthdays = $upcomingBirthdays->filter(fn($u) => !$u->is_today);
        @endphp

        @if($todayBirthdays->count() > 0)
            <div class="section-label small fw-bold text-uppercase text-pink mb-3" style="font-size: 0.65rem; letter-spacing: 1px;">Today</div>
            @foreach($todayBirthdays as $u)
            <div class="celeb-row d-flex align-items-center gap-3 mb-4 pulse-item">
              <img src="{{ $u->getProfilePicture() ?? asset('assets/img/avatars/1.png') }}" class="rounded-circle border border-pink" width="45" height="45" onerror="this.src='{{ asset('assets/img/avatars/1.png') }}'">
              <div>
                <h6 class="mb-0 fw-bold small-text text-truncate" style="max-width: 150px;">{{ $u->name }}</h6>
                <div class="date-sub-text" style="color: #ef4444;">{{ \Carbon\Carbon::parse($u->dob)->format('M d') }} • <span class="badge bg-label-pink rounded-pill p-1 px-2">HAPPY BIRTHDAY!</span></div>
              </div>
            </div>
            @endforeach
            @if($nextBirthdays->count() > 0)
                <div class="border-bottom border-light my-4"></div>
                <div class="section-label small fw-bold text-uppercase text-muted mb-3" style="font-size: 0.65rem; letter-spacing: 1px;">Upcoming</div>
            @endif
        @else
            <div class="section-label small fw-bold text-uppercase text-muted mb-3" style="font-size: 0.65rem; letter-spacing: 1px;">Upcoming</div>
        @endif

        @forelse($nextBirthdays as $u)
        <div class="celeb-row d-flex align-items-center gap-3 mb-4">
          <img src="{{ $u->getProfilePicture() ?? asset('assets/img/avatars/1.png') }}" class="rounded-circle" width="45" height="45" onerror="this.src='{{ asset('assets/img/avatars/1.png') }}'">
          <div>
            <h6 class="mb-0 fw-bold small-text text-truncate" style="max-width: 150px;">{{ $u->name }}</h6>
            <div class="date-sub-text">{{ \Carbon\Carbon::parse($u->dob)->format('M d') }}</div>
          </div>
        </div>
        @empty
          @if($todayBirthdays->count() == 0)
            <p class="text-muted small py-4 text-center">No imminent birthdays.</p>
          @endif
        @endforelse
      </div>
    </div>
  </div>

  <div class="col-xl-4 col-md-6">
    <div class="hitech-stat-card dashboard-variant card-blue h-100 uniform-card shadow-sm animate__animated animate__fadeInUp" style="animation-delay: 0.3s">
      <div class="hitech-card-header-inner mb-4 d-flex justify-content-between">
        <div class="d-flex align-items-center gap-3">
          <div class="icon-wrap-sm bg-purple-light text-purple"><i class="bx bx-party"></i></div>
          <h5 class="title mb-0">Work Anniversaries</h5>
        </div>
        <a href="javascript:void(0);" class="small fw-bold text-teal">View All</a>
      </div>
      <div class="scroll-container-fixed">
        @php
            $todayAnniversaries = $upcomingAnniversaries->filter(fn($u) => $u->is_today);
            $nextAnniversaries = $upcomingAnniversaries->filter(fn($u) => !$u->is_today);
        @endphp

        @if($todayAnniversaries->count() > 0)
            <div class="section-label small fw-bold text-uppercase text-purple mb-3" style="font-size: 0.65rem; letter-spacing: 1px;">Today</div>
            @foreach($todayAnniversaries as $u)
            <div class="celeb-row d-flex align-items-center gap-3 mb-4 pulse-item">
              <img src="{{ $u->getProfilePicture() ?? asset('assets/img/avatars/1.png') }}" class="rounded-circle border border-purple" width="45" height="45" onerror="this.src='{{ asset('assets/img/avatars/1.png') }}'">
              <div>
                <h6 class="mb-0 fw-bold small-text text-truncate" style="max-width: 150px;">{{ $u->name }}</h6>
                <div class="date-sub-text" style="color: #7c3aed;">
                    {{ floor(\Carbon\Carbon::parse($u->date_of_joining)->diffInYears(now())) }} YRS • 
                    <span class="badge bg-label-secondary rounded-pill p-1 px-2">CONGRATS!</span>
                </div>
              </div>
            </div>
            @endforeach
            @if($nextAnniversaries->count() > 0)
                <div class="border-bottom border-light my-4"></div>
                <div class="section-label small fw-bold text-uppercase text-muted mb-3" style="font-size: 0.65rem; letter-spacing: 1px;">Upcoming</div>
            @endif
        @else
            <div class="section-label small fw-bold text-uppercase text-muted mb-3" style="font-size: 0.65rem; letter-spacing: 1px;">Upcoming</div>
        @endif

        @forelse($nextAnniversaries as $u)
        <div class="celeb-row d-flex align-items-center gap-3 mb-4">
          <img src="{{ $u->getProfilePicture() ?? asset('assets/img/avatars/1.png') }}" class="rounded-circle" width="45" height="45" onerror="this.src='{{ asset('assets/img/avatars/1.png') }}'">
          <div>
            <h6 class="mb-0 fw-bold small-text text-truncate" style="max-width: 150px;">{{ $u->name }}</h6>
            <div class="date-sub-text">{{ floor(\Carbon\Carbon::parse($u->date_of_joining)->diffInYears(now())) }} YRS • {{ \Carbon\Carbon::parse($u->date_of_joining)->format('M d') }}</div>
          </div>
        </div>
        @empty
          @if($todayAnniversaries->count() == 0)
            <p class="text-muted small py-4 text-center">No imminent anniversaries.</p>
          @endif
        @endforelse
      </div>
    </div>
  </div>

  <div class="col-xl-4 col-md-12">
    <div class="hitech-stat-card dashboard-variant card-amber h-100 uniform-card shadow-sm animate__animated animate__fadeInUp" style="animation-delay: 0.35s">
      <div class="hitech-card-header-inner mb-4 d-flex justify-content-between">
        <div class="d-flex align-items-center gap-3">
          <div class="icon-wrap-sm bg-blue-light text-blue"><i class="bx bx-time"></i></div>
          <h5 class="title mb-0">Pending Approvals</h5>
        </div>
        <a href="{{ route('leaveRequests.index') }}" class="small fw-bold text-teal">Manage</a>
      </div>
      <div class="scroll-container-fixed">
        @forelse($pendingApprovals->take(5) as $approval)
        <div class="d-flex align-items-center justify-content-between mb-4">
          <div class="d-flex align-items-center gap-3">
             <img src="{{ $approval['avatar'] }}" class="rounded-circle" width="45" height="45" onerror="this.src='{{ asset('assets/img/avatars/1.png') }}'">
             <div>
               <h6 class="mb-0 fw-bold small-text text-truncate" style="max-width: 150px;">{{ $approval['user'] }} <span class="text-muted" style="font-size: 0.75rem;">(#{{ $approval['emp_id'] }})</span></h6>
               <small class="text-muted text-uppercase d-block" style="font-size: 0.65rem; letter-spacing: 0.5px;">{{ $approval['type'] }} • {{ $approval['department'] }}</small>
               @if(isset($approval['days']))
                 <small class="text-teal fw-bold" style="font-size: 0.7rem;">{{ $approval['days'] }} Days Request</small>
               @endif
             </div>
          </div>
          <div class="d-flex gap-2">
            <a href="{{ route('leaveRequests.index') }}" class="btn btn-sm btn-icon btn-success shadow-none" title="Manage"><i class="bx bx-check"></i></a>
            <a href="{{ route('leaveRequests.index') }}" class="btn btn-sm btn-icon btn-danger shadow-none" title="Manage"><i class="bx bx-x"></i></a>
          </div>
        </div>
        @empty
          <p class="text-muted small py-4 text-center">Consistent! 0 pending tasks.</p>
        @endforelse
      </div>
    </div>
  </div>

  {{-- ===== ROW 3: STRATEGIC CHARTS ===== --}}
  <div class="col-xl-8">
    <div class="hitech-stat-card dashboard-variant card-teal uniform-card shadow-sm animate__animated animate__fadeInUp" style="animation-delay: 0.4s">
       <div class="hitech-card-header-inner mb-4">
         <h5 class="title mb-0">Hiring & Attrition Trends</h5>
         <small class="text-muted">Dynamic workforce analysis (Last 12 Months)</small>
       </div>
       <div style="min-height: 350px;">
         <div id="hiringTrendsChart"></div>
       </div>
    </div>
  </div>

  <div class="col-xl-4">
    <div class="hitech-stat-card dashboard-variant card-blue uniform-card shadow-sm animate__animated animate__fadeInUp" style="animation-delay: 0.45s">
       <div class="hitech-card-header-inner mb-4">
         <h5 class="title mb-0">Organization Structure</h5>
       </div>
       <div class="d-flex flex-column align-items-center justify-content-center" style="min-height: 350px;">
         <div id="departmentChart"></div>
         <div class="dept-legend-custom w-100 mt-4 px-3">
            @foreach($departmentData->take(4) as $index => $dept)
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="small fw-bold text-muted"><span class="dot" style="background: {{ ['#004D4D', '#00897b', '#00D2D2', '#D1FAE5'][$index % 4] }}"></span> {{ $dept['name'] }}</span>
              <span class="small fw-bold text-heading">{{ $dept['count'] }}</span>
            </div>
            @endforeach
         </div>
       </div>
    </div>
  </div>

  {{-- ROW 4: TRIPLE LISTS (CANDIDATES, JOBS, ANNOUNCEMENTS/HOLIDAYS) --}}
  <div class="col-xl-4">
    <div class="hitech-stat-card dashboard-variant card-teal uniform-card shadow-sm animate__animated animate__fadeInUp" style="animation-delay: 0.5s">
      <div class="hitech-card-header-inner mb-4 d-flex justify-content-between align-items-center">
        <h5 class="title mb-0">Candidates</h5>
        <a href="{{ route('job-application.index') }}" class="small fw-bold text-teal">View All</a>
      </div>
      <div class="list-scroll-p pe-2">
        @foreach($topCandidates as $candidate)
        <div class="d-flex align-items-center justify-content-between mb-4 pb-3 border-bottom border-light">
          <div class="d-flex align-items-center gap-3">
            <img src="{{ asset('assets/img/avatars/'.rand(1, 10).'.png') }}" class="rounded-circle shadow-sm" width="40" height="40" onerror="this.src='{{ asset('assets/img/avatars/1.png') }}'">
            <div>
              <h6 class="mb-0 fw-bold small-text text-truncate" style="max-width: 120px;">{{ $candidate->name }}</h6>
              <small class="text-muted text-truncate d-block" style="max-width: 120px;">{{ $candidate->jobs->title ?? 'New' }}</small>
            </div>
          </div>
          <span class="badge bg-label-teal rounded-pill p-1 px-2 fw-bold" style="font-size: 0.65rem;">{{ $candidate->stage->title ?? 'Applied' }}</span>
        </div>
        @endforeach
      </div>
    </div>
  </div>

  <div class="col-xl-4">
    <div class="hitech-stat-card dashboard-variant card-blue uniform-card shadow-sm animate__animated animate__fadeInUp" style="animation-delay: 0.55s">
      <div class="hitech-card-header-inner mb-4 d-flex justify-content-between align-items-center">
        <h5 class="title mb-0">Open Positions</h5>
        <a href="{{ url('job') }}" class="small fw-bold text-teal">View All</a>
      </div>
      <div class="list-scroll-p pe-2">
        @foreach($activeJobs as $job)
        <div class="d-flex align-items-center justify-content-between mb-4 pb-3 border-bottom border-light">
          <div class="d-flex align-items-center gap-3">
            <div class="icon-wrap-sm bg-teal-light text-teal rounded-circle" style="width:40px; height:40px;"><i class="bx bx-briefcase fs-5"></i></div>
            <div>
              <h6 class="mb-0 fw-bold small-text text-truncate" style="max-width: 150px;">{{ $job->title }}</h6>
              <small class="text-muted">{{ $job->applications_count }} Applicants</small>
            </div>
          </div>
          <span class="badge bg-label-success rounded-pill p-1 px-2 fw-bold" style="font-size: 0.65rem;">Active</span>
        </div>
        @endforeach
      </div>
    </div>
  </div>

  <div class="col-xl-4">
    <div class="hitech-stat-card dashboard-variant card-amber uniform-card shadow-sm animate__animated animate__fadeInUp" style="animation-delay: 0.6s">
      <div class="hitech-card-header-inner mb-4 d-flex justify-content-between align-items-center">
        <h5 class="title mb-0">Notice & Holidays</h5>
        <a href="{{ url('announcements') }}" class="small fw-bold text-teal">All</a>
      </div>
      <div class="list-scroll-p pe-2">
        {{-- Mix of Announcements and Holidays --}}
        @foreach($announcements->take(2) as $ann)
        <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom border-light">
          <div class="icon-wrap-sm bg-blue-light text-blue rounded-circle" style="width:40px; height:40px;"><i class="bx bx-megaphone fs-5"></i></div>
          <div>
            <h6 class="mb-0 fw-bold small-text text-truncate" style="max-width: 180px;">{{ $ann->title }}</h6>
            <small class="text-primary fw-bold" style="font-size: 0.7rem;">ANNOUNCEMENT</small>
          </div>
        </div>
        @endforeach
        @foreach($upcomingHolidays->take(3) as $hol)
        <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom border-light">
          <div class="icon-wrap-sm bg-amber-light text-amber rounded-circle" style="width:40px; height:40px; background: #fffbeb; color: #d97706;"><i class="bx bx-calendar-event fs-5"></i></div>
          <div>
            <h6 class="mb-0 fw-bold small-text text-truncate" style="max-width: 180px;">{{ $hol->name }}</h6>
            <small class="text-warning fw-bold" style="font-size: 0.7rem;">{{ \Carbon\Carbon::parse($hol->date)->format('M d') }} • HOLIDAY</small>
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </div>

</div>

{{-- PIXEL PERFECT FAB --}}
<div class="fab-container-p">
  <button class="fab-main-p shadow-lg" id="fabToggle">
    <i class="bx bx-plus"></i>
  </button>
  <div class="fab-options-p" id="fabMenu">
    <a href="javascript:void(0);" class="fab-btn-p" data-label="Onboarding" data-bs-toggle="modal" data-bs-target="#onboardingInviteModal"><i class="bx bx-paper-plane"></i></a>
    <a href="{{ route('job-application.create') }}" class="fab-btn-p" data-label="Add Candidate"><i class="bx bx-user-plus"></i></a>
    <a href="{{ route('job.create') }}" class="fab-btn-p" data-label="Post Job"><i class="bx bx-briefcase-alt-2"></i></a>
    <a href="{{ url('holidays') }}" class="fab-btn-p" data-label="Holiday"><i class="bx bx-calendar-star"></i></a>
    <a href="{{ url('announcements') }}" class="fab-btn-p" data-label="Announce"><i class="bx bx-bell"></i></a>
  </div>
</div>

{{-- MODALS --}}
@include('tenant.employees.onboarding_invite_modal')

<style>
  /* UNIFIED 1PX SOLID BORDER DESIGN */
  .uniform-card {
    border-radius: 20px !important;
    border: 1px solid #e2e8f0 !important; /* Standard Grey Border */
    background: #ffffff !important;
    transition: all 0.3s ease !important;
    padding: 1.5rem !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03) !important;
  }
  .uniform-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.06) !important;
  }
  
  .hitech-stat-card.dashboard-variant::after {
    border-radius: 20px 20px 0 0 !important;
  }
  
  /* SCROLL SYSTEMS */
  .scroll-container-fixed { height: 160px; overflow-y: auto; scrollbar-width: none; }
  .scroll-container-fixed::-webkit-scrollbar { display: none; }
  .list-scroll-p { height: 320px; overflow-y: auto; scrollbar-width: none; }
  .list-scroll-p::-webkit-scrollbar { display: none; }

  /* COMPONENT STYLES */
  .trend-indicator { font-size: 0.75rem; font-weight: 800; display: flex; align-items: center; }
  .title { font-size: 1rem; color: #1e293b; font-weight: 800; letter-spacing: -0.01em; }
  .small-text { font-size: 0.9rem; color: #1e293b; }
  .date-sub-text { font-size: 0.7rem; color: #ef4444; font-weight: 800; text-transform: uppercase; margin-top: 2px; }

  .icon-wrap-sm { border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
  .bg-pink-light { background: #fee2e2; color: #ef4444; }
  .bg-purple-light { background: #f3e8ff; color: #7c3aed; }
  .bg-blue-light { background: #e0f2fe; color: #0284c7; }
  .bg-teal-light { background: #e0f2f2; color: #008080; }
  .dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 6px; }

  /* CELEBRATION ITEMS */
  .pulse-item { animation: subpulse 2s infinite; }
  @keyframes subpulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.02); }
    100% { transform: scale(1); }
  }
  .bg-label-pink { background-color: #fef2f2 !important; color: #ef4444 !important; }

  /* FAB SPEED DIAL */
  .fab-container-p { position: fixed; bottom: 2rem; right: 2rem; z-index: 1000; display: flex; flex-direction: column-reverse; align-items: center; gap: 0.8rem; }
  .fab-main-p { 
    width: 60px; height: 60px; border-radius: 50%; background: #004D4D; color: white; border: none; font-size: 1.8rem;
    display: flex; align-items: center; justify-content: center; transition: all 0.3s ease; cursor: pointer;
  }
  .fab-main-p:hover { transform: scale(1.1) rotate(45deg); }
  .fab-options-p { display: flex; flex-direction: column-reverse; gap: 0.8rem; opacity: 0; transform: translateY(20px); pointer-events: none; transition: all 0.3s ease; }
  .fab-container-p:hover .fab-options-p { opacity: 1; transform: translateY(0); pointer-events: auto; }
  .fab-btn-p { 
    width: 45px; height: 45px; border-radius: 50%; background: #fff; color: #004D4D; border: 1px solid #edf2f7; 
    display: flex; align-items: center; justify-content: center; font-size: 1.2rem; text-decoration: none;
  }
  .fab-btn-p:hover { background: #004D4D; color: #fff; }
  .fab-btn-p::before {
    content: attr(data-label); position: absolute; right: 55px; background: #334155; color: #fff; padding: 4px 10px;
    border-radius: 6px; font-size: 0.65rem; white-space: nowrap; opacity: 0; transition: opacity 0.3s;
  }
  .fab-btn-p:hover::before { opacity: 1; }
</style>

@endsection
