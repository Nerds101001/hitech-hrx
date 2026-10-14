@php
  $configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Roles - Apps')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/@form-validation/form-validation.scss',
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
    ])
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/@form-validation/popular.js',
    'resources/assets/vendor/libs/@form-validation/bootstrap5.js',
    'resources/assets/vendor/libs/@form-validation/auto-focus.js',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
    ])
@endsection

@section('page-script')
  @vite([
    'resources/assets/js/app/role-index.js',
    'resources/assets/js/app/preview-access.js',
    ])
@endsection

@section('content')
<style>
/* ── Roles & Permissions — master-detail shell, scoped to this page ──── */
.rp-shell-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem; }
.rp-shell-title { font-size: 1.5rem; font-weight: 800; color: #1e293b; margin-bottom: .2rem; }
.rp-shell-sub { color: #64748b; font-size: .88rem; margin: 0; }
.rp-shell-stats { display: flex; align-items: center; flex-wrap: wrap; gap: .75rem; }
.rp-stat-pill { display: flex; align-items: center; gap: 8px; background: #fff; border: 1px solid var(--hitech-border); border-radius: 14px; padding: .55rem 1rem; }
.rp-stat-pill i { font-size: 1.1rem; color: #127464; }
.rp-stat-pill-value { font-weight: 800; color: #1e293b; font-size: .95rem; line-height: 1; }
.rp-stat-pill-label { font-size: .65rem; color: #94a3b8; text-transform: uppercase; letter-spacing: .04em; }

.rp-shell-body { display: flex; align-items: flex-start; gap: 1.5rem; }

.rp-role-col { width: 300px; flex-shrink: 0; }
.rp-role-search { margin-bottom: .85rem; }
.rp-role-list { max-height: calc(100vh - 15rem); overflow-y: auto; display: flex; flex-direction: column; gap: 3px; padding-right: 2px; }
.rp-role-row {
  display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 12px; cursor: pointer;
  border: 1px solid transparent; transition: all .15s;
}
.rp-role-row:hover { background: #fff; border-color: var(--hitech-border); }
.rp-role-row.rp-role-active { background: var(--hitech-primary-light); border-color: rgba(18,116,100,.25); }
.rp-role-row-icon { width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: rgba(18,116,100,.1); color: #127464; flex-shrink: 0; }
.rp-role-row-info { min-width: 0; flex: 1; }
.rp-role-row-name { font-size: .85rem; font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.rp-role-row-count { font-size: .7rem; color: #94a3b8; }
.rp-role-row-badge { font-size: .6rem; font-weight: 800; border-radius: 20px; padding: 2px 8px; flex-shrink: 0; }
.rp-role-row-badge.rp-badge-system { background: #dcfce7; color: #166534; }
.rp-role-row-badge.rp-badge-custom { background: rgba(245,166,35,.15); color: #92610f; }

.rp-detail-col { flex: 1; min-width: 0; }
.rp-detail-col .hitech-card,
.rp-panel-wrap { background: #fff; border: 1px solid var(--hitech-border); border-radius: 20px; padding: 1.5rem 1.75rem; box-shadow: 0 4px 20px rgba(0,0,0,.02); }

/* Panel header */
.rp-panel-head { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 1rem; padding-bottom: 1.25rem; border-bottom: 1px solid #f1f5f9; }
.rp-panel-head-left { display: flex; align-items: center; gap: 14px; }
.rp-panel-icon { width: 46px !important; height: 46px !important; font-size: 1.3rem !important; margin-bottom: 0 !important; flex-shrink: 0; }
.rp-name-row { display: flex; align-items: center; gap: 8px; }
.rp-name-input {
  background: transparent; border: none; border-bottom: 2px solid transparent; color: #1e293b;
  font-size: 1.15rem; font-weight: 800; padding: 2px 2px; max-width: 280px; border-radius: 4px; transition: all .2s;
}
.rp-name-input:hover:not([readonly]) { border-bottom-color: #cbd5e1; }
.rp-name-input:focus { outline: none; border-bottom-color: #127464; background: #f8fafc; }
.rp-desc-input {
  display: block; background: transparent; border: none; border-bottom: 1px dashed transparent; color: #64748b;
  font-size: .8rem; padding: 2px 2px; margin-top: 3px; max-width: 340px; width: 100%; border-radius: 4px; transition: all .2s;
}
.rp-desc-input:hover { border-bottom-color: #cbd5e1; }
.rp-desc-input:focus { outline: none; border-bottom-color: #127464; background: #f8fafc; }
.rp-panel-head-right { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
.rp-panel-stat { text-align: center; }
.rp-panel-stat-value { font-size: 1.1rem; font-weight: 800; color: #1e293b; line-height: 1; }
.rp-panel-stat-label { font-size: .6rem; color: #94a3b8; text-transform: uppercase; letter-spacing: .04em; }

/* "Why is this sparse" truth banners */
.rp-truth-banner { display: flex; align-items: flex-start; gap: 10px; padding: .8rem 1rem; border-radius: 12px; margin-top: 1rem; font-size: .8rem; line-height: 1.5; }
.rp-truth-banner i { font-size: 1.15rem; flex-shrink: 0; margin-top: 1px; }
.rp-truth-banner strong { display: block; margin-bottom: 2px; }
.rp-truth-banner-info { background: rgba(77,108,240,.08); color: #364fc7; }
.rp-truth-banner-info i { color: #4d6cf0; }
.rp-truth-banner-warn { background: rgba(245,166,35,.1); color: #92610f; }
.rp-truth-banner-warn i { color: #f5a623; }
.rp-truth-banner-muted { background: #f8fafc; color: #64748b; border: 1px dashed #e2e8f0; }
.rp-truth-banner-muted i { color: #94a3b8; }
.rp-preview-link { font-size: .8rem; font-weight: 700; color: #127464 !important; text-decoration: underline; vertical-align: baseline; }

/* Primary: live reachability (computed, not manually ticked) */
.rp-reach-section { padding-top: 1.25rem; }
.rp-reach-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; }
.rp-reach-title { font-size: .98rem; font-weight: 800; color: #1e293b; margin: 0 0 .2rem; }
.rp-reach-sub { font-size: .78rem; color: #64748b; margin: 0; max-width: 480px; }
.rp-reach-stat { text-align: right; flex-shrink: 0; }
.rp-reach-stat-value { font-size: 1.4rem; font-weight: 800; color: #127464; line-height: 1; }
.rp-reach-stat-label { font-size: .65rem; color: #94a3b8; text-transform: uppercase; letter-spacing: .04em; }
.rp-reach-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 8px; }
.rp-reach-item.pmt-item { margin-bottom: 0; }
.rp-grant-btn {
  display: inline-flex; align-items: center; gap: 4px; margin-left: 8px; padding: 2px 10px;
  background: #127464; color: #fff; border: none; border-radius: 20px;
  font-size: .66rem; font-weight: 700; cursor: pointer; transition: all .15s; vertical-align: middle;
}
.rp-grant-btn:hover { background: #0d5c50; }
.rp-grant-btn.rp-grant-done { background: #dcfce7; color: #166534; cursor: default; }

/* Advanced section toggle - deliberately quiet until opened */
.rp-advanced-toggle {
  display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; margin-top: 1.75rem;
  padding: .85rem 1rem; background: #f8fafc; border: 1px dashed #e2e8f0; border-radius: 12px;
  font-size: .82rem; font-weight: 700; color: #475569; cursor: pointer; transition: all .15s;
}
.rp-advanced-toggle:hover { background: #f1f5f9; border-color: #cbd5e1; }
.rp-advanced-toggle i { font-size: 1rem; color: #94a3b8; transition: transform .15s; flex-shrink: 0; }
.rp-advanced-toggle.rp-advanced-open i { transform: rotate(90deg); }
#rpAdvancedSection { margin-top: 1rem; }

/* Tabs */
.rp-tabs { display: flex; gap: 1.5rem; margin-top: 1.1rem; border-bottom: 1px solid var(--hitech-border); }
.rp-tab { padding: 10px 2px; font-size: .84rem; font-weight: 700; color: #94a3b8; cursor: pointer; border-bottom: 2px solid transparent; transition: all .15s; }
.rp-tab.rp-tab-active { color: #127464; border-bottom-color: #127464; }

/* Toolbar */
.rp-toolbar { background: rgba(248,250,252,.9); border: 1px solid var(--hitech-border); border-radius: 14px; padding: .75rem 1rem; margin: 1.1rem 0; }
.rp-toolbar-inner { display: flex; align-items: center; gap: .85rem; flex-wrap: wrap; }
.rp-search { flex: 1 1 220px; max-width: 280px; }
.rp-search input { font-size: .85rem; }
.rp-preset-picker { flex: 0 0 auto; width: 220px; }
.rp-preset-picker .form-select { font-size: .8rem; font-weight: 600; }
.rp-toolbar-divider { width: 1px; align-self: stretch; background: var(--hitech-border); margin: 0 .25rem; }
.rp-admin-toggle { display: flex; align-items: center; gap: .6rem; font-size: .76rem; font-weight: 700; color: #127464; cursor: pointer; margin-left: auto; white-space: nowrap; }

/* Every switch on this page (Grant Full Admin + the 4 access-channel
   toggles) is painted from the .rp-switch-on class role-index.js sets,
   never from the browser's own :checked matching - see syncSwitchVisual()
   for why. !important + being declared after hitech-portal.scss in the
   document guarantees this always wins the cascade. */
.hitech-toggle-wrapper .hitech-switch-label { background-color: rgba(18, 116, 100, .1) !important; border-color: rgba(18, 116, 100, .2) !important; }
.hitech-toggle-wrapper .hitech-switch-label::before { transform: translateY(-50%) !important; background-color: #127464 !important; }
.hitech-toggle-wrapper.rp-switch-on .hitech-switch-label { background-color: #127464 !important; border-color: #127464 !important; }
.hitech-toggle-wrapper.rp-switch-on .hitech-switch-label::before { transform: translate(1.8125rem, -50%) !important; background-color: #fff !important; }
#rpPresetHint { flex-basis: 100%; font-size: .74rem; color: #64748b; margin: -.25rem 0 0; }
.rp-legend { display: flex; align-items: center; gap: .85rem; flex-wrap: wrap; }
.rp-legend-item { display: flex; align-items: center; gap: 6px; font-size: .7rem; font-weight: 700; color: #64748b; white-space: nowrap; }
.rp-legend-swatch { width: 14px; height: 14px; border-radius: 4px; border: 2px solid #d8dfe4; background: #fff; flex-shrink: 0; }
.rp-legend-swatch.rp-legend-granted { background: #127464; border-color: #127464; position: relative; }
.rp-legend-swatch.rp-legend-granted::after { content: ''; position: absolute; left: 3px; top: 0; width: 4px; height: 8px; border: solid #fff; border-width: 0 2px 2px 0; transform: rotate(45deg); }
.rp-legend-swatch.rp-legend-partial { background: rgba(245,166,35,.15); border-color: #f5a623; }

/* Matrix table */
.rp-matrix-wrap { border: 1px solid var(--hitech-border); border-radius: 16px; overflow: hidden; }
.rp-matrix-table { width: 100%; margin: 0; border-collapse: collapse; }
.rp-matrix-table thead th { background: rgba(18,116,100,.03); border: none; padding: 11px 14px; color: #127464; font-weight: 700; font-size: .66rem; text-transform: uppercase; letter-spacing: .05em; white-space: nowrap; }
.rp-matrix-table thead th.text-center { text-align: center; }
.rp-matrix-table tbody tr { border-top: 1px solid #f1f5f9; transition: background .15s; }
.rp-matrix-table tbody tr:hover { background: #fbfdfd; }
.rp-matrix-table td { padding: 11px 14px; vertical-align: middle; }
.rp-matrix-module { display: flex; align-items: center; gap: 9px; min-width: 170px; }
.rp-module-icon { margin-bottom: 0 !important; flex-shrink: 0; width: 30px !important; height: 30px !important; font-size: .95rem !important; }
.rp-module-title { font-size: .82rem; font-weight: 800; color: #1e293b; }
.rp-crud-cell { text-align: center; width: 60px; }
.rp-matrix-na { color: #d8dfe4; font-size: 1rem; }
.hitech-checkbox.rp-crud-check .checkmark { width: 16px; height: 16px; margin-right: 0; }

.rp-other-cell { min-width: 240px; }
.rp-other-chips { display: flex; flex-wrap: wrap; gap: 5px 12px; }
.rp-chip { display: inline-flex; align-items: center; gap: 6px; cursor: pointer; user-select: none; margin: 0; }
.rp-chip .chip-box { position: relative; width: 15px; height: 15px; border-radius: 5px; flex-shrink: 0; background: rgba(18,116,100,.05); border: 1.5px solid rgba(18,116,100,.25); transition: all .15s; }
.rp-chip .chip-box::after { content: ''; position: absolute; display: none; left: 3px; top: 0; width: 4px; height: 8px; border: solid #fff; border-width: 0 2px 2px 0; transform: rotate(45deg); }
.rp-chip input { position: absolute; opacity: 0; width: 0; height: 0; }
.rp-chip input:checked ~ .chip-box { background: #127464; border-color: #127464; }
.rp-chip input:checked ~ .chip-box::after { display: block; }
.rp-chip-label { font-size: .74rem; font-weight: 600; color: #475569; }
.rp-chip-unwired .rp-chip-label { color: #94a3b8; }
.rp-unwired-dot { color: #f5a623; font-size: .78rem; cursor: help; }
.rp-hidden-by-search { display: none !important; }

/* Headline: pages vs action permissions, labelled side by side */
.rp-headline { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin: 1.1rem 0 .4rem; }
.rp-headline-item { background: #f8fafc; border: 1px solid #eef2f6; border-radius: 14px; padding: .85rem 1.1rem; }
.rp-headline-value { font-size: 1.3rem; font-weight: 800; color: #127464; line-height: 1.1; }
.rp-headline-label { font-size: .74rem; font-weight: 800; color: #1e293b; margin-top: 2px; }
.rp-headline-sub { font-size: .68rem; color: #94a3b8; margin-top: 1px; }
.rp-tool-btn { font-size: .74rem; font-weight: 600; border-radius: 8px; }
.rp-table-note { display: flex; gap: 7px; align-items: flex-start; font-size: .72rem; color: #64748b; margin: 0 0 .8rem; }
.rp-table-note .bx { color: #4d6cf0; font-size: .9rem; margin-top: 1px; flex-shrink: 0; }

/* ✓ / ■ / ✗ marks */
.rp-mark { display: inline-flex; align-items: center; gap: 7px; font-size: .76rem; font-weight: 600; color: #334155; white-space: nowrap; }
.rp-mark-box { width: 16px; height: 16px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: .8rem; flex-shrink: 0; }
.rp-mark-yes { background: #16a34a; }
.rp-mark-view { background: #3b82f6; }
.rp-mark-partial { background: #f59e0b; }
.rp-mark-no { background: #ef4444; }
.rp-legend-item .rp-mark-box { margin-right: 2px; }

/* Module table */
.rp-mod-table { width: 100%; border-collapse: collapse; }
.rp-mod-table thead th { background: #f8fafc; padding: 11px 14px; color: #334155; font-weight: 700; font-size: .72rem; border-bottom: 1px solid #eef2f6; }
.rp-mod-table thead th + th { border-left: 1px solid #eef2f6; }
.rp-mod-row { border-top: 1px solid #f1f5f9; cursor: pointer; }
.rp-mod-row:hover { background: #fbfdfd; }
.rp-mod-row.rp-mod-open { background: rgba(18,116,100,.04); }
.rp-mod-row td { padding: 9px 14px; vertical-align: middle; }
.rp-mod-row td + td { border-left: 1px solid #f1f5f9; }
.rp-mod-name { display: flex; align-items: center; gap: 10px; font-size: .8rem; font-weight: 700; color: #1e293b; }
.rp-mod-title { flex: 1; }
.rp-mod-icon { width: 26px; height: 26px; border-radius: 7px; display: inline-flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }
.rp-mod-chev { color: #64748b; font-size: 1.1rem; transition: transform .15s; }
.rp-mod-row.rp-mod-open .rp-mod-chev { transform: rotate(180deg); }
.rp-mod-detail td { padding: 4px 14px 14px 50px; background: #fbfdfd; }
.rp-perm-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 8px; padding-top: 6px; }
.rp-perm-item { display: flex; align-items: flex-start; gap: 9px; margin: 0; padding: 8px 10px; background: #fff; border: 1px solid #f1f5f9; border-radius: 8px; cursor: pointer; }
.rp-perm-item:hover { border-color: rgba(18,116,100,.3); }
.rp-perm-item input { margin-top: 3px; accent-color: #127464; width: 15px; height: 15px; flex-shrink: 0; cursor: pointer; }
.rp-perm-item input:disabled { cursor: not-allowed; opacity: .6; }
.rp-perm-item.rp-locked { background: #fafbfc; }
.rp-perm-item.rp-locked:hover { border-color: #f1f5f9; }
.rp-perm-text { flex: 1; min-width: 0; }
.rp-perm-name { display: block; font-size: .76rem; font-weight: 600; color: #334155; }
.rp-perm-meta { display: block; font-size: .64rem; color: #94a3b8; }
@media (max-width: 768px) { .rp-headline { grid-template-columns: 1fr; } }
.rp-locked, .rp-locked input { cursor: not-allowed; }
.rp-locked .checkmark, .rp-locked .chip-box { opacity: .6; }
.rp-lock-icon { color: #94a3b8; font-size: .72rem; }
.rp-locked-note { display: flex; align-items: center; gap: 8px; margin: 0 0 .9rem; padding: .55rem .9rem; border-radius: 10px; background: rgba(18,116,100,.06); border: 1px solid rgba(18,116,100,.15); font-size: .76rem; color: #475569; }
.rp-locked-note .bx { color: #127464; font-size: .95rem; }

/* Access Channels (Advanced tab) */
.rp-channels-card { border: 1px solid var(--hitech-border) !important; }
.rp-channels-head { display: flex; align-items: center; gap: 12px; padding: 1.1rem 1.4rem; }
.rp-channels-body { padding: .2rem 1.4rem 1.3rem; border-top: 1px solid #f1f5f9; }
.rp-tile-grid-channels { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 4px 18px; padding-top: 1rem; }
.rp-tile { display: flex; align-items: center; gap: 10px; padding: 8px; border-radius: 10px; cursor: pointer; transition: background .15s; }
.rp-tile:hover { background: #f8fafc; }
.rp-tile-switch { flex-shrink: 0; transform: scale(.8); transform-origin: left center; }
.rp-tile-label { font-size: .81rem; font-weight: 600; color: #334155; }

/* Footer / save bar */
.rp-save-bar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-top: 1.25rem; padding-top: 1.1rem; border-top: 1px solid #f1f5f9; }
.rp-save-summary { font-size: .82rem; color: #64748b; }
.rp-save-summary strong { color: #127464; font-size: 1rem; }
.rp-copy-picker { width: 200px; }
.rp-copy-picker .form-select { font-size: .8rem; }

@media (max-width: 992px) {
  .rp-role-col { display: none; }
  .rp-toolbar-inner { flex-direction: column; align-items: stretch; }
  .rp-admin-toggle { margin-left: 0; }
}

/* Phone: the 250/240/170px minmax() floors above guarantee overflow under
   ~360-390px no matter how much .rp-panel-wrap's own padding shrinks, so
   collapse those grids to a single column instead of trying to fit two. */
@media (max-width: 480px) {
  .rp-detail-col .hitech-card,
  .rp-panel-wrap { padding: 1rem; }
  .rp-perm-grid,
  .rp-reach-grid,
  .rp-tile-grid-channels { grid-template-columns: 1fr; }
  .rp-mod-detail td { padding-left: 14px; }
  .rp-stat-pill { padding: .5rem .75rem; }
}
</style>


{{-- Header --}}
<div class="rp-shell-header animate__animated animate__fadeIn">
  <div>
    <h1 class="rp-shell-title">@lang('Roles & Permissions')</h1>
    <p class="rp-shell-sub">@lang('Manage system access levels, security roles, and functional permissions.')</p>
  </div>
  <div class="rp-shell-stats">
    <div class="rp-stat-pill"><i class="bx bx-group"></i><div><div class="rp-stat-pill-value">{{ $roles->count() }}</div><div class="rp-stat-pill-label">@lang('Total Roles')</div></div></div>
    <div class="rp-stat-pill"><i class="bx bx-user"></i><div><div class="rp-stat-pill-value">{{ $users->count() }}</div><div class="rp-stat-pill-label">@lang('Total Users')</div></div></div>
    <div class="rp-stat-pill"><i class="bx bx-shield"></i><div><div class="rp-stat-pill-value">{{ $roles->filter(fn($r) => in_array($r->name, Constants::BuiltInRoles))->count() }}</div><div class="rp-stat-pill-label">@lang('System Roles')</div></div></div>
    <button type="button" class="btn btn-outline-primary rounded-pill fw-bold shadow-sm d-flex align-items-center px-3"
            data-bs-target="#previewAccessModal" data-bs-toggle="modal">
        <i class="bx bx-show-alt me-1"></i> @lang('Preview Access')
    </button>
    <a href="{{ route('roles.specialPermissions') }}"
       class="btn btn-outline-primary rounded-pill fw-bold shadow-sm d-flex align-items-center px-3">
        <i class="bx bx-user-check me-1"></i> @lang('Special Permissions to User')
    </a>
    <button data-bs-target="#addOrUpdateRoleModal" data-bs-toggle="modal" class="btn btn-primary btn-hitech-glow add-new-role">
      <i class="bx bx-plus me-1"></i> @lang('Create New Role')
    </button>
  </div>
</div>

<div class="rp-shell-body animate__animated animate__fadeInUp">
  {{-- Role list --}}
  <aside class="rp-role-col">
    <div class="search-wrapper-hitech rp-role-search">
      <i class='bx bx-search ms-2 text-muted'></i>
      <input type="text" id="rpRoleSearch" class="form-control border-0 shadow-none" placeholder="@lang('Search role…')">
    </div>
    <div class="rp-role-list" id="rpRoleList">
      @foreach($roles as $r)
        @php $rIsBuiltIn = in_array($r->name, Constants::BuiltInRoles); @endphp
        <div class="rp-role-row {{ $selectedRole && $selectedRole->id === $r->id ? 'rp-role-active' : '' }}" data-id="{{ $r->id }}" data-name="{{ strtolower($r->name) }}">
          <span class="rp-role-row-icon"><i class="bx {{ $rIsBuiltIn ? 'bx-crown' : 'bx-shield-quarter' }}"></i></span>
          <div class="rp-role-row-info">
            <div class="rp-role-row-name">{{ $r->display_name }}</div>
            <div class="rp-role-row-count">{{ $r->users_count }} @lang('users')</div>
          </div>
          <span class="rp-role-row-badge {{ $rIsBuiltIn ? 'rp-badge-system' : 'rp-badge-custom' }}">{{ $rIsBuiltIn ? 'System' : 'Custom' }}</span>
        </div>
      @endforeach
    </div>
  </aside>

  {{-- Detail panel --}}
  <main class="rp-detail-col">
    <div class="rp-panel-wrap" id="rpPanel">
      @if($selectedRole)
        @include('roles._permission-panel')
      @else
        <div class="text-center py-5 text-muted">@lang('No roles yet — create one to get started.')</div>
      @endif
    </div>
  </main>
</div>

@if($settings->is_helper_text_enabled)
  <div class="alert alert-warning alert-dismissible fade show mt-5 animate__animated animate__fadeIn hitech-note-card" role="alert" style="background: rgba(255, 171, 0, 0.05); border: 1px solid rgba(255, 171, 0, 0.2); color: #ffab00;">
    <div class="d-flex align-items-center">
        <i class="bx bx-info-circle me-3 fs-3"></i>
        <div>
            <h6 class="alert-heading mb-1 text-warning fw-bold">System Protection</h6>
            <p class="mb-0 opacity-75 small">
              Default system roles ({{ implode(', ', array_map(fn($r) => ucwords(str_replace(['_', '-'], ' ', $r)), Constants::BuiltInRoles)) }}) are protected and cannot be deleted to ensure platform stability.
            </p>
        </div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  </div>
@endif

{{-- User Special Permissions summary --}}
@php $specialUsers = $users->filter(fn($u) => $u->permissions->count() > 0); @endphp
<div class="row mt-6">
  <div class="col-12">
    <div class="hitech-card p-5" style="border: 1px dashed #00ced1; background: rgba(0, 206, 209, 0.02);">
      <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
          <h4 class="fw-bold text-heading mb-1"><i class="bx bx-user-check me-2 text-teal"></i>User Special Permissions</h4>
          <p class="text-muted mb-0">{{ $specialUsers->count() }} @lang('of') {{ $users->count() }} @lang('employees have a direct permission override on top of their role.')</p>
        </div>
        <a href="{{ route('roles.specialPermissions') }}" class="btn btn-primary btn-hitech-glow">
          <i class="bx bx-user-check me-1"></i> @lang('Open User Special Permissions')
        </a>
      </div>
    </div>
  </div>
</div>

{{-- Modals --}}
@include('_partials._modals.role.addOrUpdate-role', ['rolePresets' => $rolePresets ?? config('role_presets', [])])
@include('_partials._modals.role.preview-access', ['roles' => $roles, 'users' => $users])
@endsection
