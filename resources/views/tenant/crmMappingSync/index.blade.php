@php
    $configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', __('CRM Mapping Sync'))

@section('vendor-style')
    @vite([
      'resources/assets/vendor/libs/animate-css/animate.scss',
      'resources/assets/vendor/libs/select2/select2.scss',
      'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
      'resources/assets/vendor/scss/pages/hitech-portal.scss'
    ])
    <style>
      .btn, .form-control, .form-select, .select2-container--bootstrap-5 .select2-selection { border-radius: 10px !important; }
      #crmSyncTable td { vertical-align: middle; }
      #crmReviewTable td { vertical-align: middle; }
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
      'resources/assets/js/app/crm-mapping-review-common.js',
      'resources/assets/js/app/crm-mapping-sync.js'
    ])
@endsection

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6 mx-4 py-2">
    <div>
      <h3 class="fw-extrabold mb-0 text-dark">CRM Mapping Sync</h3>
      <p class="text-muted mb-0 small">
        Links each Sales Report row (<code>party_name</code>) to its real CRM company record. This only ever sets the link
        (<code>crm_client_mapping_id</code>) — it never edits a Sales Report row's own name or figures. The same sync you'd
        find under a single salesperson's "Link customers to CRM" menu on the Sales Report page — this page just shows
        every salesperson at once instead of one at a time.
      </p>
    </div>
  </div>

  <div class="px-4">
    <div class="alert alert-info py-2 px-3 small mb-6">
      <i class="bx bx-info-circle me-1"></i>
      A row with no cached CRM companies to compare against won't match anything here, however close its name is —
      that's a caching gap (nobody has searched the CRM for that salesperson's customers yet inside HRX), not a
      failed match. Sync opens a preview of what would change — nothing is written until you click Apply Changes
      there. An exact name match (≥99.9% similarity) links automatically on Apply; anything close-but-not-exact
      opens a manual review — you pick the right company or skip it, nothing links itself on a guess.
    </div>
  </div>

  <div class="px-4">
    <div class="hitech-card-white mb-6 overflow-hidden">
      <div class="card-body p-sm-5 p-4">
        <div class="search-wrapper-hitech w-px-300 mw-100">
          <i class="bx bx-search text-muted ms-3 fs-5"></i>
          <input type="text" class="form-control" placeholder="Search salesperson..." id="crmSyncSearchInput">
        </div>
      </div>
    </div>
  </div>

  <div class="px-4">
    <div class="hitech-card-white p-0 overflow-hidden">
      <div class="table-responsive">
        <table class="table m-0 shadow-none border-top" id="crmSyncTable">
          <thead>
            <tr>
              <th>Salesperson</th>
              <th class="text-end">Sales Report rows</th>
              <th class="text-end">Already linked</th>
              <th class="text-end">Unlinked</th>
              <th class="text-end">CRM companies cached</th>
              <th class="text-center">Action</th>
              <th>Result</th>
            </tr>
          </thead>
          <tbody>
            @forelse($rows as $r)
              <tr data-search="{{ mb_strtolower(trim($r->user->first_name . ' ' . $r->user->last_name)) }}">
                <td class="fw-semibold">{{ trim($r->user->first_name . ' ' . $r->user->last_name) }}</td>
                <td class="text-end">{{ $r->total_rows }}</td>
                <td class="text-end">{{ $r->linked_count }}</td>
                <td class="text-end">
                  @if($r->unlinked_count > 0)
                    <span class="badge bg-label-warning">{{ $r->unlinked_count }}</span>
                  @else
                    <span class="text-muted">0</span>
                  @endif
                </td>
                <td class="text-end">
                  @if($r->cached_candidates === 0)
                    <span class="badge bg-label-danger" title="No CRM companies cached for this person yet — a sync will find nothing until step 2's backfill runs">0</span>
                  @else
                    {{ $r->cached_candidates }}
                  @endif
                </td>
                <td class="text-center">
                  <button type="button" class="btn btn-sm btn-outline-primary btn-crm-sync-row"
                          data-user-id="{{ $r->user->id }}"
                          @if($r->unlinked_count === 0) disabled title="Nothing unlinked for this person" @endif>
                    <i class="bx bx-sync me-1"></i> Sync
                  </button>
                </td>
                <td class="crm-sync-result small text-muted">&mdash;</td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="text-center text-muted py-5">No salespeople with Sales Report rows found.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

@include('tenant.partials.crm-mapping-preview-modal')
@include('tenant.partials.crm-mapping-review-modal')
@endsection
