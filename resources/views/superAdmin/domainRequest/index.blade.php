@extends('layouts/layoutMaster')

@section('title', __('Domain Requests'))

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
'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'])
@endsection

@section('page-script')
  @vite(['resources/assets/js/app/domain-request-index.js'])
@endsection


@section('content')
  <div class="row">
    <div class="col">
      <h4>@lang('Domain Requests')</h4>
    </div>
  </div>
  <div class="card">
    <div class="card-datatable table-responsive">
      <table class="datatables-domainRequests table border-top">
        <thead>
        <tr>
          <th>@lang('')</th>
          <th>@lang('Id')</th>
          <th>@lang('User')</th>
          <th>@lang('Domain Name')</th>
          <th>@lang('Created At')</th>
          <th>@lang('Status')</th>
          <th>@lang('Actions')</th>
        </tr>
        </thead>
      </table>
    </div>
  </div>
  @include('_partials._modals.domainRequest.domain_request_details')
@endsection
