@extends('layouts/layoutMaster')

@section('title', __('Employee Promotions'))

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
      title="Employee Promotions"
      subtitle="Manage employee promotions and career advancement"
      icon="ti ti-trending-up"
      :show-stats="false"
    />

    <!-- Actions -->
    <div class="card hitech-card-white border-0 shadow-sm rounded-4 mb-4">
      <div class="card-body">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#promotionModal">
          <i class="ti ti-plus me-2"></i>New Promotion
        </button>
      </div>
    </div>

    <!-- Promotions Table -->
    <div class="card hitech-card-white border-0 shadow-sm rounded-4">
      <div class="card-header">
        <h5 class="card-title mb-0">Promotions History</h5>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover" id="promotionsTable">
            <thead>
              <tr>
                <th>Employee</th>
                <th>Previous Designation</th>
                <th>New Designation</th>
                <th>Type</th>
                <th>Promotion Date</th>
                <th>Salary Increase</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($promotions as $promotion)
                <tr>
                  <td>{{ $promotion->user->name ?? 'N/A' }}</td>
                  <td>{{ $promotion->previousDesignation->name ?? 'N/A' }}</td>
                  <td>{{ $promotion->newDesignation->name ?? 'N/A' }}</td>
                  <td>{{ $promotion->promotion_type_label }}</td>
                  <td>{{ $promotion->promotion_date->format('d M Y') }}</td>
                  <td>{{ $promotion->salary_increase ? '$' . number_format($promotion->salary_increase, 2) : 'N/A' }}</td>
                  <td>{!! $promotion->status_badge !!}</td>
                  <td>
                    @if($promotion->status == 'pending')
                      <button type="button" class="btn btn-sm btn-success" onclick="approvePromotion({{ $promotion->id }})">
                        <i class="ti ti-check"></i>
                      </button>
                    @endif
                    <button type="button" class="btn btn-sm btn-info" onclick="viewPromotion({{ $promotion->id }})">
                      <i class="ti ti-eye"></i>
                    </button>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Promotion Modal -->
<div class="modal fade" id="promotionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-content-hitech border-0 shadow-lg">
      <div class="modal-header">
        <h5 class="modal-title">New Promotion</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="{{ route('employee-lifecycle.promotions.store') }}" method="POST">
        @csrf
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Employee</label>
              <select name="user_id" class="form-select" required>
                <option value="">Select Employee</option>
                @foreach(App\Models\User::where('status', 1)->get() as $user)
                  <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">New Designation</label>
              <select name="new_designation_id" class="form-select" required>
                <option value="">Select Designation</option>
                @foreach(App\Models\Designation::all() as $designation)
                  <option value="{{ $designation->id }}">{{ $designation->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Promotion Type</label>
              <select name="promotion_type" class="form-select" required>
                <option value="">Select Type</option>
                <option value="merit">Merit Based</option>
                <option value="seniority">Seniority</option>
                <option value="performance">Performance Based</option>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Promotion Date</label>
              <input type="date" name="promotion_date" class="form-control" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Salary Increase</label>
              <input type="number" name="salary_increase" class="form-control" step="0.01" min="0">
            </div>
            <div class="col-12 mb-3">
              <label class="form-label">Reason</label>
              <textarea name="reason" class="form-control" rows="3"></textarea>
            </div>
            <div class="col-12 mb-3">
              <label class="form-label">Notes</label>
              <textarea name="notes" class="form-control" rows="3"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Submit Promotion</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
function approvePromotion(id) {
  if (confirm('Are you sure you want to approve this promotion?')) {
    fetch(`/employee-lifecycle/promotions/${id}/approve`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      }
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        location.reload();
      } else {
        alert('Error approving promotion');
      }
    });
  }
}

function viewPromotion(id) {
  // Implementation for viewing promotion details
  console.log('View promotion:', id);
}

// Initialize DataTable
$(document).ready(function() {
  $('#promotionsTable').DataTable({
    responsive: true,
    pageLength: 25,
    order: [[4, 'desc']] // Sort by promotion date
  });
});
</script>
@endsection
