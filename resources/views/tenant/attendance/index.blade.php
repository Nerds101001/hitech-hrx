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

@section('content')
<div class="px-4">
<div class="row g-6 mb-6">
  <!-- Hero Banner -->
  <div class="col-lg-12">
    <x-hero-banner 
      title="Attendance Management" 
      subtitle="Track, monitor and optimize employee presence in real-time"
      icon="bx-time-five"
      gradient="teal"
      quote="Punctuality is not just about being on time, it's about respecting other people's time."
    />
  </div>
</div>

<!-- Stats Cards -->
<div class="row g-6 mb-6">
  <x-stat-card 
    id="statPresentCount"
    title="Today's Present" 
    value="{{ $todayPresentCount ?? 0 }}" 
    icon="bx-user-check" 
    color="success"
    trend="up"
    trendValue="+{{ $todayPresentCount > 0 ? round(($todayPresentCount / ($activeUsersCount ?: 1)) * 100) : 0 }}%"
    animation-delay="0.1s"
  />
  
  <x-stat-card 
    id="statAbsentCount"
    title="Today's Absent" 
    value="{{ $todayAbsentCount ?? 0 }}" 
    icon="bx-user-x" 
    color="danger"
    trend="down"
    trendValue="{{ $todayAbsentCount }}"
    animation-delay="0.2s"
  />
  
  <x-stat-card 
    id="statLeaveCount"
    title="On Leave" 
    value="{{ $onLeaveCount ?? 0 }}" 
    icon="bx-calendar-minus" 
    color="warning"
    animation-delay="0.3s"
  />
  
  <x-stat-card 
    id="statLateCount"
    title="Late Arrivals" 
    value="{{ $lateCount ?? 0 }}" 
    icon="bx-time" 
    color="amber"
    trend="up"
    trendValue="{{ $lateCount }} today"
    animation-delay="0.4s"
  />
</div>

<!-- Attendance Records Table -->
<div class="row g-6 mb-6">
  <div class="col-12">
    <div class="hitech-card animate__animated animate__fadeInUp" style="animation-delay: 0.1s">
      <div class="hitech-card-header border-bottom p-0">
          <ul class="nav nav-tabs nav-justified hitech-tabs-sm mb-0" role="tablist">
              <li class="nav-item">
                  <button class="nav-link py-3" id="log-view-tab" data-bs-toggle="tab" data-bs-target="#listViewTab">
                      <i class="bx bx-list-ul me-2"></i>Real-time Logs
                  </button>
              </li>
              <li class="nav-item">
                  <button class="nav-link py-3 active" id="registry-view-tab" data-bs-toggle="tab" data-bs-target="#registryViewTab">
                      <i class="bx bx-grid-alt me-2 text-primary"></i>Monthly Registry
                  </button>
              </li>
          </ul>
      </div>

      {{-- Unified White Filter Row --}}
      <div class="card-body p-4 border-bottom">
        <div class="d-flex flex-wrap align-items-center gap-3">
          {{-- Search --}}
          <div class="search-wrapper-hitech" style="width: 400px;">
            <i class="bx bx-search text-muted ms-3 fs-5"></i>
            <input type="text" class="form-control" placeholder="Search employee..." id="customSearchInput">
            <button class="btn-search shadow-sm" id="customSearchBtn">
              <i class="bx bx-search fs-5"></i>
              <span>Search</span>
            </button>
          </div>

          {{-- Quick Period Filter --}}
          <div class="compact-select" style="min-width: 140px;">
              <select id="quickPeriod" class="form-select filter-item-hitech">
                  <option value="today" selected>Today</option>
                  <option value="yesterday">Yesterday</option>
                  <option value="7days">Last 7 Days</option>
                  <option value="last_month">Last Month</option>
                  <option value="custom">Custom Date</option>
              </select>
          </div>

          {{-- Date Filter (Shown for Custom) --}}
          <div id="dateFilterWrapper" style="width: 160px; display: none;">
            <input type="date" id="date" name="date" class="form-control filter-item-hitech"
                   value="{{ request()->get('date', now()->format('Y-m-d')) }}">
          </div>

          {{-- Month/Year Filter (for Registry) --}}
          <div class="compact-select" id="registryMonthWrapper" style="min-width: 160px; display: none;">
              <input type="month" id="registryMonth" class="form-control filter-item-hitech" 
                     value="{{ now()->format('Y-m') }}">
          </div>

          {{-- Site/Unit --}}
          <div class="compact-select" style="min-width: 140px;">
              <select id="siteId" name="siteId" class="form-select select2 filter-item-hitech">
                <option value="">Unit: All</option>
                @foreach($sites as $site)
                  <option value="{{ $site->id }}">{{ $site->name }}</option>
                @endforeach
              </select>
          </div>

          {{-- Department --}}
          <div class="compact-select">
              <select id="teamId" name="teamId" class="form-select select2 filter-item-hitech">
                <option value="">Dept: All</option>
                @foreach($teams as $team)
                  <option value="{{ $team->id }}">{{ $team->name }}</option>
                @endforeach
              </select>
          </div>

          {{-- Employee --}}
          <div class="compact-select" style="min-width: 200px;">
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
          <div class="compact-select">
            <select id="shiftId" name="shiftId" class="form-select select2 filter-item-hitech">
              <option value="">Shift: All</option>
              @foreach($shifts as $shift)
                <option value="{{ $shift->id }}" {{ request()->get('shift') == $shift->id ? 'selected' : '' }}>
                  {{ $shift->name }}
                </option>
              @endforeach
            </select>
          </div>

          {{-- Spacer --}}
          <div class="flex-grow-1"></div>

          {{-- Records Per Page --}}
          <div class="d-flex align-items-center">
            <select class="form-select w-px-70 filter-item-hitech border-light shadow-none fw-bold" id="customLengthMenu">
              <option value="10">10</option>
              <option value="25">25</option>
              <option value="50">50</option>
              <option value="100">100</option>
            </select>
          </div>

          {{-- Export Icon --}}
          <button type="button" class="btn btn-hitech-icon shadow-sm me-2" onclick="exportData()" title="Export Data">
            <i class="bx bx-download fs-5"></i>
          </button>

          {{-- Bulk Import Button --}}
          <button type="button" class="btn btn-hitech-export shadow-sm" data-bs-toggle="modal" data-bs-target="#importAttendanceModal">
            <i class="bx bx-upload fs-5 me-2"></i>
            <span>Bulk Import</span>
          </button>
        </div>
      </div>

      <div class="tab-content border-0 p-0" id="attendanceTabs">
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

        {{-- Monthly Registry --}}
        <div class="tab-pane fade show active" id="registryViewTab" role="tabpanel">
            <div class="table-responsive p-0" id="registryTableContainer" style="max-height: 70vh; min-height: 400px; overflow: auto;">
                <div class="text-center p-5">
                    <div class="spinner-border text-teal" role="status"></div>
                    <p class="mt-2 text-muted">Building your monthly registry...</p>
                </div>
            </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Full Width Interactive Chart -->
<div class="row g-6">
  <div class="col-12">
    <div class="hitech-card animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
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
                  @foreach($teams as $team)
                      <option value="{{ $team->id }}">{{ $team->name }}</option>
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

            <div id="editActionWrapper" class="col-12 mt-4 d-none">
                <button type="button" class="btn btn-teal w-100 rounded-4 py-3 fw-black text-uppercase ls-1 shadow-sm hitech-btn" onclick="openEditFromDetails()">
                    <i class="bx bx-edit-alt me-1"></i> Edit This Record
                </button>
            </div>
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
function exportData() { }
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
