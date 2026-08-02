@php
    $configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', __('Setu Salesperson Mappings'))

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
    <style>
      .btn, .form-control, .form-select, .select2-container--bootstrap-5 .select2-selection {
          border-radius: 10px !important;
      }
    </style>
@endsection

@section('vendor-script')
    @vite([
      'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
      'resources/assets/vendor/libs/select2/select2.js',
      'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
    ])
@endsection

@section('page-script')
    @vite(['resources/assets/js/app/setu-mapping-index.js'])
@endsection

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">
  {{-- Standardized Header --}}
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6 mx-4 py-2">
    <div>
      <h3 class="fw-extrabold mb-0 text-dark">Setu Salesperson Mappings</h3>
      <p class="text-muted mb-0 small">Which HRX user each Tally salesperson-ledger name refers to. Rows are reclassified here, never added or removed — an "Excluded" row (a ledger bucket, city, or brand) is a correct final answer, not something to delete.</p>
    </div>
  </div>

  {{-- Stat chips --}}
  <div class="px-4 mb-4 d-flex flex-wrap gap-2">
    <span class="badge bg-label-hitech rounded-pill px-3 py-2 fw-bold">{{ $stats['total'] }} Total</span>
    <span class="badge bg-success rounded-pill px-3 py-2 fw-bold">{{ $stats['confirmed'] }} Confirmed</span>
    <span class="badge bg-warning text-dark rounded-pill px-3 py-2 fw-bold">{{ $stats['pending'] }} Pending Review</span>
    <span class="badge bg-secondary rounded-pill px-3 py-2 fw-bold">{{ $stats['excluded'] }} Excluded</span>
  </div>

  {{-- Standardized Filter Card --}}
  <div class="px-4">
    <div class="hitech-card-white mb-6 overflow-hidden">
      <div class="card-body p-sm-5 p-4">
        <div class="d-flex flex-wrap align-items-center gap-3">
          <div class="search-wrapper-hitech w-px-350 mw-100">
            <i class="bx bx-search text-muted ms-3 fs-5"></i>
            <input type="text" class="form-control" placeholder="Search Tally name or note..." id="customSearchInput">
            <button class="btn-search shadow-sm" id="customSearchBtn">
              <i class="bx bx-search fs-5"></i>
              <span>Search</span>
            </button>
          </div>

          <div class="segmented-control-hitech shadow-sm p-1 d-flex gap-1 bg-light rounded-pill ms-lg-2">
            <input type="radio" name="statusFilter" value="All" id="status_all" checked>
            <label for="status_all" class="control-label px-4 py-1 rounded-pill mb-0 pointer fw-semibold small">All</label>

            <input type="radio" name="statusFilter" value="pending" id="status_pending">
            <label for="status_pending" class="control-label px-4 py-1 rounded-pill mb-0 pointer fw-semibold small">Pending</label>

            <input type="radio" name="statusFilter" value="confirmed" id="status_confirmed">
            <label for="status_confirmed" class="control-label px-4 py-1 rounded-pill mb-0 pointer fw-semibold small">Confirmed</label>

            <input type="radio" name="statusFilter" value="excluded" id="status_excluded">
            <label for="status_excluded" class="control-label px-4 py-1 rounded-pill mb-0 pointer fw-semibold small">Excluded</label>
          </div>

          <div class="flex-grow-1"></div>

          <div class="d-flex align-items-center gap-2">
            <span class="text-muted fw-semibold small">Per Page:</span>
            <select class="form-select w-px-80 rounded-pill border-light shadow-none fw-bold" id="customLengthMenu">
                <option value="10">10</option>
                <option value="25" selected>25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Standardized Table Card --}}
  <div class="px-4">
    <div class="hitech-card-white p-0 overflow-hidden">
      <div class="card-datatable table-responsive">
        <table class="datatables-setu-mappings table m-0 shadow-none border-top">
          <thead>
            <tr>
              <th>@lang('')</th>
              <th>@lang('Tally Name')</th>
              <th>@lang('Assigned To')</th>
              <th>@lang('Status')</th>
              <th>@lang('Note')</th>
              <th class="text-center">@lang('Actions')</th>
            </tr>
          </thead>
        </table>
      </div>
    </div>
  </div>
</div>

{{-- Edit modal --}}
<div class="modal fade" id="modalEditSetuMapping" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalSetuMappingLabel">Edit Mapping</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="setuMappingForm">
        <div class="modal-body">
          <input type="hidden" id="mapping_id" name="id">
          <div class="mb-4">
            <label class="form-label fw-semibold">Tally Ledger Name</label>
            <input type="text" class="form-control" id="mapping_tally_name" disabled>
          </div>
          <div class="mb-4">
            <label class="form-label fw-semibold">Assign to</label>
            <select class="form-select select2-users" id="mapping_user_id" name="user_id">
              <option value="">— none —</option>
              @foreach($users as $u)
                <option value="{{ $u->id }}">{{ trim($u->first_name . ' ' . $u->last_name) }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-4">
            <label class="form-label fw-semibold">Status</label>
            <select class="form-select" id="mapping_status" name="status">
              <option value="pending">Pending Review</option>
              <option value="confirmed">Confirmed</option>
              <option value="excluded">Excluded (not a person)</option>
            </select>
          </div>
          <div class="mb-1">
            <label class="form-label fw-semibold">Note</label>
            <textarea class="form-control" id="mapping_note" name="note" rows="2" placeholder="Why excluded, or any context for this decision"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-hitech-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
