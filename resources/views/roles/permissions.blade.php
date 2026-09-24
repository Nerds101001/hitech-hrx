@php
  $configData = Helper::appClasses();
  $isBuiltIn = in_array($role->name, Constants::BuiltInRoles);
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Configure ' . $role->display_name)

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
    ])
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
    ])
@endsection

@section('page-script')
  @vite([
    'resources/assets/js/app/role-permissions.js',
    ])
@endsection

@section('content')
<style>
/* ── Role Permissions workbench — scoped to this page only ───────────── */
.rp-page { padding-bottom: 1rem; }

.rp-hero { padding: 2rem 3rem; min-height: auto; align-items: flex-start; }
.rp-back-link {
  color: rgba(255,255,255,.75); font-size: .75rem; font-weight: 700; text-decoration: none;
  display: inline-flex; align-items: center; gap: 6px; letter-spacing: .04em; text-transform: uppercase;
  transition: color .15s;
}
.rp-back-link:hover { color: #fff; }

.rp-name-row { display: flex; align-items: center; gap: 10px; margin-top: .35rem; }
.rp-name-input {
  background: transparent; border: none; border-bottom: 2px solid transparent;
  color: #fff; font-size: 1.75rem; font-weight: 800; letter-spacing: -0.02em;
  padding: 2px 4px; max-width: 420px; border-radius: 4px; transition: all .2s;
}
.rp-name-input:hover:not([readonly]) { border-bottom-color: rgba(255,255,255,.35); }
.rp-name-input:focus { outline: none; border-bottom-color: #fff; background: rgba(255,255,255,.08); }
.rp-name-input[readonly] { cursor: default; }
.rp-name-lock { color: rgba(255,255,255,.55); font-size: 1rem; flex-shrink: 0; }

.rp-desc-input {
  display: block; background: transparent; border: none; border-bottom: 1px dashed transparent;
  color: rgba(255,255,255,.8); font-size: .82rem; font-weight: 500; font-style: italic;
  padding: 3px 4px; margin-top: .5rem; max-width: 440px; width: 100%; border-radius: 4px; transition: all .2s;
}
.rp-desc-input::placeholder { color: rgba(255,255,255,.45); font-style: italic; }
.rp-desc-input:hover { border-bottom-color: rgba(255,255,255,.3); }
.rp-desc-input:focus { outline: none; border-bottom-color: #fff; background: rgba(255,255,255,.08); }

.rp-toolbar {
  position: sticky; top: .75rem; z-index: 20;
  background: rgba(248,250,252,.94); backdrop-filter: blur(10px);
  border: 1px solid var(--hitech-border); border-radius: 16px;
  padding: .85rem 1.25rem; margin: 1.5rem 0; box-shadow: 0 8px 24px rgba(0,0,0,.05);
}
.rp-toolbar-inner { display: flex; align-items: center; gap: .85rem; flex-wrap: wrap; }
.rp-search { flex: 1 1 240px; max-width: 300px; }
.rp-search input { font-size: .85rem; }
.rp-preset-picker { flex: 0 0 auto; width: 230px; }
.rp-preset-picker .form-select { font-size: .8rem; font-weight: 600; }
.rp-toolbar-divider { width: 1px; align-self: stretch; background: var(--hitech-border); margin: 0 .35rem; }
.rp-admin-toggle {
  display: flex; align-items: center; gap: .6rem; font-size: .78rem; font-weight: 700;
  color: #127464; cursor: pointer; margin-left: auto; white-space: nowrap;
}
#rpPresetHint { flex-basis: 100%; font-size: .75rem; color: #64748b; margin: -.25rem 0 0; }

.rp-legend { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
.rp-legend-item { display: flex; align-items: center; gap: 6px; font-size: .72rem; font-weight: 700; color: #64748b; white-space: nowrap; }
.rp-legend-swatch { width: 15px; height: 15px; border-radius: 5px; border: 2px solid #d8dfe4; background: #fff; flex-shrink: 0; }
.rp-legend-swatch.rp-legend-granted { background: #127464; border-color: #127464; position: relative; }
.rp-legend-swatch.rp-legend-granted::after {
  content: ''; position: absolute; left: 4px; top: 1px; width: 4px; height: 8px;
  border: solid #fff; border-width: 0 2px 2px 0; transform: rotate(45deg);
}
.rp-legend-swatch.rp-legend-partial { background: rgba(245,166,35,.15); border-color: #f5a623; }

.rp-body { display: flex; align-items: flex-start; gap: 1.5rem; }

.rp-rail { width: 250px; flex-shrink: 0; position: sticky; top: 5.5rem; max-height: calc(100vh - 7rem); overflow-y: auto; padding-bottom: 1rem; }
.rp-rail-inner { display: flex; flex-direction: column; gap: 2px; }
.rp-rail-item {
  display: flex; align-items: center; gap: 10px; padding: 7px 12px; border-radius: 10px;
  text-decoration: none; color: #475569; font-size: .78rem; font-weight: 600; transition: all .15s;
}
.rp-rail-item:hover { background: #fff; color: #127464; }
.rp-rail-item.rp-active { background: var(--hitech-primary-light); color: #127464; }
.rp-rail-dot { width: 6px; height: 6px; border-radius: 50%; background: #cbd5e1; flex-shrink: 0; transition: background .2s; }
.rp-rail-item[data-all-selected="1"] .rp-rail-dot { background: #127464; }
.rp-rail-name { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.rp-rail-count {
  font-size: .62rem; font-weight: 800; color: #94a3b8; background: #eef2f4;
  padding: 1px 7px; border-radius: 20px; transition: all .2s;
}
.rp-rail-count.rp-count-full { background: #127464; color: #fff; }
.rp-rail-count.rp-count-partial { background: rgba(18,116,100,.15); color: #127464; }

.rp-main { flex: 1; min-width: 0; }

.rp-matrix-wrap { background: #fff; border: 1px solid var(--hitech-border); border-radius: 20px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,.02); }
.rp-matrix-table { width: 100%; margin: 0; border-collapse: collapse; }
.rp-matrix-table thead th {
  background: rgba(18,116,100,.03); border: none; padding: 12px 14px; color: #127464;
  font-weight: 700; font-size: .68rem; text-transform: uppercase; letter-spacing: .06em; white-space: nowrap;
}
.rp-matrix-table thead th.text-center { text-align: center; }
.rp-matrix-table tbody tr { border-top: 1px solid #f1f5f9; scroll-margin-top: 5.5rem; transition: background .15s; }
.rp-matrix-table tbody tr:hover { background: #fbfdfd; }
.rp-matrix-table td { padding: 12px 14px; vertical-align: middle; }
.rp-matrix-module { display: flex; align-items: center; gap: 10px; min-width: 180px; }
.rp-module-icon { margin-bottom: 0 !important; flex-shrink: 0; width: 32px !important; height: 32px !important; font-size: 1rem !important; }
.rp-module-title { font-size: .84rem; font-weight: 800; color: #1e293b; }
.rp-crud-cell { text-align: center; width: 64px; }
.rp-matrix-na { color: #d8dfe4; font-size: 1rem; }

.rp-other-cell { min-width: 260px; }
.rp-other-chips { display: flex; flex-wrap: wrap; gap: 6px 14px; }
.rp-chip { display: inline-flex; align-items: center; gap: 6px; cursor: pointer; user-select: none; margin: 0; }
.rp-chip .chip-box {
  position: relative; width: 16px; height: 16px; border-radius: 5px; flex-shrink: 0;
  background: rgba(18,116,100,.05); border: 1.5px solid rgba(18,116,100,.25); transition: all .15s;
}
.rp-chip .chip-box::after {
  content: ''; position: absolute; display: none; left: 4px; top: 1px; width: 4px; height: 8px;
  border: solid #fff; border-width: 0 2px 2px 0; transform: rotate(45deg);
}
.rp-chip input { position: absolute; opacity: 0; width: 0; height: 0; }
.rp-chip input:checked ~ .chip-box { background: #127464; border-color: #127464; }
.rp-chip input:checked ~ .chip-box::after { display: block; }
.rp-chip-label { font-size: .76rem; font-weight: 600; color: #475569; }
.rp-chip-unwired .rp-chip-label { color: #94a3b8; }
.rp-unwired-dot { color: #f5a623; font-size: .8rem; cursor: help; }

.hitech-checkbox.rp-crud-check .checkmark { width: 17px; height: 17px; margin-right: 0; }

.rp-hidden-by-search { display: none !important; }

.rp-channels-card { margin-top: 1.25rem; }
.rp-channels-head { display: flex; align-items: center; gap: 12px; padding: 1.1rem 1.4rem; }
.rp-channels-body { padding: .2rem 1.4rem 1.3rem; border-top: 1px solid #f1f5f9; }
.rp-tile-grid-channels { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 4px 18px; padding-top: 1rem; }
.rp-tile { display: flex; align-items: center; gap: 10px; padding: 8px; border-radius: 10px; cursor: pointer; transition: background .15s; }
.rp-tile:hover { background: #f8fafc; }
.rp-tile-switch { flex-shrink: 0; transform: scale(.8); transform-origin: left center; }
.rp-tile-label { font-size: .81rem; font-weight: 600; color: #334155; }

.rp-save-bar {
  position: sticky; bottom: 1rem; z-index: 25; margin-top: 1.5rem;
  background: #fff; border: 1px solid var(--hitech-border); border-radius: 16px;
  box-shadow: 0 12px 34px rgba(0,0,0,.1); padding: 1rem 1.5rem;
  display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;
}
.rp-save-summary { font-size: .85rem; color: #64748b; }
.rp-save-summary strong { color: #127464; font-size: 1.1rem; }

@media (max-width: 992px) {
  .rp-rail { display: none; }
  .rp-toolbar-inner { flex-direction: column; align-items: stretch; }
  .rp-admin-toggle { margin-left: 0; }
  .rp-preset-picker { width: 100%; }
}
</style>

<div class="rp-page">
  <form id="rpForm" onsubmit="return false">
    <input type="hidden" name="id" value="{{ $role->id }}">

    {{-- Hero --}}
    <div class="hitech-page-hero rp-hero animate__animated animate__fadeIn">
      <div class="hitech-page-hero-text">
        <a href="{{ route('roles.index') }}" class="rp-back-link"><i class="bx bx-arrow-back"></i> @lang('All Roles')</a>
        <div class="rp-name-row">
          @if($isBuiltIn)
            <input type="text" id="rpRoleName" name="name" class="rp-name-input" value="{{ $role->name }}" readonly
                   data-bs-toggle="tooltip" data-bs-placement="bottom" title="System role names can't be changed.">
            <i class="bx bx-lock-alt rp-name-lock" data-bs-toggle="tooltip" title="Protected system role"></i>
          @else
            <input type="text" id="rpRoleName" name="name" class="rp-name-input" value="{{ $role->name }}">
            <i class="bx bx-pencil rp-name-lock" style="font-size: .9rem;"></i>
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
      <div class="emp-hero-meta">
        <div class="hero-quick-stat">
          <div class="stat-value" id="rpSelectedCount">{{ count($rolePermissionNames) }}</div>
          <div class="stat-label">@lang('of') {{ $totalPermissionsCount }}</div>
        </div>
        <div class="hero-quick-stat">
          <div class="stat-value">{{ $role->users()->count() }}</div>
          <div class="stat-label">@lang('Users')</div>
        </div>
      </div>
    </div>

    {{-- Sticky toolbar --}}
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
          <span class="rp-legend-item"><span class="rp-legend-swatch rp-legend-partial"></span> @lang('Partial (some in module)')</span>
        </div>

        <div class="rp-toolbar-divider"></div>

        <label class="rp-admin-toggle" for="rpGrantAll">
          <div class="hitech-toggle-wrapper">
            <input type="checkbox" class="hitech-switch-input" id="rpGrantAll">
            <label class="hitech-switch-label" for="rpGrantAll"></label>
          </div>
          @lang('Grant Full Administrative Access')
        </label>

        <p id="rpPresetHint" class="mb-0"></p>
      </div>
    </div>

    <div class="rp-body">
      {{-- Left rail --}}
      <aside class="rp-rail">
        <div class="rp-rail-inner">
          @foreach($permissionMatrix as $moduleName => $m)
            @php
              $moduleTotal = count(array_filter($m['crud'])) + count($m['other']);
            @endphp
            <a href="#rp-module-{{ $m['slug'] }}" class="rp-rail-item" data-target="rp-module-{{ $m['slug'] }}">
              <span class="rp-rail-dot"></span>
              <span class="rp-rail-name">{{ $moduleName }}</span>
              <span class="rp-rail-count">0/{{ $moduleTotal }}</span>
            </a>
          @endforeach
          <a href="#rp-module-access-channels" class="rp-rail-item" data-target="rp-module-access-channels">
            <span class="rp-rail-dot" style="background: #4d6cf0;"></span>
            <span class="rp-rail-name">@lang('Access Channels')</span>
            <span class="rp-rail-count">&nbsp;</span>
          </a>
        </div>
      </aside>

      {{-- CRUD-style permission matrix --}}
      <main class="rp-main">
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
                  @php $moduleTotal = count(array_filter($m['crud'])) + count($m['other']); @endphp
                  <tr id="rp-module-{{ $m['slug'] }}" data-module="{{ $m['slug'] }}" data-search="{{ strtolower($moduleName) }}">
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

        {{-- Access Channels (device / login / tracking toggles) --}}
        <div class="hitech-card rp-channels-card" id="rp-module-access-channels" data-search="access channels device mobile web gps tracking login">
          <div class="rp-channels-head">
            <span class="stat-icon-wrap icon-blue rp-module-icon"><i class="bx bx-devices"></i></span>
            <div>
              <h6 class="rp-module-title mb-0">@lang('Access Channels')</h6>
              <span class="text-muted" style="font-size: .72rem;">@lang('How this role is allowed to sign in and be tracked')</span>
            </div>
          </div>
          <div class="rp-channels-body">
            <div class="rp-tile-grid-channels">
              <label class="rp-tile" data-search="multi check in">
                <div class="hitech-toggle-wrapper rp-tile-switch">
                  <input type="checkbox" class="hitech-switch-input" id="rpMultiCheckIn" name="isMultiCheckInEnabled" {{ $role->is_multiple_check_in_enabled ? 'checked' : '' }}>
                  <label class="hitech-switch-label" for="rpMultiCheckIn"></label>
                </div>
                <span class="rp-tile-label">@lang('Multi Check-In')</span>
              </label>
              <label class="rp-tile" data-search="mobile app">
                <div class="hitech-toggle-wrapper rp-tile-switch">
                  <input type="checkbox" class="hitech-switch-input" id="rpMobileApp" name="mobileAppAccess" {{ $role->is_mobile_app_access_enabled ? 'checked' : '' }}>
                  <label class="hitech-switch-label" for="rpMobileApp"></label>
                </div>
                <span class="rp-tile-label">@lang('Mobile App')</span>
              </label>
              <label class="rp-tile" data-search="web login">
                <div class="hitech-toggle-wrapper rp-tile-switch">
                  <input type="checkbox" class="hitech-switch-input" id="rpWebLogin" name="webAppAccess" {{ $role->is_web_access_enabled ? 'checked' : '' }}>
                  <label class="hitech-switch-label" for="rpWebLogin"></label>
                </div>
                <span class="rp-tile-label">@lang('Web Login')</span>
              </label>
              <label class="rp-tile" data-search="gps tracking location">
                <div class="hitech-toggle-wrapper rp-tile-switch">
                  <input type="checkbox" class="hitech-switch-input" id="rpGpsTracking" name="locationActivityTracking" {{ $role->is_location_activity_tracking_enabled ? 'checked' : '' }}>
                  <label class="hitech-switch-label" for="rpGpsTracking"></label>
                </div>
                <span class="rp-tile-label">@lang('GPS Tracking')</span>
              </label>
            </div>
          </div>
        </div>
      </main>
    </div>

    {{-- Sticky save bar --}}
    <div class="rp-save-bar">
      <div class="rp-save-summary"><strong id="rpSaveCount">{{ count($rolePermissionNames) }}</strong> @lang('permissions selected')</div>
      <div>
        <a href="{{ route('roles.index') }}" class="btn btn-label-secondary px-4 me-3">@lang('Discard')</a>
        <button type="button" id="rpSaveBtn" class="btn btn-primary btn-hitech-glow px-5">
          <i class="bx bx-check-double me-1"></i> @lang('Save Role Permissions')
        </button>
      </div>
    </div>
  </form>
</div>
@endsection
