@extends('layouts/layoutMaster')

@section('title', __('Employee Transfers'))

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
      title="Employee Transfers"
      subtitle="Manage internal department and team transfers"
      icon="ti ti-switch-horizontal"
      :show-stats="false"
    />

    <div class="card hitech-card-white border-0 shadow-sm rounded-4 mb-4">
      <div class="card-body">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#transferModal">
          <i class="ti ti-plus me-2"></i>New Transfer
        </button>
      </div>
    </div>

    <div class="card hitech-card-white border-0 shadow-sm rounded-4">
      <div class="card-header border-bottom">
        <h5 class="card-title mb-0">Transfer History</h5>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>Employee</th>
                <th>From</th>
                <th>To</th>
                <th>Transfer Date</th>
                <th>Status</th>
                <th>Approved By</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($transfers as $transfer)
                <tr>
                  <td>
                    <div class="d-flex align-items-center">
                      <div class="avatar avatar-sm me-3">
                        <img src="{{ $transfer->user->getProfilePicture() }}" alt="Avatar" class="rounded-circle">
                      </div>
                      <div class="d-flex flex-column">
                        <span class="fw-bold">{{ $transfer->user->name }}</span>
                        <small class="text-muted">{{ $transfer->user->code }}</small>
                      </div>
                    </div>
                  </td>
                  <td>
                    <small class="d-block">{{ $transfer->fromDepartment->name ?? 'N/A' }}</small>
                    <small class="text-muted">{{ $transfer->fromTeam->name ?? 'N/A' }}</small>
                  </td>
                  <td>
                    <small class="d-block fw-bold text-primary">{{ $transfer->toDepartment->name ?? 'N/A' }}</small>
                    <small class="text-muted">{{ $transfer->toTeam->name ?? 'N/A' }}</small>
                  </td>
                  <td>{{ $transfer->transfer_date->format('d M Y') }}</td>
                  <td>
                    <span class="badge {{ $transfer->status == 'approved' ? 'bg-label-success' : ($transfer->status == 'pending' ? 'bg-label-warning' : 'bg-label-danger') }}">
                      {{ ucfirst($transfer->status) }}
                    </span>
                  </td>
                  <td>{{ $transfer->approvedBy->name ?? '-' }}</td>
                  <td>
                    <div class="dropdown">
                      <button class="btn btn-sm p-0" type="button" data-bs-toggle="dropdown">
                        <i class="ti ti-dots-vertical"></i>
                      </button>
                      <div class="dropdown-menu dropdown-menu-end">
                        @if($transfer->status == 'pending')
                          <form action="{{ route('employee-lifecycle.transfers.approve', $transfer->id) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="dropdown-item text-success"><i class="ti ti-check me-1"></i> Approve</button>
                          </form>
                        @endif
                        <a class="dropdown-item" href="javascript:void(0);"><i class="ti ti-eye me-1"></i> View Details</a>
                      </div>
                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" class="text-center py-4 text-muted">No transfer records found.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
        <div class="mt-4">
          {{ $transfers->links() }}
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Transfer Modal -->
<div class="modal fade" id="transferModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-content-hitech border-0 shadow-lg">
      <div class="modal-header">
        <h5 class="modal-title">Request Transfer</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="{{ route('employee-lifecycle.transfers.store') }}" method="POST">
        @csrf
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Employee</label>
            <select name="user_id" class="form-select select2" required>
              <option value="">Select Employee</option>
              @foreach(App\Models\User::where('status', \App\Enums\UserAccountStatus::ACTIVE)->get() as $user)
                <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->code }})</option>
              @endforeach
            </select>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">To Department</label>
              <select name="to_department_id" class="form-select" required>
                <option value="">Select Department</option>
                @foreach(App\Models\Department::all() as $dept)
                  <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">To Team (Optional)</label>
              <select name="to_team_id" class="form-select">
                <option value="">Select Team</option>
                @foreach(App\Models\Team::all() as $team)
                  <option value="{{ $team->id }}">{{ $team->name }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Transfer Date</label>
              <input type="date" name="transfer_date" class="form-control" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Effective Date</label>
              <input type="date" name="effective_date" class="form-control" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Reason for Transfer</label>
            <textarea name="reason" class="form-control" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Submit Request</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
