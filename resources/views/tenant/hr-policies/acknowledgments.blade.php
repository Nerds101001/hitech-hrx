@extends('layouts/layoutMaster')

@section('title', 'Policy Acknowledgments')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
@endsection

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6 mx-4">
    <h3 class="fw-extrabold mb-0 text-dark">Policy Acknowledgments: {{ $policy->title }}</h3>
    <div class="d-flex gap-3 flex-wrap">
        <a href="{{ route('hr-policies.index') }}" class="btn btn-hitech shadow-sm rounded-pill px-5 d-flex align-items-center gap-2">
            <i class="bx bx-left-arrow-alt fs-5"></i>
            <span class="fw-bold">Back to Policies</span>
        </a>
    </div>
  </div>

  <div class="row g-6 mb-6 mx-0 px-4">
    <div class="col-xl-4 col-md-6 animate__animated animate__fadeInUp" style="animation-delay: 0.05s">
      <div class="hitech-stat-card dashboard-variant card-teal uniform-card">
        <div class="stat-card-header mb-2">
          <div class="stat-icon-wrap icon-teal" style="width:38px; height:38px; font-size:1.2rem;"><i class="bx bx-file"></i></div>
          <div class="trend-indicator text-success" style="font-size:0.65rem; padding: 2px 6px;">Total Signatures</div>
        </div>
        <div>
          <h3 class="stat-value mb-0">{{ $acknowledgments->count() }}</h3>
          <span class="stat-label">Acknowledgments</span>
        </div>
      </div>
    </div>

    <div class="col-xl-4 col-md-6 animate__animated animate__fadeInUp" style="animation-delay: 0.1s">
      <div class="hitech-stat-card dashboard-variant card-blue uniform-card">
        <div class="stat-card-header mb-2">
          <div class="stat-icon-wrap icon-blue" style="width:38px; height:38px; font-size:1.2rem;"><i class="bx bx-target-lock"></i></div>
          <div class="trend-indicator text-primary" style="font-size:0.65rem; padding: 2px 6px;">
            @if(empty($policy->target_departments))
                All Depts
            @else
                Targeted
            @endif
          </div>
        </div>
        <div>
          <h3 class="stat-value mb-0">
             @if(empty($policy->target_departments))
                 Global
             @else
                 {{ count($policy->target_departments) }} Depts
             @endif
          </h3>
          <span class="stat-label">Target Audience</span>
        </div>
      </div>
    </div>
  </div>

  <div class="animate__animated animate__fadeIn px-4 mt-6">
    <div class="hitech-card-white p-0 overflow-hidden shadow-sm">
      <div class="table-responsive">
        <table class="table m-0 shadow-none border-0" id="acknowledgmentsTable">
            <thead class="text-dark">
                <tr>
                    <th class="ps-4">Employee Details</th>
                    <th>Employee ID</th>
                    <th>IP Address</th>
                    <th>Acknowledged At</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                @forelse($acknowledgments as $ack)
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td class="ps-4">
                        <div class="d-flex justify-content-start align-items-center user-name">
                            <div class="avatar-wrapper">
                                <div class="avatar avatar-sm me-3">
                                    <span class="avatar-initial-hitech" style="background-color: #127464; color: white; display: flex; align-items: center; justify-content: center; border-radius: 50%; width: 100%; height: 100%;">{{ strtoupper(substr($ack->user->first_name, 0, 1)) }}</span>
                                </div>
                            </div>
                            <div class="d-flex flex-column">
                                <span class="fw-medium mb-0" style="font-size: 0.875rem; color: #1e293b;">{{ $ack->user->first_name }} {{ $ack->user->last_name }}</span>
                                <small class="text-muted" style="font-size: 0.75rem;">{{ $ack->user->email }}</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-code-hitech" style="background-color: rgba(18, 116, 100, 0.1); color: #127464; border-radius: 4px; padding: 4px 8px; font-weight: 600;">{{ $ack->user->employee_code ?? 'N/A' }}</span>
                    </td>
                    <td>
                        <span class="text-body">{{ $ack->ip_address }}</span>
                    </td>
                    <td>
                        <span class="badge bg-teal-light text-teal rounded-pill px-3 py-1 fw-semibold" style="font-size:0.75rem;"><i class="bx bx-calendar me-1"></i>{{ $ack->acknowledged_at->format('d M Y') }}</span>
                    </td>
                    <td>
                        @if(is_array($ack->signature_data))
                            <span class="badge bg-success-light text-success rounded-pill px-3 py-1 fw-bold">Verified by System</span>
                        @else
                            <span class="badge bg-label-secondary rounded-pill px-3 py-1 fw-bold">Standard</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center py-5">
                        <div class="d-flex flex-column align-items-center justify-content-center">
                            <i class="bx bx-user-x text-muted mb-2" style="font-size: 2.5rem;"></i>
                            <p class="mb-0 text-muted">No one has acknowledged this policy yet.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="py-4"></div>
      </div>
    </div>
  </div>

</div>
@endsection
