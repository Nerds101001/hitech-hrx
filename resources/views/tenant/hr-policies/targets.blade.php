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
  $ackCount = $users->filter(fn($u) => isset($ackMap[$u->id]))->count();
  $pending  = $users->count() - $ackCount;
@endphp
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6 mx-4">
    <div>
      <h3 class="fw-extrabold mb-0 text-dark">Target Users: {{ $policy->title }}</h3>
      <p class="text-muted mb-0 mt-1">
        @if(empty($policy->target_departments))
          Targeted at <strong>all employees</strong> (no department filter).
        @else
          Departments:
          @foreach($targetDepts as $td)
            @if($td['name'])
              <span class="badge bg-info-light text-info">{{ $td['name'] }}</span>
            @else
              <span class="badge bg-danger-light text-danger" title="This department id does not exist">ID {{ $td['id'] }} — not found</span>
            @endif
          @endforeach
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
        <div><h3 class="stat-value mb-0">{{ $users->count() }}</h3><span class="stat-label">Target Users</span></div>
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

  @php $missingDepts = $targetDepts->whereNull('name')->pluck('id')->all(); @endphp
  @if($users->isEmpty() && !empty($policy->target_departments))
    <div class="mx-4 mb-4 p-4 rounded-3" style="background:#fff7ed;border:1px solid #fed7aa;">
      <div class="fw-bold text-dark mb-1"><i class="bx bx-error-circle text-warning me-1"></i>This policy currently reaches nobody</div>
      <div class="small text-muted">
        It targets
        @if($missingDepts)
          department id(s) <strong>{{ implode(', ', $missingDepts) }}</strong> that <strong>no longer exist</strong>, so no employee matches.
        @else
          departments that have no active employees.
        @endif
        Open the policy's <strong>Edit</strong> menu to pick valid departments — or clear them to target <strong>all employees</strong>.
      </div>
    </div>
  @endif

  <div class="animate__animated animate__fadeIn px-4 mt-6">
    <div class="hitech-card-white p-0 overflow-hidden shadow-sm">
      <div class="table-responsive">
        <table class="table m-0 shadow-none border-0">
          <thead class="text-dark">
            <tr>
              <th class="ps-4">Employee</th>
              <th>Department</th>
              <th>Email</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            @forelse($users as $u)
              <tr style="border-bottom:1px solid #f1f5f9;">
                <td class="ps-4">
                  <div class="d-flex align-items-center">
                    <div class="avatar avatar-sm me-3">
                      <span class="avatar-initial-hitech" style="background-color:#127464;color:#fff;display:flex;align-items:center;justify-content:center;border-radius:50%;width:100%;height:100%;">{{ strtoupper(substr($u->first_name,0,1)) }}</span>
                    </div>
                    <span class="fw-medium" style="font-size:0.875rem;color:#1e293b;">{{ $u->first_name }} {{ $u->last_name }}</span>
                  </div>
                </td>
                <td><span class="text-body">{{ optional($u->department)->name ?: '—' }}</span></td>
                <td><small class="text-muted">{{ $u->email }}</small></td>
                <td>
                  @if(isset($ackMap[$u->id]))
                    <span class="badge bg-teal-light text-teal rounded-pill px-3 py-1 fw-semibold" style="font-size:0.75rem;"><i class="bx bx-check me-1"></i>Acknowledged {{ \Illuminate\Support\Carbon::parse($ackMap[$u->id])->format('d M Y') }}</span>
                  @else
                    <span class="badge bg-label-warning rounded-pill px-3 py-1 fw-semibold" style="font-size:0.75rem;"><i class="bx bx-time me-1"></i>Pending</span>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-center py-5"><i class="bx bx-user-x text-muted mb-2" style="font-size:2.5rem;"></i><p class="mb-0 text-muted">No active users match this policy's target.</p></td></tr>
            @endforelse
          </tbody>
        </table>
        <div class="py-4"></div>
      </div>
    </div>
  </div>

</div>
@endsection
