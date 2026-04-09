@extends('layouts/layoutMaster')

@section('title', 'Asset Categories')

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
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
    'resources/assets/vendor/libs/@form-validation/popular.js',
    'resources/assets/vendor/libs/@form-validation/bootstrap5.js',
    'resources/assets/vendor/libs/@form-validation/auto-focus.js',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
  ])
@endsection

@section('page-script')
  @vite(['resources/js/main-datatable.js'])
  @vite(['resources/assets/js/app/asset-categories-index.js'])
  <style>
    .btn, .form-control, .form-select, .select2-container--bootstrap-5 .select2-selection {
        border-radius: 10px !important;
    }
  </style>
@endsection

@section('content')
<div class="row g-6 px-4">
  {{-- Hero Banner --}}
  <div class="col-lg-12">
    <x-hero-banner
      title="Asset Category Management"
      subtitle="Define and manage classifications for company assets"
      icon="bx-category"
      gradient="primary"
    />
  </div>

  {{-- Stats Cards --}}
  <x-stat-card
    title="Total Categories"
    value="{{ $stats['total'] }}"
    icon="bx-list-ul"
    color="primary"
    animation-delay="0.1s"
  />

  <x-stat-card
    title="Active"
    value="{{ $stats['active'] }}"
    icon="bx-check-circle"
    color="success"
    animation-delay="0.2s"
  />

  <x-stat-card
    title="Inactive"
    value="{{ $stats['inactive'] }}"
    icon="bx-x-circle"
    color="secondary"
    animation-delay="0.3s"
  />

  {{-- Main Content Table --}}
  <div class="col-12">
    <div class="hitech-card animate__animated animate__fadeInUp" style="animation-delay: 0.4s">
      <div class="hitech-card-header p-sm-5 p-4 border-bottom">
        <div class="row align-items-center g-4">
          <div class="col-md-7 d-flex align-items-center gap-3">
            <div class="search-wrapper-hitech w-px-400">
              <i class="bx bx-search text-muted ms-3"></i>
              <input type="text" class="form-control" placeholder="Search categories..." id="customSearchInput">
              <button class="btn-search" id="customSearchBtn">
                <i class="bx bx-search fs-5"></i>
              </button>
            </div>
          </div>
          <div class="col-md-5 d-flex align-items-center justify-content-end gap-3">
            <button type="button" class="btn btn-hitech add-new px-4 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#offcanvasAddOrUpdateCategory">
              <i class="bx bx-plus-circle fs-5"></i>
              <span>@lang('Add New Category')</span>
            </button>
          </div>
        </div>
      </div>

      <div class="card-datatable table-responsive p-0">
        <table class="datatables-asset-categories table table-hover border-top mb-0">
          <thead>
            <tr>
              <th></th>
              <th>#</th>
              <th>Name</th>
              <th>Description</th>
              <th>Assets Attached</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
        </table>
      </div>
    </div>
  </div>
</div>

@include('_partials._modals.asset-categories.add_or_update')
@endsection
