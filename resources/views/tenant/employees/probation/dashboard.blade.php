@extends('layouts/layoutMaster')

@section('title', 'Probation Dashboard')

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
    <h3 class="fw-extrabold mb-0 text-dark">Probation Dashboard</h3>
    <div class="d-flex gap-3 flex-wrap align-items-center">
      <nav aria-label="breadcrumb" class="me-2">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item"><a href="{{ route('tenant.dashboard') }}">Dashboard</a></li>
          <li class="breadcrumb-item active">Probation</li>
        </ol>
      </nav>
      <a href="{{ route('probation.index') }}" class="btn-hitech shadow-sm rounded-pill px-5 d-flex align-items-center gap-2">
        <i class="bx bx-list-check fs-5"></i>
        <span class="fw-bold">View Evaluation Queue</span>
      </a>
    </div>
  </div>

  {{-- Premium Stats Bar --}}
  <div class="row g-6 mb-6 mx-0 px-4 probation-stat-row">
    <div class="col-xl-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay: 0.05s">
      <div class="hitech-stat-card dashboard-variant card-teal uniform-card clickable-stat" onclick="applyProbationFilter('on_probation')">
        <div class="stat-card-header mb-2">
          <div class="stat-icon-wrap icon-teal" style="width:38px; height:38px; font-size:1.2rem;"><i class="bx bx-time-five"></i></div>
        </div>
        <div>
          <h3 class="stat-value mb-0">{{ $statCounts['on_probation'] }}</h3>
          <span class="stat-label">On Probation</span>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay: 0.1s">
      <div class="hitech-stat-card dashboard-variant card-red uniform-card clickable-stat" onclick="applyProbationFilter('overdue')">
        <div class="stat-card-header mb-2">
          <div class="stat-icon-wrap icon-red" style="width:38px; height:38px; font-size:1.2rem;"><i class="bx bx-error-circle"></i></div>
        </div>
        <div>
          <h3 class="stat-value mb-0">{{ $statCounts['overdue'] }}</h3>
          <span class="stat-label">Overdue / No Decision</span>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay: 0.15s">
      <div class="hitech-stat-card dashboard-variant card-blue uniform-card clickable-stat" onclick="applyProbationFilter('confirmed')">
        <div class="stat-card-header mb-2">
          <div class="stat-icon-wrap icon-blue" style="width:38px; height:38px; font-size:1.2rem;"><i class="bx bx-badge-check"></i></div>
        </div>
        <div>
          <h3 class="stat-value mb-0">{{ $statCounts['confirmed'] }}</h3>
          <span class="stat-label">Confirmed</span>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
      <div class="hitech-stat-card dashboard-variant card-amber uniform-card clickable-stat" onclick="applyProbationFilter('extended')">
        <div class="stat-card-header mb-2">
          <div class="stat-icon-wrap icon-amber" style="width:38px; height:38px; font-size:1.2rem;"><i class="bx bx-calendar-plus"></i></div>
        </div>
        <div>
          <h3 class="stat-value mb-0">{{ $statCounts['extended'] }}</h3>
          <span class="stat-label">Extended</span>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay: 0.25s">
      <div class="hitech-stat-card dashboard-variant card-red uniform-card clickable-stat" onclick="applyProbationFilter('terminated')">
        <div class="stat-card-header mb-2">
          <div class="stat-icon-wrap icon-red" style="width:38px; height:38px; font-size:1.2rem;"><i class="bx bx-user-x"></i></div>
        </div>
        <div>
          <h3 class="stat-value mb-0" style="color: #dc2626;">{{ $statCounts['terminated'] }}</h3>
          <span class="stat-label">Terminated</span>
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
              <input type="text" class="form-control" placeholder="Search name / code..." id="customSearchInput">
              <button class="btn-search shadow-sm" id="customSearchBtn">
                <i class="bx bx-search fs-5"></i><span>Search</span>
              </button>
            </div>
            <button class="btn btn-white shadow-sm rounded-pill px-4 border d-flex justify-content-center align-items-center gap-2" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFilters">
              <i class="bx bx-filter-alt text-muted"></i>
              <span class="fw-semibold">Filters</span>
            </button>
          </div>

          <div class="col-lg-5 d-flex flex-wrap align-items-center justify-content-lg-end gap-3 mt-3 mt-lg-0">
            <div class="d-flex align-items-center gap-3">
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
              <label class="form-label fw-bold small text-muted text-uppercase mb-2">Status</label>
              <select class="form-select select2" id="statusFilter">
                <option value="">All</option>
                <option value="on_probation">On Probation</option>
                <option value="overdue">Overdue</option>
                <option value="confirmed">Confirmed</option>
                <option value="extended">Extended</option>
                <option value="terminated">Terminated</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold small text-muted text-uppercase mb-2">Department</label>
              <select class="form-select select2" id="departmentFilter">
                <option value="">All Departments</option>
                @foreach($departments as $dept)
                  <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                @endforeach
              </select>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Roster Table --}}
  <div class="px-4">
    <div class="hitech-card-white overflow-hidden">
      <div class="card-datatable table-responsive">
        <table id="probationTable" class="table table-hover border-top mb-0">
          <thead>
            <tr>
              <th>Employee</th>
              <th>Department</th>
              <th>DOJ</th>
              <th>Period</th>
              <th>End Date</th>
              <th>Status</th>
              <th>Latest Decision</th>
              <th>Decision Details</th>
              <th class="text-center">Action</th>
            </tr>
          </thead>
        </table>
      </div>
    </div>
  </div>
</div>

<style>
    @media (min-width: 1200px) {
        .probation-stat-row > div {
            flex: 0 0 20%;
            max-width: 20%;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var reviewBase = "{{ url('probation/review') }}/";
    var evaluateBase = "{{ url('probation/evaluate') }}/";

    var decisionBadgeColors = {
        'Confirmed': 'success',
        'Extended': 'warning',
        'Terminated': 'danger',
        'Awaiting HR Decision': 'info',
        'No Decision Yet': 'secondary'
    };

    var table = $('#probationTable').DataTable({
        processing: true,
        serverSide: true,
        dom: 'rtip',
        ajax: {
            url: "{{ route('probation.dashboardAjax') }}",
            data: function (d) {
                d.statusFilter = $('#statusFilter').val();
                d.departmentFilter = $('#departmentFilter').val();
            }
        },
        columns: [
            { data: 'name' },
            { data: 'department' },
            { data: 'doj' },
            { data: 'period' },
            { data: 'end_date' },
            { data: 'status_display' },
            { data: 'decision_label' },
            { data: null, defaultContent: '' },
            { data: null, defaultContent: '', orderable: false, searchable: false }
        ],
        columnDefs: [
            {
                targets: 0,
                render: function (data, type, full) {
                    return '<div class="d-flex align-items-center">' +
                        '<div class="avatar avatar-sm me-3"><img src="' + full.profile_picture + '" alt="Avatar" class="rounded-circle"></div>' +
                        '<div><div class="fw-bold text-dark">' + full.name + '</div><small class="text-muted">' + full.code + '</small></div>' +
                        '</div>';
                }
            },
            {
                targets: 3,
                render: function (data) {
                    return data ? data + ' mo' : '—';
                }
            },
            {
                targets: 4,
                render: function (data) {
                    return data || '—';
                }
            },
            {
                targets: 5,
                render: function (data) {
                    return '<small>' + data + '</small>';
                }
            },
            {
                targets: 6,
                render: function (data) {
                    var color = decisionBadgeColors[data] || 'secondary';
                    return '<span class="badge bg-label-' + color + ' rounded-pill px-3">' + data + '</span>';
                }
            },
            {
                targets: 7,
                render: function (data, type, full) {
                    var html = '<div class="small">' + (full.decided_by || '—') + '</div>';
                    html += '<div class="small text-muted">' + (full.decided_at || '—') + '</div>';
                    return html;
                }
            },
            {
                targets: 8,
                className: 'text-center',
                render: function (data, type, full) {
                    if (full.decision_label === 'Awaiting HR Decision') {
                        return '<a href="' + reviewBase + full.evaluation_id + '" class="btn btn-sm btn-hitech rounded-pill px-3"><i class="bx bx-show me-1"></i>Review</a>';
                    }
                    if (full.decision_label === 'No Decision Yet') {
                        return '<a href="' + evaluateBase + full.id + '" class="btn btn-sm btn-outline-primary rounded-pill px-3"><i class="bx bx-edit me-1"></i>Evaluate</a>';
                    }
                    return '<span class="text-muted">—</span>';
                }
            }
        ],
        order: [[4, 'asc']],
        pageLength: 25,
        responsive: false,
        language: {
            sLengthMenu: '_MENU_',
            paginate: { next: '<i class="bx bx-chevron-right bx-18px"></i>', previous: '<i class="bx bx-chevron-left bx-18px"></i>' }
        }
    });

    window.applyProbationFilter = function (status) {
        $('#statusFilter').val(status).trigger('change');
        document.querySelector('#probationTable').scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    $('#statusFilter, #departmentFilter').on('change', function () {
        table.ajax.reload();
    });

    $('#customSearchBtn').on('click', function () { table.search($('#customSearchInput').val()).draw(); });
    $('#customSearchInput').on('keyup', function (e) { if (e.key === 'Enter') table.search($(this).val()).draw(); });
    $('#customLengthMenu').on('change', function () { table.page.len($(this).val()).draw(); });
});
</script>
@endsection
