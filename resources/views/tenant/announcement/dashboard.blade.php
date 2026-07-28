@extends('layouts/layoutMaster')

@section('title', 'Announcements')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
    'resources/assets/vendor/libs/select2/select2.scss',
    'resources/assets/vendor/libs/animate-css/animate.scss',
  ])
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
    'resources/assets/vendor/libs/select2/select2.js',
  ])
@endsection

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">

  {{-- Header --}}
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6 mx-4">
    <div>
      <h3 class="fw-extrabold mb-0 text-dark">Announcements</h3>
      <p class="text-muted mb-0 small">
        @if($isManager)
          Post notices to your own team — they appear in their portal and inbox.
        @else
          Publish company-wide or target by department, location, role or designation.
        @endif
      </p>
    </div>
    <div class="d-flex gap-3 flex-wrap align-items-center">
      <nav aria-label="breadcrumb" class="me-2">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item"><a href="{{ route('tenant.dashboard') }}">Dashboard</a></li>
          <li class="breadcrumb-item active">Announcements</li>
        </ol>
      </nav>
      <a href="{{ route('announcements.feed') }}" class="btn btn-white shadow-sm rounded-pill px-4 border d-flex align-items-center gap-2">
        <i class="bx bx-news text-muted"></i><span class="fw-semibold">View Feed</span>
      </a>
      <button type="button" class="btn-hitech shadow-sm rounded-pill px-5 d-flex align-items-center gap-2"
              data-bs-toggle="modal" data-bs-target="#modalAnnouncement" id="newAnnouncementBtn">
        <i class="bx bx-plus fs-5"></i><span class="fw-bold">New Announcement</span>
      </button>
    </div>
  </div>

  {{-- Stats --}}
  <div class="row g-6 mb-6 mx-0 px-4 announcement-stat-row">
    @php
      $cards = [
        ['', 'card-blue', 'icon-blue', 'bx-broadcast', $statCounts['total'], 'Total'],
        ['active', 'card-teal', 'icon-teal', 'bx-check-circle', $statCounts['active'], 'Active Now'],
        ['urgent', 'card-red', 'icon-red', 'bx-error-circle', $statCounts['urgent'], 'High / Critical'],
        ['targeted', 'card-amber', 'icon-amber', 'bx-target-lock', $statCounts['targeted'], 'Targeted'],
        ['expired', 'card-blue', 'icon-blue', 'bx-time-five', $statCounts['expired'], 'Expired'],
      ];
    @endphp
    @foreach($cards as $i => [$filter, $cardCls, $iconCls, $icon, $value, $label])
      <div class="col-xl-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay: {{ 0.05 * ($i + 1) }}s">
        <div class="hitech-stat-card dashboard-variant {{ $cardCls }} uniform-card clickable-stat"
             onclick="applyAnnouncementFilter('{{ $filter }}')">
          <div class="stat-card-header mb-2">
            <div class="stat-icon-wrap {{ $iconCls }}" style="width:38px; height:38px; font-size:1.2rem;">
              <i class="bx {{ $icon }}"></i>
            </div>
          </div>
          <div>
            <h3 class="stat-value mb-0">{{ $value }}</h3>
            <span class="stat-label">{{ $label }}</span>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  {{-- Filters --}}
  <div class="px-4" id="filter-section">
    <div class="hitech-card-white mb-6 overflow-hidden">
      <div class="card-body p-sm-5 p-4">
        <div class="row align-items-center g-6">
          <div class="col-lg-7 d-flex flex-wrap align-items-center gap-3">
            <div class="search-wrapper-hitech w-px-400 mw-100">
              <i class="bx bx-search text-muted ms-3"></i>
              <input type="text" class="form-control" placeholder="Search title / content..." id="customSearchInput">
              <button class="btn-search shadow-sm" id="customSearchBtn"><i class="bx bx-search fs-5"></i><span>Search</span></button>
            </div>
            <button class="btn btn-white shadow-sm rounded-pill px-4 border d-flex align-items-center gap-2" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFilters">
              <i class="bx bx-filter-alt text-muted"></i><span class="fw-semibold">Filters</span>
            </button>
            <button class="btn btn-white shadow-sm rounded-pill px-4 border d-flex align-items-center gap-2" type="button" id="clearFiltersBtn">
              <i class="bx bx-reset text-muted"></i><span class="fw-semibold">Reset</span>
            </button>
          </div>
          <div class="col-lg-5 d-flex flex-wrap align-items-center justify-content-lg-end gap-3 mt-3 mt-lg-0">
            <div class="d-flex align-items-center gap-3">
              <span class="text-muted fw-semibold small">Per Page:</span>
              <select class="form-select w-px-80 rounded-pill border-light shadow-none fw-bold" id="customLengthMenu">
                <option value="10">10</option><option value="25" selected>25</option>
                <option value="50">50</option><option value="100">100</option>
              </select>
            </div>
          </div>
        </div>

        <div class="collapse" id="advancedFilters">
          <div class="row g-6 pt-5 mt-4 border-top">
            <div class="col-md-3">
              <label class="form-label fw-bold small text-muted text-uppercase mb-2">Audience</label>
              <select class="form-select select2" id="audienceFilter">
                <option value="">All</option>
                <option value="global">Everyone</option>
                <option value="targeted">Targeted</option>
                <option value="team">Team only</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold small text-muted text-uppercase mb-2">Type</label>
              <select class="form-select select2" id="typeFilter">
                <option value="">All</option>
                @foreach(['general','urgent','policy','holiday','event'] as $t)
                  <option value="{{ $t }}">{{ ucfirst($t) }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold small text-muted text-uppercase mb-2">Priority</label>
              <select class="form-select select2" id="priorityFilter">
                <option value="">All</option>
                @foreach(['low','medium','high','critical'] as $p)
                  <option value="{{ $p }}">{{ ucfirst($p) }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold small text-muted text-uppercase mb-2">Status</label>
              <select class="form-select select2" id="statusFilter">
                <option value="">All</option>
                <option value="active">Active</option>
                <option value="expired">Expired</option>
                <option value="inactive">Deactivated</option>
              </select>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Table --}}
  <div class="px-4">
    <div class="hitech-card-white overflow-hidden">
      <div class="card-datatable table-responsive">
        <table id="announcementTable" class="table table-hover border-top mb-0">
          <thead>
            <tr>
              <th>Announcement</th>
              <th>Audience</th>
              <th>Reach</th>
              <th>Active Window</th>
              <th>Published</th>
              <th>By</th>
              <th class="text-center">Action</th>
            </tr>
          </thead>
        </table>
      </div>
    </div>
  </div>
</div>

@include('tenant.announcement._form_modal')

<style>
  @media (min-width: 1200px) { .announcement-stat-row > div { flex: 0 0 20%; max-width: 20%; } }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var isManager = @json($isManager);

  var table = $('#announcementTable').DataTable({
    processing: true, serverSide: true, dom: 'rtip', pageLength: 25,
    order: [[4, 'desc']],
    ajax: {
      url: "{{ route('announcements.dashboardAjax') }}",
      data: function (d) {
        d.audienceFilter = $('#audienceFilter').val();
        d.typeFilter     = $('#typeFilter').val();
        d.priorityFilter = $('#priorityFilter').val();
        d.statusFilter   = $('#statusFilter').val();
        d.searchTerm     = $('#customSearchInput').val();
      }
    },
    columns: [
      { data: 'title' },
      { data: 'audience_summary', orderable: false },
      { data: 'reach', orderable: false },
      { data: 'window', orderable: false },
      { data: 'published', name: 'published_at' },
      { data: 'author', orderable: false },
      { data: null, orderable: false, searchable: false }
    ],
    columnDefs: [
      {
        targets: 0,
        render: function (data, type, full) {
          var esc = function (s) { return $('<div>').text(s == null ? '' : s).html(); };
          var prio = { critical: 'danger', high: 'warning', medium: 'info', low: 'secondary' }[full.priority] || 'secondary';
          var badges = '<span class="badge bg-label-' + prio + ' rounded-pill me-1">' + esc(full.priority) + '</span>' +
                       '<span class="badge badge-brand-soft rounded-pill">' + esc(full.type) + '</span>';
          var expired = full.is_expired ? '<span class="badge badge-muted rounded-pill ms-1">EXPIRED</span>' : '';
          var off = full.is_active ? '' : '<span class="badge bg-label-danger rounded-pill ms-1">OFF</span>';
          return '<div class="fw-bold text-dark">' + esc(data) + '</div>' +
                 '<div class="mt-1">' + badges + expired + off + '</div>';
        }
      },
      {
        targets: 1,
        render: function (data, type, full) {
          var icon = full.audience_type === 'global' ? 'bx-globe'
                   : (full.audience_type === 'team' ? 'bx-group' : 'bx-target-lock');
          var safe = $('<div>').text(data || '').html();
          return '<span class="badge badge-brand rounded-pill"><i class="bx ' + icon + ' me-1"></i>' +
                 (safe.length > 46 ? safe.substring(0, 46) + '…' : safe) + '</span>';
        }
      },
      {
        targets: 2,
        render: function (data) {
          return '<span class="fw-bold">' + (data || 0) + '</span> <small class="text-muted">people</small>';
        }
      },
      {
        targets: 6,
        render: function (data, type, full) {
          return '<div class="d-flex align-items-center justify-content-center gap-2">' +
            '<a href="javascript:;" class="text-hitech edit-record" data-id="' + full.id + '" title="Edit"><i class="bx bx-edit fs-4"></i></a>' +
            '<a href="javascript:;" class="text-warning toggle-record" data-id="' + full.id + '" title="Activate / Deactivate"><i class="bx bx-power-off fs-4"></i></a>' +
            '<a href="javascript:;" class="text-danger delete-record" data-id="' + full.id + '" title="Delete"><i class="bx bx-trash fs-4"></i></a>' +
            '</div>';
        }
      }
    ]
  });

  $('#customSearchBtn').on('click', function () { table.ajax.reload(); });
  $('#customSearchInput').on('keypress', function (e) { if (e.which === 13) table.ajax.reload(); });
  $('#audienceFilter,#typeFilter,#priorityFilter,#statusFilter').on('change', function () { table.ajax.reload(); });
  $('#customLengthMenu').on('change', function () { table.page.len($(this).val()).draw(); });
  $('#clearFiltersBtn').on('click', function () {
    $('#audienceFilter,#typeFilter,#priorityFilter,#statusFilter').val('').trigger('change.select2');
    $('#customSearchInput').val(''); table.ajax.reload();
  });
  window.applyAnnouncementFilter = function (f) {
    $('#audienceFilter,#statusFilter').val('').trigger('change.select2');
    if (f === 'active' || f === 'expired') { $('#statusFilter').val(f).trigger('change.select2'); }
    else if (f === 'targeted') { $('#audienceFilter').val('targeted').trigger('change.select2'); }
    else if (f === 'urgent') { $('#priorityFilter').val('critical').trigger('change.select2'); }
    table.ajax.reload();
    document.getElementById('filter-section').scrollIntoView({ behavior: 'smooth', block: 'center' });
  };

  // ---- audience toggle ----
  function syncAudience() {
    var v = $('input[name=audience_type]:checked').val();
    $('#targetingBlock').toggle(v === 'targeted');
  }
  $(document).on('change', 'input[name=audience_type]', syncAudience);

  $('#newAnnouncementBtn').on('click', function () {
    $('#announcementForm')[0].reset();
    $('#announcementId').val('');
    $('.target-select').val(null).trigger('change');
    $('#announcementModalTitle').text('New Announcement');
    var def = isManager ? 'team' : 'global';
    $('input[name=audience_type][value=' + def + ']').prop('checked', true);
    syncAudience();
  });

  $(document).on('click', '.edit-record', function () {
    var id = $(this).data('id');
    $.get("{{ url('announcements/getByIdAjax') }}/" + id, function (res) {
      var a = res.data || res;
      $('#announcementId').val(a.id);
      $('#annTitle').val(a.title);
      $('#annContent').val(a.content);
      $('#annType').val(a.type).trigger('change.select2');
      $('#annPriority').val(a.priority).trigger('change.select2');
      $('#annStart').val(a.start_date);
      $('#annEnd').val(a.end_date);
      $('input[name=audience_type][value=' + a.audience_type + ']').prop('checked', true);
      $('#annDepartments').val(a.target_department_ids).trigger('change');
      $('#annSites').val(a.target_site_ids).trigger('change');
      $('#annDesignations').val(a.target_designation_ids).trigger('change');
      $('#annRoles').val(a.target_role_names).trigger('change');
      $('#annNotifyEmail').prop('checked', !!a.notify_email);
      $('#annNeedsAck').prop('checked', !!a.requires_acknowledgment);
      $('#announcementModalTitle').text('Edit Announcement');
      syncAudience();
      $('#modalAnnouncement').modal('show');
    });
  });

  $('#announcementForm').on('submit', function (e) {
    e.preventDefault();
    var $btn = $(this).find('button[type=submit]').prop('disabled', true);
    $.ajax({
      url: "{{ route('announcements.store') }}",
      method: 'POST',
      data: $(this).serialize(),
      success: function (res) {
        $('#modalAnnouncement').modal('hide');
        var reach = (res.data && res.data.reach) != null ? res.data.reach : 0;
        if (window.Swal) {
          Swal.fire({ icon: 'success', title: 'Published',
            text: 'Delivered to ' + reach + ' employee(s).', timer: 2200, showConfirmButton: false });
        }
        setTimeout(function () { window.location.reload(); }, 1200);
      },
      error: function (xhr) {
        var msg = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.data)) || 'Could not save.';
        if (xhr.responseJSON && xhr.responseJSON.errors) {
          msg = Object.values(xhr.responseJSON.errors).flat().join('\n');
        }
        if (window.Swal) Swal.fire({ icon: 'error', title: 'Error', text: msg }); else alert(msg);
      },
      complete: function () { $btn.prop('disabled', false); }
    });
  });

  $(document).on('click', '.toggle-record', function () {
    $.post("{{ url('announcements/toggleStatusAjax') }}/" + $(this).data('id'),
      { _token: "{{ csrf_token() }}" }, function () { table.ajax.reload(); });
  });

  $(document).on('click', '.delete-record', function () {
    var id = $(this).data('id');
    var go = function () {
      $.ajax({
        url: "{{ url('announcements/deleteAjax') }}/" + id, method: 'DELETE',
        data: { _token: "{{ csrf_token() }}" },
        success: function () { table.ajax.reload(); setTimeout(function () { window.location.reload(); }, 600); }
      });
    };
    if (window.Swal) {
      Swal.fire({
        title: 'Delete this announcement?', text: 'It will disappear from every employee feed.',
        icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete',
        customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-label-secondary' },
        buttonsStyling: false
      }).then(function (r) { if (r.isConfirmed) go(); });
    } else if (confirm('Delete this announcement?')) { go(); }
  });

  // select2 for multi-target pickers
  if ($.fn.select2) {
    $('.target-select').select2({ dropdownParent: $('#modalAnnouncement'), width: '100%' });
  }
  syncAudience();
});
</script>
@endsection
