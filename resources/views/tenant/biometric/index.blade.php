@extends('layouts.layoutMaster')

@section('title', 'Biometric Devices')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
  <style>
    .icon-teal { background: rgba(18, 116, 100, 0.1); color: #127464; }
    .icon-blue { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
    .icon-orange { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
    
    .status-badge {
        padding: 0.35em 1em;
        border-radius: 50px;
        font-weight: 700;
        font-size: 0.7rem;
        text-transform: uppercase;
    }
    .status-online { background-color: #E6F4EA; color: #1E7E34; }
    .status-offline { background-color: #FCE8E6; color: #C5221F; }
    
    .hitech-action-icon {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        transition: all 0.3s ease;
        border: none;
    }
    .hitech-action-icon:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
  </style>
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
  ])
@endsection

@section('page-script')
  <script>
    $(document).ready(function() {
      var table = $('.datatables-biometric').DataTable({
        dom: 't<"d-flex justify-content-between align-items-center mx-3 mt-4 mb-2" <"small text-muted" i> <"pagination-wrapper" p>>',
        language: {
          info: 'Showing _START_ to _END_ of _TOTAL_ devices',
          paginate: {
            next: '<i class="bx bx-chevron-right"></i>',
            previous: '<i class="bx bx-chevron-left"></i>'
          }
        },
        scrollX: true
      });
      
      $('.test-connection').on('click', function() {
          var btn = $(this);
          var id = btn.data('id');
          var ip = btn.data('ip');
          var port = btn.data('port');
          
          btn.find('i').addClass('bx-spin');
          
          $.ajax({
              url: '{{ route("biometric.test-connection") }}',
              method: 'POST',
              data: {
                  _token: '{{ csrf_token() }}',
                  ip_address: ip,
                  port: port
              },
              success: function(response) {
                  btn.find('i').removeClass('bx-spin');
                  if(response.status == 'success') {
                      Swal.fire({
                        title: 'Connected!',
                        text: response.message,
                        icon: 'success',
                        customClass: { confirmButton: 'btn btn-primary' },
                        buttonsStyling: false
                      });
                  } else {
                      Swal.fire({
                        title: 'Failed!',
                        text: response.message,
                        icon: 'error',
                        customClass: { confirmButton: 'btn btn-primary' },
                        buttonsStyling: false
                      });
                  }
              }
          });
      });
    });
  </script>
@endsection

@section('content')
<div class="animate__animated animate__fadeIn">
  <div class="d-flex justify-content-between align-items-center mb-6 px-4">
    <div>
        <h4 class="fw-bold mb-1 text-heading" style="font-size: 1.5rem;">Biometric Devices</h4>
        <p class="text-muted small mb-0">Manage and sync your physical attendance machines.</p>
    </div>
    @can('Manage Attendance')
      <a href="{{ route('biometric.create') }}" class="btn-hitech shadow-sm rounded-pill px-5">
        <i class="bx bx-plus-circle me-1"></i> Add New Machine
      </a>
    @endcan
  </div>

  <div class="row g-4 mb-4">
    <div class="col-lg-4 col-md-6">
      <div class="card hitech-stat-card border-0">
        <div class="card-body p-4">
          <div class="stat-icon-wrap icon-teal">
            <i class="bx bx-chip"></i>
          </div>
          <span class="stat-label text-muted small fw-bold text-uppercase d-block mb-1">Total Devices</span>
          <h3 class="stat-value fw-bold mb-0 text-dark">{{ $devices->count() }}</h3>
        </div>
      </div>
    </div>
    <div class="col-lg-4 col-md-6">
      <div class="card hitech-stat-card border-0">
        <div class="card-body p-4">
          <div class="stat-icon-wrap icon-blue">
            <i class="bx bx-cloud-upload"></i>
          </div>
          <span class="stat-label text-muted small fw-bold text-uppercase d-block mb-1">Sync Status</span>
          <h3 class="stat-value fw-bold mb-0 text-dark">WAN Enabled</h3>
        </div>
      </div>
    </div>
    <div class="col-lg-4 col-md-6">
      <div class="card hitech-stat-card border-0">
        <div class="card-body p-4">
          <div class="stat-icon-wrap icon-orange">
            <i class="bx bx-timer"></i>
          </div>
          <span class="stat-label text-muted small fw-bold text-uppercase d-block mb-1">Latest Global Sync</span>
          <h3 class="stat-value fw-bold mb-0 text-dark" style="font-size: 1.2rem;">{{ $devices->max('last_sync_at') ?: 'Never' }}</h3>
        </div>
      </div>
    </div>
  </div>

  <div class="card hitech-card border-0 shadow-sm overflow-hidden">
    <div class="card-header bg-white py-4 px-4 border-bottom d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <i class="bx bx-list-ul me-2 fs-4 text-teal"></i>
            <h6 class="mb-0 fw-bold">Device Registry</h6>
        </div>
    </div>
    <div class="card-body p-0">
      <table class="table datatables-biometric table-hover mb-0">
        <thead class="bg-light">
          <tr>
            <th class="ps-4">Machine Name</th>
            <th>IP Address</th>
            <th>Port</th>
            <th>Branch / Site</th>
            <th>Status</th>
            <th>Last Sync</th>
            <th class="text-end pe-4">Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach($devices as $device)
            <tr>
              <td class="ps-4">
                <div class="d-flex align-items-center">
                  <div class="rounded-3 p-2 me-3" style="background: rgba(18, 116, 100, 0.05); color: #127464;">
                    <i class="bx bx-fingerprint fs-4"></i>
                  </div>
                  <span class="fw-bold">{{ $device->name }}</span>
                </div>
              </td>
              <td><code>{{ $device->ip_address }}</code></td>
              <td>{{ $device->port }}</td>
              <td>{{ $device->site->name ?? '--' }}</td>
              <td>
                <span class="status-badge {{ $device->status == 'online' ? 'status-online' : 'status-offline' }}">
                    {{ $device->status }}
                </span>
              </td>
              <td><span class="text-muted small">{{ $device->last_sync_at ?: 'No data' }}</span></td>
              <td class="pe-4">
                <div class="d-flex justify-content-end gap-2">
                  <button type="button" class="hitech-action-icon btn-outline-info test-connection" 
                          data-id="{{ $device->id }}" data-ip="{{ $device->ip_address }}" data-port="{{ $device->port }}"
                          title="Test Connection">
                    <i class="bx bx-broadcast"></i>
                  </button>
                  <a href="{{ route('biometric.edit', $device->id) }}" class="hitech-action-icon btn-outline-primary" title="Edit Machine">
                    <i class="bx bx-edit"></i>
                  </a>
                  <form action="{{ route('biometric.destroy', $device->id) }}" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="hitech-action-icon btn-outline-danger" title="Delete Device" onclick="return confirm('Remove this biometric machine?')">
                      <i class="bx bx-trash"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @endforeach
          @if($devices->isEmpty())
          <tr>
            <td colspan="7" class="text-center py-5">
              <div class="opacity-50">
                <i class="bx bx-file-blank fs-1 mb-2"></i>
                <p>No biometric devices found. Start by adding your first machine.</p>
              </div>
            </td>
          </tr>
          @endif
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
