@extends('layouts/layoutMaster')

@section('title', __('Employee Terminations'))

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
      title="Employee Terminations"
      subtitle="Manage company-initiated separations and offboarding"
      icon="ti ti-user-x"
      :show-stats="false"
    />

    <div class="card hitech-card-white border-0 shadow-sm rounded-4 mb-4">
      <div class="card-body">
        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#terminationModal">
          <i class="ti ti-plus me-2"></i>Initiate Termination
        </button>
      </div>
    </div>

    <div class="card hitech-card-white border-0 shadow-sm rounded-4">
      <div class="card-header border-bottom">
        <h5 class="card-title mb-0">Termination Records</h5>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-hover">
            <thead>
              <tr>
                <th>Employee</th>
                <th>Termination Type</th>
                <th>Notice Date</th>
                <th>Termination Date</th>
                <th>Status</th>
                <th>Processed By</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($terminations as $termination)
                <tr>
                  <td>
                    <div class="d-flex align-items-center">
                      <div class="avatar avatar-sm me-3">
                        <img src="{{ $termination->user->getProfilePicture() }}" alt="Avatar" class="rounded-circle">
                      </div>
                      <div class="d-flex flex-column">
                        <span class="fw-bold">{{ $termination->user->name }}</span>
                        <small class="text-muted">{{ $termination->user->code }}</small>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span class="text-capitalize">{{ str_replace('_', ' ', $termination->type) }}</span>
                  </td>
                  <td>{{ $termination->notice_date->format('d M Y') }}</td>
                  <td>{{ $termination->termination_date->format('d M Y') }}</td>
                  <td>
                    <span class="badge {{ $termination->status == 'completed' ? 'bg-label-success' : 'bg-label-warning' }}">
                      {{ ucfirst($termination->status) }}
                    </span>
                  </td>
                  <td>{{ $termination->processedBy->name ?? 'System' }}</td>
                  <td>
                    <button class="btn btn-sm btn-icon btn-label-primary"><i class="ti ti-file-text"></i></button>
                    <button class="btn btn-sm btn-icon btn-label-danger"><i class="ti ti-trash"></i></button>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" class="text-center py-4 text-muted">No termination records found.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
        <div class="mt-4">
          {{ $terminations->links() }}
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Termination Modal -->
<div class="modal fade" id="terminationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-content-hitech border-0 shadow-lg">
      <div class="modal-header">
        <h5 class="modal-title text-danger">Process Employee Termination</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="{{ route('employee-lifecycle.terminations.store') }}" method="POST">
        @csrf
        <div class="modal-body">
            <div class="alert alert-warning d-flex align-items-center mb-4">
                <i class="ti ti-alert-triangle me-2"></i>
                <span>This action will deactivate the user's account upon the termination date.</span>
            </div>
          <div class="mb-3">
            <label class="form-label">Employee</label>
            <select name="user_id" class="form-select select2" required>
              <option value="">Select Employee</option>
              @foreach(App\Models\User::where('status', '!=', \App\Enums\UserAccountStatus::TERMINATED)->get() as $user)
                <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->code }})</option>
              @endforeach
            </select>
          </div>

          <!-- Dynamic Assets Checker Container -->
          <div id="termination-assets-container" class="mb-3 d-none">
            <label class="form-label d-block fw-bold text-warning mb-2">
              <i class="ti ti-device-laptop me-1"></i> Assigned Assets & Equipment
            </label>
            <div class="border rounded p-3 bg-light">
              <div class="alert alert-info py-2 px-3 mb-3" style="font-size: 0.85rem;">
                Please verify if these assets have been returned. Checked assets will be marked as <strong>Available</strong> and restocked automatically.
              </div>
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="selectAllAssets" checked>
                <label class="form-check-label fw-bold" for="selectAllAssets">
                  Mark all as Returned
                </label>
              </div>
              <hr class="my-2">
              <div id="termination-assets-list" style="max-height: 180px; overflow-y: auto;">
                <!-- Filled dynamically via JS -->
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Termination Type</label>
            <select name="termination_type" class="form-select" required>
              <option value="layoff">Layoff</option>
              <option value="misconduct">Misconduct</option>
              <option value="performance">Poor Performance</option>
              <option value="violation">Policy Violation</option>
              <option value="contract_end">End of Contract</option>
            </select>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Notice Date</label>
              <input type="date" name="notice_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Termination Date</label>
              <input type="date" name="termination_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>
          </div>
          <div class="mb-0">
            <label class="form-label">Remarks / Internal Notes</label>
            <textarea name="remarks" class="form-control" rows="4"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Confirm Termination</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
  $(document).ready(function() {
    // Select element
    const $employeeSelect = $('select[name="user_id"]');
    const $assetsContainer = $('#termination-assets-container');
    const $assetsList = $('#termination-assets-list');
    const $selectAll = $('#selectAllAssets');

    // Function to load assets
    function checkEmployeeAssets(userId) {
      if (!userId) {
        $assetsContainer.addClass('d-none');
        $assetsList.empty();
        return;
      }

      // Fetch assets using user-assets AJAX route
      const url = `{{ route('employee-lifecycle.user-assets', '') }}/${userId}`;
      
      $.ajax({
        url: url,
        method: 'GET',
        success: function(response) {
          if (response.success && response.assets && response.assets.length > 0) {
            $assetsList.empty();
            response.assets.forEach(function(asset) {
              const html = `
                <div class="form-check mb-2">
                  <input class="form-check-input asset-checkbox" type="checkbox" name="returned_assets[]" value="${asset.id}" id="asset_${asset.id}" checked>
                  <label class="form-check-label" for="asset_${asset.id}">
                    <span class="fw-semibold text-dark">${asset.name}</span> <small class="text-muted">(${asset.asset_code})</small>
                    ${asset.serial_number ? `<span class="badge bg-label-secondary ms-1" style="font-size:0.7rem;">S/N: ${asset.serial_number}</span>` : ''}
                  </label>
                </div>
              `;
              $assetsList.append(html);
            });
            $assetsContainer.removeClass('d-none');
            // reset select-all status to checked
            $selectAll.prop('checked', true);
          } else {
            $assetsContainer.addClass('d-none');
            $assetsList.empty();
          }
        },
        error: function(xhr, status, error) {
          console.error("Failed to load user assets:", error);
          $assetsContainer.addClass('d-none');
          $assetsList.empty();
        }
      });
    }

    // Bind event for standard & Select2 dropdown
    $employeeSelect.on('change', function() {
      checkEmployeeAssets($(this).val());
    });

    // Select/Deselect All Checkbox Logic
    $selectAll.on('change', function() {
      const isChecked = $(this).prop('checked');
      $('.asset-checkbox').prop('checked', isChecked);
    });

    // Individual checkbox change updates SelectAll checkbox
    $(document).on('change', '.asset-checkbox', function() {
      const total = $('.asset-checkbox').length;
      const checked = $('.asset-checkbox:checked').length;
      $selectAll.prop('checked', total === checked);
    });
  });
</script>
@endsection
