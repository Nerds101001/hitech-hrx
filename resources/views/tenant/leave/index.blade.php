@php use App\Enums\LeaveRequestStatus; @endphp
@extends('layouts/layoutMaster')

@section('title', __('Leave Management'))

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
    'resources/assets/vendor/libs/@form-validation/form-validation.scss',
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
    'resources/assets/vendor/libs/select2/select2.scss',
    'resources/assets/vendor/libs/apex-charts/apex-charts.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])

@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
    'resources/assets/vendor/libs/@form-validation/popular.js',
    'resources/assets/vendor/libs/@form-validation/bootstrap5.js',
    'resources/assets/vendor/libs/@form-validation/auto-focus.js',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.js',
    'resources/assets/vendor/libs/select2/select2.js',
    'resources/assets/vendor/libs/apex-charts/apexcharts.js'
  ])
@endsection

@section('page-script')
  @vite(['resources/assets/js/app/leave-requests-index.js'])
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css"/>
  <script src="https://cdn.jsdelivr.net/gh/mcstudios/glightbox/dist/js/glightbox.min.js"></script>
  <script>
    window.isAccountsRole = {{ auth()->user()->hasRole('accounts') ? 'true' : 'false' }};
  </script>
@endsection

@section('content')


  <!-- Leave Requests Table Section -->
  <div class="row g-6 mb-6">
    <div class="col-12">
      <div class="hitech-card animate__animated animate__fadeInUp" style="animation-delay: 0.1s">
        <div class="hitech-card-header border-bottom">
          <div class="d-flex align-items-center justify-content-between w-100">
            <div class="d-flex align-items-center gap-4">
                {{-- Main tab: Leave / Outdoor Duty --}}
                {{-- Hitech Segmented Toggle --}}
                @if(!auth()->user()->hasRole('accounts'))
                <div class="hitech-segmented-control bg-light-soft rounded-pill p-1 d-flex" style="border: 1px solid rgba(0, 90, 90, 0.1);">
                    <button type="button" class="btn btn-sm rounded-pill px-4 status-toggle-btn active" data-status="pending">
                        <i class="bx bx-time-five me-1"></i>Pending
                    </button>
                    <button type="button" class="btn btn-sm rounded-pill px-4 status-toggle-btn" data-status="approved">
                        <i class="bx bx-check-double me-1"></i>Approved
                    </button>
                </div>
                @else
                <div class="hitech-segmented-control bg-light-soft rounded-pill p-1 d-flex" style="border: 1px solid rgba(0, 90, 90, 0.1);">
                    <button type="button" class="btn btn-sm rounded-pill px-4 status-toggle-btn active" data-status="approved">
                        <i class="bx bx-check-double me-1"></i>Approved Leaves Only
                    </button>
                </div>
                @endif
            </div>
            @if(!auth()->user()->hasRole('accounts'))
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-sm btn-hitech" onclick="approveSelected()">
                <i class="bx bx-check me-1"></i>Approve Selected
              </button>
              <button type="button" class="btn btn-sm btn-hitech-alert" onclick="rejectSelected()">
                <i class="bx bx-x me-1"></i>Reject Selected
              </button>
            </div>
            @endif
          </div>
        </div>

        {{-- Integrated Filter Row --}}
        <div class="card-body p-4 border-bottom">
          <div class="d-flex flex-wrap align-items-center gap-3">
            {{-- Search --}}
            <div class="search-wrapper-hitech" style="width: 400px;">
              <i class="bx bx-search text-muted ms-3 fs-5"></i>
              <input type="text" class="form-control" placeholder="Search requests..." id="customSearchInput">
              <button class="btn-search shadow-sm" id="customSearchBtn">
                <i class="bx bx-search fs-5"></i>
                <span>Search</span>
              </button>
            </div>

            {{-- Date Filter --}}
            <div style="width: 160px;">
              <input type="date" id="dateFilter" name="dateFilter" class="form-control filter-item-hitech" title="Daily Filter">
            </div>

            {{-- Month Filter --}}
            <div class="compact-select" style="min-width: 120px;">
              <select id="monthFilter" name="monthFilter" class="form-select select2 filter-item-hitech">
                <option value="">Month: All</option>
                @for($m=1; $m<=12; $m++)
                  <option value="{{ $m }}">{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
                @endfor
              </select>
            </div>

            {{-- Year Filter --}}
            <div class="compact-select" style="min-width: 100px;">
              <select id="yearFilter" name="yearFilter" class="form-select select2 filter-item-hitech">
                <option value="">Year: All</option>
                @for($y=date('Y'); $y>=date('Y')-5; $y--)
                  <option value="{{ $y }}">{{ $y }}</option>
                @endfor
              </select>
            </div>

            {{-- Employee Filter --}}
            <div class="compact-select" style="min-width: 200px;">
              <select id="employeeFilter" name="employeeFilter" class="form-select select2 filter-item-hitech">
                <option value="">Emp: All</option>
                @foreach($employees as $employee)
                  <option value="{{ $employee->id }}">{{ $employee->getFullName() }}</option>
                @endforeach
              </select>
            </div>

            {{-- Leave Type Filter --}}
            <div class="compact-select">
              <select id="leaveTypeFilter" name="leaveTypeFilter" class="form-select select2 filter-item-hitech">
                <option value="">Type: All</option>
                @foreach($leaveTypes as $leaveType)
                  <option value="{{ $leaveType->id }}">{{ $leaveType->name }}</option>
                @endforeach
              </select>
            </div>

            {{-- Hidden Status Filter (Controlled by toggle) --}}
            <input type="hidden" id="statusFilter" value="{{ auth()->user()->hasRole('accounts') ? 'approved' : 'pending' }}">

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
          </div>
        </div>

        {{-- LEAVE REQUESTS TABLE --}}
        <div id="leaveSection">
          <div class="card-datatable table-responsive p-0">
            <table class="datatables-leaveRequests table table-hover border-top mb-0">
              <thead>
                <tr>
                  @if(!auth()->user()->hasRole('accounts'))
                  <th><input type="checkbox" id="selectAll" class="form-check-input"></th>
                  @else
                  <th>ID</th>
                  @endif
                  <th>Employee</th>
                  <th>Department</th>
                  <th>Leave Type</th>
                  <th>From Date</th>
                  <th>Days</th>
                  <th>Reason</th>
                  <th class="status-col">Status</th>
                  <th>Attachment</th>
                  <th class="action-col">Actions</th>
                  <th class="approved-by-col" style="display:none;">Approved By</th>
                  <th class="approved-at-col" style="display:none;">Approved At</th>
                </tr>
              </thead>
            </table>
          </div>
        </div>

        {{-- OUTDOOR DUTY SECTION --}}
        <div id="outdoorSection" style="display:none;">
          {{-- Outdoor Duty Filter Row --}}
          <div class="p-4 border-bottom">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
              <div class="d-flex flex-wrap gap-3 align-items-center">
                <div class="search-wrapper-hitech" style="width:300px;">
                  <i class="bx bx-search text-muted ms-3 fs-5"></i>
                  <input type="text" class="form-control" placeholder="Search purpose, location..." id="odSearchInput">
                </div>
                <select id="odStatusFilter" class="form-select form-select-sm filter-item-hitech" style="width:130px;">
                  <option value="">Status: All</option>
                  <option value="pending" selected>Pending</option>
                  <option value="approved">Approved</option>
                  <option value="rejected">Rejected</option>
                  <option value="cancelled">Cancelled</option>
                </select>
                <select id="odMonthFilter" class="form-select form-select-sm filter-item-hitech" style="width:130px;">
                  <option value="">Month: All</option>
                  @for($m=1;$m<=12;$m++)
                    <option value="{{ $m }}" @selected($m==now()->month)>{{ date('F',mktime(0,0,0,$m,1)) }}</option>
                  @endfor
                </select>
                @if(!auth()->user()->hasRole(['employee']))
                <select id="odEmployeeFilter" class="form-select form-select-sm filter-item-hitech select2" style="width:200px;">
                  <option value="">Emp: All</option>
                  @foreach($employees as $e)
                    <option value="{{ $e->id }}">{{ $e->getFullName() }}</option>
                  @endforeach
                </select>
                @endif
              </div>
              @if(!auth()->user()->hasRole('accounts'))
              <button class="btn btn-sm btn-hitech" data-bs-toggle="modal" data-bs-target="#odApplyModal">
                <i class="bx bx-plus me-1"></i>Apply for Outdoor Duty
              </button>
              @endif
            </div>
          </div>

          <div class="card-datatable table-responsive p-0">
            <table id="odTable" class="table table-hover border-top mb-0">
              <thead>
                <tr>
                  <th>Employee</th>
                  <th>Date</th>
                  <th>Purpose</th>
                  <th>Location</th>
                  <th>Exp. Return</th>
                  <th>Status</th>
                  <th>Approved By</th>
                  <th>Applied On</th>
                  @if(!auth()->user()->hasRole('accounts'))
                  <th>Actions</th>
                  @endif
                </tr>
              </thead>
            </table>
          </div>
        </div>

      </div>
    </div>
  </div>

  {{-- Outdoor Duty: Apply Modal --}}
  <div class="modal fade" id="odApplyModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bx bx-map-pin me-2"></i>Apply for Outdoor Duty</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="odApplyForm">
          @csrf
          <div class="modal-body">
            @if(!auth()->user()->hasRole('employee'))
            <div class="mb-3">
              <label class="form-label fw-semibold">Employee</label>
              <select name="od_user_id" class="form-select select2">
                <option value="{{ auth()->id() }}">Myself — {{ auth()->user()->getFullName() }}</option>
                @foreach($employees as $e)
                  @if($e->id !== auth()->id())
                    <option value="{{ $e->id }}">{{ $e->getFullName() }} ({{ $e->code }})</option>
                  @endif
                @endforeach
              </select>
            </div>
            @endif
            <div class="mb-3">
              <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
              <input type="date" name="od_date" class="form-control" required value="{{ date('Y-m-d') }}">
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Purpose <span class="text-danger">*</span></label>
              <textarea name="od_purpose" class="form-control" rows="3" required maxlength="500" placeholder="Reason / task for outdoor duty..."></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Location / Destination</label>
              <input type="text" name="od_location" class="form-control" maxlength="255" placeholder="e.g. Client office, site visit...">
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Expected Return Time</label>
              <input type="time" name="od_expected_return" class="form-control">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-hitech" id="odSubmitBtn">
              <span class="spinner-border spinner-border-sm d-none me-1" id="odSpinner"></span>Submit
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  {{-- Outdoor Duty: View/Action Modal --}}
  <div class="modal fade" id="odViewModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Outdoor Duty Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="d-flex align-items-center gap-3 mb-4">
            <div class="avatar avatar-md">
              <span class="avatar-initial rounded-circle bg-label-primary" id="odViewInitials"></span>
            </div>
            <div>
              <div class="fw-semibold" id="odViewName"></div>
              <small class="text-muted" id="odViewCode"></small>
            </div>
            <span class="badge ms-auto" id="odViewBadge"></span>
          </div>
          <table class="table table-borderless table-sm">
            <tr><td class="text-muted" style="width:40%">Date</td><td id="odViewDate" class="fw-semibold"></td></tr>
            <tr><td class="text-muted">Purpose</td><td id="odViewPurpose"></td></tr>
            <tr><td class="text-muted">Location</td><td id="odViewLocation"></td></tr>
            <tr><td class="text-muted">Expected Return</td><td id="odViewReturn"></td></tr>
            <tr><td class="text-muted">Applied On</td><td id="odViewApplied"></td></tr>
            <tr id="odApproverRow" class="d-none"><td class="text-muted">Approved By</td><td id="odViewApprover"></td></tr>
            <tr id="odNotesRow" class="d-none"><td class="text-muted">Notes</td><td id="odViewNotes"></td></tr>
          </table>
          @if(!auth()->user()->hasRole(['employee','accounts']))
          <div id="odActionSection" class="d-none border-top pt-3 mt-2">
            <input type="hidden" id="odActionId">
            <div class="mb-3">
              <label class="form-label fw-semibold">Remarks (optional)</label>
              <textarea id="odActionNotes" class="form-control" rows="2"></textarea>
            </div>
            <div class="d-flex gap-2">
              <button class="btn btn-success btn-sm flex-fill" id="odApproveBtn"><i class="bx bx-check me-1"></i>Approve</button>
              <button class="btn btn-danger btn-sm flex-fill" id="odRejectBtn"><i class="bx bx-x me-1"></i>Reject</button>
            </div>
          </div>
          @endif
        </div>
      </div>
    </div>
  </div>



@include('_partials._modals.leave.leave_request_details')

<script>
function filterLeaveRequests() {
  const employee = document.getElementById('employeeFilter').value;
  const date = document.getElementById('dateFilter').value;
  const leaveType = document.getElementById('leaveTypeFilter').value;
  const status = document.getElementById('statusFilter').value;
  
  const url = new URL(window.location.href);
  if (employee) url.searchParams.set('employee', employee);
  if (date) url.searchParams.set('date', date);
  if (leaveType) url.searchParams.set('leaveType', leaveType);
  if (status) url.searchParams.set('status', status);
  
  window.location.href = url.toString();
}

function getSelectedIds() {
  const selected = [];
  $('.dt-checkboxes:checked').each(function() {
    selected.push($(this).val());
  });
  return selected;
}

function processBulkAction(status) {
  const ids = getSelectedIds();
  if (ids.length === 0) {
    Swal.fire({
      icon: 'warning',
      title: 'Warning',
      text: 'Please select at least one leave request',
      customClass: { confirmButton: 'btn btn-primary' }
    });
    return;
  }

  Swal.fire({
    title: 'Are you sure?',
    text: `You want to mark ${ids.length} request(s) as ${status}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, proceed!',
    cancelButtonText: 'Cancel',
    customClass: {
      confirmButton: 'btn btn-hitech me-3',
      cancelButton: 'btn btn-hitech-secondary'
    },
    buttonsStyling: false
  }).then(function (result) {
    if (result.value) {
      $.ajax({
        url: '{{ route("leaveRequests.bulkActionAjax") }}',
        type: 'POST',
        data: {
          ids: ids,
          status: status,
          _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
          if (response.status === 'success') {
            Swal.fire({
              icon: 'success',
              title: 'Success!',
              text: response.message,
              customClass: { confirmButton: 'btn btn-success' }
            });
            $('.datatables-leaveRequests').DataTable().ajax.reload();
            $('#selectAll').prop('checked', false);
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Error!',
              text: response.message,
              customClass: { confirmButton: 'btn btn-danger' }
            });
          }
        },
        error: function(err) {
          Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Something went wrong',
            customClass: { confirmButton: 'btn btn-danger' }
          });
        }
      });
    }
  });
}

function approveSelected() {
  processBulkAction('approved');
}

function rejectSelected() {
  processBulkAction('rejected');
}

// ── Outdoor Duty Tab ──────────────────────────────────────────────────────────
$(function () {
  const OD = {
    list:    '{{ url("leaveRequests/outdoorDuty/listAjax") }}',
    store:   '{{ url("leaveRequests/outdoorDuty/storeAjax") }}',
    action:  '{{ url("leaveRequests/outdoorDuty/actionAjax") }}',
    getById: '{{ url("leaveRequests/outdoorDuty/getByIdAjax") }}',
  };
  const canAct = {{ auth()->user()->hasRole(['admin','hr','manager']) ? 'true' : 'false' }};

  // Main tab toggle
  $('.main-tab-btn').on('click', function () {
    $('.main-tab-btn').removeClass('active');
    $(this).addClass('active');
    const tab = $(this).data('tab');
    if (tab === 'outdoor') {
      $('#leaveSection').hide();
      $('#outdoorSection').show();
      $('.hitech-segmented-control:not(.main-tab-btn)').closest('.hitech-segmented-control').hide();
      $('[onclick]').hide(); // hide bulk approve/reject for leave
      if (!$.fn.DataTable.isDataTable('#odTable')) initOdTable();
    } else {
      $('#outdoorSection').hide();
      $('#leaveSection').show();
      $('.hitech-segmented-control').show();
      $('[onclick]').show();
    }
  });

  let odTable;
  function initOdTable() {
    let cols = [
      {
        data: null,
        render: d => `<div class="d-flex align-items-center gap-2">
          ${d.user_profile_image
            ? `<img src="${d.user_profile_image}" class="rounded-circle" style="width:30px;height:30px;object-fit:cover">`
            : `<span class="avatar-initial rounded-circle bg-label-primary d-inline-flex align-items-center justify-content-center" style="width:30px;height:30px;font-size:11px">${d.user_initial}</span>`}
          <div><div class="fw-semibold small">${d.user_name}</div><div class="text-muted" style="font-size:11px">${d.user_code||''}</div></div>
        </div>`
      },
      { data: 'date_fmt' },
      { data: 'purpose', render: d => d && d.length > 45 ? d.substring(0,45)+'…' : (d||'') },
      { data: 'location', defaultContent: '<span class="text-muted">—</span>' },
      { data: 'expected_return_time', defaultContent: '<span class="text-muted">—</span>' },
      {
        data: 'status',
        render: s => {
          const map = { pending:'warning', approved:'success', rejected:'danger', cancelled:'secondary' };
          const od  = s === 'approved' ? ' <span class="badge bg-label-info ms-1 fw-bold" title="Outdoor Duty marked Present">O</span>' : '';
          return `<span class="badge bg-label-${map[s]||'secondary'}">${s.charAt(0).toUpperCase()+s.slice(1)}</span>${od}`;
        }
      },
      { data: 'approved_by_name', defaultContent: '<span class="text-muted">—</span>' },
      { data: 'created_at', render: d => d ? new Date(d).toLocaleDateString('en-GB') : '' },
    ];

    if (canAct) {
      cols.push({
        data: null, orderable: false,
        render: d => `
          <button class="btn btn-sm btn-icon btn-outline-primary od-view-btn" data-id="${d.id}"><i class="bx bx-show"></i></button>
          ${d.status==='pending' ? `
          <button class="btn btn-sm btn-icon btn-outline-success od-approve-btn" data-id="${d.id}"><i class="bx bx-check"></i></button>
          <button class="btn btn-sm btn-icon btn-outline-danger od-reject-btn" data-id="${d.id}"><i class="bx bx-x"></i></button>` : ''}
        `
      });
    }

    odTable = $('#odTable').DataTable({
      processing: true, serverSide: true,
      ajax: { url: OD.list, data: d => {
        d.employeeFilter = $('#odEmployeeFilter').val();
        d.statusFilter   = $('#odStatusFilter').val();
        d.monthFilter    = $('#odMonthFilter').val();
        d.searchTerm     = $('#odSearchInput').val();
      }},
      columns: cols,
      order: [[7, 'desc']],
      pageLength: 25,
    });

    ['#odStatusFilter','#odMonthFilter','#odEmployeeFilter'].forEach(id =>
      $(id).on('change', () => odTable.ajax.reload()));
    let st; $('#odSearchInput').on('input', () => { clearTimeout(st); st = setTimeout(() => odTable.ajax.reload(), 400); });
  }

  // Apply form submit
  $('#odApplyForm').on('submit', function (e) {
    e.preventDefault();
    const btn = $('#odSubmitBtn'), sp = $('#odSpinner');
    btn.prop('disabled', true); sp.removeClass('d-none');
    $.ajax({
      url: OD.store, method: 'POST', data: $(this).serialize(),
      success: r => {
        if (r.status === 'success') {
          Swal.fire({ icon: 'success', title: 'Submitted!', text: r.message, timer: 2000, showConfirmButton: false });
          $('#odApplyModal').modal('hide');
          this.reset();
          if (odTable) odTable.ajax.reload();
        } else {
          Swal.fire({ icon: 'error', title: 'Error', text: r.message });
        }
      },
        error: (xhr) => {
          let msg = 'Something went wrong.';
          if (xhr.responseJSON && xhr.responseJSON.message) {
            msg = xhr.responseJSON.message;
          }
          Swal.fire({ icon: 'error', title: 'Error', text: msg });
        },
      complete: () => { btn.prop('disabled', false); sp.addClass('d-none'); }
    });
  });

  // View detail modal
  $(document).on('click', '.od-view-btn', function () {
    const id = $(this).data('id');
    $.get(OD.getById + '/' + id, function (d) {
      $('#odViewInitials').text(d.userInitials);
      $('#odViewName').text(d.userName);
      $('#odViewCode').text(d.userCode || '');
      $('#odViewDate').text(d.date);
      $('#odViewPurpose').text(d.purpose);
      $('#odViewLocation').text(d.location);
      $('#odViewReturn').text(d.expectedReturn);
      $('#odViewApplied').text(d.createdAt);
      const map = { pending:'warning', approved:'success', rejected:'danger', cancelled:'secondary' };
      $('#odViewBadge').attr('class', `badge bg-label-${map[d.status]||'secondary'}`).text(d.status.charAt(0).toUpperCase()+d.status.slice(1));
      d.approvedByName ? ($('#odViewApprover').text(d.approvedByName+(d.approvedAt?' — '+d.approvedAt:'')), $('#odApproverRow').removeClass('d-none')) : $('#odApproverRow').addClass('d-none');
      d.approvalNotes  ? ($('#odViewNotes').text(d.approvalNotes), $('#odNotesRow').removeClass('d-none')) : $('#odNotesRow').addClass('d-none');
      $('#odActionId').val(d.id);
      (canAct && d.status === 'pending') ? $('#odActionSection').removeClass('d-none') : $('#odActionSection').addClass('d-none');
      $('#odViewModal').modal('show');
    });
  });

  // Inline quick approve
  $(document).on('click', '.od-approve-btn', function () { odDoAction($(this).data('id'), 'approved', ''); });
  $(document).on('click', '.od-reject-btn', function () {
    const id = $(this).data('id');
    Swal.fire({ title: 'Reject?', input: 'text', inputPlaceholder: 'Reason (optional)', showCancelButton: true,
      confirmButtonColor: '#d33', confirmButtonText: 'Reject' })
      .then(r => { if (r.isConfirmed) odDoAction(id, 'rejected', r.value || ''); });
  });

  // Modal approve/reject
  $('#odApproveBtn').on('click', () => odDoAction($('#odActionId').val(), 'approved', $('#odActionNotes').val()));
  $('#odRejectBtn').on('click',  () => odDoAction($('#odActionId').val(), 'rejected', $('#odActionNotes').val()));

  function odDoAction(id, status, notes) {
    $.ajax({
      url: OD.action, method: 'POST',
      data: { _token: $('meta[name=csrf-token]').attr('content'), id, status, adminNotes: notes },
      success: r => {
        if (r.status === 'success') {
          Swal.fire({ icon: 'success', title: status.charAt(0).toUpperCase()+status.slice(1)+'!', text: r.message, timer: 2000, showConfirmButton: false });
          $('#odViewModal').modal('hide');
          if (odTable) odTable.ajax.reload();
        } else {
          Swal.fire({ icon: 'error', title: 'Error', text: r.message });
        }
      }
    });
  }
});

// Select All Checkbox Logic
document.addEventListener('DOMContentLoaded', function() {
  $('#selectAll').on('change', function() {
    $('.dt-checkboxes').prop('checked', $(this).prop('checked'));
  });
  
  // Also uncheck selectAll if any individual checkbox is unchecked
  $(document).on('change', '.dt-checkboxes', function() {
    if ($('.dt-checkboxes:checked').length === $('.dt-checkboxes').length) {
      $('#selectAll').prop('checked', true);
    } else {
      $('#selectAll').prop('checked', false);
    }
  });
});


</script>
@endsection
