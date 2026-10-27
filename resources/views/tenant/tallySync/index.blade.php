@php
    $configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', __('Tally Sales Sync'))

@section('vendor-style')
    @vite([
      'resources/assets/vendor/libs/animate-css/animate.scss',
      'resources/assets/vendor/libs/select2/select2.scss',
      'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
      'resources/assets/vendor/scss/pages/hitech-portal.scss'
    ])
    <style>
      .btn, .form-control, .form-select, .select2-container--bootstrap-5 .select2-selection { border-radius: 10px !important; }
      #tallyReviewTable td { vertical-align: middle; }
    </style>
@endsection

@section('vendor-script')
    @vite([
      'resources/assets/vendor/libs/select2/select2.js',
      'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
    ])
@endsection

@section('page-script')
    @vite([
      'resources/assets/js/app/tally-period-picker.js',
      'resources/assets/js/app/tally-review-common.js',
      'resources/assets/js/app/tally-sync-index.js'
    ])
@endsection

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">
  {{-- Standardized Header --}}
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6 mx-4 py-2">
    <div>
      <h3 class="fw-extrabold mb-0 text-dark">Tally Sales Sync</h3>
      <p class="text-muted mb-0 small">Trigger a Tally reconciliation for any confirmed salesperson directly, for any fiscal year, quarter, or custom month range — customer-wise, month-wise, pulled straight from the already-synced local Tally invoices (no external API). To correct WHICH Tally salesperson tag(s) belong to someone, use <a href="{{ route('sales-group-mappings.index') }}">Sales Group Mapping</a> instead — this page only ever syncs, never changes a mapping.</p>
    </div>
  </div>

  {{-- Period picker + search --}}
  <div class="px-4">
    <div class="hitech-card-white mb-6 overflow-hidden">
      <div class="card-body p-sm-5 p-4">
        <div class="d-flex flex-wrap align-items-center gap-3">
          @include('tenant.partials.tally-period-picker')

          <div class="flex-grow-1"></div>

          <div class="search-wrapper-hitech w-px-300 mw-100">
            <i class="bx bx-search text-muted ms-3 fs-5"></i>
            <input type="text" class="form-control" placeholder="Search salesperson or Tally tag..." id="tallySyncSearchInput">
          </div>
        </div>
        <div class="alert alert-info py-2 px-3 small mt-3 mb-0">
          <i class="bx bx-info-circle me-1"></i>
          The period above applies to every row's Sync button — change it once, then sync whichever salespeople you need. Tally is the source of truth: an exact-name match has its figures corrected and its name standardised to Tally's spelling (you'll see a preview and confirm first). Similar-but-not-exact names are never changed automatically — they open a review where you choose, per customer, whether to update an existing row or create a new one.
        </div>
      </div>
    </div>
  </div>

  {{-- Confirmed mappings table --}}
  <div class="px-4">
    <div class="hitech-card-white p-0 overflow-hidden">
      <div class="table-responsive">
        <table class="table m-0 shadow-none border-top" id="tallySyncTable">
          <thead>
            <tr>
              <th>Salesperson</th>
              <th>Tally Salesperson Tag(s)</th>
              <th class="text-center">Action</th>
              <th>Result</th>
            </tr>
          </thead>
          <tbody>
            @forelse($mappings as $m)
              <tr data-search="{{ mb_strtolower(trim($m->user->first_name . ' ' . $m->user->last_name) . ' ' . implode(' ', $m->ledger_groups)) }}">
                <td class="fw-semibold">{{ trim($m->user->first_name . ' ' . $m->user->last_name) }}</td>
                <td>
                  @foreach($m->ledger_groups as $lg)
                    <span class="badge bg-label-secondary">{{ $lg }}</span>
                  @endforeach
                </td>
                <td class="text-center">
                  <div class="d-flex justify-content-center gap-1">
                    @if(strcasecmp((string) auth()->user()?->email, 'ai@rustx.com') === 0)
                      <button type="button" class="btn btn-sm btn-outline-secondary btn-recheck-row" data-user-id="{{ $m->user_id }}" title="Quick totals straight from Tally -- no Sales Report book comparison, no Tally gateway call">
                        <i class="bx bx-calculator me-1"></i> Recheck
                      </button>
                    @endif
                    <button type="button" class="btn btn-sm btn-outline-primary btn-sync-row" data-user-id="{{ $m->user_id }}">
                      <i class="bx bx-sync me-1"></i> Sync
                    </button>
                  </div>
                </td>
                <td class="sync-result small text-muted">&mdash;</td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="text-center text-muted py-5">No confirmed sales group mappings yet — confirm some on the Sales Group Mapping page first.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

@include('tenant.partials.tally-preview-modal')
@include('tenant.partials.tally-review-modal')
@endsection
