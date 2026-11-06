@extends('layouts/layoutMaster')

@section('title', 'Policy Target Users')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
@endsection

@section('content')
@php
  $allTargeted = $users->merge($individualUsers)->unique('id');
  $ackCount = $allTargeted->filter(fn($u) => isset($ackMap[$u->id]))->count();
  $pending  = $allTargeted->count() - $ackCount;
@endphp
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6 mx-4">
    <div>
      <h3 class="fw-extrabold mb-0 text-dark">Target Users: {{ $policy->title }}</h3>
      <p class="text-muted mb-0 mt-1">
        @if(!empty($policy->target_departments))
          Departments:
          @foreach($targetDepts as $td)
            @if($td['name'])
              <span class="badge bg-info-light text-info">{{ $td['name'] }}</span>
            @else
              <span class="badge bg-danger-light text-danger" title="This department id does not exist">ID {{ $td['id'] }} — not found</span>
            @endif
          @endforeach
        @endif
        @if(!empty($policy->target_users))
          <span class="badge bg-label-primary ms-1"><i class="bx bx-user me-1"></i>{{ count($policy->target_users) }} individual{{ count($policy->target_users) !== 1 ? 's' : '' }}</span>
        @endif
        @if(empty($policy->target_departments) && empty($policy->target_users))
          Targeted at <strong>all employees</strong> (no restriction).
        @endif
      </p>
    </div>
    <div class="d-flex gap-3 flex-wrap">
      <a href="{{ route('hr-policies.acknowledgments', $policy->id) }}" class="btn btn-hitech-secondary shadow-sm rounded-pill px-4 d-flex align-items-center gap-2">
        <i class="bx bx-list-check fs-5"></i><span class="fw-bold">Acknowledgments</span>
      </a>
      <a href="{{ route('hr-policies.index') }}" class="btn btn-hitech shadow-sm rounded-pill px-5 d-flex align-items-center gap-2">
        <i class="bx bx-left-arrow-alt fs-5"></i><span class="fw-bold">Back to Policies</span>
      </a>
    </div>
  </div>

  <div class="row g-6 mb-6 mx-0 px-4">
    <div class="col-xl-4 col-md-6">
      <div class="hitech-stat-card dashboard-variant card-blue uniform-card">
        <div class="stat-card-header mb-2"><div class="stat-icon-wrap icon-blue" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-group"></i></div></div>
        <div><h3 class="stat-value mb-0">{{ $allTargeted->count() }}</h3><span class="stat-label">Target Users</span></div>
      </div>
    </div>
    <div class="col-xl-4 col-md-6">
      <div class="hitech-stat-card dashboard-variant card-teal uniform-card">
        <div class="stat-card-header mb-2"><div class="stat-icon-wrap icon-teal" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-check-circle"></i></div></div>
        <div><h3 class="stat-value mb-0">{{ $ackCount }}</h3><span class="stat-label">Acknowledged</span></div>
      </div>
    </div>
    <div class="col-xl-4 col-md-6">
      <div class="hitech-stat-card dashboard-variant card-amber uniform-card">
        <div class="stat-card-header mb-2"><div class="stat-icon-wrap icon-amber" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-time-five"></i></div></div>
        <div><h3 class="stat-value mb-0">{{ $pending }}</h3><span class="stat-label">Pending</span></div>
      </div>
    </div>
  </div>

  {{-- Tabs: Departments / Employees --}}
  <div class="animate__animated animate__fadeIn px-4 mt-2">
    <ul class="nav nav-pills mb-4" id="targetViewTabs" role="tablist" style="background:#f8fafc;border-radius:8px;padding:4px;width:fit-content;">
      <li class="nav-item" role="presentation">
        <button class="nav-link active px-4 py-2" id="tab-dept" data-bs-toggle="pill" data-bs-target="#pane-dept" type="button" role="tab">
          <i class="bx bx-buildings me-1"></i>By Department
          <span class="badge bg-info rounded-pill ms-1">{{ $users->count() }}</span>
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link px-4 py-2" id="tab-emp" data-bs-toggle="pill" data-bs-target="#pane-emp" type="button" role="tab">
          <i class="bx bx-user me-1"></i>Individual Employees
          <span class="badge bg-primary rounded-pill ms-1">{{ $individualUsers->count() }}</span>
        </button>
      </li>
    </ul>

    <div class="tab-content">
      {{-- Department targets tab --}}
      <div class="tab-pane fade show active" id="pane-dept" role="tabpanel">
        @php $missingDepts = $targetDepts->whereNull('name')->pluck('id')->all(); @endphp
        @if($users->isEmpty() && !empty($policy->target_departments))
          <div class="mb-4 p-4 rounded-3" style="background:#fff7ed;border:1px solid #fed7aa;">
            <div class="fw-bold text-dark mb-1"><i class="bx bx-error-circle text-warning me-1"></i>This policy currently reaches nobody via departments</div>
            <div class="small text-muted">
              It targets
              @if($missingDepts)
                department id(s) <strong>{{ implode(', ', $missingDepts) }}</strong> that <strong>no longer exist</strong>, so no employee matches.
              @else
                departments that have no active employees.
              @endif
              Open the policy's <strong>Edit</strong> menu to pick valid departments.
            </div>
          </div>
        @endif
        <div class="hitech-card-white p-0 overflow-hidden shadow-sm">
          <div class="table-responsive">
            @include('tenant.hr-policies._user-table', ['tableUsers' => $users, 'ackMap' => $ackMap, 'emptyMsg' => 'No department targeting — this policy applies to all, or is individually targeted only.'])
          </div>
        </div>
      </div>

      {{-- Individual employee targets tab --}}
      <div class="tab-pane fade" id="pane-emp" role="tabpanel">
        <div class="hitech-card-white p-0 overflow-hidden shadow-sm">
          <div class="table-responsive">
            @include('tenant.hr-policies._user-table', ['tableUsers' => $individualUsers, 'ackMap' => $ackMap, 'emptyMsg' => 'No individual employees are targeted for this policy.'])
          </div>
        </div>
      </div>
    </div>
  </div>

</div>
@endsection
