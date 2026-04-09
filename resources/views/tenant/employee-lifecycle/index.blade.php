@extends('layouts/layoutMaster')

@section('title', __('Employee Lifecycle Management'))

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/select2/select2.scss',
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/libs/apex-charts/apex-charts.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
  @include('components.enhanced-css')
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/select2/select2.full.min.js',
    'resources/assets/vendor/libs/apex-charts/apex-charts.min.js',
    'resources/assets/vendor/js/bootstrap.js',
  ])
@endsection

@section('content')
<div class="content-wrapper">
  <!-- Content -->
  <div class="container-xxl flex-grow-1 container-p-y">
    <!-- Hero Banner -->
    <x-hero-banner 
      title="Employee Lifecycle Management"
      subtitle="Manage promotions, transfers, warnings, and more"
      icon="ti ti-user-heart"
      :show-stats="true"
    />

    <!-- Stats Cards -->
    <div class="row gy-4 mb-4">
      <x-stat-card 
        title="Pending Promotions"
        :value="$stats['pending_promotions']"
        icon="ti ti-trending-up"
        color="primary"
        trend="+12%"
        trend-label="vs last month"
      />
      <x-stat-card 
        title="Pending Transfers"
        :value="$stats['pending_transfers']"
        icon="ti ti-arrows-right-left"
        color="info"
        trend="+8%"
        trend-label="vs last month"
      />
      <x-stat-card 
        title="Active Warnings"
        :value="$stats['active_warnings']"
        icon="ti ti-alert-triangle"
        color="warning"
        trend="-5%"
        trend-label="vs last month"
      />
      <x-stat-card 
        title="Open Complaints"
        :value="$stats['open_complaints']"
        icon="ti ti-message-2"
        color="danger"
        trend="+15%"
        trend-label="vs last month"
      />
    </div>

    <!-- Quick Actions -->
    <x-quick-actions>
      <x-quick-action 
        title="New Promotion"
        description="Promote an employee"
        icon="ti ti-trending-up"
        color="primary"
        link="#"
        modal-target="#promotionModal"
      />
      <x-quick-action 
        title="Transfer Employee"
        description="Move employee to new department"
        icon="ti ti-arrows-right-left"
        color="info"
        link="#"
        modal-target="#transferModal"
      />
      <x-quick-action 
        title="Issue Warning"
        description="Record employee warning"
        icon="ti ti-alert-triangle"
        color="warning"
        link="#"
        modal-target="#warningModal"
      />
      <x-quick-action 
        title="File Complaint"
        description="Record employee complaint"
        icon="ti ti-message-2"
        color="danger"
        link="#"
        modal-target="#complaintModal"
      />
      <x-quick-action 
        title="Create Announcement"
        description="Send company announcement"
        icon="ti ti-bell"
        color="success"
        link="#"
        modal-target="#announcementModal"
      />
    </x-quick-actions>

    <!-- Recent Activities -->
    <div class="row">
      <div class="col-md-6 mb-4">
        <div class="card">
          <div class="card-header">
            <h5 class="card-title mb-0">Recent Promotions</h5>
          </div>
          <div class="card-body">
            @if($recentPromotions->count() > 0)
              <div class="table-responsive">
                <table class="table table-hover">
                  <thead>
                    <tr>
                      <th>Employee</th>
                      <th>From</th>
                      <th>To</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($recentPromotions as $promotion)
                      <tr>
                        <td>{{ $promotion->user->name ?? 'N/A' }}</td>
                        <td>{{ $promotion->previousDesignation->name ?? 'N/A' }}</td>
                        <td>{{ $promotion->newDesignation->name ?? 'N/A' }}</td>
                        <td>{!! $promotion->status_badge !!}</td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            @else
              <div class="text-center py-4">
                <i class="ti ti-trending-up ti-3x text-muted mb-3"></i>
                <p class="text-muted">No recent promotions</p>
              </div>
            @endif
          </div>
        </div>
      </div>

      <div class="col-md-6 mb-4">
        <div class="card">
          <div class="card-header">
            <h5 class="card-title mb-0">Recent Transfers</h5>
          </div>
          <div class="card-body">
            @if($recentTransfers->count() > 0)
              <div class="table-responsive">
                <table class="table table-hover">
                  <thead>
                    <tr>
                      <th>Employee</th>
                      <th>From</th>
                      <th>To</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($recentTransfers as $transfer)
                      <tr>
                        <td>{{ $transfer->user->name ?? 'N/A' }}</td>
                        <td>{{ $transfer->fromDepartment->name ?? 'N/A' }}</td>
                        <td>{{ $transfer->toDepartment->name ?? 'N/A' }}</td>
                        <td>{!! $transfer->status_badge !!}</td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            @else
              <div class="text-center py-4">
                <i class="ti ti-arrows-right-left ti-3x text-muted mb-3"></i>
                <p class="text-muted">No recent transfers</p>
              </div>
            @endif
          </div>
        </div>
      </div>
    </div>

    <!-- Lifecycle Overview Chart -->
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="card-title mb-0">Lifecycle Overview</h5>
      </div>
      <div class="card-body">
        <div id="lifecycleChart" style="height: 300px;"></div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Lifecycle Overview Chart
  const lifecycleChart = new ApexCharts(document.querySelector("#lifecycleChart"), {
    series: [{
      name: 'Promotions',
      data: [31, 40, 28, 51, 42, 109, 100]
    }, {
      name: 'Transfers',
      data: [11, 32, 45, 32, 34, 52, 41]
    }, {
      name: 'Warnings',
      data: [15, 25, 18, 35, 22, 30, 28]
    }, {
      name: 'Complaints',
      data: [8, 12, 15, 18, 14, 22, 19]
    }],
    chart: {
      height: 300,
      type: 'area',
      toolbar: {
        show: false
      },
    },
    dataLabels: {
      enabled: false
    },
    stroke: {
      curve: 'smooth',
      width: 2
    },
    fill: {
      type: 'gradient',
      gradient: {
        shadeIntensity: 1,
        opacityFrom: 0.4,
        opacityTo: 0.1,
      }
    },
    legend: {
      position: 'top',
      horizontalAlign: 'center'
    },
    xaxis: {
      categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
    },
    tooltip: {
      x: {
        format: 'dd/MM/yy'
      },
    },
  });

  lifecycleChart.render();
});
</script>
@endsection
