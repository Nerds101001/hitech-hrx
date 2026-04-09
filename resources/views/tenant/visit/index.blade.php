@extends('layouts/layoutMaster')

@section('title', __('Visits'))

<!-- Vendor Styles -->
@section('vendor-style')
  @vite(['resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
 'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
 'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
 'resources/assets/vendor/libs/@form-validation/form-validation.scss',
 'resources/assets/vendor/libs/animate-css/animate.scss',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
  'resources/assets/vendor/scss/pages/hitech-portal.scss'])
@endsection

<!-- Vendor Scripts -->
@section('vendor-script')
  @vite(['resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
   'resources/assets/vendor/libs/@form-validation/popular.js',
    'resources/assets/vendor/libs/@form-validation/bootstrap5.js',
    'resources/assets/vendor/libs/@form-validation/auto-focus.js',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'])
@endsection

@section('page-script')
  @vite(['resources/assets/js/app/visits-index.js'])
@endsection


@section('content')

  <!-- Filters -->
  <div class="hitech-card mb-4 animate__animated animate__fadeInUp">
      <div class="hitech-card-header">
          <h5 class="mb-0 text-white">Filter Options</h5>
      </div>
      <div class="card-body">
          <div class="row align-items-end">
              <div class="col-md-3">
                  <label for="dateFilter" class="form-label text-white opacity-75">Filter by date</label>
                  <input type="date" id="dateFilter" name="dateFilter" class="form-control" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #fff;">
              </div>
          </div>
      </div>
  </div>


  <!-- Visits table card -->
  <div class="hitech-card animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
    <div class="hitech-card-header">
        <h5 class="mb-0 text-white">Visit Logs</h5>
    </div>
    <div class="card-datatable table-responsive">
      <table class="datatables-visits table border-top">
        <thead>
        <tr>
          <th>@lang('')</th>
          <th>@lang('Sl.No')</th>
          <th>@lang('User')</th>
          <th>@lang('Client')</th>
          <th>@lang('Created At')</th>
          <th>@lang('Image')</th>
          <th>@lang('Actions')</th>
        </tr>
        </thead>
      </table>
    </div>
  </div>
  @include('_partials._modals.visit.show_visit_details')
@endsection
