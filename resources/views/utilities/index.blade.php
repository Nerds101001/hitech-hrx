@extends('layouts/layoutMaster')

@section('title', __('Utilities'))

<!-- Vendor Styles -->
@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'
  ])
@endsection

<!-- Vendor Scripts -->
@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
  ])
@endsection

@section('page-script')
  @vite(['resources/js/main-datatable.js'])
  @vite(['resources/assets/js/app/utilities-index.js'])
@endsection


@section('content')
  <div class="row">
    <div class="col">
      <h4>@lang('Utilities')</h4>
    </div>
    <div class="col text-end">

    </div>
  </div>
  <!-- Backup Management Section -->
  <div class="card mb-4 shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center bg-primary text-white">
      <h5 class="mb-0 text-white">Backup Management</h5>
      <div class="btn-group">
        <!-- Create Backup Icon Button -->
        @csrf
        <a onclick="createBackup()" class="btn btn-light text-black btn-icon shadow-sm" data-bs-toggle="tooltip"
           data-popup="tooltip-custom" data-bs-placement="top" title="Create Backup">
          <i class="bx bx-cloud-upload"></i>
        </a>

        <!-- Refresh Backup List Icon Button -->
        <button id="refresh-backup-list" class="btn btn-light text-black btn-icon shadow-sm ms-2"
                data-bs-toggle="tooltip" data-popup="tooltip-custom" data-bs-placement="top" title="Refresh Backup List"
                onclick="getBackupList()">
          <i class="bx bx-refresh"></i>
        </button>
      </div>
    </div>
    <div class="card-body">
      <!-- Backup List -->
      <div id="backupListDiv" class="row g-3 mt-3">
        <div class="d-flex justify-content-center mt-3">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Cache Management Section -->
  <div class="card mb-4 shadow-sm">
    <div class="card-header bg-primary text-white">
      <h5 class="mb-0 text-white">Cache & Logs Management</h5>
    </div>
    <div class="card-body">
      <div class="d-flex flex-wrap justify-content-start gap-3 mt-3">
        <form action="{{ route('utilities.clearCache') }}" method="POST" class="d-inline">
          @csrf
          <button id="clear-cache" class="btn btn-warning">Clear Cache</button>
        </form>
        <form action="{{ route('utilities.clearLog') }}" method="POST" class="d-inline">
          @csrf
          <button id="clear-logs" class="btn btn-danger">Clear Logs</button>
      </div>
    </div>
  </div>

@endsection
