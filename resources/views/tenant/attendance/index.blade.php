@extends('layouts.layoutMaster')

@section('title', __('Attendance Management'))

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
    'resources/assets/vendor/libs/select2/select2.scss',
    'resources/assets/vendor/libs/apex-charts/apex-charts.scss',
    'resources/assets/vendor/libs/toastr/toastr.scss',
    'resources/assets/vendor/libs/animate-css/animate.scss'
  ])
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
    'resources/assets/vendor/libs/moment/moment.js',
    'resources/assets/vendor/libs/select2/select2.js',
    'resources/assets/vendor/libs/apex-charts/apexcharts.js',
    'resources/assets/vendor/libs/toastr/toastr.js'
  ])
@endsection

@section('page-style')
<style>
  /* --- compact stat strip (replaces the old hero banner + 4 tall cards) --- */
  .attendance-statbar .stat-pill {
      display: inline-flex; align-items: center; gap: 7px;
      background: #fff; border: 1px solid #e7eaee; border-radius: 50px;
      padding: 5px 14px; font-size: .78rem; white-space: nowrap;
      box-shadow: 0 1px 2px rgba(16,24,40,.04);
  }
  .attendance-statbar .sp-dot { width: 9px; height: 9px; border-radius: 50%; flex: 0 0 auto; }
  .attendance-statbar .sp-val { font-weight: 800; font-size: .95rem; color: #2f3944; line-height: 1; }
  .attendance-statbar .sp-lbl { color: #7b8794; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; font-size: .62rem; }
  .attendance-statbar .stat-pill-muted { background: #f6f8f9; }

  /* --- Select2: the default highlighted option was near-white text on a pale
         grey row, which made the hovered department unreadable. Force the brand
         teal row with white text instead. --- */
  .select2-container--default .select2-results__option--highlighted[aria-selected],
  .select2-container--default .select2-results__option--highlighted,
  .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable {
      background-color: #0f6e6e !important;
      color: #fff !important;
  }
  .select2-container--default .select2-results__option[aria-selected="true"],
  .select2-container--default .select2-results__option--selected {
      background-color: #e8f2f2 !important;
      color: #0f6e6e !important;
      font-weight: 700;
  }
  .select2-container--default .select2-results__option {
      padding: 7px 12px; font-size: .82rem; color: #4b5563;
  }
  .select2-results__options { max-height: 260px; }

  /* --- one-line compact toolbar ---------------------------------------
     The filters used to wrap onto a second row with tall 400px-wide controls,
     eating ~120px of vertical space above the grid. Everything now sits on a
     single line at a smaller control height, and scrolls sideways only if the
     window is genuinely too narrow. */
  .attendance-filterbar { overflow-x: auto; overflow-y: visible; padding-bottom: 2px; }
  .attendance-filterbar::-webkit-scrollbar { height: 5px; }
  .attendance-filterbar::-webkit-scrollbar-thumb { background: #d6dce1; border-radius: 4px; }

  .attendance-filterbar .form-select,
  .attendance-filterbar .form-control,
  .attendance-filterbar .filter-item-hitech {
      height: 34px; min-height: 34px; padding: 4px 26px 4px 10px;
      font-size: .76rem; line-height: 1.2;
  }
  .attendance-filterbar .search-wrapper-hitech { height: 34px; }
  .attendance-filterbar .search-wrapper-hitech .form-control { height: 32px; padding-left: 4px; font-size: .76rem; }
  .attendance-filterbar .search-wrapper-hitech .btn-search { height: 30px; padding: 0 12px; font-size: .74rem; }
  .attendance-filterbar .search-wrapper-hitech .btn-search span { font-size: .74rem; }
  .attendance-filterbar .btn { height: 34px; padding: 0 12px; font-size: .76rem; display: inline-flex; align-items: center; }

  /* Select2 renders its own box — match the shrunken height */
  .attendance-filterbar .select2-container .select2-selection--single { height: 34px !important; }
  .attendance-filterbar .select2-container--default .select2-selection--single .select2-selection__rendered {
      line-height: 32px !important; font-size: .76rem; padding-left: 10px; padding-right: 22px;
  }
  .attendance-filterbar .select2-container--default .select2-selection--single .select2-selection__arrow { height: 32px !important; }

  /* The global .compact-select sets min-width:140px, which beat the per-control
     inline widths (a min-width always wins over a smaller width). Release it so
     each filter can take the narrow size set on its wrapper. */
  .attendance-filterbar .compact-select { flex: 0 0 auto; min-width: 0 !important; }
  .attendance-filterbar .form-select,
  .attendance-filterbar .form-control,
  .attendance-filterbar .filter-item-hitech,
  .attendance-filterbar .btn { height: 34px !important; min-height: 34px !important; }
  .attendance-filterbar .btn { padding: 0 10px !important; }
  .attendance-filterbar > .d-flex.align-items-center { flex: 0 0 auto; }
  /* keep the action buttons pinned right without stretching the row */
  .attendance-filterbar .btn-hitech-export span { font-size: .74rem; }
  /* catch every remaining control (length menu, icon + import buttons) so the
     whole row shares one height instead of mixing 34px and 42px */
  .attendance-filterbar select,
  .attendance-filterbar input,
  .attendance-filterbar button,
  .attendance-filterbar .select2-selection { height: 34px !important; min-height: 34px !important; }
  .attendance-filterbar > * { align-self: center; }
  .attendance-filterbar .search-wrapper-hitech .btn-search { height: 28px !important; }
  /* btn-hitech-icon / btn-hitech-export set their own padding with !important in
     the app stylesheet, which outranked the generic .btn rule above and left the
     two action buttons 8px taller than every other control on the row. */
  .attendance-filterbar button.btn.btn-hitech-icon,
  .attendance-filterbar button.btn.btn-hitech-export,
  .attendance-filterbar button.btn-hitech-icon,
  .attendance-filterbar button.btn-hitech-export {
      height: 34px !important; min-height: 34px !important;
      padding-top: 0 !important; padding-bottom: 0 !important;
      padding-left: 10px !important; padding-right: 10px !important;
      display: inline-flex !important; align-items: center !important;
  }
  .attendance-filterbar button.btn-hitech-export i { font-size: 1rem !important; margin-right: 5px !important; }
  .attendance-filterbar button.btn-hitech-icon i { font-size: 1rem !important; }

  /* --- breathing room -------------------------------------------------
     First pass squeezed everything against the card edges and clipped the
     longer labels ("Sort: Name (A-Z)") under the dropdown arrow. */
  .attendance-filterbar .select2-container--default .select2-selection--single .select2-selection__rendered {
      padding-right: 30px !important; padding-left: 12px !important;
  }
  .attendance-filterbar .select2-container--default .select2-selection--single { border-color: #dfe3e7 !important; border-radius: 7px !important; }
  .attendance-filterbar .form-select, .attendance-filterbar .form-control { padding-right: 30px !important; padding-left: 12px !important; }
  .attendance-filterbar .search-wrapper-hitech { padding-left: 6px; }
  .attendance-filterbar .search-wrapper-hitech .form-control { padding-left: 8px !important; }
  /* the row itself gets side padding so nothing touches the card border */
  .attendance-filterbar { padding: 2px 2px 6px 2px; }

  /* legend sat flush against the card edges too */
  #attendanceLegend { padding: .65rem 1.25rem !important; }
  .attendance-legend .legend-chip { padding: 4px 12px; }

  .attendance-statbar { padding: 0 2px; }

  /* --- tab row: tabs left, actions right ------------------------------
     nav-justified stretched two tabs across the full card width, leaving a
     huge dead zone; the actions now use that space instead of crowding the
     filter row (which is why the filters needed a scrollbar). */
  .attendance-tabbar { padding-left: .5rem; }
  .attendance-tabbar .nav-tabs { border-bottom: 0; flex: 0 0 auto; }
  /* Tabs sized to match the Bulk Import button (34px) so the whole top row
     shares one control height. */
  .attendance-tabbar .nav-tabs .nav-link {
      height: 34px !important; min-height: 34px !important;
      padding: 0 16px !important; white-space: nowrap;
      display: inline-flex !important; align-items: center !important;
      font-size: .78rem; line-height: 1 !important; border-radius: 7px;
  }
  .attendance-tabbar .nav-tabs .nav-item { display: flex; align-items: center; }
  .attendance-tabbar { padding: .6rem .5rem .6rem 1rem; }

  /* The search button is absolutely positioned inside its wrapper; once the
     wrapper was narrowed it spilled over the neighbouring date filter. Clip it
     to the wrapper and drop the label so the input keeps usable width. */
  .attendance-filterbar .search-wrapper-hitech {
      overflow: hidden; border-radius: 7px; position: relative;
  }
  .attendance-filterbar .search-wrapper-hitech .btn-search {
      position: absolute !important; right: 0; top: 50%; transform: translateY(-50%);
      height: 30px !important; padding: 0 10px !important; border-radius: 6px; z-index: 2;
  }
  .attendance-filterbar .search-wrapper-hitech .btn-search span { display: none; }
  .attendance-filterbar .search-wrapper-hitech .form-control { padding-right: 42px !important; }
  .attendance-tabactions { flex: 0 0 auto; }
  .attendance-tabactions .form-select,
  .attendance-tabactions .btn {
      height: 34px !important; min-height: 34px !important;
      font-size: .76rem; display: inline-flex; align-items: center;
  }
  .attendance-tabbar .attendance-tabactions button.btn.btn-hitech-icon,
  .attendance-tabbar .attendance-tabactions button.btn.btn-hitech-export,
  .attendance-tabactions button.btn-hitech-icon,
  .attendance-tabactions button.btn-hitech-export {
      height: 34px !important; min-height: 34px !important;
      padding: 0 12px !important; line-height: 1 !important;
      display: inline-flex !important; align-items: center !important;
  }
  .attendance-tabactions button.btn-hitech-export i,
  .attendance-tabactions button.btn-hitech-icon i { font-size: 1rem !important; }
  .attendance-tabactions button.btn-hitech-export i { margin-right: 6px !important; }
  .attendance-tabactions .form-select { padding: 0 26px 0 10px !important; }

  @media (max-width: 900px) {
      .attendance-tabbar { padding-bottom: .5rem; }
      .attendance-tabactions { padding-left: .75rem; }
  }

  /* Unlike the filter row (which already scrolls on its own, see
     .attendance-filterbar below) and the two tables (already wrapped in
     .table-responsive / overflow:auto), this tab+action bar never got the
     same horizontal-scroll-box treatment: its tabs and action buttons are
     all flex:0 0 auto / nowrap, so at phone width their combined min-content
     width leaked into the page's own scrollWidth instead of staying
     contained. Same established pattern applied here too. */
  @media (max-width: 480px) {
      .attendance-tabbar {
          flex-wrap: nowrap !important;
          overflow-x: auto;
          -webkit-overflow-scrolling: touch;
      }
      .attendance-tabbar .nav-tabs,
      .attendance-tabactions {
          flex-shrink: 0;
      }
  }
</style>
@endsection

@section('content')
<div class="px-4">
@if(session('import_failures'))
  <div class="alert alert-warning alert-dismissible mt-3" role="alert">
    <h6 class="alert-heading mb-2"><i class="bx bx-error-circle me-1"></i> {{ count(session('import_failures')) }} attendance row(s) failed to import</h6>
    <p class="mb-2 small">These rows were <strong>not</strong> saved. Re-upload the same file to retry only the missing ones (already-imported rows are updated harmlessly).</p>
    <div class="small" style="max-height: 180px; overflow-y: auto;">
      @foreach(session('import_failures') as $f)
        <div class="border-bottom py-1"><code>{{ $f }}</code></div>
      @endforeach
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif
{{-- Compact stat strip. The old layout opened with a full-width hero banner
     plus four tall cards, which pushed the actual registry grid below the fold
     on a 1366px screen. The page title is already in the navbar, so the banner
     was pure duplication; the four numbers now sit in one slim row. --}}
<div class="attendance-statbar d-flex flex-wrap align-items-center gap-2 mb-3">
  <div class="stat-pill" title="Employees present today">
    <span class="sp-dot bg-teal"></span>
    <span class="sp-val" id="statPresentCount">{{ $todayPresentCount ?? 0 }}</span>
    <span class="sp-lbl">Present</span>
  </div>
  <div class="stat-pill" title="Employees absent today">
    <span class="sp-dot bg-red"></span>
    <span class="sp-val" id="statAbsentCount">{{ $todayAbsentCount ?? 0 }}</span>
    <span class="sp-lbl">Absent</span>
  </div>
  <div class="stat-pill" title="Employees on approved leave today">
    <span class="sp-dot bg-indigo-vibrant"></span>
    <span class="sp-val" id="statLeaveCount">{{ $onLeaveCount ?? 0 }}</span>
    <span class="sp-lbl">On Leave</span>
  </div>
  <div class="stat-pill" title="Late arrivals today">
    <span class="sp-dot bg-warning"></span>
    <span class="sp-val" id="statLateCount">{{ $lateCount ?? 0 }}</span>
    <span class="sp-lbl">Late</span>
  </div>
  <div class="stat-pill stat-pill-muted ms-auto" title="Total active employees">
    <span class="sp-val">{{ $activeUsersCount ?? 0 }}</span>
    <span class="sp-lbl">Active staff</span>
  </div>
</div>

<!-- Attendance Records Table -->
<div class="row g-6 mb-6">
  <div class="col-12">
    <div class="hitech-card">
      {{-- Tabs on the left, page actions on the right. The actions used to live in
           the filter row, which pushed it past the available width and forced a
           horizontal scrollbar; the tab strip had ~600px of unused space. --}}
      <div class="hitech-card-header border-bottom p-0 d-flex align-items-center justify-content-between flex-wrap gap-2 attendance-tabbar">
          <ul class="nav nav-tabs hitech-tabs-sm mb-0 flex-nowrap" role="tablist">
              <li class="nav-item">
                  <button class="nav-link py-3 active" id="registry-view-tab" data-bs-toggle="tab" data-bs-target="#registryViewTab">
                      <i class="bx bx-grid-alt me-2 text-primary"></i>Monthly Registry
                  </button>
              </li>
              <li class="nav-item">
                  <button class="nav-link py-3" id="log-view-tab" data-bs-toggle="tab" data-bs-target="#listViewTab">
                      <i class="bx bx-list-ul me-2"></i>Real-time Logs
                  </button>
              </li>
          </ul>

          <div class="d-flex align-items-center gap-2 attendance-tabactions pe-3">
            {{-- Records Per Page --}}
            <select class="form-select filter-item-hitech border-light shadow-none fw-bold" id="customLengthMenu" style="width: 74px;">
              <option value="10">10</option>
              <option value="25">25</option>
              <option value="50">50</option>
              <option value="100">100</option>
            </select>

            {{-- Export --}}
            <button type="button" class="btn btn-hitech-icon shadow-sm" onclick="exportData()" title="Export Data">
              <i class="bx bx-download fs-5"></i>
            </button>

            @if(auth()->user()->hasRole(['admin', 'hr', 'accounts']))
            <button type="button" class="btn btn-hitech-export shadow-sm" data-bs-toggle="modal" data-bs-target="#importAttendanceModal">
              <i class="bx bx-upload fs-5 me-2"></i>
              <span>Bulk Import</span>
            </button>
            @endif
          </div>
      </div>

      {{-- Unified White Filter Row --}}
      <div class="card-body px-4 py-3 border-bottom">
        <div class="d-flex flex-nowrap align-items-center gap-2 attendance-filterbar">
          {{-- Search --}}
          <div class="search-wrapper-hitech" style="width: 172px; flex:0 0 172px;">
            <i class="bx bx-search text-muted ms-3 fs-5"></i>
            <input type="text" class="form-control" placeholder="Search employee..." id="customSearchInput">
            <button class="btn-search shadow-sm" id="customSearchBtn">
              <i class="bx bx-search fs-5"></i>
              <span>Search</span>
            </button>
          </div>

          {{-- Quick Period Filter --}}
          <div class="compact-select" style="width: 110px;">
              <select id="quickPeriod" class="form-select filter-item-hitech">
                  <option value="today" selected>Today</option>
                  <option value="yesterday">Yesterday</option>
                  <option value="7days">Last 7 Days</option>
                  <option value="last_month">Last Month</option>
                  <option value="custom">Custom Date</option>
              </select>
          </div>

          {{-- Date Filter (Shown for Custom) --}}
          <div id="dateFilterWrapper" style="width: 132px; display: none;">
            <input type="date" id="date" name="date" class="form-control filter-item-hitech"
                   value="{{ request()->get('date', now()->format('Y-m-d')) }}">
          </div>

          {{-- Month/Year Filter (for Registry) --}}
          <div class="compact-select" id="registryMonthWrapper" style="width: 132px; display: none;">
              <input type="month" id="registryMonth" class="form-control filter-item-hitech" 
                     value="{{ now()->format('Y-m') }}">
          </div>

          {{-- Site/Unit --}}
          <div class="compact-select" style="width: 114px;">
              <select id="siteId" name="siteId" class="form-select select2 filter-item-hitech">
                <option value="">Unit: All</option>
                @foreach($sites as $site)
                  <option value="{{ $site->id }}">{{ $site->name }}</option>
                @endforeach
              </select>
          </div>

          {{-- Department (real departments — the old list was the unused `teams` table) --}}
          <div class="compact-select" style="width: 136px;">
              <select id="departmentId" name="departmentId" class="form-select select2 filter-item-hitech">
                <option value="">Dept: All</option>
                @foreach($departments as $department)
                  <option value="{{ $department->id }}">{{ $department->name }}</option>
                @endforeach
              </select>
          </div>

          {{-- Employee --}}
          <div class="compact-select" style="width: 136px;">
            <select id="userId" name="userId" class="form-select select2 filter-item-hitech">
              <option value="">Emp: All</option>
              @foreach($users as $user)
                @php /** @var \App\Models\User $user */ @endphp
                <option value="{{ $user->id }}" {{ request()->get('user') == $user->id ? 'selected' : '' }}>
                  {{ $user?->getFullName() }}
                </option>
              @endforeach
            </select>
          </div>

          {{-- Shift --}}
          <div class="compact-select" style="width: 112px;">
            <select id="shiftId" name="shiftId" class="form-select select2 filter-item-hitech">
              <option value="">Shift: All</option>
              @foreach($shifts as $shift)
                <option value="{{ $shift->id }}" {{ request()->get('shift') == $shift->id ? 'selected' : '' }}>
                  {{ $shift->name }}
                </option>
              @endforeach
            </select>
          </div>

          {{-- Sort (Monthly Registry) --}}
          <div class="compact-select" id="registrySortWrap" style="width: 150px;">
            <select id="registrySort" class="form-select select2 filter-item-hitech">
              <option value="name_asc">Sort: Name (A-Z)</option>
              <option value="name_desc">Sort: Name (Z-A)</option>
              <option value="code_asc">Sort: Emp Code</option>
              <option value="absents_desc">Sort: Most Absent</option>
              <option value="presents_desc">Sort: Most Present</option>
              <option value="lates_desc">Sort: Most Late</option>
              <option value="leaves_desc">Sort: Most Leave</option>
            </select>
          </div>

        </div>
      </div>

      @include('_partials.attendance_legend')

      <div class="tab-content border-0 p-0" id="attendanceTabs">
        {{-- Monthly Registry --}}
        <div class="tab-pane fade show active" id="registryViewTab" role="tabpanel">
            <div class="table-responsive p-0" id="registryTableContainer" style="max-height: 70vh; min-height: 400px; overflow: auto;">
                <div class="text-center p-5">
                    <div class="spinner-border text-teal" role="status"></div>
                    <p class="mt-2 text-muted">Building your monthly registry...</p>
                </div>
            </div>
        </div>

        {{-- List View --}}
        <div class="tab-pane fade" id="listViewTab" role="tabpanel">
            <div class="card-datatable table-responsive p-0">
              <table id="attendanceTable" class="table table-hover border-top mb-0">
                <thead>
                  <tr>
                    <th>Employee</th>
                    <th>Date</th>
                    <th>Shift</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Working Hours</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Full Width Interactive Chart -->
<div class="row g-6">
  <div class="col-12">
    <div class="hitech-card" style="animation-delay: 0.2s">
      <div class="hitech-card-header border-bottom">
        <div class="d-flex align-items-center gap-3">
            <h5 class="title mb-0">Weekly Attendance Analytics</h5>
            <div class="d-flex align-items-center gap-2 ms-4">
                <span class="badge badge-hitech-success rounded-pill px-3">Present</span>
                <span class="badge badge-hitech-danger rounded-pill px-3">Absent</span>
            </div>
        </div>
      </div>
      <div class="card-body p-4 border-bottom">
        <div class="d-flex flex-wrap align-items-center gap-3">
          <div class="compact-select" style="min-width: 140px;">
              <select id="chartPeriod" class="form-select select2 filter-item-hitech">
                  <option value="today">Today</option>
                  <option value="yesterday">Yesterday</option>
                  <option value="7days" selected>7 Days</option>
                  <option value="1month">30 Days</option>
                  <option value="3months">3 Months</option>
                  <option value="1year">1 Year</option>
              </select>
          </div>
          <div class="compact-select" style="min-width: 160px;">
              <select id="chartTeamFilter" class="form-select select2 filter-item-hitech">
                  <option value="">Dept: All</option>
                  @foreach($departments as $department)
                      <option value="{{ $department->id }}">{{ $department->name }}</option>
                  @endforeach
              </select>
          </div>
          <div class="compact-select" style="min-width: 200px;">
              <select id="chartUserFilter" class="form-select select2 filter-item-hitech">
                  <option value="">Emp: All</option>
                  @foreach($users as $user)
                      <option value="{{ $user->id }}">{{ $user?->getFullName() }}</option>
                  @endforeach
              </select>
          </div>
          <div class="flex-grow-1"></div>
          <button type="button" class="btn btn-hitech-icon shadow-sm" onclick="refreshChart()" title="Refresh Analytics">
            <i class="bx bx-refresh fs-5"></i>
          </button>
        </div>
      </div>
      <div class="card-body">
        <div id="weeklyAttendanceChart" style="min-height: 400px;"></div>
      </div>
    </div>
  </div>
</div>

{{-- MODAL SECTION (Moved Inside Content) --}}

{{-- Premium Day Details Modal --}}
<div class="modal fade" id="dayDetailsModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
            <div class="modal-icon-header me-3"><i class="bx bx-time-five fs-3"></i></div>
            <div>
                <h5 class="modal-title-hitech mb-0" id="detailDate">Date</h5>
                <small class="text-white opacity-75 tracking-wider fw-bold text-uppercase" style="font-size: 0.65rem;" id="detailName">Employee Name</small>
            </div>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal"><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body modal-body-hitech">
        <div class="text-center mb-5" id="detailStatus">
            <span class="badge bg-label-teal px-4 py-2 rounded-pill fw-bold text-uppercase ls-1">Present</span>
        </div>
        <div class="row g-4 gx-lg-5">
            <div class="col-6">
                <div class="p-4 rounded-4 bg-light shadow-sm text-center border-bottom border-teal border-4">
                    <i class="bx bx-log-in-circle text-teal fs-2 mb-2"></i>
                    <h3 class="fw-black mb-1 text-dark" id="detailCheckIn">--:--</h3>
                    <span class="text-muted small text-uppercase fw-bold ls-1">PUNCH IN</span>
                </div>
            </div>
            <div class="col-6">
                <div class="p-4 rounded-4 bg-light shadow-sm text-center border-bottom border-orange border-4">
                    <i class="bx bx-log-out-circle text-orange fs-2 mb-2"></i>
                    <h3 class="fw-black mb-1 text-dark" id="detailCheckOut">--:--</h3>
                    <span class="text-muted small text-uppercase fw-bold ls-1">PUNCH OUT</span>
                </div>
            </div>
            <div class="col-12 mt-4">
                <div class="p-4 rounded-4 text-center" style="background-color: #f0fdf4; border: 2px dashed #bbf7d0;">
                    <i class="bx bx-stopwatch text-teal fs-4 me-2"></i>
                    <span class="text-dark small fw-black text-uppercase ls-1 d-block mb-1">Working Duration</span>
                    <h1 class="fw-black text-teal mb-0" style="font-size: 3rem;" id="detailHours">0:00h</h1>
                </div>
            </div>
            
            <div id="adjustmentAuditSection" class="col-12 mt-4 d-none">
                <div class="p-4 rounded-4 bg-label-warning border border-warning border-opacity-25 shadow-sm text-start">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="d-flex align-items-center">
                            <i class="bx bxs-edit-alt text-warning fs-4 me-2"></i>
                            <span class="text-dark small fw-black text-uppercase ls-1">Adjustment Audit</span>
                        </div>
                        <div id="auditAttachmentWrapper" class="d-none">
                            <a href="#" id="auditAttachmentLink" target="_blank" class="btn btn-sm btn-label-warning rounded-pill px-3">
                                <i class="bx bx-paperclip me-1"></i> View Evidence
                            </a>
                        </div>
                    </div>
                    <p class="mb-1 text-dark small fw-bold">Adjusted by: <span id="auditBy" class="text-teal">Admin</span></p>
                    <p class="mb-0 text-muted fw-bold italic" id="auditReason" style="font-size:0.72rem; line-height:1.2;">"Reason goes here..."</p>
                </div>
            </div>

            @if(auth()->user()->hasRole(['admin', 'hr', 'accounts']))
            <div id="editActionWrapper" class="col-12 mt-4 d-none">
                <button type="button" class="btn btn-teal w-100 rounded-4 py-3 fw-black text-uppercase ls-1 shadow-sm hitech-btn" onclick="openEditFromDetails()">
                    <i class="bx bx-edit-alt me-1"></i> Edit This Record
                </button>
            </div>
            @endif
        </div>
      </div>
      <div class="modal-footer border-0 p-4 pt-0">
          <button type="button" class="btn btn-label-teal w-100 rounded-4 py-3 fw-black text-uppercase ls-1 shadow-sm" data-bs-dismiss="modal">Close Entry Details</button>
      </div>
    </div>
  </div>
</div>

{{-- Edit Attendance Modal --}}
<div class="modal fade" id="editAttendanceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
            <div class="modal-icon-header me-3"><i class="bx bx-edit fs-3"></i></div>
            <div>
                <h5 class="modal-title-hitech mb-0">Record Adjustment</h5>
                <small class="text-white opacity-75 tracking-wider fw-bold text-uppercase" style="font-size: 0.65rem;" id="editEmpName">Emp Name</small>
            </div>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal"><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body modal-body-hitech p-4">
        <form id="editAttendanceForm">
            <input type="hidden" id="editAttendanceId">
            <div class="row g-3">
                <div class="col-6">
                    <label class="form-label fw-bold text-dark small text-uppercase ls-1">Punch In</label>
                    <input type="time" class="form-control rounded-3" id="editInTime" name="check_in_time">
                </div>
                <div class="col-6">
                    <label class="form-label fw-bold text-dark small text-uppercase ls-1">Punch Out</label>
                    <input type="time" class="form-control rounded-3" id="editOutTime" name="check_out_time">
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold text-dark small text-uppercase ls-1">Status Policy</label>
                    <select class="form-select rounded-3" id="editStatus" name="status">
                        <option value="present">Full Day (Present)</option>
                        <option value="late">Half Day (Late)</option>
                        <option value="absent">Absent</option>
                        <option value="work_from_home">WFH</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold text-dark small text-uppercase ls-1">Admin Adjustment Reason <span class="text-danger">*</span></label>
                    <textarea class="form-control rounded-3" id="editAdminReason" name="admin_reason" rows="2" placeholder="Explain the rationale for this adjustment..." required></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold text-dark small text-uppercase ls-1">Proof of Adjustment <span id="proofRequiredMarker" class="text-danger d-none">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bx bx-paperclip"></i></span>
                        <input type="file" class="form-control rounded-start-0" id="editAttachment" name="attachment" accept="image/*,.pdf">
                    </div>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-teal w-100 fw-black text-uppercase ls-1 shadow-sm hitech-btn py-3">Commit Changes</button>
            </div>
        </form>
      </div>
    </div>
  </div>
</div>

{{-- Professional Employee Summary Dashboard --}}
<div class="modal fade" id="employeeSummaryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
            <div class="avatar avatar-lg me-3 border border-white border-3 shadow-md rounded-circle overflow-hidden">
                <span class="avatar-initial rounded-circle bg-white text-teal fw-black fs-4" id="sumAvatar">??</span>
            </div>
            <div>
                <h4 class="modal-title-hitech mb-0" id="sumName">Employee Name</h4>
                <small class="text-white opacity-75 tracking-widest fw-bold" id="sumCode">EMP CODE</small>
            </div>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal"><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body modal-body-hitech p-5">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <h6 class="fw-black mb-0 text-dark text-uppercase ls-1">Monthly Analytics Snapshot</h6>
            <span class="badge bg-label-teal border border-teal border-opacity-10 rounded-pill px-3 py-2 fw-bold" id="sumMonth">Period</span>
        </div>
        <div class="row g-4 mb-5">
            <div class="col-6">
                <div class="card border-0 shadow-none bg-label-success rounded-4 p-4 text-center h-100 hover-premium-card border-bottom border-success border-4">
                    <h1 class="fw-black mb-0 text-success" id="sumPresent">0</h1>
                    <small class="text-muted fw-bold text-uppercase ls-1 mt-1">Present</small>
                </div>
            </div>
            <div class="col-6">
                <div class="card border-0 shadow-none bg-label-danger rounded-4 p-4 text-center h-100 hover-premium-card border-bottom border-danger border-4">
                    <h1 class="fw-black mb-0 text-danger" id="sumAbsent">0</h1>
                    <small class="text-muted fw-bold text-uppercase ls-1 mt-1">Absent</small>
                </div>
            </div>
        </div>
        <div class="bg-light p-4 rounded-4 border border-opacity-50">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-dark fw-black small text-uppercase ls-1">Attendance Precision</span>
                <span class="fw-black text-teal fs-4 mb-0"><span id="sumScore">0</span>%</span>
            </div>
            <div class="progress bg-white border shadow-sm" style="height: 12px; border-radius: 20px;">
                <div class="progress-bar bg-teal-gradient progress-bar-striped progress-bar-animated" role="progressbar" id="sumProgress" style="width: 0%"></div>
            </div>
        </div>
      </div>
      <div class="modal-footer border-0 p-4 pt-0">
          <button type="button" class="btn btn-label-secondary w-100 rounded-4 py-3 fw-black text-uppercase ls-1 shadow-sm" data-bs-dismiss="modal">Exit Dashboard</button>
      </div>
    </div>
  </div>
</div>

<!-- Attendance Preview Modal -->
<div class="modal fade" id="attendancePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content modal-content-hitech shadow-lg">
            <div class="modal-header modal-header-hitech">
                <div class="d-flex align-items-center">
                    <div class="modal-icon-header me-3"><i class="bx bx-show fs-3"></i></div>
                    <div>
                        <h5 class="modal-title modal-title-hitech mb-1">Synchronization Preview</h5>
                        <p class="text-white opacity-75 mb-0" style="font-size:0.72rem;">Review machine logs matched against staff profiles</p>
                    </div>
                </div>
                <button type="button" class="btn-close-hitech" data-bs-dismiss="modal"><i class="bx bx-x"></i></button>
            </div>
            <div class="modal-body p-0" id="previewModalBody">
                <div class="p-5 text-center my-4">
                    <div class="spinner-border text-teal mb-3" role="status" style="width: 3rem; height: 3rem;"></div>
                    <h5 class="fw-bold text-dark">Processing Biometric Logs...</h5>
                </div>
            </div>
            <div class="modal-footer border-top bg-light p-4 gap-3">
                <button type="button" class="btn btn-label-secondary px-4 rounded-pill fw-bold" data-bs-dismiss="modal">Discard</button>
                <form id="attendanceConfirmForm" action="{{ route('attendance.biometric-import.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="records" id="previewRecordsInput">
                    <button type="submit" class="btn btn-teal px-5 shadow-md rounded-pill fw-black text-uppercase ls-1">
                        <i class="bx bx-check-circle me-1"></i> Confirm & Commit
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Import Attendance Modal --}}
<div class="modal fade" id="importAttendanceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
            <div class="modal-icon-header me-3"><i class="bx bx-upload fs-3"></i></div>
            <div>
                <h5 class="modal-title modal-title-hitech mb-0">Attendance Import Hub</h5>
                <p class="text-white opacity-75 mb-0" style="font-size:0.72rem;">Choose your sync method (Excel or Biometric ID Match)</p>
            </div>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal"><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body p-0">
          <ul class="nav nav-tabs nav-justified hitech-tabs border-bottom" role="tablist">
            <li class="nav-item">
              <button class="nav-link active py-3" data-bs-toggle="tab" data-bs-target="#standardImportTab">
                  <i class="bx bx-file me-2"></i>Standard Excel
              </button>
            </li>
            <li class="nav-item">
              <button class="nav-link py-3" data-bs-toggle="tab" data-bs-target="#biometricImportTab">
                  <i class="bx bx-fingerprint me-2 text-teal"></i>Biometric (ID Match)
              </button>
            </li>
          </ul>

          <div class="tab-content p-5">
              <div class="tab-pane fade show active" id="standardImportTab">
                <form action="{{ route('attendance.biometric-import.preview') }}" method="POST" enctype="multipart/form-data">
                  @csrf
                  <div class="row g-4">
                      <div class="col-lg-7">
                        <div class="p-5 border-2 border-dashed rounded-4 bg-light cursor-pointer hover-bg-teal-soft h-100 d-flex flex-column align-items-center justify-content-center" onclick="document.getElementById('importFile').click()">
                            <i class="bx bx-file display-4 text-teal mb-3"></i>
                            <h6 class="fw-bold text-dark mb-1">Upload Standard Excel</h6>
                            <input type="file" id="importFile" name="file" class="d-none" onchange="submitAttendanceFile(this, 'fileNameDisplay')" accept=".xlsx,.xls,.csv">
                            <div id="fileNameDisplay" class="mt-3 text-teal fw-black d-none"></div>
                        </div>
                      </div>
                      <div class="col-lg-5">
                          <div class="bg-white border rounded-4 p-4 h-100 shadow-sm">
                              <div class="d-flex justify-content-between align-items-center mb-3">
                                  <span class="text-dark fw-black small text-uppercase ls-1">Standard Format</span>
                                  <a href="{{ route('attendance.download-sample') }}" download="attendance_import_sample.csv" class="btn btn-sm btn-label-teal rounded-pill">
                                      <i class="bx bx-download me-1"></i>Sample
                                  </a>
                              </div>
                              <p class="text-muted tiny mb-0"><i class="bx bx-info-circle me-1"></i> Data will be matched with staff records before finalizing.</p>
                          </div>
                      </div>
                  </div>
                </form>
              </div>
          </div>
      </div>
    </div>
  </div>
</div>
</div>
@endsection

@section('page-script')
@vite([
  'resources/js/main-select2.js',
  'resources/assets/js/app/attendance-index.js'
])
<script>
function exportData() {
  const isRegistry = $('#registry-view-tab').hasClass('active');
  const params = new URLSearchParams();

  const userId = $('#userId').val();
  const shiftId = $('#shiftId').val();
  const departmentId = $('#departmentId').val();
  const siteId = $('#siteId').val();
  const searchTerm = $('#customSearchInput').val();
  if (userId) params.set('userId', userId);
  if (shiftId) params.set('shiftId', shiftId);
  if (departmentId) params.set('departmentId', departmentId);
  if (siteId) params.set('siteId', siteId);
  if (searchTerm) params.set('searchTerm', searchTerm);

  let url;
  if (isRegistry) {
    // Same month input, sort control, and legend-chip filter the Monthly
    // Registry view itself reads — see loadMonthlyRegistry/registryRowsForDisplay
    // in attendance-index.js.
    const monthVal = $('#registryMonth').val();
    if (monthVal) {
      const [y, m] = monthVal.split('-');
      params.set('year', y);
      params.set('month', m);
    }
    const sort = $('#registrySort').val();
    if (sort) params.set('sort', sort);
    if (window.__legendStatus) params.set('legendStatus', window.__legendStatus);
    url = '{{ route('attendance.export.registry') }}';
  } else {
    // Same quickPeriod -> date-range logic the Real-Time Logs DataTable ajax
    // uses — see the `data` function on #attendanceTable in attendance-index.js.
    const quickPeriod = $('#quickPeriod').val();
    if (quickPeriod && quickPeriod !== 'custom') {
      let start = moment();
      let end = moment();
      if (quickPeriod === 'yesterday') { start = moment().subtract(1, 'days'); end = moment().subtract(1, 'days'); }
      else if (quickPeriod === '7days') { start = moment().subtract(7, 'days'); end = moment(); }
      else if (quickPeriod === 'last_month') { start = moment().subtract(1, 'months').startOf('month'); end = moment().subtract(1, 'months').endOf('month'); }
      else if (quickPeriod === 'today') { start = moment(); end = moment(); }
      params.set('startDate', start.format('YYYY-MM-DD'));
      params.set('endDate', end.format('YYYY-MM-DD'));
    } else {
      params.set('date', $('#date').val());
    }
    url = '{{ route('attendance.export.logs') }}';
  }

  window.location.href = url + '?' + params.toString();
}
document.addEventListener('DOMContentLoaded', function() {
  const options = {
    series: [{ name: 'Present', data: [] }, { name: 'Absent', data: [] }],
    chart: { height: 400, type: 'area', toolbar: { show: true }, fontFamily: 'Inter, sans-serif' },
    colors: ['#005a5a', '#ff4d49'],
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 3 },
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1, stops: [0, 90, 100] } },
    grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
    xaxis: { categories: [], axisBorder: { show: false }, axisTicks: { show: false }, labels: { style: { colors: '#64748b', fontSize: '12px' } } },
    yaxis: { labels: { style: { colors: '#64748b', fontSize: '12px' } } },
    tooltip: { theme: 'light' },
    legend: { show: false }
  };
  window.attendanceChart = new ApexCharts(document.querySelector("#weeklyAttendanceChart"), options);
  window.attendanceChart.render();
  refreshChart();
});
</script>
<style>
.sticky-col { position: sticky; left: 0; z-index: 2; }
.hitech-registry-table { border-collapse: separate !important; border-spacing: 0 !important; }
.hitech-registry-table thead th { padding: 12px 6px; font-size: 0.75rem; vertical-align: middle; position: sticky; top: 0; z-index: 10; background: #f8fafc !important; }
.hitech-registry-table tbody td.sticky-col { background: #fff !important; z-index: 11; border-right: 2px solid #f1f3f5 !important; }
.attendance-box { min-height: 32px; transition: all 0.2s; }
.border-dashed { border-style: dashed !important; border-width: 2px !important; }
.opacity-50 { opacity: 0.5 !important; }

.text-primary, .border-primary, .bg-label-primary { 
    color: #127464 !important; 
    border-color: #127464 !important; 
    background-color: rgba(18, 116, 100, 0.1) !important;
}
</style>
@endsection
