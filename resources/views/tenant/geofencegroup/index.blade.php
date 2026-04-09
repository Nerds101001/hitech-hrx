@extends('layouts/layoutMaster')

@section('title', __('Geofence Group Management'))

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
  ])
@endsection

@section('page-script')
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const dt_table = $('.datatables-geofence');
      if (dt_table.length) {
        dt_table.DataTable({
          processing: true,
          serverSide: true,
          ajax: "{{ route('geofencegroup.indexAjax') }}",
          columns: [
            { data: 'id' },
            { data: 'name' },
            { data: 'radius' },
            { data: 'status' },
            { data: 'action', orderable: false, searchable: false }
          ],
          dom: '<"card-header flex-column flex-md-row"<"head-label text-center"><"dt-action-buttons text-end pt-3 pt-md-0"B>><"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>t<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
          buttons: [
            {
              text: '<i class="bx bx-plus me-sm-1"></i> <span class="d-none d-sm-inline-block">Add New Geofence</span>',
              className: 'create-new btn btn-primary'
            }
          ]
        });
      }
    });
  </script>
@endsection

@section('content')
<div class="row g-6 px-4">
  <div class="col-lg-12">
    <x-hero-banner 
      title="Geofence Group Management" 
      subtitle="Manage geographical boundaries for mobile attendance"
      icon="bx-map-alt"
      gradient="primary"
    />
  </div>

  <div class="col-12 mt-6">
    <div class="hitech-card animate__animated animate__fadeInUp">
      <div class="hitech-card-header border-bottom">
        <h5 class="title mb-0">Geofence Groups</h5>
      </div>
      <div class="card-datatable table-responsive p-0">
        <table class="datatables-geofence table table-hover border-top mb-0">
          <thead>
            <tr>
              <th>ID</th>
              <th>Group Name</th>
              <th>Radius (m)</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
