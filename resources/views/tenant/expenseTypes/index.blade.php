@php
    $configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', __('Expense Types'))

<!-- Vendor Styles -->
@section('vendor-style')
    @vite([
      'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss', 
      'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss', 
      'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss', 
      'resources/assets/vendor/libs/@form-validation/form-validation.scss', 
      'resources/assets/vendor/libs/animate-css/animate.scss', 
      'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
      'resources/assets/vendor/scss/pages/hitech-portal.scss'
    ])
    <style>
      .btn, .form-control, .form-select, .select2-container--bootstrap-5 .select2-selection {
          border-radius: 10px !important;
      }
    </style>
@endsection

<!-- Vendor Scripts -->
@section('vendor-script')
    @vite(['resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js', 'resources/assets/vendor/libs/@form-validation/popular.js', 'resources/assets/vendor/libs/@form-validation/bootstrap5.js', 'resources/assets/vendor/libs/@form-validation/auto-focus.js', 'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'])
@endsection

@section('page-script')
    @vite(['resources/js/main-datatable.js'])
    @vite(['resources/js/main-helper.js'])
    @vite(['resources/assets/js/app/expense-type-index.js'])
@endsection


@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">
  {{-- Standardized Header --}}
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6 mx-4 py-2">
    <h3 class="fw-extrabold mb-0 text-dark">Expense Type Management</h3>
    <button type="button" class="btn btn-hitech-primary shadow-sm rounded-pill px-5 add-new" data-bs-toggle="modal" data-bs-target="#modalAddOrUpdateExpenseType">
      <i class="bx bx-plus-circle me-1"></i>Add New Type
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
            <input type="text" class="form-control" placeholder="Search expense types..." id="customSearchInput">
            <button class="btn-search shadow-sm" id="customSearchBtn">
              <i class="bx bx-search fs-5"></i>
              <span>Search</span>
            </button>
          </div>

          {{-- Status Filter (Segmented Control) --}}
          <div class="segmented-control-hitech shadow-sm p-1 d-flex gap-1 bg-light rounded-pill ms-lg-2">
            <input type="radio" name="statusFilter" value="All" id="status_all" checked>
            <label for="status_all" class="control-label px-4 py-1 rounded-pill mb-0 pointer fw-semibold small">All Types</label>

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

            <button type="button" class="btn btn-hitech-primary px-4 shadow-sm" id="btnExportExpenseTypes">
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
        <table class="datatables-expenseTypes table m-0 shadow-none border-top">
          <thead>
            <tr>
              <th>@lang('')</th>
              <th>@lang('Id')</th>
              <th>@lang('Name')</th>
              <th>@lang('Code')</th>
              <th>@lang('Description')</th>
              <th class="text-center">@lang('Status')</th>
              <th class="text-center">@lang('Actions')</th>
            </tr>
          </thead>
        </table>
      </div>
    </div>
  </div>
</div>

    @include('_partials._modals.expenseType.add_or_update_expense_type')
@endsection
