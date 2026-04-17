@extends('layouts/layoutMaster')

@section('title', 'HR Command Hub')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/select2/select2.scss',
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/libs/apex-charts/apex-charts.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
  <style>
    .hr-hero-premium {
      background: linear-gradient(135deg, #005A5A 0%, #008080 100%);
      border-radius: 24px;
      padding: 2.5rem;
      color: white;
      box-shadow: 0 15px 35px rgba(0, 90, 90, 0.2);
    }
    .keka-stat-card {
        background: white; border-radius: 20px; padding: 1.5rem; border: 1px solid rgba(0, 77, 84, 0.05);
        transition: all 0.3s ease; box-shadow: 0 10px 30px -10px rgba(0,0,0,0.1);
    }
    .keka-stat-card:hover { transform: translateY(-5px); box-shadow: 0 20px 40px rgba(0, 77, 84, 0.1); }
    
    .stat-icon-hr { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; margin-bottom: 1rem; }
    .bg-soft-teal { background: rgba(0, 128, 128, 0.1); color: #008080; }
    .bg-soft-blue { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
    .bg-soft-amber { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
    .bg-soft-purple { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
  </style>
@endsection

@section('content')
<div class="px-3">
  
  <!-- Hero Section -->
  <div class="row mb-5 animate__animated animate__fadeIn">
    <div class="col-12">
      <div class="hr-hero-premium">
        <div class="row align-items-center">
          <div class="col-lg-8">
            <span class="badge rounded-pill bg-white bg-opacity-20 px-3 py-2 mb-3 fw-bold">PEOPLE OPERATIONS : HR HUB</span>
            <h1 class="display-6 fw-bold text-white mb-2">Strategic HR Command</h1>
            <p class="fs-5 opacity-75 mb-0">Managing <b>{{ $totalUser }}</b> employees and monitoring organizational health in real-time.</p>
          </div>
          <div class="col-lg-4 text-end d-none d-lg-block">
             <button class="btn btn-white rounded-pill px-4 fw-bold shadow-lg" data-bs-toggle="modal" data-bs-target="#onboardingInviteModal">
                <i class="bx bx-user-plus me-1"></i> New Onboarding
             </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Primary Metrics Row -->
  <div class="row g-4 mb-5 animate__animated animate__fadeInUp">
    <div class="col-xl-3 col-md-6">
      <div class="keka-stat-card">
        <div class="stat-icon-hr bg-soft-teal"><i class="bx bx-group"></i></div>
        <div class="d-flex justify-content-between align-items-end">
           <div>
              <h2 class="fw-black text-dark mb-0">{{ $totalUser }}</h2>
              <div class="text-muted small fw-bold text-uppercase">Headcount</div>
           </div>
           <div class="text-success small fw-bold"><i class="bx bx-up-arrow-alt"></i> {{ $newHiresThisMonth }} New</div>
        </div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6">
      <div class="keka-stat-card">
        <div class="stat-icon-hr bg-soft-blue"><i class="bx bx-check-double"></i></div>
        <div class="d-flex justify-content-between align-items-end">
           <div>
              <h2 class="fw-black text-dark mb-0">{{ $todayPresentUsers }}</h2>
              <div class="text-muted small fw-bold text-uppercase">Check-ins</div>
           </div>
           <div class="text-primary small fw-bold">{{ $totalUser > 0 ? round(($todayPresentUsers/$totalUser)*100) : 0 }}% active</div>
        </div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6">
      <div class="keka-stat-card">
        <div class="stat-icon-hr bg-soft-amber"><i class="bx bx-calendar-minus"></i></div>
        <div class="d-flex justify-content-between align-items-end">
           <div>
              <h2 class="fw-black text-dark mb-0">{{ $onLeaveUsersCount }}</h2>
              <div class="text-muted small fw-bold text-uppercase">On Leave</div>
           </div>
           <div class="text-warning small fw-bold">Live Status</div>
        </div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6">
      <div class="keka-stat-card">
        <div class="stat-icon-hr bg-soft-purple"><i class="bx bx-timer"></i></div>
        <div class="d-flex justify-content-between align-items-end">
           <div>
              <h2 class="fw-black text-dark mb-0">{{ $upcomingProbationEnds->count() }}</h2>
              <div class="text-muted small fw-bold text-uppercase">Probation Ends</div>
           </div>
           <span class="badge bg-label-secondary rounded-pill fw-bold" style="font-size: 0.6rem;">Action Ready</span>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-5">
    <!-- Main HR Operations -->
    <div class="col-xl-9">
       
       <!-- Hiring & Lifecycle Trend -->
       <div class="hitech-card mb-5">
          <div class="hitech-card-header border-bottom">
             <h5 class="title mb-0">Hiring & Retention Trends</h5>
             <small class="text-muted fw-bold">Workforce analysis over the last 12 months</small>
          </div>
          <div class="card-body">
             <div id="hiringTrendsChart" style="min-height: 350px;"></div>
          </div>
       </div>

       <!-- Pending Approvals Queue -->
       <div class="hitech-card">
          <div class="hitech-card-header border-bottom">
             <h5 class="title mb-0">Pending Approvals Queue</h5>
             <a href="{{ route('approvals.index') }}" class="btn btn-sm btn-label-teal">View Full Queue</a>
          </div>
          <div class="card-body p-0">
             <div class="table-responsive">
                <table class="table table-hover">
                   <thead class="bg-light-soft">
                      <tr>
                         <th class="small fw-bold">Employee</th>
                         <th class="small fw-bold">Request Type</th>
                         <th class="small fw-bold">Requested On</th>
                         <th class="small fw-bold text-end">Action</th>
                      </tr>
                   </thead>
                   <tbody>
                      @forelse($pendingApprovals->take(5) as $approval)
                      <tr>
                         <td>
                            <div class="d-flex align-items-center gap-2">
                               <img src="{{ $approval['avatar'] }}" class="rounded-circle" width="32" onerror="this.src='{{ asset('assets/img/avatars/1.png') }}'">
                               <div>
                                  <div class="fw-bold text-dark small">{{ $approval['user'] }}</div>
                                  <div class="text-muted" style="font-size: 10px;">{{ $approval['emp_id'] }} • {{ $approval['department'] }}</div>
                               </div>
                            </div>
                         </td>
                         <td><span class="badge bg-label-teal rounded-pill fw-bold" style="font-size: 0.65rem;">{{ $approval['type'] }}</span></td>
                         <td><span class="text-muted small">{{ now()->subHours(2)->diffForHumans() }}</span></td>
                         <td class="text-end">
                            <a href="{{ route('approvals.index') }}" class="btn btn-sm btn-icon btn-label-primary rounded-pill"><i class="bx bx-right-arrow-alt"></i></a>
                         </td>
                      </tr>
                      @empty
                      <tr><td colspan="4" class="text-center py-5 text-muted small">All systems green! No pending approvals.</td></tr>
                      @endforelse
                   </tbody>
                </table>
             </div>
          </div>
       </div>

    </div>

    <!-- Sidebar Elements -->
    <div class="col-xl-3 flex-column d-flex gap-5">
       
       <!-- Department Distribution -->
       <div class="hitech-card">
          <div class="hitech-card-header border-bottom">
             <h6 class="title mb-0">Workforce Split</h6>
          </div>
          <div class="card-body py-4">
             <div id="departmentChart" style="min-height: 250px;"></div>
             <div class="mt-4">
                @foreach($departmentData->take(3) as $index => $dept)
                <div class="d-flex justify-content-between align-items-center mb-2">
                   <span class="small fw-bold text-muted"><span class="dot" style="background: {{ ['#004D4D', '#00897b', '#00D2D2', '#D1FAE5'][$index % 4] }}"></span> {{ $dept['name'] }}</span>
                   <span class="small fw-bold text-dark">{{ $dept['count'] }}</span>
                </div>
                @endforeach
             </div>
          </div>
       </div>

       <!-- Birthdays & Celebrations -->
       <div class="hitech-card flex-grow-1">
          <div class="hitech-card-header border-bottom">
             <h6 class="title mb-0">Upcoming Celebrations</h6>
             <i class="bx bx-cake text-danger"></i>
          </div>
          <div class="card-body">
             @forelse($upcomingBirthdays->take(4) as $u)
             <div class="d-flex align-items-center gap-3 mb-4">
                <img src="{{ $u->getProfilePicture() }}" class="rounded-circle shadow-sm" width="36" height="36" onerror="this.src='{{ asset('assets/img/avatars/1.png') }}'">
                <div>
                   <div class="fw-bold text-dark small">{{ $u->name }}</div>
                   <div class="text-danger fw-bold" style="font-size: 9px; text-transform: uppercase;">{{ \Carbon\Carbon::parse($u->dob)->format('M d') }} • Birthday</div>
                </div>
             </div>
             @empty
             <p class="text-center text-muted small py-4">No events in sight.</p>
             @endforelse
          </div>
       </div>

    </div>
  </div>

</div>
@include('tenant.employees.onboarding_invite_modal')
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
  const hiringTrendsChart = new ApexCharts(document.querySelector("#hiringTrendsChart"), {
    series: [{ name: 'Hires', data: {!! json_encode($hiringTrend['hires'] ?? []) !!} }, { name: 'Attrition', data: {!! json_encode($hiringTrend['attrition'] ?? []) !!} }],
    chart: { type: 'area', height: 350, toolbar: { show: false } },
    colors: ['#00897b', '#dc2626'],
    stroke: { curve: 'smooth', width: 3 },
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.05 } },
    xaxis: { categories: {!! json_encode($hiringTrend['labels'] ?? []) !!} },
    grid: { borderColor: '#f1f5f9' },
    dataLabels: { enabled: false }
  });
  if(document.querySelector("#hiringTrendsChart")) hiringTrendsChart.render();

  const deptData = {!! json_encode($departmentData) !!};
  const departmentChart = new ApexCharts(document.querySelector("#departmentChart"), {
    series: deptData.map(d => d.count || 0),
    chart: { type: 'donut', height: 250 },
    labels: deptData.map(d => d.name),
    colors: ['#004D4D', '#00897b', '#00D2D2', '#D1FAE5'],
    legend: { show: false },
    plotOptions: { pie: { donut: { size: '75%' } } }
  });
  if(document.querySelector("#departmentChart")) departmentChart.render();
});
</script>
@endsection
