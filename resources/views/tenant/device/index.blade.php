@extends('layouts/layoutMaster')

@section('title', __('Devices'))

<!-- Vendor Styles -->
@section('vendor-style')
    @vite([
        'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss', 
        'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss', 
        'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss', 
        'resources/assets/vendor/libs/@form-validation/form-validation.scss', 
        'resources/assets/vendor/libs/animate-css/animate.scss', 
        'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss', 
        'resources/assets/vendor/libs/select2/select2.scss',
        'resources/assets/vendor/scss/pages/hitech-portal.scss'
    ])
@endsection

<!-- Vendor Scripts -->
@section('vendor-script')
    @vite([
        'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js', 
        'resources/assets/vendor/libs/@form-validation/popular.js', 
        'resources/assets/vendor/libs/@form-validation/bootstrap5.js', 
        'resources/assets/vendor/libs/@form-validation/auto-focus.js', 
        'resources/assets/vendor/libs/sweetalert2/sweetalert2.js', 
        'resources/assets/vendor/libs/select2/select2.js'
    ])
@endsection

@section('page-script')
    @vite(['resources/assets/js/app/device-index.js'])
@endsection


@section('content')

    <!-- Filter Row -->
    <div class="hitech-card mb-4 animate__animated animate__fadeInUp">
        <div class="hitech-card-header">
            <h5 class="mb-0 text-white">Filter Options</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <label for="userFilter" class="form-label text-white opacity-75">Filter by user</label>
                    <select class="form-select select2" id="userFilter">
                        <option value="">All Users</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}">{{ $user->first_name }} {{ $user->last_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Device Status table card -->
    <div class="hitech-card animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
        <div class="hitech-card-header">
            <h5 class="mb-0 text-white">Registered Devices</h5>
        </div>
        <div class="card-datatable table-responsive">
            <table class="datatables-deviceStatus table border-top">
                <thead>
                    <tr>
                        <th>@lang('')</th>
                        <th>@lang('Id')</th>
                        <th>@lang('User')</th>
                        <th>@lang('Device Type')</th>
                        <th>@lang('Brand')</th>
                        <th>@lang('Model')</th>
                        <th>@lang('App Version')</th>
                        <th>@lang('Actions')</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
    @include('_partials._modals.device.show_device_details')
@endsection
