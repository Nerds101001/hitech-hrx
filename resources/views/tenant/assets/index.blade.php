@extends('layouts/layoutMaster')

@section('title', 'Asset Management')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
    'resources/assets/vendor/libs/select2/select2.scss',
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
  <x-enhanced-css />
  <style>
    .admin-hero { margin-top: 0 !important; visibility: visible !important; min-height: 180px !important; }
    .stat-card-label { color: #475569 !important; font-weight: 700 !important; opacity: 1 !important; }
    .stat-card-sub { color: #64748b !important; }
    .stat-card-value { color: #1e293b !important; font-weight: 800 !important; }
    .hitech-card { border: 1px solid #e2e8f0 !important; box-shadow: 0 10px 30px rgba(13, 110, 253, 0.05) !important; background: #fff !important; }
    .badge { font-weight: 700 !important; box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important; padding: 0.5em 0.8em !important; }
    /* Force visibility of hero banner */
    .admin-hero-content { opacity: 1 !important; }
  </style>
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
    'resources/assets/vendor/libs/select2/select2.js',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
  ])
@endsection

@section('content')
<div class="row g-4">
  <div class="col-lg-12">
    <x-hero-banner
      title="Asset Management"
      subtitle="Track company assets, assignments, and lifecycle status"
      icon="bx-archive"
      gradient="primary"
    />
  </div>

  <x-stat-card
    title="Total Assets"
    value="{{ $stats['total'] ?? 0 }}"
    icon="bx-package"
    color="blue"
    animation-delay="0.1s"
  />

  <x-stat-card
    title="Available"
    value="{{ $stats['available'] ?? 0 }}"
    icon="bx-check-circle"
    color="teal"
    animation-delay="0.2s"
  />

  <x-stat-card
    title="Assigned"
    value="{{ $stats['assigned'] ?? 0 }}"
    icon="bx-user"
    color="blue"
    animation-delay="0.3s"
  />

  <x-stat-card
    title="Maintenance"
    value="{{ $stats['maintenance'] ?? 0 }}"
    icon="bx-wrench"
    color="amber"
    animation-delay="0.4s"
  />

  {{-- Main Content Table --}}
  <div class="col-12">
    <div class="hitech-card animate__animated animate__fadeInUp" style="animation-delay: 0.5s">
      <div class="hitech-card-header p-sm-5 p-4 border-bottom">
        <div class="row align-items-center g-6">
          <div class="col-md-7 d-flex align-items-center gap-3">
            <div class="search-wrapper-hitech w-px-400">
              <i class="bx bx-search text-muted ms-3"></i>
              <input type="text" class="form-control" placeholder="Search assets..." id="customSearchInput">
              <button class="btn-search" id="customSearchBtn">
                <i class="bx bx-search fs-5"></i>
              </button>
            </div>
          </div>
          <div class="col-md-5 d-flex align-items-center justify-content-end gap-3">
            <button type="button" class="btn btn-hitech add-new px-4 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalAddOrUpdateAsset">
              <i class="bx bx-plus-circle fs-5"></i>
              <span>@lang('Add Asset')</span>
            </button>
          </div>
        </div>
      </div>
      <div class="card-datatable table-responsive p-0">
        <table class="datatables-assets table table-hover border-top mb-0">
          <thead>
            <tr>
              <th>Code</th>
              <th>Name</th>
              <th>Category</th>
              <th>Assigned To</th>
              <th>Status</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
        </table>
      </div>
    </div>
  </div>
</div>
@include('_partials._modals.assets.add_or_update')
@endsection

@section('page-script')
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      // Select2 initialization for modal
      const initSelect2 = () => {
        $('.select2-modal').select2({
          dropdownParent: $('#modalAddOrUpdateAsset'),
          placeholder: 'Select an option',
          allowClear: true
        });
      };

      const dt = $('.datatables-assets').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
          url: '{{ route('assets.listAjax') }}',
          data: function (d) {
            d.searchTerm = $('#customSearchInput').val();
          }
        },
        columns: [
          { data: 'asset_code', name: 'asset_code' },
          { data: 'name', name: 'name' },
          { data: 'category_name', name: 'category.name', orderable: false, searchable: false },
          { data: 'assigned_user', name: 'assignedUser.first_name', orderable: false, searchable: false },
          { data: 'status_badge', name: 'status', orderable: false, searchable: false },
          { data: 'action', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        dom: '<"row"<"col-sm-12"tr>><"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
        language: {
          sLengthMenu: '_MENU_',
          search: '',
          searchPlaceholder: 'Search Asset',
          info: 'Displaying _START_ to _END_ of _TOTAL_ entries',
          paginate: {
            next: '<i class="bx bx-chevron-right bx-sm"></i>',
            previous: '<i class="bx bx-chevron-left bx-sm"></i>'
          }
        },
        responsive: true
      });

      $('#customSearchInput').on('keyup', function () {
        dt.draw();
      });

      $('#customSearchBtn').on('click', function () {
        dt.draw();
      });

      // --- Dynamic Parameters Logic ---
      const dynamicFieldsContainer = $('#dynamicFieldsContainer');
      const dynamicFieldsArea = $('#dynamicFieldsArea');

      const renderDynamicFields = (parameters, values = {}) => {
        dynamicFieldsArea.empty();
        if (parameters && parameters.length > 0) {
          parameters.forEach(param => {
            const value = values[param] || '';
            const field = `
              <div class="col-md-6 mb-3">
                <label class="form-label-hitech">${param}</label>
                <input type="text" name="extra_details[${param}]" class="form-control form-control-hitech" value="${value}" placeholder="Enter ${param}">
              </div>
            `;
            dynamicFieldsArea.append(field);
          });
          dynamicFieldsContainer.removeClass('d-none');
        } else {
          dynamicFieldsContainer.addClass('d-none');
        }
      };

      $('#asset_category_id').on('change', function() {
        const categoryId = $(this).val();
        if (categoryId) {
          $.get(`${baseUrl}asset-categories/${categoryId}/edit`, function(res) {
            if (res.success && res.data.parameters) {
              renderDynamicFields(res.data.parameters);
            } else {
              renderDynamicFields([]);
            }
          });
        } else {
          renderDynamicFields([]);
        }
      });

      // Handle Add Asset
      $(document).on('click', '.add-new', function() {
        $('#assetForm')[0].reset();
        $('#asset_id').val('');
        $('#modalAssetLabel').text('Add Asset');
        renderDynamicFields([]);
        initSelect2();
      });

      // Handle Edit Asset
      $(document).on('click', '.edit-record', function(e) { // Changed from .edit-asset to .edit-record based on getListAjax column definition
        e.preventDefault();
        const id = $(this).data('id') || $(this).attr('onclick').match(/\d+/)[0]; // Handling both data-id and onclick call
        $('#modalAssetLabel').text('Edit Asset');
        
        $.get(`${baseUrl}asset-management/getAssetAjax/${id}`, function(res) {
          if (res.success) {
            const data = res.data;
            $('#asset_id').val(data.id);
            $('#asset_name').val(data.name);
            $('#asset_code').val(data.asset_code);
            $('#asset_category_id').val(data.category_id).trigger('change');
            $('#asset_assigned_to').val(data.assigned_to).trigger('change');
            $('#asset_status').val(data.status);
            $('#asset_brand').val(data.brand);
            $('#asset_model').val(data.model);
            $('#asset_serial_number').val(data.serial_number);
            $('#asset_location').val(data.location);
            $('#asset_warranty_expiry').val(data.warranty_expiry ? data.warranty_expiry.split('T')[0] : '');
            $('#asset_description').val(data.description);
            
            // Render dynamic fields after category change triggered
            setTimeout(() => {
              if (data.category && data.category.parameters) {
                renderDynamicFields(data.category.parameters, data.extra_details || {});
              }
            }, 300);

            $('#modalAddOrUpdateAsset').modal('show');
          }
        });
      });
      
      // Submit Handler
      $('#assetForm').on('submit', function(e) {
        e.preventDefault();
        const id = $('#asset_id').val();
        const url = id ? `${baseUrl}asset-management/${id}` : `{{ route('assets.store') }}`;
        
        // Use FormData for file upload
        const formData = new FormData(this);
        if (id) {
            formData.append('_method', 'PUT');
        }

        $.ajax({
          url: url,
          method: 'POST', // Always POST when using FormData + _method override
          data: formData,
          processData: false,
          contentType: false,
          success: function(res) {
            $('#modalAddOrUpdateAsset').modal('hide');
            Swal.fire({ icon: 'success', title: 'Success', text: 'Asset saved successfully' });
            dt.draw();
          },
          error: function(err) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to save asset' });
          }
        });
      });
    });
  </script>
@endsection
