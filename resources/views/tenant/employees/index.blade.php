@extends('layouts/layoutMaster')

@section('title', 'Employees')

<!-- Vendor Styles -->
@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
    'resources/assets/vendor/libs/select2/select2.scss',
    'resources/assets/vendor/libs/@form-validation/form-validation.scss',
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
  ])
@endsection

<!-- Vendor Scripts -->
@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/moment/moment.js',
    'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
    'resources/assets/vendor/libs/select2/select2.js',
    'resources/assets/vendor/libs/@form-validation/popular.js',
    'resources/assets/vendor/libs/@form-validation/bootstrap5.js',
    'resources/assets/vendor/libs/@form-validation/auto-focus.js',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
  ])
@endsection

<!-- Page Scripts -->
@section('page-script')
  @vite([
    'resources/js/main-helper.js',
    'resources/js/main-select2.js'
  ])
<script>
/**
 * Polling for jQuery - Ensures the script runs AFTER the Vite module bundle is ready.
 */
(function checkJQuery() {
  if (typeof jQuery !== 'undefined' && typeof $.fn.dataTable !== 'undefined') {
    $(function () {
      const dt_user_table = $('#directoryTable');
      const employeeViewBase = "{{ route('employees.show', '') }}/";

      const statusObj = {
        active: { title: 'Active', class: 'bg-label-success' },
        inactive: { title: 'Inactive', class: 'bg-label-danger' },
        suspended: { title: 'Suspended', class: 'bg-label-danger' },
        blocked: { title: 'Blocked', class: 'bg-label-danger' },
        onboarding: { title: 'Onboarding', class: 'bg-label-warning' },
        onboarding_submitted: { title: 'Review Required', class: 'bg-label-warning' },
        relieved: { title: 'Relieved', class: 'bg-label-danger' },
        terminated: { title: 'Terminated', class: 'bg-label-danger' },
        default: { title: 'Unknown', class: 'bg-label-secondary' }
      };

      // Redraw on filter change
      $('#roleFilter, #teamFilter, #designationFilter, #statusFilter').on('change', function () {
        if (window.dt_user) window.dt_user.ajax.reload();
      });

      // DataTable Init
      if (dt_user_table.length) {
        window.dt_user = dt_user_table.DataTable({
          processing: true,
          serverSide: true,
          ajax: {
            url: "{{ route('employees.indexAjax') }}",
            type: 'GET',
            data: function (d) {
              d.roleFilter = $('#roleFilter').val();
              d.teamFilter = $('#teamFilter').val();
              d.designationFilter = $('#designationFilter').val();
              d.statusFilter = $('#statusFilter').val();
            }
          },
          columns: [
            { data: 'name' },
            { data: 'code' },
            { data: 'team' },
            { data: 'designation' },
            { data: 'status' },
            { data: 'joined' },
            { data: '' }
          ],
          columnDefs: [
            {
              targets: 0, // Name & Avatar
              render: function (data, type, full, meta) {
                var $name = full['name'] || 'Unknown';
                var $email = full['email'] || '';
                var initials = $name.match(/\b\w/g) || [];
                var $initials = ((initials.shift() || '') + (initials.pop() || '')).toUpperCase();
                var $output = full['profile_picture'] ? `<img src="${full['profile_picture']}" alt="Avatar" class="avatar rounded-circle" />` : `<span class="avatar-initial-hitech">${$initials}</span>`;
                return `
                  <div class="d-flex justify-content-start align-items-center user-name">
                    <div class="avatar-wrapper"><div class="avatar avatar-sm me-3">${$output}</div></div>
                    <div class="d-flex flex-column">
                      <a href="${employeeViewBase + full['id']}" class="text-heading text-truncate"><span class="fw-medium mb-0" style="font-size: 0.875rem;">${$name}</span></a>
                      <small class="text-muted" style="font-size: 0.75rem;">${$email}</small>
                    </div>
                  </div>`;
              }
            },
            {
              targets: 1, // Employee ID
              render: function (data, type, full, meta) {
                return `<span class="badge badge-code-hitech">${full['code'] || 'N/A'}</span>`;
              }
            },
            {
              targets: 2, // Department
              render: function (data, type, full, meta) { return `<span class="text-body">${full['team'] || 'N/A'}</span>`; }
            },
            {
              targets: 3, // Designation
              render: function (data, type, full, meta) { return `<span class="text-body">${full['designation'] || 'N/A'}</span>`; }
            },
            {
              targets: 4, // Status
              render: function (data, type, full, meta) {
                var $status = full['status'];
                var statusInfo = statusObj[$status] || statusObj['default'];
                var badgeClass = ($status === 'active') ? 'bg-success-light text-success' : 'bg-teal-light text-teal';
                if (['inactive', 'relieved', 'terminated'].includes($status)) badgeClass = 'bg-danger-light text-danger';
                return `<span class="badge ${badgeClass} rounded-pill px-3 py-1 fw-bold">${statusInfo.title}</span>`;
              }
            },
            {
              targets: 5, // Joined
              render: function (data, type, full, meta) {
                var date = full['joined'];
                if (!date || date === 'N/A') return '<span class="text-body">N/A</span>';
                return `<span class="text-body">${moment(date).format('DD MMM, YYYY')}</span>`;
              }
            },
            {
              targets: 6, // Actions
              searchable: false, orderable: false,
              render: function (data, type, full, meta) {
                var unlockBtn = full['is_security_locked'] 
                  ? `<a class="icon-sophisticated unlock-security" data-id="${full['id']}" href="javascript:;" title="Remove Security Lock"><i class="bx bx-lock-open-alt text-success"></i></a>` 
                  : '';
                return `
                  <div class="d-flex align-items-center justify-content-center gap-2">
                    <a class="icon-sophisticated view" href="${employeeViewBase + full['id']}" title="View"><i class="bx bx-show"></i></a>
                    ${unlockBtn}
                    <a class="icon-sophisticated reset-password" data-id="${full['id']}" data-name="${full['name']}" data-phone="${full['official_phone'] || full['phone'] || ''}" href="javascript:;" title="Reset Password"><i class="bx bx-key"></i></a>
                  </div>`;
              }
            }
          ],
          order: [[0, 'asc']],
          dom: 'rtip',
          responsive: false,
          language: {
            sLengthMenu: '_MENU_',
            paginate: { next: '<i class="bx bx-chevron-right bx-18px"></i>', previous: '<i class="bx bx-chevron-left bx-18px"></i>' }
          }
        });

        // Search & Length
        $('#customSearchBtn').on('click', () => window.dt_user.search($('#customSearchInput').val()).draw());
        $('#customSearchInput').on('keyup', (e) => { if (e.key === 'Enter') window.dt_user.search($('#customSearchInput').val()).draw(); });
        $('#customLengthMenu').on('change', function() { window.dt_user.page.len($(this).val()).draw(); });
      }

      // Password Reset Listener (Using Bootstrap Modal to match Leave Request format)
      $(document).on('click', '.reset-password', function (e) {
        e.preventDefault();
        const user_id = $(this).data('id');
        const userName = $(this).data('name') || 'Employee';
        const userPhone = $(this).data('phone') || '';
        
        // Formula: Ucfirst(Name[:4]) + @ + Phone[-4] (Fallback for missing phone)
        let firstName = userName.split(' ')[0].trim() || 'User';
        let phoneToUse = userPhone && userPhone !== 'N/A' && userPhone !== 'null' && userPhone !== '' ? String(userPhone) : '12345678';
        let lastFour = phoneToUse.slice(-4);
        let passwordPreview = firstName.charAt(0).toUpperCase() + firstName.slice(1, 4).toLowerCase() + '@' + lastFour;

        $('#displayResetPassword').text(passwordPreview);
        $('#confirmResetBtn').data('id', user_id);
        $('#hitechResetPasswordModal').modal('show');
      });

      // Confirm Reset Action
      $('#confirmResetBtn').on('click', function() {
        const user_id = $(this).data('id');
        const btn = $(this);
        
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Processing...');

        $.ajax({
          type: 'POST',
          url: "{{ route('employees.resetPasswordAjax') }}",
          data: { id: user_id, _token: '{{ csrf_token() }}' },
          success: function (response) {
            $('#hitechResetPasswordModal').modal('hide');
            btn.prop('disabled', false).text('Confirm Reset');
            
            // Handle AppData response format
            const msg = (response.data && response.data.message) ? response.data.message : (response.message || 'Password reset successfully');

            Swal.fire({ 
              icon: 'success', 
              title: 'Success!', 
              text: msg, 
              customClass: { confirmButton: 'btn btn-success' } 
            });
          },
          error: function (error) {
            $('#hitechResetPasswordModal').modal('hide');
            btn.prop('disabled', false).text('Confirm Reset');
            
            Swal.fire({ 
              icon: 'error', 
              title: 'Error!', 
              text: 'Something went wrong.', 
              customClass: { confirmButton: 'btn btn-danger' } 
            });
          }
        });
      });

      // Security Unlock Listener
      $(document).on('click', '.unlock-security, .security-unlock', function(e) {
        e.preventDefault();
        const user_id = $(this).data('id');
        
        Swal.fire({
          title: 'Remove Security Lock?',
          text: "This will immediately restore access to the employee's account by clearing failed login attempts.",
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#008080',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Yes, Unlock it!'
        }).then((result) => {
          if (result.isConfirmed) {
            $.ajax({
              type: 'POST',
              url: "{{ route('employees.unlockSecurityAjax') }}",
              data: { id: user_id, _token: '{{ csrf_token() }}' },
              success: function (response) {
                Swal.fire('Unlocked!', response.message, 'success')
                .then(() => { if (window.dt_user) window.dt_user.ajax.reload(); else window.location.reload(); });
              },
              error: function (error) {
                Swal.fire('Error!', error.responseJSON?.message || 'Failed to remove lock.', 'error');
              }
            });
          }
        });
      });

    });
  } else {
    setTimeout(checkJQuery, 100);
  }
})();
</script>
@endsection


@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">
  {{-- Integrated Header & Toggle --}}
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6 mx-4">
    <h3 class="fw-extrabold mb-0 text-dark">Employee Directory</h3>
    <div class="d-flex gap-3 flex-wrap">
      @if(auth()->user()->can('user-create') || auth()->user()->hasRole('hr'))
        <div class="dropdown">
          <button class="btn btn-hitech shadow-sm rounded-pill px-5 dropdown-toggle hide-arrow d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bx bx-paper-plane"></i>
            <span class="fw-bold">Onboarding & Bulk Action</span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end dropdown-menu-hitech shadow-lg">
            <li><a class="dropdown-item dropdown-item-hitech d-flex align-items-center gap-2 py-2" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#onboardingInviteModalV2"><i class="bx bx-user-plus text-primary"></i>Invite Candidate</a></li>
            <li><a class="dropdown-item dropdown-item-hitech d-flex align-items-center gap-2 py-2" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#bulkImportModal"><i class="bx bx-upload text-success"></i>Bulk Import (CSV)</a></li>
            <li><a class="dropdown-item dropdown-item-hitech d-flex align-items-center gap-2 py-2" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#bulkExportModal"><i class="bx bx-download text-info"></i>Bulk Export</a></li>
            <li><hr class="dropdown-divider my-2"></li>
            <li><a class="dropdown-item dropdown-item-hitech d-flex align-items-center gap-2 py-2" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#downloadTemplateModal"><i class="bx bx-file text-warning"></i>Download Template</a></li>
          </ul>
        </div>

        <a href="{{ route('employees.create') }}" class="btn-hitech shadow-sm rounded-pill px-5 d-flex align-items-center gap-2">
          <i class="bx bx-plus-circle fs-5"></i>
          <span class="fw-bold">Add Employee</span>
        </a>
      @endif
    </div>
  </div>

  {{-- Premium Stats Bar --}}
  <div class="row g-6 mb-6 mx-0 px-4">
    <div class="col-xl-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay: 0.05s">
      <div class="hitech-stat-card dashboard-variant card-teal uniform-card clickable-stat" onclick="applyStatusFilter('all')">
        <div class="stat-card-header mb-2">
          <div class="stat-icon-wrap icon-teal" style="width:38px; height:38px; font-size:1.2rem;"><i class="bx bx-group"></i></div>
          <div class="trend-indicator text-success" style="font-size:0.65rem; padding: 2px 6px;"><i class="bx bx-trending-up me-1"></i>+4%</div>
        </div>
        <div>
          <h3 class="stat-value mb-0">{{ $totalUser }}</h3>
          <div class="d-flex justify-content-between align-items-center">
            <span class="stat-label">Total Team</span>
            <span class="badge bg-label-success rounded-pill" style="font-size: 0.55rem;">+1 New</span>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay: 0.1s">
      <div class="hitech-stat-card dashboard-variant card-blue uniform-card clickable-stat" onclick="applyStatusFilter('active')">
        <div class="stat-card-header mb-2">
          <div class="stat-icon-wrap icon-blue" style="width:38px; height:38px; font-size:1.2rem;"><i class="bx bx-check-circle"></i></div>
          <div class="trend-indicator text-primary" style="font-size:0.65rem; padding: 2px 6px;"><i class="bx bx-check-circle me-1"></i>98%</div>
        </div>
        <div>
          <h3 class="stat-value mb-0">{{ $active }}</h3>
          <div class="d-flex justify-content-between align-items-center">
            <span class="stat-label">Active Now</span>
            <span class="text-danger fw-extrabold" style="font-size: 0.65rem;">0 Absent</span>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay: 0.15s">
      <div class="hitech-stat-card dashboard-variant card-amber uniform-card clickable-stat" onclick="applyStatusFilter('onboarding')">
        <div class="stat-card-header mb-2">
          <div class="stat-icon-wrap icon-amber" style="width:38px; height:38px; font-size:1.2rem;"><i class="bx bx-paper-plane"></i></div>
          <div class="trend-indicator text-warning" style="font-size:0.65rem; padding: 2px 6px;">{{ $onboardingCount }} Today</div>
        </div>
        <div>
          <h3 class="stat-value mb-0">{{ $onboardingCount }}</h3>
          <span class="stat-label">In Onboarding</span>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
      <div class="hitech-stat-card dashboard-variant card-red uniform-card clickable-stat" onclick="applyStatusFilter('relieved')">
        <div class="stat-card-header mb-2">
          <div class="stat-icon-wrap icon-red" style="width:38px; height:38px; font-size:1.2rem;"><i class="bx bx-user-x"></i></div>
          <div class="trend-indicator text-danger" style="font-size:0.65rem; padding: 2px 6px;">Archived</div>
        </div>
        <div>
          <h3 class="stat-value mb-0" style="color: #dc2626;">{{ $relieved }}</h3>
          <span class="stat-label">Relieved</span>
        </div>
      </div>
    </div>
  </div>

  <div class="px-4" id="filter-section">
    <div class="hitech-card-white mb-6 overflow-hidden">
      <div class="card-body p-sm-5 p-4">
        <div class="row align-items-center g-6">
          {{-- Search & Filters Toggle --}}
          <div class="col-lg-7 d-flex flex-wrap align-items-center gap-3">
            <div class="search-wrapper-hitech w-px-400 mw-100">
              <i class="bx bx-search text-muted ms-3"></i>
              <input type="text" class="form-control" placeholder="Search..." id="customSearchInput">
              <button class="btn-search shadow-sm" id="customSearchBtn">
                <i class="bx bx-search fs-5"></i><span>Search</span>
              </button>
            </div>
            <button class="btn btn-white shadow-sm rounded-pill px-4 border d-flex justify-content-center align-items-center gap-2" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFilters">
              <i class="bx bx-filter-alt text-muted"></i>
              <span class="fw-semibold">Filters</span>
            </button>
          </div>

          {{-- View Toggle & Per Page --}}
          <div class="col-lg-5 d-flex flex-wrap align-items-center justify-content-lg-end gap-3 mt-3 mt-lg-0">
            <div class="view-toggle-hitech shadow-sm">
              <button class="btn-toggle active" onclick="toggleView('list')" id="list-toggle-btn">
                <i class="bx bx-list-ul"></i>
              </button>
              <button class="btn-toggle" onclick="toggleView('card')" id="card-toggle-btn">
                <i class="bx bx-grid-alt"></i>
              </button>
            </div>

            <div class="d-flex align-items-center gap-3">
              <span class="text-muted fw-semibold small">Per Page:</span>
              <select class="form-select w-px-80 rounded-pill border-light shadow-none fw-bold" id="customLengthMenu">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
              </select>
            </div>
          </div>
        </div>

        <div class="collapse" id="advancedFilters">
          <div class="row g-6 pt-5 mt-4 border-top">
            <div class="col-md-3">
              <label class="form-label fw-bold small text-muted text-uppercase mb-2">Role</label>
              <select class="form-select select2" id="roleFilter">
                <option value="">All Roles</option>
                @foreach($roles as $role)
                  <option value="{{ $role->name }}">{{ $role->display_name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold small text-muted text-uppercase mb-2">Department (Team)</label>
              <select class="form-select select2" id="teamFilter">
                <option value="">All Departments</option>
                @foreach($teams as $team)
                  <option value="{{ $team->id }}">{{ $team->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold small text-muted text-uppercase mb-2">Designation</label>
              <select class="form-select select2" id="designationFilter">
                <option value="">All Designations</option>
                @foreach($designations as $designation)
                  <option value="{{ $designation->id }}">{{ $designation->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold small text-muted text-uppercase mb-2">Status</label>
              <select class="form-select select2" id="statusFilter">
                <option value="">All Statuses</option>
                <option value="active">Active</option>
                <option value="onboarding">Onboarding</option>
                <option value="onboarding_submitted">Review Required</option>
                <option value="inactive">Inactive</option>
                <option value="relieved">Relieved</option>
              </select>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Users List Table (List View) --}}
  <div id="list-view-container" class="animate__animated animate__fadeIn px-4">
    <div class="hitech-card-white p-0 overflow-hidden">
      <div class="card-datatable table-responsive">
        <table id="directoryTable" class="table m-0 shadow-none border-0">
          <thead class="text-dark">
          <tr>
            <th>Name</th>
            <th>Employee ID</th>
            <th>Department</th>
            <th>Designation</th>
            <th>Employee Status</th>
            <th>Joined</th>
            <th class="text-center">Actions</th>
          </tr>
          </thead>
        </table>
        {{-- Extra spacing --}}
        <div class="py-4"></div>
      </div>
    </div>
  </div>

  <!-- Users Grid (Card View) -->
  <div id="card-view-container" style="display: none;" class="animate__animated animate__fadeIn px-4">
    <div class="row g-6">
      @forelse($users as $user)
        <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
          <div class="hitech-employee-card-centered shadow-sm">
            {{-- Action Dropdown (Top Left - Dots) --}}
            <div class="card-dots">
                <div class="dropdown">
                  <button class="btn btn-icon btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bx bx-dots-vertical-rounded fs-5 text-muted"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-hitech shadow-lg">
                    @php
                      $isLocked = in_array($user->status->value, ['inactive', 'suspended', 'blocked']);
                      $lockLabel = $isLocked ? 'Unlock Account' : 'Lock Account';
                      $lockIcon = $isLocked ? 'bx-lock-open-alt' : 'bx-lock-alt';
                      $isSecurityLocked = ($user->locked_until && $user->locked_until->isFuture());
                    @endphp
                    <li><a class="dropdown-item dropdown-item-hitech reset-password" href="javascript:;" data-id="{{ $user->id }}" data-name="{{ $user->name }}" data-phone="{{ $user->official_phone ?? $user->phone }}"><i class="bx bx-key me-2 text-warning"></i>Reset Password</a></li>
                    @if($isSecurityLocked)
                      <li><a class="dropdown-item dropdown-item-hitech security-unlock" href="javascript:;" data-id="{{ $user->id }}"><i class="bx bx-lock-open-alt me-2 text-success"></i>Unlock Security</a></li>
                    @endif
                    <li><hr class="dropdown-divider mx-3"></li>
                    <li><a class="dropdown-item dropdown-item-hitech toggle-status-record" href="javascript:;" data-id="{{ $user->id }}"><i class="bx {{ $lockIcon }} me-2 text-danger"></i>{{ $lockLabel }}</a></li>
                  </ul>
                </div>
            </div>

            {{-- Status Badge (Top Right) --}}
            <div class="card-status-badge">
              @php
                $statusValue = $user->status->value;
                $displayLabel = ($statusValue === 'onboarding_submitted') ? 'Review Required' : $user->status->label();
                $statusClass = match($statusValue) {
                    'active' => 'bg-success',
                    'onboarding_submitted' => 'bg-warning text-dark',
                    'relieved', 'terminated' => 'bg-danger',
                    default => 'bg-info text-dark'
                };
              @endphp
              <span class="badge {{ $statusClass }} rounded-pill px-3 py-1 shadow-xs fw-bold" style="font-size: 0.6rem; letter-spacing: 0.03em;">{{ $displayLabel }}</span>
            </div>

            {{-- Avatar Section (Centered) --}}
            <div class="avatar-wrap">
                @if(method_exists($user, 'getProfilePicture') && $user->getProfilePicture())
                  <img src="{{ $user->getProfilePicture() }}" alt="Avatar" class="avatar-img shadow-sm">
                @else
                  <div class="avatar-img d-flex align-items-center justify-content-center bg-light text-teal fw-bold fs-4 shadow-sm border">
                    {{ strtoupper(substr($user->first_name ?? 'U', 0, 1)) }}
                  </div>
                @endif
            </div>

            {{-- Basic Info --}}
            <h6 class="emp-name text-truncate w-100" title="{{ $user->full_name }}">{{ $user->full_name }}</h6>
            <div class="emp-id">#{{ $user->code ?? 'N/A' }}</div>
            <div class="emp-email" title="{{ $user->email }}">{{ $user->email }}</div>

            {{-- Details Sub-Box (Grid Style) --}}
            <div class="card-detail-group">
                <div class="card-detail-item">
                  <span class="label">Department</span>
                  <span class="value">{{ $user->team?->name ?? 'No Team' }}</span>
                </div>
                <div class="card-detail-item">
                  <span class="label">Official Phone</span>
                  <span class="value">{{ $user->official_phone ?? ($user->phone ?? 'N/A') }}</span>
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="card-action-bar">
              <a href="javascript:;" class="btn-card-action btn-card-reset reset-password" data-id="{{ $user->id }}" data-name="{{ $user->name }}" data-phone="{{ $user->official_phone ?? $user->phone }}">
                <i class="bx bx-key fs-6"></i>Reset
              </a>
              <a href="{{ route('employees.show', $user->id) }}" class="btn-card-action btn-card-view">
                <i class="bx bx-show fs-6"></i>View
              </a>
            </div>
          </div>
        </div>
      @empty
        <div class="col-12 text-center py-10">
          <p class="text-muted">No employees found.</p>
        </div>
      @endforelse
    </div>
    
    <!-- Pagination -->
    <div class="mt-8 d-flex justify-content-center hitech-pagination">
      {{ $users->links('pagination::bootstrap-5') }}
    </div>
  </div>

</div> {{-- End layout-full-width --}}

@include('tenant.employees.onboarding_invite_modal')

<!-- Reset Password Modal (Format Matched to Leave Request) -->
<div class="modal fade" id="hitechResetPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content modal-content-hitech">
            <div class="modal-header modal-header-hitech">
                <div class="d-flex align-items-center">
                    <div class="modal-icon-header me-3">
                        <i class="bx bx-key fs-3"></i>
                    </div>
                    <h5 class="modal-title modal-title-hitech">Reset Password?</h5>
                </div>
                <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" aria-label="Close">
                    <i class="bx bx-x"></i>
                </button>
            </div>
            <div class="modal-body modal-body-hitech text-center py-6 px-5">
                <div class="p-6 mb-6 border animate__animated animate__pulse" style="background: rgba(0, 128, 128, 0.06); border: 1px dashed rgba(0, 128, 128, 0.3) !important; border-radius: 2rem !important;">
                    <p class="text-muted mb-3 small fw-bold text-uppercase" style="letter-spacing: 1.5px; opacity: 0.8;">The New Password Will Be:</p>
                    <h1 class="text-primary fw-extrabold mb-0" id="displayResetPassword" style="letter-spacing: 3px; font-size: 2.5rem; text-shadow: 0 2px 4px rgba(0,128,128,0.1);">---</h1>
                </div>
                <p class="text-muted small px-4">
                    <i class="bx bx-info-circle me-1"></i>
                    The user will automatically receive a secure email notification with these new credentials.
                </p>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 mt-2">
                <button type="button" class="btn btn-outline-secondary px-6 rounded-pill fw-bold" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-hitech px-8 rounded-pill fw-bold" id="confirmResetBtn">Confirm Reset</button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Import Modal -->
<div class="modal fade" id="bulkImportModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <h5 class="modal-title modal-title-hitech d-flex align-items-center gap-3">
           <i class="bx bx-upload fs-4"></i>
           Bulk Import Candidates
        </h5>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal">
           <i class="bx bx-x"></i>
        </button>
      </div>
      <div class="modal-body p-6">
        <form id="bulkImportForm">
          <div class="mb-6">
            <label class="form-label-hitech">Select CSV File</label>
            <input type="file" id="importFile" class="form-control form-control-hitech" required accept=".csv">
            <div class="alert alert-info mt-3 small rounded-4 border-0" style="background: rgba(0, 128, 128, 0.05); color: #004D4D;">
              <strong>Correct Format:</strong> Employee ID, First Name, Last Name, Email, Phone, Role ... <br>
              <a href="{{ route('employees.downloadOnboardingTemplate') }}" class="fw-bold text-decoration-underline" style="color: #008080;">Download Template</a>
            </div>
          </div>

          <div class="d-flex justify-content-end gap-3 mt-4">
            <button type="button" class="btn btn-label-danger px-6 rounded-pill fw-bold" data-bs-dismiss="modal">Cancel</button>
            <button type="button" onclick="previewImport()" class="btn btn-hitech px-8 fw-bold rounded-pill" id="previewBtn">Preview List</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- NEW: Bulk Preview Modal (Wide & Premium) -->
<div class="modal fade" id="bulkPreviewModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content modal-content-hitech border-0 shadow-2xl">
      <div class="modal-header modal-header-hitech">
        <h5 class="modal-title modal-title-hitech d-flex align-items-center gap-3">
           <div class="bg-white bg-opacity-20 rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
             <img src="{{ asset('assets/img/logo-white.png') }}" alt="Logo" style="height: 28px; width: auto;">
           </div>
           <div>
             <span class="d-block fw-bold text-white">Review Import Queue</span>
             <small class="text-white text-opacity-75 fs-xs fw-normal">Validate candidates before sending invitations</small>
           </div>
        </h5>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal">
           <i class="bx bx-x"></i>
        </button>
      </div>
      <div class="modal-body p-0">
        {{-- Clean Stats Bar (No Grey) --}}
        <div class="preview-stats d-flex align-items-center gap-5 px-8 py-4 border-bottom">
            <div class="d-flex align-items-center gap-2">
              <span class="text-muted small fw-bold text-uppercase">Total:</span>
              <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1-5 rounded-pill fw-bold" id="totalRows">0</span>
            </div>
            <div class="d-flex align-items-center gap-2">
              <span class="text-muted small fw-bold text-uppercase">Valid:</span>
              <span class="badge bg-success bg-opacity-10 text-success px-3 py-1-5 rounded-pill fw-bold" id="validRows">0</span>
            </div>
            <div class="d-flex align-items-center gap-2">
              <span class="text-muted small fw-bold text-uppercase">Errors:</span>
              <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-1-5 rounded-pill fw-bold" id="invalidRows">0</span>
            </div>
        </div>
        
        <div class="table-responsive" style="max-height: 480px;">
          <table class="table table-hover align-middle mb-0" id="previewTable">
            <thead class="sticky-top bg-white" style="z-index: 10;">
              <tr class="border-bottom">
                <th class="ps-8 py-4 text-muted small fw-bold text-uppercase" style="letter-spacing: 0.05em;">Status</th>
                <th class="py-4 text-muted small fw-bold text-uppercase" style="letter-spacing: 0.05em;">Employee ID</th>
                <th class="py-4 text-muted small fw-bold text-uppercase" style="letter-spacing: 0.05em;">Name</th>
                <th class="py-4 text-muted small fw-bold text-uppercase" style="letter-spacing: 0.05em;">Identity</th>
                <th class="py-4 text-muted small fw-bold text-uppercase" style="letter-spacing: 0.05em;">Validation Details</th>
              </tr>
            </thead>
            <tbody>
              <!-- Rows injected via JS -->
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer px-8 py-5 border-top justify-content-between">
        <div class="text-muted small">
          <i class="bx bx-info-circle me-1"></i>
          Only <span class="fw-bold text-success">Valid</span> candidates will receive invitations.
        </div>
        <div class="d-flex gap-3">
          <button type="button" class="btn btn-label-danger px-8 rounded-pill fw-bold" data-bs-dismiss="modal">Cancel</button>
          <button type="button" onclick="confirmImport()" id="confirmBtn" class="btn btn-hitech px-10 fw-bold rounded-pill shadow-sm" style="min-width: 250px;">
            <i class="bx bx-mail-send me-2 fs-5"></i>Confirm & Send Invitations
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Bulk Export Modal -->
<div class="modal fade" id="bulkExportModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <h5 class="modal-title modal-title-hitech d-flex align-items-center gap-3">
           <i class="bx bx-download fs-4"></i>
           Export Directory
        </h5>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal">
           <i class="bx bx-x"></i>
        </button>
      </div>
      <div class="modal-body p-6">
        <p class="text-muted">Generate a full export of your current employee directory in CSV format.</p>
        <div class="d-flex justify-content-end gap-3 mt-6">
          <button type="button" class="btn btn-label-secondary px-6" data-bs-dismiss="modal">Cancel</button>
          <a href="{{ route('employees.bulkExportOnboarding') }}" class="btn btn-hitech px-8 fw-bold rounded-pill">Export Now</a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Download Template Modal -->
<div class="modal fade" id="downloadTemplateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <h5 class="modal-title modal-title-hitech d-flex align-items-center gap-3">
           <i class="bx bx-file fs-4"></i>
           Get Import Template
        </h5>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal">
           <i class="bx bx-x"></i>
        </button>
      </div>
      <div class="modal-body p-6 text-center">
        <div class="hitech-icon-wrapper mx-auto mb-4" style="width: 60px; height: 60px; background: rgba(0, 128, 128, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #008080;">
          <i class="bx bx-spreadsheet fs-2"></i>
        </div>
        <h6>Import Template (CSV)</h6>
        <p class="text-muted small">Download the correctly formatted CSV template to ensure a smooth bulk onboarding process.</p>
        <div class="d-flex justify-content-center gap-3 mt-6">
          <button type="button" class="btn btn-label-secondary px-6" data-bs-dismiss="modal">Close</button>
          <a href="{{ route('employees.downloadOnboardingTemplate') }}" class="btn btn-hitech px-8 fw-bold rounded-pill">Download CSV</a>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  let sessionCandidates = [];

  window.applyStatusFilter = function(status) {
    const statusSelect = $('#statusFilter');
    if (status === 'all') {
      statusSelect.val('').trigger('change');
    } else {
      statusSelect.val(status).trigger('change');
    }
    
    // Smooth scroll to table if not in view
    document.querySelector('.datatables-users').scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  window.previewImport = function() {
    const fileInput = document.getElementById('importFile');
    if (!fileInput.files[0]) {
      alert('Please select a CSV file.');
      return;
    }

    const btn = document.getElementById('previewBtn');
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Analyzing...';
    btn.disabled = true;

    const formData = new FormData();
    formData.append('file', fileInput.files[0]);
    formData.append('_token', '{{ csrf_token() }}');

    fetch("{{ route('employees.validateBulkOnboarding') }}", {
      method: "POST",
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      btn.innerHTML = 'Preview List';
      btn.disabled = false;
      
      if (data.success) {
        sessionCandidates = data.data;
        renderPreview(data);
        const modalEl = document.getElementById('bulkImportModal');
        const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        modal.hide();
        new bootstrap.Modal(document.getElementById('bulkPreviewModal')).show();
      } else {
        alert('Validation failed: ' + data.message);
      }
    })
    .catch(err => {
      btn.innerHTML = 'Preview List';
      btn.disabled = false;
      alert('Error analyzing file.');
    });
  }

  function renderPreview(res) {
    document.getElementById('totalRows').innerText = res.total;
    document.getElementById('validRows').innerText = res.valid_count;
    document.getElementById('invalidRows').innerText = res.total - res.valid_count;

    const tbody = document.querySelector('#previewTable tbody');
    tbody.innerHTML = '';

    res.data.forEach(item => {
      const row = document.createElement('tr');
      row.className = 'border-bottom';
      if (!item.is_valid) row.style.backgroundColor = 'rgba(255, 62, 29, 0.04)';
      
      const statusIcon = item.is_valid 
        ? '<div class="bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center mx-auto" style="width: 32px; height: 32px;"><i class="bx bx-check fs-5"></i></div>' 
        : '<div class="bg-danger bg-opacity-10 text-danger rounded-circle d-flex align-items-center justify-content-center mx-auto" style="width: 32px; height: 32px;"><i class="bx bx-error fs-5"></i></div>';

      const errorTags = item.errors.map(err => `<span class="badge bg-danger rounded-pill me-1" style="font-size: 0.65rem;">${err}</span>`).join('');

      row.innerHTML = `
        <td class="ps-8 text-center">${statusIcon}</td>
        <td><span class="fw-bold text-dark">${item.code || '<span class="text-muted fw-normal">Auto-Gen</span>'}</span></td>
        <td>
          <div class="fw-bold text-heading" style="font-size: 0.9rem;">${item.first_name} ${item.last_name}</div>
          <div class="text-muted small">${item.role || 'Employee'}</div>
        </td>
        <td>
          <div class="text-dark small"><i class="bx bx-envelope me-1"></i>${item.email}</div>
          <div class="text-muted small mt-1"><i class="bx bx-phone me-1"></i>${item.phone}</div>
        </td>
        <td>${item.is_valid ? '<span class="badge bg-success bg-opacity-10 text-success px-3 rounded-pill fw-bold">Ready to Invite</span>' : errorTags}</td>
      `;
      tbody.appendChild(row);
    });

    document.getElementById('confirmBtn').disabled = res.valid_count === 0;
  }

  window.confirmImport = function() {
    const btn = document.getElementById('confirmBtn');
    const originalContent = btn.innerHTML;
    
    if (sessionCandidates.length === 0) {
      alert('No candidates found to process.');
      return;
    }

    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Sending Invitations...';
    btn.disabled = true;

    console.log('Confirming import for candidates:', sessionCandidates);

    fetch("{{ route('employees.processBulkOnboarding') }}", {
      method: "POST",
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'Accept': 'application/json'
      },
      body: JSON.stringify({ candidates: sessionCandidates })
    })
    .then(async res => {
      const data = await res.json();
      if (!res.ok) throw new Error(data.message || 'Server error occurred');
      return data;
    })
    .then(data => {
      if (data.success) {
        Swal.fire({
          title: 'Invitations Sent!',
          text: data.message,
          icon: 'success',
          confirmButtonColor: '#008080',
          confirmButtonText: 'Great, Thank You'
        }).then(() => window.location.reload());
      } else {
        throw new Error(data.message || 'Failed to process import');
      }
    })
    .catch(err => {
      console.error('Import Error:', err);
      Swal.fire({
        title: 'Import Failed',
        text: err.message,
        icon: 'error',
        confirmButtonColor: '#008080'
      });
      btn.innerHTML = originalContent;
      btn.disabled = false;
    });
  }


  window.toggleView = function(view) {
    const listView = document.getElementById('list-view-container');
    const cardView = document.getElementById('card-view-container');
    const listBtn = document.getElementById('list-toggle-btn');
    const cardBtn = document.getElementById('card-toggle-btn');

    if (view === 'list') {
      if(listView) listView.style.display = 'block';
      if(cardView) cardView.style.display = 'none';
      if(listBtn) listBtn.classList.add('active');
      if(cardBtn) cardBtn.classList.remove('active');
    } else {
      if(listView) listView.style.display = 'none';
      if(cardView) cardView.style.display = 'block';
      if(cardBtn) cardBtn.classList.add('active');
      if(listBtn) listBtn.classList.remove('active');
    }
  }
</script>
@endsection
