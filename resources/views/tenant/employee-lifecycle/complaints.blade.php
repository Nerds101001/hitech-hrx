@extends('layouts/layoutMaster')

@section('title', __('Employee Complaints'))

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
      title="Employee Complaints"
      subtitle="Manage internal grievances and formal complaints"
      icon="ti ti-message-exclamation"
      :show-stats="false"
    />

    <div class="card hitech-card-white border-0 shadow-sm rounded-4 mb-4">
      <div class="card-body">
        <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#complaintModal">
          <i class="ti ti-plus me-2"></i>Log New Complaint
        </button>
      </div>
    </div>

    <div class="card hitech-card-white border-0 shadow-sm rounded-4">
      <div class="card-header border-bottom">
        <h5 class="card-title mb-0">Complaints Registry</h5>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>Complainant</th>
                <th>Against (Employee)</th>
                <th>Title</th>
                <th>Date</th>
                <th>Category</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($complaints as $complaint)
                <tr>
                  <td>{{ $complaint->complainant->name ?? 'N/A' }}</td>
                  <td>{{ $complaint->user->name ?? 'N/A' }}</td>
                  <td>{{ Str::limit($complaint->title, 25) }}</td>
                  <td>{{ $complaint->complaint_date->format('d M Y') }}</td>
                  <td><span class="badge bg-label-secondary text-capitalize">{{ $complaint->category }}</span></td>
                  <td>
                    <span class="badge {{ $complaint->status == 'resolved' ? 'bg-label-success' : ($complaint->status == 'pending' ? 'bg-label-warning' : 'bg-label-danger') }}">
                      {{ ucfirst($complaint->status) }}
                    </span>
                  </td>
                  <td>
                    <button class="btn btn-sm btn-icon btn-label-primary"><i class="ti ti-eye"></i></button>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" class="text-center py-4 text-muted">No complaints found.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
        <div class="mt-4">
          {{ $complaints->links() }}
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Complaint Modal -->
<div class="modal fade" id="complaintModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-content-hitech border-0 shadow-lg">
      <div class="modal-header">
        <h5 class="modal-title">Log Formal Complaint</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="{{ route('employee-lifecycle.complaints.store') }}" method="POST">
        @csrf
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Complainant</label>
              <select name="complainant_id" class="form-select select2" required>
                <option value="">Select Employee</option>
                @foreach(App\Models\User::all() as $user)
                  <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Against (Employee)</label>
              <select name="user_id" class="form-select select2" required>
                <option value="">Select Employee</option>
                @foreach(App\Models\User::all() as $user)
                  <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Title / Subject</label>
            <input type="text" name="title" class="form-control" placeholder="Brief summary of the issue" required>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Category</label>
              <select name="category" class="form-select" required>
                <option value="behavior">Behavior / Conduct</option>
                <option value="performance">Performance</option>
                <option value="harassment">Harassment</option>
                <option value="equipment">Equipment / Facility</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Date of Grievance</label>
              <input type="date" name="complaint_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>
          </div>
          <div class="mb-0">
            <label class="form-label">Detailed Description</label>
            <textarea name="description" class="form-control" rows="4" required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-warning">Log Complaint</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
