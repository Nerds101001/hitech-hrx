@php
  $isBuiltIn = in_array($selectedRole->name, Constants::BuiltInRoles);
  $role = $selectedRole;
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

  {{-- PRIMARY: what this role can actually reach, computed live from the
       real route/menu rules - not a manually-ticked list, so it can never
       drift from reality. This is the answer to "what can this role do",
       full stop; the granular checkboxes further down are a different,
       narrower thing (see the section below). --}}
  <div class="rp-reach-section">
    <div class="rp-reach-head">
      <div>
        <h6 class="rp-reach-title">@lang('What :role Can Actually Reach', ['role' => $role->display_name])</h6>
        <p class="rp-reach-sub">@lang('Computed live from the real route and menu rules for this role — not a manual list, so it can\'t go out of sync.')</p>
      </div>
      <div class="rp-reach-stat">
        <div class="rp-reach-stat-value">{{ $reachability['visibleCount'] }} / {{ $reachability['totalCount'] }}</div>
        <div class="rp-reach-stat-label">@lang('sections reachable')</div>
      </div>
    </div>
    <div class="rp-reach-grid">
      @foreach($reachability['tree'] as $item)
        <div class="pmt-item rp-reach-item {{ $item['visible'] ? 'pmt-visible' : 'pmt-hidden' }}">
          <div class="pmt-row">
            <div class="pmt-icon"><i class="{{ $item['icon'] ?? 'bx bx-square' }}"></i></div>
            <div class="pmt-name">{{ $item['name'] }}</div>
            <span class="pmt-badge {{ $item['visible'] ? 'pmt-badge-on' : 'pmt-badge-off' }}">{{ $item['visible'] ? 'VISIBLE' : 'HIDDEN' }}</span>
          </div>
          @if(!$item['visible'] && $item['reason'])
            <div class="pmt-reason">{{ $item['reason'] }}</div>
          @endif
        </div>
      @endforeach
    </div>
  </div>

  {{-- SECONDARY: manual permission overrides. Deliberately demoted and
       collapsed - these are individual action-level grants (approve this,
       export that, manage the other) within pages the role can already
       open per the section above; they never control whether a page opens
       at all. Easy to mistake for the same thing as the reachability
       list above if it isn't visually clear these are different questions. --}}
  <button type="button" class="rp-advanced-toggle" id="rpAdvancedToggle">
    <i class="bx bx-chevron-right"></i>
    @lang('Advanced: Granular Action Permissions')
    <span class="text-muted fw-normal">— @lang('individual actions within pages this role can already open, not page access itself')</span>
  </button>

  <div id="rpAdvancedSection" class="d-none">
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

        <div class="rp-legend">
          <span class="rp-legend-item"><span class="rp-legend-swatch rp-legend-granted"></span> @lang('Granted')</span>
          <span class="rp-legend-item"><span class="rp-legend-swatch"></span> @lang('Not granted')</span>
          <span class="rp-legend-item"><span class="rp-legend-swatch rp-legend-partial"></span> @lang('Partial')</span>
        </div>

        <div class="rp-toolbar-divider"></div>

        <label class="rp-admin-toggle" for="rpGrantAll">
          <div class="hitech-toggle-wrapper">
            <input type="checkbox" class="hitech-switch-input" id="rpGrantAll" autocomplete="off">
            <label class="hitech-switch-label" for="rpGrantAll"></label>
          </div>
          @lang('Grant Full Administrative Access')
        </label>

        <p id="rpPresetHint" class="mb-0"></p>
      </div>
    </div>

    <div class="rp-matrix-wrap">
      <div class="table-responsive">
        <table class="rp-matrix-table">
          <thead>
            <tr>
              <th>@lang('Module / Feature')</th>
              <th class="text-center">@lang('View')</th>
              <th class="text-center">@lang('Create')</th>
              <th class="text-center">@lang('Edit')</th>
              <th class="text-center">@lang('Delete')</th>
              <th class="text-center">@lang('All')</th>
              <th>@lang('Other Permissions')</th>
            </tr>
          </thead>
          <tbody>
            @foreach($permissionMatrix as $moduleName => $m)
              <tr data-module="{{ $m['slug'] }}" data-search="{{ strtolower($moduleName) }}">
                <td>
                  <div class="rp-matrix-module">
                    <span class="stat-icon-wrap icon-teal rp-module-icon"><i class="bx bx-folder"></i></span>
                    <span class="rp-module-title">{{ $moduleName }}</span>
                  </div>
                </td>
                @foreach(['view','create','edit','delete'] as $verb)
                  <td class="rp-crud-cell">
                    @if($m['crud'][$verb])
                      <label class="hitech-checkbox rp-crud-check justify-content-center m-0"
                             data-bs-toggle="tooltip" title="{{ $m['crud'][$verb]['label'] }}">
                        <input type="checkbox"
                               class="rp-permission-checkbox {{ $m['slug'] }}-checkbox"
                               data-module="{{ $m['slug'] }}"
                               name="permissions[]"
                               value="{{ $m['crud'][$verb]['name'] }}"
                               {{ in_array($m['crud'][$verb]['name'], $rolePermissionNames) ? 'checked' : '' }}>
                        <span class="checkmark"></span>
                      </label>
                    @else
                      <span class="rp-matrix-na">—</span>
                    @endif
                  </td>
                @endforeach
                <td class="rp-crud-cell">
                  <label class="hitech-checkbox rp-crud-check justify-content-center m-0">
                    <input type="checkbox" class="rp-module-select-all" data-module="{{ $m['slug'] }}">
                    <span class="checkmark"></span>
                  </label>
                </td>
                <td class="rp-other-cell">
                  @if(count($m['other']))
                    <div class="rp-other-chips">
                      @foreach($m['other'] as $permName => $label)
                        <label class="rp-chip {{ !($wiredPermissions[$permName] ?? true) ? 'rp-chip-unwired' : '' }}" data-search="{{ strtolower($label) }}">
                          <input type="checkbox"
                                 class="rp-permission-checkbox {{ $m['slug'] }}-checkbox"
                                 data-module="{{ $m['slug'] }}"
                                 name="permissions[]"
                                 value="{{ $permName }}"
                                 {{ in_array($permName, $rolePermissionNames) ? 'checked' : '' }}>
                          <span class="chip-box"></span>
                          <span class="rp-chip-label">{{ $label }}</span>
                          @unless($wiredPermissions[$permName] ?? true)
                            <i class="bx bx-info-circle rp-unwired-dot" data-bs-toggle="tooltip" data-bs-placement="top"
                               title="@lang('Not checked anywhere in the app yet — granting this currently has no effect.')"></i>
                          @endunless
                        </label>
                      @endforeach
                    </div>
                  @else
                    <span class="rp-matrix-na">—</span>
                  @endif
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
                  <img class="rounded-circle" src="{{ $u->getProfilePicture() }}" alt="">
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
  </div>{{-- /rpAdvancedSection --}}
</form>
