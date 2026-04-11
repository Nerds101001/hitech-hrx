@extends('layouts/layoutMaster')

@section('title', __('Disciplinary Warnings'))

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
      title="Disciplinary Warnings"
      subtitle="Manage incident reports and official warnings for employees"
      icon="ti ti-alert-triangle"
      :show-stats="false"
    />

    <div class="card hitech-card-white border-0 shadow-sm rounded-4 mb-4">
      <div class="card-body">
        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#warningModal">
          <i class="ti ti-plus me-2"></i>Issue New Warning
        </button>
      </div>
    </div>

    <div class="card hitech-card-white border-0 shadow-sm rounded-4">
      <div class="card-header border-bottom">
        <h5 class="card-title mb-0">Warning Records</h5>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>Employee</th>
                <th>Subject</th>
                <th>Severity</th>
                <th>Warning Date</th>
                <th>Action Taken</th>
                <th>Issued By</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($warnings as $warning)
                <tr>
                  <td>
                    <div class="d-flex align-items-center">
                      <div class="avatar avatar-sm me-3">
                        <img src="{{ $warning->user->getProfilePicture() }}" alt="Avatar" class="rounded-circle">
                      </div>
                      <div class="d-flex flex-column">
                        <span class="fw-bold">{{ $warning->user->name }}</span>
                        <small class="text-muted">{{ $warning->user->code }}</small>
                      </div>
                    </div>
                  </td>
                  <td>{{ Str::limit($warning->subject, 30) }}</td>
                  <td>
                    <span class="badge {{ $warning->severity == 'critical' ? 'bg-label-danger' : ($warning->severity == 'high' ? 'bg-label-warning' : 'bg-label-info') }}">
                      {{ ucfirst($warning->severity) }}
                    </span>
                  </td>
                  <td>{{ $warning->warning_date->format('d M Y') }}</td>
                  <td>{{ $warning->action_taken ?: 'None' }}</td>
                  <td>{{ $warning->issuedBy->name ?? 'System' }}</td>
                  <td>
                    <button class="btn btn-sm btn-icon btn-label-primary"><i class="ti ti-eye"></i></button>
                    <button class="btn btn-sm btn-icon btn-label-info"><i class="ti ti-file-text"></i></button>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" class="text-center py-4 text-muted">No disciplinary records found.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
        <div class="mt-4">
          {{ $warnings->links() }}
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Warning Modal -->
<div class="modal fade" id="warningModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-content-hitech border-0 shadow-lg">
      <div class="modal-header">
        <h5 class="modal-title">Issue Official Warning</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="{{ route('employee-lifecycle.warnings.store') }}" method="POST">
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
          <div class="mb-3">
            <label class="form-label">Subject / Type of Incident</label>
            <input type="text" name="subject" class="form-control" placeholder="Late arrivals, Misconduct, etc." required>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Severity Level</label>
              <select name="severity" class="form-select" required>
                <option value="low">Verbal / Low</option>
                <option value="medium" selected>First Written / Medium</option>
                <option value="high">Final Warning / High</option>
                <option value="critical">Critical / Immediate Suspension</option>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Warning Date</label>
              <input type="date" name="warning_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Incident Description</label>
            <textarea name="description" class="form-control" rows="4" required></textarea>
          </div>
          <div class="mb-0">
            <label class="form-label">Corrective Action Taken</label>
            <input type="text" name="action_taken" class="form-control" placeholder="Suspension for 3 days, etc.">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Issue Warning</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
