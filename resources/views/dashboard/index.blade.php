@extends('layouts/layoutMaster')

@section('title', 'Super Admin Dashboard')

@section('vendor-style')
  @vite(['resources/assets/vendor/libs/apex-charts/apex-charts.scss', 'resources/assets/vendor/scss/pages/hitech-portal.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/apex-charts/apexcharts.js'])
@endsection

@section('page-script')
  <script>
    let orderChart = null;

    function reloadOrderChart(months) {
      fetch(`/getOrderHistoryAjax?months=${months}`)
        .then(r => r.json())
        .then(data => {
          if (orderChart) {
            orderChart.updateOptions({
              series: [{ data: data.map(i => i.total) }],
              xaxis: { categories: data.map(i => `Month ${i.month}`) }
            });
          }
        })
        .catch(() => {});
    }

    document.addEventListener('DOMContentLoaded', function () {
      const orderHistoryChartEl = document.querySelector('#orderHistoryChart');
      const orderHistoryData = @json($orderHistory);
      
      if (orderHistoryChartEl) {
        const options = {
          chart: {
            type: 'area',
            height: 350,
            toolbar: { show: false },
            parentHeightOffset: 0,
            background: 'transparent'
          },
          series: [{
            name: 'Total Amount',
            data: orderHistoryData.map(item => item.total)
          }],
          xaxis: {
            categories: orderHistoryData.map(item => `Month ${item.month}`),
            labels: { style: { colors: '#b6bee3', fontSize: '13px' } },
            axisBorder: { show: false },
            axisTicks: { show: false }
          },
          yaxis: {
            labels: { style: { colors: '#b6bee3', fontSize: '13px' } }
          },
          grid: {
            borderColor: 'rgba(255, 255, 255, 0.1)',
            padding: { top: -20, bottom: -10 }
          },
          colors: ['#00cfe8'],
          fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.7,
                opacityTo: 0.2,
                stops: [0, 90, 100]
            }
          },
          dataLabels: { enabled: false },
          stroke: { curve: 'smooth', width: 3 },
          tooltip: { theme: 'dark' }
        };

        const chart = new ApexCharts(orderHistoryChartEl, options);
        chart.render();
        orderChart = chart;
      }
    });
  </script>
@endsection

@section('content')

  <!-- Hero Section -->
  <div class="row mb-4 animate__animated animate__fadeInDown">
    <div class="col-12">
      <div class="admin-hero hitech-card border-0 position-relative overflow-hidden">
        <div class="d-flex align-items-center position-relative z-1 p-4">
          <div class="avatar avatar-xl me-4 border-2 border-white rounded-circle">
             <img src="{{ Auth::user()->getProfilePicture() ?? asset('assets/img/avatars/1.png') }}" alt="Avatar" class="rounded-circle">
          </div>
          <div>
            <h2 class="text-white mb-1 fw-bold">Welcome back, Super Admin! 🚀</h2>
            <p class="text-white opacity-75 mb-0 text-large">Here's what's happening in your system today.</p>
          </div>
        </div>
        <!-- Decorative bg elements -->
        <div class="position-absolute top-0 end-0 h-100 w-50" 
             style="background: linear-gradient(90deg, transparent, rgba(0, 207, 232, 0.1)); clip-path: polygon(20% 0%, 100% 0, 100% 100%, 0% 100%);">
        </div>
      </div>
    </div>
  </div>

  <!-- Overview Cards -->
  <div class="row animate__animated animate__fadeInUp">
    <div class="col-sm-6 col-xl-3 mb-4">
      <div class="hitech-stat-card dashboard-variant card-teal h-100 d-flex flex-column justify-content-between">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
                <div class="stat-card-label">Total Orders</div>
                <h3 class="stat-value mb-0">{{ $totalOrders }}</h3>
            </div>
            <div class="stat-icon-wrap icon-teal"><i class="bx bx-cart"></i></div>
        </div>
        <small class="text-success fw-semibold"><i class='bx bx-check-circle'></i> {{ $completedOrders }} Completed</small>
        <div class="progress mt-3" style="height: 6px;">
            <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $totalOrders > 0 ? ($completedOrders / $totalOrders) * 100 : 0 }}%" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-xl-3 mb-4">
      <div class="hitech-stat-card dashboard-variant card-amber h-100 d-flex flex-column justify-content-between">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
                <div class="stat-card-label">Pending Requests</div>
                <h3 class="stat-value mb-0">{{ $pendingRequests }}</h3>
            </div>
            <div class="stat-icon-wrap icon-amber"><i class="bx bx-time-five"></i></div>
        </div>
        <small class="text-warning fw-semibold"><i class='bx bx-error'></i> Action Required</small>
         <div class="progress mt-3" style="height: 6px;">
            <div class="progress-bar bg-warning" role="progressbar" style="width: 70%" aria-valuenow="70" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-xl-3 mb-4">
      <div class="hitech-stat-card dashboard-variant card-teal h-100 d-flex flex-column justify-content-between">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
                <div class="stat-card-label">Active Domains</div>
                <h3 class="stat-value mb-0">{{ $activeDomains }}</h3>
            </div>
            <div class="stat-icon-wrap icon-teal"><i class="bx bx-globe"></i></div>
        </div>
        <small class="text-success fw-semibold"><i class='bx bx-check'></i> Operational</small>
        <div class="progress mt-3" style="height: 6px;">
            <div class="progress-bar bg-success" role="progressbar" style="width: 90%" aria-valuenow="90" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-xl-3 mb-4">
      <div class="hitech-stat-card dashboard-variant card-blue h-100 d-flex flex-column justify-content-between">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
                <div class="stat-card-label">New Customers</div>
                <h3 class="stat-value mb-0">{{ $newCustomers }}</h3>
            </div>
            <div class="stat-icon-wrap icon-blue"><i class="bx bx-user-plus"></i></div>
        </div>
        <small class="text-info fw-semibold"><i class='bx bx-calendar'></i> This Month</small>
        <div class="progress mt-3" style="height: 6px;">
            <div class="progress-bar bg-info" role="progressbar" style="width: 40%" aria-valuenow="40" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Order History Graph & Offline Requests -->
  <div class="row animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
    <!-- Order History Graph -->
    <div class="col-lg-8 mb-4">
      <div class="hitech-card h-100">
        <div class="hitech-card-header">
            <div>
               <h5 class="mb-0">Order History</h5>
               <small class="text-muted">Monthly Revenue Overview</small>
            </div>
            <div class="dropdown">
                <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">Last 6 Months</button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="javascript:void(0);" data-months="12" onclick="reloadOrderChart(12)">Last 12 Months</a></li>
                    <li><a class="dropdown-item" href="javascript:void(0);" data-months="1" onclick="reloadOrderChart(1)">Last 30 Days</a></li>
                </ul>
            </div>
        </div>
        <div class="card-body">
          <div id="orderHistoryChart"></div>
        </div>
      </div>
    </div>

    <!-- Offline Requests -->
    <div class="col-lg-4 mb-4">
      <div class="hitech-card h-100">
        <div class="hitech-card-header">
           <div>
               <h5 class="mb-0">Offline Requests</h5>
               <small class="text-muted">Pending Approvals</small>
           </div>
           <a href="{{ route('offlineRequests.index') }}" class="btn btn-sm btn-secondary">View All</a>
        </div>
        <div class="card-body p-0 overflow-auto" style="max-height: 400px;">
          @if($offlineRequests->count() > 0)
            <ul class="list-group list-group-flush bg-transparent">
              @foreach($offlineRequests as $request)
                <li class="list-group-item border-bottom px-4 py-3">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong class="d-block">{{ $request->user->first_name ?? $request->user->name ?? 'N/A' }}</strong>
                        <small class="text-muted">{{ $request->created_at->format('d M, Y') }}</small>
                    </div>
                    <span class="badge bg-label-warning">{{ $request->status }}</span>
                  </div>
                </li>
              @endforeach
            </ul>
          @else
            <div class="text-center p-4">
               <div class="avatar avatar-md bg-label-secondary rounded-circle mx-auto mb-3">
                   <i class="bx bx-check bx-sm"></i>
               </div>
               <h6 class="text-white mb-1">No Pending Requests</h6>
               <small class="text-muted">All caught up!</small>
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>

  <!-- Domain Requests and Recent Customers -->
  <div class="row animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
    <!-- Domain Requests -->
    <div class="col-lg-6 mb-4">
      <div class="hitech-card h-100">
        <div class="hitech-card-header">
           <div>
               <h5 class="mb-0">Domain Requests</h5>
               <small class="text-muted">Pending Domains</small>
           </div>
           <a href="{{ route('domainRequests.index') }}" class="btn btn-sm btn-secondary">View All</a>
        </div>
        <div class="card-body p-0 overflow-auto" style="max-height: 400px;">
          @if($domainRequests->count() > 0)
            <ul class="list-group list-group-flush bg-transparent">
              @foreach($domainRequests as $request)
                <li class="list-group-item border-bottom px-4 py-3">
                  <div class="d-flex align-items-center">
                     @include('_partials._profile-avatar', ['user' => $request->user])
                     <div class="ms-3 flex-grow-1">
                        <strong class="d-block">{{ $request->name }}</strong>
                        <small class="text-muted">{{ $request->created_at->format('d M, Y') }}</small>
                     </div>
                     <span class="badge bg-label-info">{{ $request->status }}</span>
                  </div>
                </li>
              @endforeach
            </ul>
          @else
            <div class="text-center p-4">
               <div class="avatar avatar-md bg-label-secondary rounded-circle mx-auto mb-3">
                   <i class="bx bx-globe bx-sm"></i>
               </div>
               <h6 class="text-white mb-1">No Pending Domains</h6>
               <small class="text-muted">Everything is running smoothly.</small>
            </div>
          @endif
        </div>
      </div>
    </div>

    <!-- Recent Customers -->
    <div class="col-lg-6 mb-4">
      <div class="hitech-card h-100">
        <div class="hitech-card-header">
           <div>
               <h5 class="mb-0">Recent Customers</h5>
               <small class="text-muted">Newest Signups</small>
           </div>
           <a href="{{ route('account.index') }}" class="btn btn-sm btn-secondary">View All</a>
        </div>
        <div class="card-body p-0 overflow-auto" style="max-height: 400px;">
          @if($recentCustomers->count() > 0)
            <ul class="list-group list-group-flush bg-transparent">
              @foreach($recentCustomers as $customer)
                <li class="list-group-item border-bottom px-4 py-3">
                    <div class="d-flex align-items-center">
                        @include('_partials._profile-avatar', ['user' => $customer])
                        <div class="ms-3 flex-grow-1">
                            <strong class="d-block">{{ $customer->first_name ?? $customer->name ?? $customer->email }}</strong>
                            <small class="text-muted">Joined {{ $customer->created_at->format('d M, Y') }}</small>
                        </div>
                        <a href="{{ route('account.index') }}?search={{ $customer->email }}" class="btn btn-sm btn-icon btn-secondary"><i class="bx bx-show"></i></a>
                    </div>
                </li>
              @endforeach
            </ul>
          @else
            <div class="text-center p-4">
               <div class="avatar avatar-md bg-label-secondary rounded-circle mx-auto mb-3">
                   <i class="bx bx-user-x bx-sm"></i>
               </div>
               <h6 class="text-white mb-1">No Recent Customers</h6>
               <small class="text-muted">Quiet day today.</small>
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>

@endsection
