@extends('layouts/layoutMaster')

@section('title', __('Business Trips'))

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
      title="Business Trips"
      subtitle="Manage employee travel requests and off-site assignments"
      icon="ti ti-plane-departure"
      :show-stats="false"
    />

    <div class="card hitech-card-white border-0 shadow-sm rounded-4 mb-4">
      <div class="card-body">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tripModal">
          <i class="ti ti-plus me-2"></i>New Trip Request
        </button>
      </div>
    </div>

    <div class="card hitech-card-white border-0 shadow-sm rounded-4">
      <div class="card-header border-bottom">
        <h5 class="card-title mb-0">Travel History</h5>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>Employee</th>
                <th>Destination</th>
                <th>Purpose</th>
                <th>Dates</th>
                <th>Budget</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($trips as $trip)
                <tr>
                  <td>{{ $trip->user->name }}</td>
                  <td>{{ $trip->destination }}</td>
                  <td>{{ Str::limit($trip->purpose, 20) }}</td>
                  <td>
                    <small>{{ $trip->start_date->format('d M') }} - {{ $trip->end_date->format('d M Y') }}</small>
                  </td>
                  <td>{{ number_format($trip->estimated_budget, 2) }}</td>
                  <td>
                    <span class="badge {{ $trip->status == 'approved' ? 'bg-label-success' : ($trip->status == 'pending' ? 'bg-label-warning' : 'bg-label-danger') }}">
                      {{ ucfirst($trip->status) }}
                    </span>
                  </td>
                  <td>
                    <button class="btn btn-sm btn-icon btn-label-primary"><i class="ti ti-eye"></i></button>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" class="text-center py-4 text-muted">No travel records found.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
        <div class="mt-4">
          {{ $trips->links() }}
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Trip Modal -->
<div class="modal fade" id="tripModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-content-hitech border-0 shadow-lg">
      <div class="modal-header">
        <h5 class="modal-title">Business Trip Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="{{ route('employee-lifecycle.trips.store') }}" method="POST">
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
            <label class="form-label">Destination</label>
            <input type="text" name="destination" class="form-control" placeholder="London, UK / Branch Office B" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Primary Purpose</label>
            <input type="text" name="purpose" class="form-control" placeholder="Client Meeting, Training, etc." required>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Start Date</label>
              <input type="date" name="start_date" class="form-control" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">End Date</label>
              <input type="date" name="end_date" class="form-control" required>
            </div>
          </div>
          <div class="mb-0">
            <label class="form-label">Estimated Budget</label>
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" name="estimated_budget" class="form-control" step="0.01">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Submit Trip Request</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
