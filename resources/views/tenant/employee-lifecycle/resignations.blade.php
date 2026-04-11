@extends('layouts/layoutMaster')

@section('title', __('Employee Resignations'))

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/select2/select2.scss',
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
  @include('components.enhanced-css')
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/select2/select2.full.min.js',
    'resources/assets/vendor/js/bootstrap.js',
  ])
@endsection

@section('content')
<div class="content-wrapper">
  <div class="container-xxl flex-grow-1 container-p-y">
    <x-hero-banner 
      title="Employee Resignations"
      subtitle="Track and manage employee-initiated departures"
      icon="ti ti-logout"
      :show-stats="false"
    />

    <div class="card hitech-card-white border-0 shadow-sm rounded-4 mb-4">
      <div class="card-body">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#resignationModal">
          <i class="ti ti-plus me-2"></i>Log New Resignation
        </button>
      </div>
    </div>

    <div class="card hitech-card-white border-0 shadow-sm rounded-4">
      <div class="card-header border-bottom">
        <h5 class="card-title mb-0">Resignations List</h5>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>Employee</th>
                <th>Notice Date</th>
                <th>Resignation Date</th>
                <th>Relieving Date</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($resignations as $resignation)
                <tr>
                  <td>
                    <div class="d-flex align-items-center">
                      <div class="avatar avatar-sm me-3">
                        <img src="{{ $resignation->user->getProfilePicture() }}" alt="Avatar" class="rounded-circle">
                      </div>
                      <div class="d-flex flex-column">
                        <span class="fw-bold">{{ $resignation->user->name }}</span>
                        <small class="text-muted">{{ $resignation->user->code }}</small>
                      </div>
                    </div>
                  </td>
                  <td>{{ $resignation->notice_date->format('d M Y') }}</td>
                  <td>{{ $resignation->resignation_date->format('d M Y') }}</td>
                  <td>{{ $resignation->relieving_date ? $resignation->relieving_date->format('d M Y') : 'TBD' }}</td>
                  <td><small>{{ Str::limit($resignation->reason, 20) }}</small></td>
                  <td>
                    <span class="badge {{ $resignation->status == 'accepted' ? 'bg-label-success' : ($resignation->status == 'pending' ? 'bg-label-warning' : 'bg-label-secondary') }}">
                      {{ ucfirst($resignation->status) }}
                    </span>
                  </td>
                  <td>
                    <div class="dropdown">
                      <button class="btn btn-sm p-0" type="button" data-bs-toggle="dropdown">
                        <i class="ti ti-dots-vertical"></i>
                      </button>
                      <div class="dropdown-menu dropdown-menu-end">
                        @if($resignation->status == 'pending')
                          <form action="{{ route('employee-lifecycle.resignations.approve', $resignation->id) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="dropdown-item text-success"><i class="ti ti-check me-1"></i> Accept</button>
                          </form>
                        @endif
                        <a class="dropdown-item" href="javascript:void(0);"><i class="ti ti-exit me-1"></i> Exit Interview</a>
                      </div>
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" class="text-center py-4 text-muted">No resignation records found.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
        <div class="mt-4">
          {{ $resignations->links() }}
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Resignation Modal -->
<div class="modal fade" id="resignationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-content-hitech border-0 shadow-lg">
      <div class="modal-header">
        <h5 class="modal-title">Log Employee Resignation</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="{{ route('employee-lifecycle.resignations.store') }}" method="POST">
        @csrf
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Employee</label>
            <select name="user_id" class="form-select select2" required>
              <option value="">Select Employee</option>
              @foreach(App\Models\User::where('status', \App\Enums\UserAccountStatus::ACTIVE)->get() as $user)
                <option value="{{ $user->id }}">{{ $user->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Notice Date</label>
              <input type="date" name="notice_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Resignation Date</label>
              <input type="date" name="resignation_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Expected Relieving Date</label>
            <input type="date" name="relieving_date" class="form-control">
          </div>
          <div class="mb-0">
            <label class="form-label">Reason for Leaving</label>
            <textarea name="reason" class="form-control" rows="4" placeholder="Personal reasons, Better opportunity, etc."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Process Resignation</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
