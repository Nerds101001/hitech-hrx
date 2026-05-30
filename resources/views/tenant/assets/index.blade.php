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
    /* ===================== Hero Header ===================== */
    .sv-hero {
      background: linear-gradient(135deg, #0d7377 0%, #14a085 40%, #0a9396 70%, #00b4d8 100%);
      border-radius: 18px;
      padding: 2.5rem 2.8rem;
      margin-bottom: 2rem;
      position: relative;
      overflow: hidden;
      box-shadow: 0 8px 32px rgba(13, 115, 119, 0.30);
    }
    .sv-hero::before {
      content: '';
      position: absolute;
      top: -60px; right: -60px;
      width: 260px; height: 260px;
      background: rgba(255,255,255,0.07);
      border-radius: 50%;
    }
    .sv-hero::after {
      content: '';
      position: absolute;
      bottom: -80px; left: -40px;
      width: 200px; height: 200px;
      background: rgba(255,255,255,0.05);
      border-radius: 50%;
    }
    .sv-hero-title {
      font-size: 1.85rem;
      font-weight: 800;
      color: #fff;
      letter-spacing: -0.5px;
      margin-bottom: 0.3rem;
    }
    .sv-hero-subtitle {
      color: rgba(255,255,255,0.80);
      font-size: 0.97rem;
      margin-bottom: 0;
    }
    .sv-hero .btn-new {
      background: #fff;
      color: #0d7377;
      border: none;
      font-weight: 700;
      border-radius: 10px;
      padding: 0.65rem 1.4rem;
      font-size: 0.92rem;
      box-shadow: 0 4px 18px rgba(0,0,0,0.12);
      transition: all 0.22s ease;
    }
    .sv-hero .btn-new:hover {
      background: #0d7377;
      color: #fff;
      box-shadow: 0 6px 22px rgba(13,115,119,0.35);
      transform: translateY(-2px);
    }



    .stat-card-label { color: #475569 !important; font-weight: 700 !important; opacity: 1 !important; }
    .stat-card-sub { color: #64748b !important; }
    .stat-card-value { color: #1e293b !important; font-weight: 800 !important; }
    .hitech-card { border: 1px solid #e2e8f0 !important; box-shadow: 0 10px 30px rgba(13, 110, 253, 0.05) !important; background: #fff !important; }
    .badge { font-weight: 700 !important; box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important; padding: 0.5em 0.8em !important; }
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
<div class="animate__animated animate__fadeIn px-4">
  {{-- ===== Hero Header ===== --}}
  <div class="sv-hero mb-4">
    <div class="d-flex justify-content-between align-items-center" style="position:relative; z-index:2;">
      <div>
        <h1 class="sv-hero-title">📦 Asset Management</h1>
        <p class="sv-hero-subtitle">Track company assets, assignments, and lifecycle status in one place.</p>
      </div>
      <div>
        <button type="button" class="btn btn-new add-new" data-bs-toggle="modal" data-bs-target="#modalAddOrUpdateAsset">
          <i class="bx bx-plus me-1"></i> Add Asset
        </button>
      </div>
    </div>
  </div>

  <div class="row g-4 mb-4">

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
      <div class="card-header bg-white border-bottom" style="padding: 1.5rem !important;">
        <div class="d-flex align-items-center justify-content-between w-100">
          <div class="d-flex align-items-center gap-4">
            <h5 class="title mb-0 text-dark fw-bold">Assets List</h5>
          </div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-hitech add-new d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalAddOrUpdateAsset">
              <i class="bx bx-plus me-1"></i> Add Asset
            </button>
          </div>
        </div>
      </div>
      <div class="card-body p-4 border-bottom">
        <div class="d-flex flex-wrap align-items-center gap-3">
          {{-- Search --}}
          <div class="search-wrapper-hitech" style="width: 400px;">
            <i class="bx bx-search text-muted ms-3 fs-5"></i>
            <input type="text" class="form-control" placeholder="Search assets..." id="customSearchInput" style="padding-left: 2.5rem;">
            <button class="btn-search shadow-sm" id="customSearchBtn">
              <i class="bx bx-search fs-5"></i>
              <span>Search</span>
            </button>
          </div>

          {{-- Category Filter --}}
          <div class="compact-select" style="min-width: 160px;">
            <select id="filterCategory" class="form-select select2 filter-item-hitech">
              <option value="">Category: All</option>
              @foreach($categories as $category)
                <option value="{{ $category->id }}">{{ $category->name }}</option>
              @endforeach
            </select>
          </div>

          {{-- Status Filter --}}
          <div class="compact-select" style="min-width: 150px;">
            <select id="filterStatus" class="form-select select2 filter-item-hitech">
              <option value="">Status: All</option>
              <option value="available">Available</option>
              <option value="assigned">Assigned</option>
              <option value="maintenance">Maintenance</option>
              <option value="retired">Retired</option>
            </select>
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
            d.category = $('#filterCategory').val();
            d.status = $('#filterStatus').val();
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

      $('#customSearchInput').on('keyup', function (e) {
        if(e.key === 'Enter') {
            dt.draw();
        }
      });

      $('#customSearchBtn, #filterCategory, #filterStatus').on('click change', function () {
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
