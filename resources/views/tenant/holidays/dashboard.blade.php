@extends('layouts/layoutMaster')

@section('title', 'Holiday Dashboard')

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

@section('page-script')
  @vite(['resources/js/main-select2.js'])
@endsection

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">

  {{-- Integrated Header --}}
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6 mx-4">
    <div>
      <h3 class="fw-extrabold mb-0 text-dark">Holiday Dashboard</h3>
      <p class="text-muted mb-0 small">Location-wise holiday calendar — employees see their own location's list plus company-wide holidays.</p>
    </div>
    <div class="d-flex gap-3 flex-wrap align-items-center">
      <nav aria-label="breadcrumb" class="me-2">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item"><a href="{{ route('tenant.dashboard') }}">Dashboard</a></li>
          <li class="breadcrumb-item active">Holidays</li>
        </ol>
      </nav>
      <button type="button" class="btn-hitech shadow-sm rounded-pill px-5 d-flex align-items-center gap-2"
              data-bs-toggle="modal" data-bs-target="#modalAddOrUpdateHoliday" id="addHolidayBtn">
        <i class="bx bx-plus fs-5"></i>
        <span class="fw-bold">Add Holiday</span>
      </button>
    </div>
  </div>

  {{-- Premium Stats Bar --}}
  <div class="row g-6 mb-6 mx-0 px-4 holiday-stat-row">
    <div class="col-xl-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay: 0.05s">
      <div class="hitech-stat-card dashboard-variant card-blue uniform-card clickable-stat" onclick="applyHolidayFilter('')">
        <div class="stat-card-header mb-2">
          <div class="stat-icon-wrap icon-blue" style="width:38px; height:38px; font-size:1.2rem;"><i class="bx bx-calendar"></i></div>
        </div>
        <div>
          <h3 class="stat-value mb-0">{{ $statCounts['total'] }}</h3>
          <span class="stat-label">Total ({{ $year }})</span>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay: 0.1s">
      <div class="hitech-stat-card dashboard-variant card-teal uniform-card clickable-stat" onclick="applyHolidayFilter('upcoming')">
        <div class="stat-card-header mb-2">
          <div class="stat-icon-wrap icon-teal" style="width:38px; height:38px; font-size:1.2rem;"><i class="bx bx-calendar-star"></i></div>
        </div>
        <div>
          <h3 class="stat-value mb-0">{{ $statCounts['upcoming'] }}</h3>
          <span class="stat-label">Upcoming</span>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay: 0.15s">
      <div class="hitech-stat-card dashboard-variant card-amber uniform-card clickable-stat" onclick="applyHolidayMonth({{ now()->month }})">
        <div class="stat-card-header mb-2">
          <div class="stat-icon-wrap icon-amber" style="width:38px; height:38px; font-size:1.2rem;"><i class="bx bx-calendar-event"></i></div>
        </div>
        <div>
          <h3 class="stat-value mb-0">{{ $statCounts['this_month'] }}</h3>
          <span class="stat-label">This Month</span>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
      <div class="hitech-stat-card dashboard-variant card-blue uniform-card clickable-stat" onclick="applyHolidayFilter('global')">
        <div class="stat-card-header mb-2">
          <div class="stat-icon-wrap icon-blue" style="width:38px; height:38px; font-size:1.2rem;"><i class="bx bx-globe"></i></div>
        </div>
        <div>
          <h3 class="stat-value mb-0">{{ $statCounts['global'] }}</h3>
          <span class="stat-label">All Locations</span>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay: 0.25s">
      <div class="hitech-stat-card dashboard-variant card-teal uniform-card clickable-stat" onclick="applyHolidayFilter('location')">
        <div class="stat-card-header mb-2">
          <div class="stat-icon-wrap icon-teal" style="width:38px; height:38px; font-size:1.2rem;"><i class="bx bx-map-pin"></i></div>
        </div>
        <div>
          <h3 class="stat-value mb-0">{{ $statCounts['location'] }}</h3>
          <span class="stat-label">Location-Specific</span>
        </div>
      </div>
    </div>
  </div>

  {{-- Filter Bar --}}
  <div class="px-4" id="filter-section">
    <div class="hitech-card-white mb-6 overflow-hidden">
      <div class="card-body p-sm-5 p-4">
        <div class="row align-items-center g-6">
          <div class="col-lg-7 d-flex flex-wrap align-items-center gap-3">
            <div class="search-wrapper-hitech w-px-400 mw-100">
              <i class="bx bx-search text-muted ms-3"></i>
              <input type="text" class="form-control" placeholder="Search holiday name / code..." id="customSearchInput">
              <button class="btn-search shadow-sm" id="customSearchBtn">
                <i class="bx bx-search fs-5"></i><span>Search</span>
              </button>
            </div>
            <button class="btn btn-white shadow-sm rounded-pill px-4 border d-flex justify-content-center align-items-center gap-2" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFilters">
              <i class="bx bx-filter-alt text-muted"></i>
              <span class="fw-semibold">Filters</span>
            </button>
            <button class="btn btn-white shadow-sm rounded-pill px-4 border d-flex justify-content-center align-items-center gap-2" type="button" id="clearFiltersBtn">
              <i class="bx bx-reset text-muted"></i>
              <span class="fw-semibold">Reset</span>
            </button>
          </div>

          <div class="col-lg-5 d-flex flex-wrap align-items-center justify-content-lg-end gap-3 mt-3 mt-lg-0">
            <div class="d-flex align-items-center gap-2">
              <span class="text-muted fw-semibold small">Year:</span>
              <select class="form-select w-px-100 rounded-pill border-light shadow-none fw-bold" id="yearFilter">
                @foreach($years as $y)
                  <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
                @endforeach
              </select>
            </div>
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

        <div class="collapse" id="advancedFilters">
          <div class="row g-6 pt-5 mt-4 border-top">
            <div class="col-md-3">
              <label class="form-label fw-bold small text-muted text-uppercase mb-2">Location</label>
              <select class="form-select select2" id="siteFilter">
                <option value="">All (every location)</option>
                <option value="global">Company-wide only</option>
                @foreach($sites as $site)
                  <option value="{{ $site->id }}">{{ $site->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold small text-muted text-uppercase mb-2">Department</label>
              <select class="form-select select2" id="departmentFilter">
                <option value="">All Departments</option>
                <option value="none">Not department-specific</option>
                @foreach(($departments ?? []) as $dept)
                  <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold small text-muted text-uppercase mb-2">Month</label>
              <select class="form-select select2" id="monthFilter">
                <option value="">All Months</option>
                @foreach(range(1, 12) as $m)
                  <option value="{{ $m }}">{{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold small text-muted text-uppercase mb-2">Timing</label>
              <select class="form-select select2" id="scopeFilter">
                <option value="">All</option>
                <option value="upcoming">Upcoming</option>
                <option value="past">Past</option>
                <option value="global">Company-wide</option>
                <option value="location">Location-specific</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold small text-muted text-uppercase mb-2">Status</label>
              <select class="form-select select2" id="statusFilter">
                <option value="">All</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Holiday Table --}}
  <div class="px-4">
    <div class="hitech-card-white overflow-hidden">
      <div class="card-datatable table-responsive">
        <table id="holidayTable" class="table table-hover border-top mb-0">
          <thead>
            <tr>
              <th>Holiday</th>
              <th>Date</th>
              <th>Day</th>
              <th>Time Off</th>
              <th>Location</th>
              <th>Notes</th>
              <th>Status</th>
              <th class="text-center">Action</th>
            </tr>
          </thead>
        </table>
      </div>
    </div>
  </div>
</div>

@include('tenant.holidays._add_modal', ['sites' => $sites])

<style>
  @media (min-width: 1200px) {
    .holiday-stat-row > div { flex: 0 0 20%; max-width: 20%; }
  }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var table = $('#holidayTable').DataTable({
    processing: true,
    serverSide: true,
    dom: 'rtip',
    pageLength: 25,
    order: [[1, 'asc']],
    ajax: {
      url: "{{ route('holidays.dashboardAjax') }}",
      data: function (d) {
        d.yearFilter   = $('#yearFilter').val();
        d.siteFilter   = $('#siteFilter').val();
        d.monthFilter  = $('#monthFilter').val();
        d.departmentFilter = $('#departmentFilter').val();
        d.scopeFilter  = $('#scopeFilter').val();
        d.statusFilter = $('#statusFilter').val();
        d.searchTerm   = $('#customSearchInput').val();
      }
    },
    columns: [
      { data: 'name' },
      { data: 'date_formatted', name: 'date' },
      { data: 'day_name', orderable: false },
      { data: 'type_label', name: 'holiday_type' },
      { data: 'site_name', name: 'site_id' },
      { data: 'notes_text', orderable: false },
      { data: 'status_value' },
      { data: null, orderable: false, searchable: false }
    ],
    columnDefs: [
      {
        targets: 0,
        render: function (data, type, full) {
          var past = full.is_past
            ? '<span class="badge badge-muted rounded-pill ms-2" style="font-size:0.65rem;">PAST</span>'
            : '';
          return '<span class="fw-bold text-dark">' + $('<div>').text(data).html() + '</span>' + past +
                 '<br><small class="text-muted">' + $('<div>').text(full.code || '').html() + '</small>';
        }
      },
      {
        targets: 3,
        render: function (data, type, full) {
          var cls = full.is_part_day ? 'badge-brand-soft' : 'badge-brand-solid';
          var g = '';
          if (full.gender_scope && full.gender_scope !== 'all') {
            g = '<br><small class="text-muted">' + full.gender_scope + ' staff only</small>';
          }
          return '<span class="badge ' + cls + ' rounded-pill">' + $('<div>').text(data || '').html() + '</span>' + g;
        }
      },
      {
        targets: 4,
        render: function (data, type, full) {
          var dept = full.dept_name
            ? '<br><span class="badge badge-brand-soft rounded-pill mt-1"><i class="bx bx-sitemap me-1"></i>' +
              $('<div>').text(full.dept_name).html() + '</span>'
            : '';
          if (full.is_global) {
            return '<span class="badge badge-brand-solid rounded-pill"><i class="bx bx-globe me-1"></i>All Locations</span>' + dept;
          }
          return '<span class="badge badge-brand rounded-pill"><i class="bx bx-map-pin me-1"></i>' +
                 $('<div>').text(data).html() + '</span>' + dept;
        }
      },
      {
        targets: 5,
        render: function (data) {
          if (!data) return '<span class="text-muted">—</span>';
          var safe = $('<div>').text(data).html();
          return '<span class="text-muted small" title="' + safe + '">' +
                 (data.length > 40 ? safe.substring(0, 40) + '…' : safe) + '</span>';
        }
      },
      {
        targets: 6,
        render: function (data) {
          var cls = data === 'active' ? 'success' : 'secondary';
          var label = data ? data.charAt(0).toUpperCase() + data.slice(1) : '';
          return '<span class="badge bg-label-' + cls + ' rounded-pill">' + label + '</span>';
        }
      },
      {
        targets: 7,
        render: function (data, type, full) {
          return '<div class="d-flex align-items-center justify-content-center gap-2">' +
            '<a href="javascript:;" class="text-hitech edit-record" data-id="' + full.id + '" title="Edit"><i class="bx bx-edit fs-4"></i></a>' +
            '<a href="javascript:;" class="text-danger delete-record" data-id="' + full.id + '" title="Delete"><i class="bx bx-trash fs-4"></i></a>' +
            '</div>';
        }
      }
    ]
  });

  // Filters
  $('#customSearchBtn').on('click', function () { table.ajax.reload(); });
  $('#customSearchInput').on('keypress', function (e) { if (e.which === 13) table.ajax.reload(); });
  $('#siteFilter, #monthFilter, #departmentFilter, #scopeFilter, #statusFilter, #yearFilter').on('change', function () { table.ajax.reload(); });
  $('#customLengthMenu').on('change', function () { table.page.len($(this).val()).draw(); });
  $('#clearFiltersBtn').on('click', function () {
    $('#siteFilter, #monthFilter, #departmentFilter, #scopeFilter, #statusFilter').val('').trigger('change.select2');
    $('#customSearchInput').val('');
    table.ajax.reload();
  });

  window.applyHolidayFilter = function (scope) {
    $('#monthFilter').val('').trigger('change.select2');
    $('#scopeFilter').val(scope).trigger('change.select2');
    table.ajax.reload();
    document.getElementById('filter-section').scrollIntoView({ behavior: 'smooth', block: 'center' });
  };
  window.applyHolidayMonth = function (month) {
    $('#scopeFilter').val('').trigger('change.select2');
    $('#monthFilter').val(month).trigger('change.select2');
    table.ajax.reload();
    document.getElementById('filter-section').scrollIntoView({ behavior: 'smooth', block: 'center' });
  };

  // Add / Edit
  $('#addHolidayBtn').on('click', function () {
    $('#holidayForm')[0].reset();
    $('#holidayId').val('');
    $('#holidaySiteIds').val(null).trigger('change');
    $('#holidayDepartmentIds').val(null).trigger('change');
    $('#holidayModalTitle').text('Add Holiday');
    $('input[name=holiday_type][value=full]').prop('checked', true);
    if (window.syncHolidayType) window.syncHolidayType();
  });

  $(document).on('click', '.edit-record', function () {
    var id = $(this).data('id');
    $.get("{{ url('holidays/getByIdAjax') }}/" + id, function (res) {
      var h = res.data || res;
      $('#holidayId').val(h.id);
      $('#holidayName').val(h.name);
      $('#holidayCode').val(h.code);
      $('#holidayDate').val(h.date);
      $('#holidaySiteIds').val(h.site_ids || []).trigger('change');
      $('#holidayDepartmentIds').val(h.department_ids || []).trigger('change');
      $('#holidayNotes').val(h.notes);
      $('input[name=holiday_type][value=' + (h.holiday_type || 'full') + ']').prop('checked', true);
      $('#holidayHoursOff').val(h.hours_off || '');
      $('#holidaySession').val(h.session || '');
      $('#holidayGender').val(h.applies_to_gender || 'all');
      if (window.syncHolidayType) window.syncHolidayType();
      $('#holidayModalTitle').text('Edit Holiday');
      $('#modalAddOrUpdateHoliday').modal('show');
    });
  });

  $('#holidayForm').on('submit', function (e) {
    e.preventDefault();
    var $btn = $(this).find('button[type=submit]').prop('disabled', true);
    $.ajax({
      url: "{{ route('holidays.addOrUpdateHolidayAjax') }}",
      method: 'POST',
      data: $(this).serialize(),
      success: function () {
        $('#modalAddOrUpdateHoliday').modal('hide');
        table.ajax.reload();
        if (window.Swal) Swal.fire({ icon: 'success', title: 'Saved', timer: 1400, showConfirmButton: false });
        setTimeout(function () { window.location.reload(); }, 900); // refresh stat cards
      },
      error: function (xhr) {
        var msg = 'Could not save the holiday.';
        if (xhr.responseJSON && xhr.responseJSON.errors) {
          msg = Object.values(xhr.responseJSON.errors).flat().join('\n');
        }
        if (window.Swal) Swal.fire({ icon: 'error', title: 'Error', text: msg });
        else alert(msg);
      },
      complete: function () { $btn.prop('disabled', false); }
    });
  });

  $(document).on('click', '.delete-record', function () {
    var id = $(this).data('id');
    var go = function () {
      $.ajax({
        url: "{{ url('holidays/deleteAjax') }}/" + id,
        method: 'DELETE',
        data: { _token: "{{ csrf_token() }}" },
        success: function () {
          table.ajax.reload();
          setTimeout(function () { window.location.reload(); }, 600);
        }
      });
    };
    if (window.Swal) {
      Swal.fire({
        title: 'Delete this holiday?',
        text: 'Employees at that location will no longer see it.',
        icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete',
        customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-label-secondary' },
        buttonsStyling: false
      }).then(function (r) { if (r.isConfirmed) go(); });
    } else if (confirm('Delete this holiday?')) { go(); }
  });
});
</script>
@endsection
