@extends('layouts/layoutMaster')

@section('title', __('Department Management'))

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
    'resources/assets/vendor/libs/select2/select2.scss',
    'resources/assets/vendor/libs/@form-validation/form-validation.scss',
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
    'resources/assets/vendor/libs/apex-charts/apex-charts.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
    'resources/assets/vendor/libs/select2/select2.js',
    'resources/assets/vendor/libs/@form-validation/popular.js',
    'resources/assets/vendor/libs/@form-validation/bootstrap5.js',
    'resources/assets/vendor/libs/@form-validation/auto-focus.js',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.js',
    'resources/assets/vendor/libs/apex-charts/apexcharts.js'
  ])
@endsection

@section('page-script')
  @vite(['resources/js/main-datatable.js'])
  @vite(['resources/js/main-helper.js'])
  @vite(['resources/assets/js/app/department-index.js'])
  <style>
    .form-control, .form-select, .select2-container--bootstrap-5 .select2-selection {
        border-radius: 12px !important;
    }
    .search-light-badge {
        background: rgba(0, 90, 90, 0.08);
        color: #005a5a;
        border: 1px solid rgba(0, 90, 90, 0.1);
    }
    .filter-item-hitech {
        height: 42px !important;
        border-radius: 10px !important;
    }
    .btn-hitech-icon {
        width: 42px;
        height: 42px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #005a5a;
        color: white;
        border-radius: 10px;
        transition: all 0.3s ease;
    }
    .btn-hitech-icon:hover {
        background: #004d4d;
        color: white;
        transform: translateY(-2px);
    }
    /* Search Bar Refinement (Pill shape) */
    .search-wrapper-hitech {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 50px !important;
        padding: 4px 4px 4px 0.5rem;
        display: flex;
        align-items: center;
        height: 50px;
        transition: all 0.3s ease;
    }
    .search-wrapper-hitech:focus-within {
        border-color: #005a5a;
        box-shadow: 0 0 0 4px rgba(0, 90, 90, 0.05);
    }
    .search-wrapper-hitech .form-control {
        border: none !important;
        box-shadow: none !important;
        background: transparent !important;
        height: 100% !important;
        font-size: 0.95rem;
        color: #1e293b !important;
    }
    .search-wrapper-hitech .btn-search {
        height: 40px !important;
        border-radius: 50px !important;
        background: #005a5a !important;
        color: #fff !important;
        padding: 0 1.75rem !important;
        font-weight: 600 !important;
        border: none !important;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
    }
    .search-wrapper-hitech .btn-search:hover {
        background: #004d4d !important;
        box-shadow: 0 4px 12px rgba(0, 90, 90, 0.2);
    }
    /* Stat Card Link Arrow */
    .stat-card-link-arrow {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8fafc;
        color: #94a3b8;
        transition: all 0.3s ease;
    }
    .hitech-stat-card:hover .stat-card-link-arrow {
        background: rgba(0, 90, 90, 0.08);
        color: #005a5a;
        transform: translateX(3px);
    }
  </style>
@endsection

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">
  {{-- Standardized Header --}}
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6 mx-4 py-2">
    <h3 class="fw-extrabold mb-0 text-dark">Department Management</h3>
    <button type="button" class="btn btn-hitech-primary shadow-sm rounded-pill px-5 add-new-department" data-bs-toggle="modal"
            data-bs-target="#modalAddOrUpdateDepartment">
      <i class="bx bx-plus-circle me-1"></i>Add Department
    </button>
  </div>

  {{-- Standardized Filter Card --}}
  <div class="px-4">
    <div class="hitech-card-white mb-6 overflow-hidden">
      <div class="card-body p-sm-5 p-4">
        <div class="d-flex flex-wrap align-items-center gap-3">
          {{-- Search Section --}}
          <div class="search-wrapper-hitech w-px-350 mw-100">
            <i class="bx bx-search text-muted ms-3 fs-5"></i>
            <input type="text" class="form-control" placeholder="Search department name or code..." id="customSearchInput">
            <button class="btn-search shadow-sm" id="customSearchBtn">
              <i class="bx bx-search fs-5"></i>
              <span>Search</span>
            </button>
          </div>

          {{-- Status Filter (Segmented Control) --}}
          <div class="segmented-control-hitech shadow-sm p-1 d-flex gap-1 bg-light rounded-pill ms-lg-2">
            <input type="radio" name="statusFilter" value="All" id="status_all" checked>
            <label for="status_all" class="control-label px-4 py-1 rounded-pill mb-0 pointer fw-semibold small">All Depts</label>

            <input type="radio" name="statusFilter" value="active" id="status_active">
            <label for="status_active" class="control-label px-4 py-1 rounded-pill mb-0 pointer fw-semibold small">Active</label>

            <input type="radio" name="statusFilter" value="inactive" id="status_inactive">
            <label for="status_inactive" class="control-label px-4 py-1 rounded-pill mb-0 pointer fw-semibold small">Inactive</label>
          </div>

          {{-- Spacer --}}
          <div class="flex-grow-1"></div>

          {{-- Length Menu & Export --}}
          <div class="d-flex align-items-center gap-3">
            <div class="d-flex align-items-center gap-2">
              <span class="text-muted fw-semibold small">Per Page:</span>
              <select class="form-select w-px-80 rounded-pill border-light shadow-none fw-bold" id="customLengthMenu">
                  <option value="10">10</option>
                  <option value="25">25</option>
                  <option value="50">50</option>
                  <option value="100">100</option>
              </select>
            </div>

            <button type="button" class="btn btn-hitech-primary px-4 shadow-sm" id="btnExportDepartments">
              <i class="bx bx-export fs-5"></i>
              <span>EXPORT</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Standardized Table Card --}}
  <div class="px-4">
    <div class="hitech-card-white p-0 overflow-hidden">
      <div class="card-datatable table-responsive">
        @include('_partials._loaders.center_loader')
        <table class="datatables-departments table m-0 shadow-none border-top">
          <thead>
            <tr>
              <th></th>
              <th>Id</th>
              <th>Department Name</th>
              <th>@lang('Code')</th>
              <th>@lang('Reports To')</th>
              <th>@lang('Managers')</th>
              <th>@lang('Status')</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
        </table>
      </div>
    </div>
  </div>
</div>


@include('_partials._modals.departments.add_or_update_departments')

<script>
function exportDepartments() {
  // Export department data
}

// Department Distribution Chart
document.addEventListener('DOMContentLoaded', function() {
  const options = {
    series: [{
      name: 'Employees',
      data: [45, 32, 28, 25, 22, 18, 15, 12]
    }],
    chart: {
      height: 300,
      type: 'bar',
      toolbar: { show: false },
      fontFamily: 'Inter, sans-serif'
    },
    colors: ['#005a5a'],
    plotOptions: {
      bar: {
        borderRadius: 8,
        horizontal: false,
        columnWidth: '60%',
        distributed: true
      }
    },
    dataLabels: {
      enabled: true,
      formatter: function(val) {
        return val + ' employees';
      },
      offsetY: -6,
      style: {
        fontSize: '12px',
        colors: ["#373d3f"]
      }
    },
    xaxis: {
      categories: ['Engineering', 'Sales', 'Marketing', 'HR', 'Finance', 'Operations', 'IT', 'Admin']
    },
    fill: {
      type: 'gradient',
      gradient: {
        shade: 'light',
        type: "vertical",
        shadeIntensity: 0.5,
        gradientToColors: ['#008080'],
        stops: [0, 100]
      }
    }
  };
  
  const chart = new ApexCharts(document.querySelector("#departmentChart"), options);
  chart.render();
});
</script>
@endsection
