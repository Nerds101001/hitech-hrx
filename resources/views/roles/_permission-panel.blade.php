@php
  $isBuiltIn = in_array($selectedRole->name, Constants::BuiltInRoles);
  $role = $selectedRole;
  $lockedReason = $isSuperRole
    ? __('Admin has full access to everything - this can\'t be removed.')
    : __('Built into the :role role - it has this access by role, so it can\'t be unticked here.', ['role' => $role->display_name]);
@endphp

<form id="rpForm" onsubmit="return false" autocomplete="off">
  <input type="hidden" name="id" value="{{ $role->id }}">

  {{-- Role header --}}
  <div class="rp-panel-head">
    <div class="rp-panel-head-left">
      <span class="stat-icon-wrap icon-teal rp-panel-icon"><i class="bx {{ $isBuiltIn ? 'bx-crown' : 'bx-shield-quarter' }}"></i></span>
      <div>
        <div class="rp-name-row">
          @if($isBuiltIn)
            <input type="text" id="rpRoleName" name="name" class="rp-name-input" value="{{ $role->name }}" readonly
                   data-bs-toggle="tooltip" data-bs-placement="bottom" title="System role names can't be changed.">
            <i class="bx bx-lock-alt text-muted" style="font-size: .9rem;" data-bs-toggle="tooltip" title="Protected system role"></i>
          @else
            <input type="text" id="rpRoleName" name="name" class="rp-name-input" value="{{ $role->name }}">
            <i class="bx bx-pencil text-muted" style="font-size: .85rem;"></i>
          @endif
          @if($isBuiltIn)
            <span class="badge bg-label-success rounded-pill px-3">System</span>
          @else
            <span class="badge bg-label-warning rounded-pill px-3">Custom</span>
          @endif
        </div>
        <input type="text" id="rpRoleDescription" name="description" class="rp-desc-input"
               value="{{ $role->description }}" placeholder="@lang('Add a short description for this role…')">
      </div>
    </div>
    <div class="rp-panel-head-right">
      <div class="rp-panel-stat">
        <div class="rp-panel-stat-value">{{ $role->users_count }}</div>
        <div class="rp-panel-stat-label">@lang('Users')</div>
      </div>
      <span class="badge bg-label-success rounded-pill px-3">@lang('Active')</span>
      <span class="text-muted small">@lang('Updated') {{ $role->updated_at?->format('d M Y, h:i A') }}</span>
      @if(!$isBuiltIn)
        <button type="button" class="btn btn-icon btn-label-danger rp-delete-role" data-id="{{ $role->id }}" data-bs-toggle="tooltip" title="Delete role">
          <i class="bx bx-trash"></i>
        </button>
      @endif
    </div>
  </div>

  {{-- The two questions this page answers, labelled side by side, so a 0
       in one never reads as contradicting the other. Pages come from the
       live route/menu rules for this role; action permissions are the
       boxes in the table below and update as you tick. --}}
  <div class="rp-headline">
    <div class="rp-headline-item">
      <div class="rp-headline-value">{{ $reachability['visibleCount'] }} of {{ $reachability['totalCount'] }}</div>
      <div class="rp-headline-label">@lang('Pages this role can open')</div>
      <div class="rp-headline-sub">@lang('Sidebar sections — includes pages open to every signed-in person')</div>
    </div>
    <div class="rp-headline-item">
      <div class="rp-headline-value"><span id="rpHeadPerms"></span> of {{ $totalPermissionsCount }}</div>
      <div class="rp-headline-label">@lang('Action permissions')</div>
      <div class="rp-headline-sub">@lang('Approve, export, manage… — the boxes in the table below')</div>
    </div>
  </div>

  {{-- Tabs --}}
  <div class="rp-tabs">
    <div class="rp-tab rp-tab-active" data-tab="modules">@lang('Module / Feature Permissions')</div>
    <div class="rp-tab" data-tab="advanced">@lang('Advanced Settings')</div>
    <div class="rp-tab" data-tab="users">@lang('Assigned Users') ({{ $role->users_count }})</div>
  </div>

  {{-- Module / Feature Permissions tab --}}
  <div id="rpTabModules">
    <div class="rp-toolbar">
      <div class="rp-toolbar-inner">
        <div class="search-wrapper-hitech rp-search">
          <i class='bx bx-search ms-2 text-muted'></i>
          <input type="text" id="rpSearch" class="form-control border-0 shadow-none" placeholder="@lang('Search modules or permissions…')">
        </div>

        @if(!empty($rolePresets))
        <div class="hitech-input-group-glass rp-preset-picker">
          <span class="input-group-text"><i class="bx bx-magic-wand"></i></span>
          <select id="rpPreset" class="form-select">
            <option value="">@lang('Apply a template…')</option>
            @foreach($rolePresets as $presetKey => $preset)
              <option value="{{ $presetKey }}"
                      data-permissions="{{ implode(',', $preset['permissions'] ?? []) }}"
                      title="{{ $preset['description'] ?? '' }}">{{ $preset['label'] ?? $presetKey }}</option>
            @endforeach
          </select>
        </div>
        @endif

        <button type="button" class="btn btn-sm btn-outline-secondary rp-tool-btn" id="rpExpandAll"><i class="bx bx-expand-alt me-1"></i>@lang('Expand All')</button>
        <button type="button" class="btn btn-sm btn-outline-secondary rp-tool-btn" id="rpCollapseAll"><i class="bx bx-collapse-alt me-1"></i>@lang('Collapse All')</button>

        <div class="rp-legend">
          <span class="rp-legend-item"><span class="rp-mark-box rp-mark-yes"><i class="bx bx-check"></i></span>@lang('Granted')</span>
          <span class="rp-legend-item"><span class="rp-mark-box rp-mark-view"><i class="bx bx-check"></i></span>@lang('Page access')</span>
          <span class="rp-legend-item"><span class="rp-mark-box rp-mark-partial"><i class="bx bx-minus"></i></span>@lang('Partial')</span>
          <span class="rp-legend-item"><span class="rp-mark-box rp-mark-no"><i class="bx bx-x"></i></span>@lang('Not granted')</span>
        </div>

        <div class="rp-toolbar-divider"></div>

        <label class="rp-admin-toggle" for="rpGrantAll">
          <div class="hitech-toggle-wrapper">
            <input type="checkbox" class="hitech-switch-input" id="rpGrantAll" autocomplete="off" {{ $isSuperRole ? 'disabled' : '' }}>
            <label class="hitech-switch-label" for="rpGrantAll"></label>
          </div>
          @lang('Grant Full Administrative Access')
        </label>

        <p id="rpPresetHint" class="mb-0"></p>
      </div>
    </div>

    @if(count($lockedPermissionNames))
      <div class="rp-locked-note">
        <i class="bx bx-lock-alt"></i>
        @if($isSuperRole)
          @lang('Admin has full access to everything, so every box is ticked and locked.')
        @else
          @lang(':count boxes are locked: the :role role has that access by role in the code, so unticking them wouldn\'t remove it. The rest can be ticked or unticked as normal.', ['count' => count($lockedPermissionNames), 'role' => $role->display_name])
        @endif
      </div>
    @endif

    @php
      // Module -> [boxicon, colour], same as the User Special Permissions page.
      $moduleIcons = [
        'Dashboard' => ['bxs-home', '#2563eb'], 'Announcements' => ['bxs-megaphone', '#9333ea'],
        'Employees' => ['bxs-user-detail', '#0d9488'], 'Attendance' => ['bxs-time-five', '#2563eb'],
        'Leaves' => ['bxs-calendar-minus', '#f59e0b'], 'Travel & Expense' => ['bxs-plane-alt', '#f97316'],
        'Payroll' => ['bxs-wallet', '#9333ea'], 'Recruitment & Onboarding' => ['bxs-user-plus', '#2563eb'],
        'Job Postings' => ['bxs-briefcase', '#0d9488'], 'Job Applications' => ['bxs-file', '#6366f1'],
        'Job Categories & Stages' => ['bxs-layer', '#8b5cf6'], 'Interview & Screening' => ['bxs-chat', '#06b6d4'],
        'Hierarchy & Probation' => ['bx-git-branch', '#64748b'], 'Sales Field Ops' => ['bxs-map', '#e11d48'],
        'Telephony' => ['bxs-phone-call', '#16a34a'], 'CCare Attendance' => ['bxs-headphone', '#0ea5e9'],
        'Intelligent Vault' => ['bxs-lock-alt', '#475569'], 'Asset Management' => ['bxs-box', '#ca8a04'],
        'Monitoring' => ['bxs-map-pin', '#dc2626'], 'Kingo Bingo' => ['bxs-trophy', '#f59e0b'],
        'Performance' => ['bx-line-chart', '#2563eb'], 'Loans & Advances' => ['bxs-bank', '#0d9488'],
        'Reports' => ['bxs-report', '#f97316'], 'Settings & Administration' => ['bxs-cog', '#475569'],
        'Configuration Masters' => ['bxs-data', '#6366f1'], 'Operations' => ['bxs-wrench', '#64748b'],
        'System Utilities' => ['bxs-server', '#475569'], 'Training' => ['bxs-graduation', '#9333ea'],
      ];
    @endphp

    <p class="rp-table-note"><i class="bx bx-info-circle"></i>@lang('Open a module to tick or untick its permissions, then Save. Greyed boxes are built into this role and can\'t be removed here.')</p>

    <div class="rp-matrix-wrap">
      <div class="table-responsive">
        <table class="rp-mod-table">
          <thead>
            <tr>
              <th>@lang('Module / Feature')</th>
              <th>@lang('Role Permission')</th>
              <th class="text-center">@lang('Select All')</th>
            </tr>
          </thead>
          <tbody>
            @foreach($permissionMatrix as $moduleName => $m)
              @php
                $cells = [];
                foreach (['view', 'create', 'edit', 'delete'] as $verb) {
                  if ($m['crud'][$verb]) $cells[$m['crud'][$verb]['name']] = $m['crud'][$verb]['label'];
                }
                $cells += $m['other'];
                $section = $moduleSection[$moduleName] ?? null;
                $icon = $moduleIcons[$moduleName] ?? ['bxs-folder', '#64748b'];
              @endphp
              <tr class="rp-mod-row" data-module="{{ $m['slug'] }}" data-search="{{ strtolower($moduleName) }}"
                  data-section="{{ $section }}" data-section-open="{{ ($section && isset($openSections[$section])) ? 1 : 0 }}">
                <td>
                  <div class="rp-mod-name">
                    <span class="rp-mod-icon" style="background: {{ $icon[1] }}1a; color: {{ $icon[1] }};"><i class="bx {{ $icon[0] }}"></i></span>
                    <span class="rp-mod-title">{{ $moduleName }}</span>
                    <i class="bx bx-chevron-down rp-mod-chev"></i>
                  </div>
                </td>
                <td class="rp-mod-mark"></td>
                <td class="text-center">
                  <label class="hitech-checkbox rp-crud-check justify-content-center m-0" title="@lang('Tick every permission in this module')">
                    <input type="checkbox" class="rp-module-select-all" data-module="{{ $m['slug'] }}">
                    <span class="checkmark"></span>
                  </label>
                </td>
              </tr>
              <tr class="rp-mod-detail d-none" data-detail-for="{{ $m['slug'] }}" data-search="{{ strtolower(implode(' ', $cells)) }}">
                <td colspan="3">
                  <div class="rp-perm-grid">
                    @foreach($cells as $permName => $label)
                      @php
                        $isLocked = in_array($permName, $lockedPermissionNames);
                        $isUnwired = !($wiredPermissions[$permName] ?? true);
                      @endphp
                      <label class="rp-perm-item {{ $isLocked ? 'rp-locked' : '' }}" @if($isLocked) title="{{ $lockedReason }}" @endif>
                        <input type="checkbox"
                               class="rp-permission-checkbox {{ $m['slug'] }}-checkbox"
                               data-module="{{ $m['slug'] }}"
                               name="permissions[]"
                               value="{{ $permName }}"
                               {{ in_array($permName, $rolePermissionNames) ? 'checked' : '' }}
                               {{ $isLocked ? 'disabled' : '' }}>
                        <span class="rp-perm-text">
                          <span class="rp-perm-name">{{ $label }}</span>
                          <span class="rp-perm-meta">
                            @if($isLocked) @lang('Built into this role')
                            @elseif($isUnwired) @lang('No effect yet — nothing in the app checks this')
                            @endif
                          </span>
                        </span>
                        @if($isLocked)<i class="bx bx-lock-alt rp-lock-icon"></i>@endif
                      </label>
                    @endforeach
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- Advanced Settings tab --}}
  <div id="rpTabAdvanced" class="d-none">
    <div class="hitech-card rp-channels-card">
      <div class="rp-channels-head">
        <span class="stat-icon-wrap icon-blue rp-module-icon"><i class="bx bx-devices"></i></span>
        <div>
          <h6 class="rp-module-title mb-0">@lang('Access Channels')</h6>
          <span class="text-muted" style="font-size: .72rem;">@lang('How this role is allowed to sign in and be tracked')</span>
        </div>
      </div>
      <div class="rp-channels-body">
        <div class="rp-tile-grid-channels">
          <label class="rp-tile">
            <div class="hitech-toggle-wrapper rp-tile-switch">
              <input type="checkbox" class="hitech-switch-input" id="rpMultiCheckIn" name="isMultiCheckInEnabled" autocomplete="off" data-actual="{{ $role->is_multiple_check_in_enabled ? 1 : 0 }}" {{ $role->is_multiple_check_in_enabled ? 'checked' : '' }}>
              <label class="hitech-switch-label" for="rpMultiCheckIn"></label>
            </div>
            <span class="rp-tile-label">@lang('Multi Check-In')</span>
          </label>
          <label class="rp-tile">
            <div class="hitech-toggle-wrapper rp-tile-switch">
              <input type="checkbox" class="hitech-switch-input" id="rpMobileApp" name="mobileAppAccess" autocomplete="off" data-actual="{{ $role->is_mobile_app_access_enabled ? 1 : 0 }}" {{ $role->is_mobile_app_access_enabled ? 'checked' : '' }}>
              <label class="hitech-switch-label" for="rpMobileApp"></label>
            </div>
            <span class="rp-tile-label">@lang('Mobile App')</span>
          </label>
          <label class="rp-tile">
            <div class="hitech-toggle-wrapper rp-tile-switch">
              <input type="checkbox" class="hitech-switch-input" id="rpWebLogin" name="webAppAccess" autocomplete="off" data-actual="{{ $role->is_web_access_enabled ? 1 : 0 }}" {{ $role->is_web_access_enabled ? 'checked' : '' }}>
              <label class="hitech-switch-label" for="rpWebLogin"></label>
            </div>
            <span class="rp-tile-label">@lang('Web Login')</span>
          </label>
          <label class="rp-tile">
            <div class="hitech-toggle-wrapper rp-tile-switch">
              <input type="checkbox" class="hitech-switch-input" id="rpGpsTracking" name="locationActivityTracking" autocomplete="off" data-actual="{{ $role->is_location_activity_tracking_enabled ? 1 : 0 }}" {{ $role->is_location_activity_tracking_enabled ? 'checked' : '' }}>
              <label class="hitech-switch-label" for="rpGpsTracking"></label>
            </div>
            <span class="rp-tile-label">@lang('GPS Tracking')</span>
          </label>
        </div>
      </div>
    </div>
  </div>

  {{-- Assigned Users tab --}}
  <div id="rpTabUsers" class="d-none">
    <div class="hitech-card p-4">
      <div class="row g-3">
        @forelse($role->users as $u)
          <div class="col-md-4">
            <div class="d-flex align-items-center p-2 rounded" style="background: #fff; border: 1px solid #f1f5f9;">
              <div class="avatar avatar-sm me-3">
                @if($u->profile_picture)
                  <img class="rounded-circle" src="{{ $u->getProfilePicture() }}" alt="" loading="lazy" decoding="async">
                @else
                  <span class="avatar-initial rounded-circle bg-label-primary">{{ $u->getInitials() }}</span>
                @endif
              </div>
              <div>
                <span class="d-block fw-bold text-heading small">{{ $u->getFullName() }}</span>
                <span class="text-muted" style="font-size: .72rem;">{{ $u->email }}</span>
              </div>
            </div>
          </div>
        @empty
          <div class="col-12 text-center py-4 text-muted">@lang('No users have this role yet.')</div>
        @endforelse
      </div>
    </div>
  </div>

  {{-- Footer actions --}}
  <div class="rp-save-bar">
    <div class="rp-save-summary"><strong id="rpSaveCount">{{ count($rolePermissionNames) }}</strong> @lang('of') {{ $totalPermissionsCount }} @lang('permissions selected')</div>
    <div class="d-flex align-items-center gap-3">
      <button type="button" class="btn btn-label-secondary px-4" id="rpDiscardBtn">@lang('Discard')</button>
      <div class="hitech-input-group-glass rp-copy-picker">
        <span class="input-group-text"><i class="bx bx-copy"></i></span>
        <select id="rpCopyFromRole" class="form-select">
          <option value="">@lang('Copy from role…')</option>
          @foreach($roles as $r)
            @if($r->id !== $role->id)
              <option value="{{ $r->id }}">{{ $r->display_name }}</option>
            @endif
          @endforeach
        </select>
      </div>
      <button type="button" class="btn btn-outline-primary" data-bs-target="#previewAccessModal" data-bs-toggle="modal" id="rpPreviewBtn">
        <i class="bx bx-show-alt me-1"></i> @lang('Preview Access')
      </button>
      <button type="button" id="rpSaveBtn" class="btn btn-primary btn-hitech-glow px-5">
        <i class="bx bx-check-double me-1"></i> @lang('Save Role Permissions')
      </button>
    </div>
  </div>
</form>
