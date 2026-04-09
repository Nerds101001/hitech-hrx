@php
  use Illuminate\Support\Facades\Session;$configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Tenants')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
    'resources/assets/vendor/libs/@form-validation/form-validation.scss',
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'
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
  @vite([
    'resources/js/main-datatable.js',
    'resources/assets/js/app/tenant-index.js'
    ])
@endsection

@section('content')
  <div class="row">
    <div class="col">
      <h4>@lang('Tenants')</h4>
    </div>
    <div class="col text-end">
      <button type="button" class="btn btn-primary" data-bs-toggle="offcanvas"
              data-bs-target="#offcanvasCreateTenant">
        <i class="bx bx-plus bx-sm me-0 me-sm-2"></i> @lang('Create')
      </button>
    </div>
  </div>
  <div class="card">
    <div class="card-datatable table-responsive">
      <table id="datatable" class="table border-top">
        <thead>
        <tr>
          <th>@lang('Id')</th>
          <th>@lang('Name')</th>
          <th>@lang('Domain')</th>
          <th>@lang('Created On')</th>
        </tr>
        </thead>
        <tbody>
        @foreach($tenants as $tenant)
          <tr>
            <td>{{$loop->iteration}}</td>
            <td>{{$tenant->id}}</td>
            <th>{{$tenant->domains->count() > 0 ? $tenant->domains->first()->domain : 'N/A'}}</th>
            <td>{{$tenant->created_at}}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>

  </div>

  @include('_partials._modals.superAdmin.add_tenant')
@endsection
