@extends('layouts/layoutMaster')

@section('title', __('Coupons'))

<!-- Vendor Styles -->
@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
    'resources/assets/vendor/libs/@form-validation/form-validation.scss',
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'
  ])
@endsection

<!-- Vendor Scripts -->
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
  @vite(['resources/js/main-helper.js'])
  @vite(['resources/assets/js/app/coupon-index.js'])
@endsection

@section('content')
  <div class="row">
    <div class="col">
      <h4>@lang('Coupons')</h4>
    </div>
    <div class="col text-end">
      <button type="button" class="btn btn-primary add-new" data-bs-toggle="offcanvas"
              data-bs-target="#offcanvasCreateCoupon">
        <i class="bx bx-plus bx-sm me-0 me-sm-2"></i> @lang('Add New')
      </button>
    </div>
  </div>
  <!-- Notification table card -->
  <div class="card">
    <div class="card-datatable table-responsive">
      <table class="datatables-coupons table border-top">
        <thead>
        <tr>
          <th>@lang('')</th>
          <th>@lang('Id')</th>
          <th>@lang('Discount Type')</th>
          <th>@lang('Code')</th>
          <th>@lang('Expiry Date')</th>
          <th>@lang('Status')</th>
          <th>@lang('Actions')</th>
        </tr>
        </thead>
      </table>
    </div>

  </div>
    @include('_partials._modals.coupon.create_coupon')
@endsection
