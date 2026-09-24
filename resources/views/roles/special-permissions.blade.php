@php
  $configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', 'User Special Permissions')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
    'resources/assets/vendor/libs/@form-validation/form-validation.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
    ])
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.js',
    'resources/assets/vendor/libs/@form-validation/popular.js',
    'resources/assets/vendor/libs/@form-validation/bootstrap5.js',
    'resources/assets/vendor/libs/@form-validation/auto-focus.js',
    ])
@endsection

@section('page-script')
  @vite([
    'resources/assets/js/app/role-special-permissions.js',
    ])
@endsection

@section('content')
<style>
/* ── User Special Permissions — scoped to this page only ─────────────── */
.usp-page { padding-bottom: 1rem; }
.usp-hero { padding: 2rem 3rem; min-height: auto; }
.usp-back-link {
  color: rgba(255,255,255,.75); font-size: .75rem; font-weight: 700; text-decoration: none;
  display: inline-flex; align-items: center; gap: 6px; letter-spacing: .04em; text-transform: uppercase;
}
.usp-back-link:hover { color: #fff; }

.usp-body { display: flex; align-items: flex-start; gap: 1.5rem; margin-top: 1.5rem; }

/* Left: employee picker */
.usp-emp-col { width: 290px; flex-shrink: 0; }
.usp-emp-search { margin-bottom: .85rem; }
.usp-emp-list { max-height: calc(100vh - 13rem); overflow-y: auto; display: flex; flex-direction: column; gap: 3px; padding-right: 2px; }
.usp-emp-row {
  display: flex; align-items: center; gap: 10px; padding: 9px 10px; border-radius: 12px;
  cursor: pointer; border: 1px solid transparent; transition: all .15s;
}
.usp-emp-row:hover { background: #fff; border-color: var(--hitech-border); }
.usp-emp-row.usp-emp-active { background: var(--hitech-primary-light); border-color: rgba(18,116,100,.25); }
.usp-emp-avatar { width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0; object-fit: cover; }
.usp-emp-avatar-fallback {
  width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center;
  justify-content: center; background: #127464; color: #fff; font-weight: 700; font-size: .7rem;
}
.usp-emp-info { min-width: 0; flex: 1; }
.usp-emp-name { font-size: .8rem; font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.usp-emp-role { font-size: .68rem; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.usp-emp-badge {
  font-size: .6rem; font-weight: 800; background: #127464; color: #fff; border-radius: 20px;
  padding: 1px 7px; flex-shrink: 0;
}

/* Center: detail panel */
.usp-main { flex: 1; min-width: 0; }
.usp-empty { text-align: center; padding: 5rem 1rem; color: #94a3b8; }

.usp-profile-card { display: flex; align-items: center; gap: 1rem; padding: 1.4rem 1.6rem; flex-wrap: wrap; }
.usp-profile-avatar { width: 56px; height: 56px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.usp-profile-avatar-fallback {
  width: 56px; height: 56px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center;
  justify-content: center; background: #127464; color: #fff; font-weight: 700; font-size: 1.1rem;
}
.usp-profile-meta { flex: 1; min-width: 200px; }
.usp-profile-name { font-size: 1.1rem; font-weight: 800; color: #1e293b; display: flex; align-items: center; gap: 8px; }
.usp-profile-sub { font-size: .78rem; color: #64748b; margin-top: 2px; }
.usp-profile-actions { display: flex; gap: 10px; flex-wrap: wrap; }

.usp-summary-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1.1rem; }
.usp-info-card { padding: 1.1rem 1.3rem; }
.usp-info-card-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: .5rem; }
.usp-info-card-title { font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: #94a3b8; }
.usp-info-card-value { font-size: .95rem; font-weight: 800; color: #1e293b; }
.usp-info-card-sub { font-size: .76rem; color: #64748b; margin-top: 2px; }

.usp-tabs { display: flex; gap: 6px; margin-top: 1.25rem; border-bottom: 1px solid var(--hitech-border); }
.usp-tab {
  padding: 10px 4px; margin-right: 1.5rem; font-size: .82rem; font-weight: 700; color: #94a3b8;
  cursor: pointer; border-bottom: 2px solid transparent; transition: all .15s;
}
.usp-tab.usp-tab-active { color: #127464; border-bottom-color: #127464; }

.usp-toolbar { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; margin: 1.1rem 0; }
.usp-search { flex: 1 1 220px; max-width: 300px; }
.usp-legend { display: flex; gap: 1rem; flex-wrap: wrap; }
.usp-legend-item { display: flex; align-items: center; gap: 6px; font-size: .7rem; font-weight: 700; color: #64748b; white-space: nowrap; }
.usp-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
.usp-dot-full { background: #127464; }
.usp-dot-view { background: #4d6cf0; }
.usp-dot-partial { background: #f5a623; }
.usp-dot-none { background: #e2e8f0; }

.usp-recon-table { width: 100%; border-collapse: collapse; }
.usp-recon-table thead th {
  background: rgba(18,116,100,.03); padding: 10px 14px; color: #127464; font-weight: 700;
  font-size: .66rem; text-transform: uppercase; letter-spacing: .05em; text-align: left;
}
.usp-recon-table thead th.text-center { text-align: center; }
.usp-recon-row { border-top: 1px solid #f1f5f9; cursor: pointer; }
.usp-recon-row:hover { background: #fbfdfd; }
.usp-recon-row td { padding: 11px 14px; vertical-align: middle; }
.usp-recon-module { display: flex; align-items: center; gap: 9px; font-size: .82rem; font-weight: 700; color: #1e293b; }
.usp-recon-module i.bx-chevron-right { transition: transform .15s; color: #94a3b8; }
.usp-recon-row.usp-expanded i.bx-chevron-right { transform: rotate(90deg); }
.usp-state-pill { display: inline-flex; align-items: center; gap: 5px; font-size: .7rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
.usp-state-full { background: #dcfce7; color: #166534; }
.usp-state-view_only { background: #dbeafe; color: #1e40af; }
.usp-state-partial { background: rgba(245,166,35,.15); color: #92610f; }
.usp-state-none { background: #f1f5f9; color: #94a3b8; }
.usp-source-badge { font-size: .68rem; font-weight: 700; color: #64748b; }
.usp-detail-row td { padding: 0 14px 14px 40px; background: #fbfdfd; border-top: none; }
.usp-detail-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 6px 16px; padding-top: 8px; }
.usp-detail-item { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 6px 10px; background: #fff; border: 1px solid #f1f5f9; border-radius: 8px; }
.usp-detail-label { font-size: .74rem; font-weight: 600; color: #475569; }
.usp-detail-tags { display: flex; gap: 4px; flex-shrink: 0; }
.usp-tag { font-size: .6rem; font-weight: 800; padding: 2px 6px; border-radius: 6px; }
.usp-tag-role { background: rgba(77,108,240,.12); color: #4d6cf0; }
.usp-tag-special { background: rgba(18,116,100,.12); color: #127464; }
.usp-tag-none { background: #f1f5f9; color: #cbd5e1; }
.usp-hidden-by-search { display: none !important; }

/* Right: summary */
.usp-summary-col { width: 270px; flex-shrink: 0; }
.usp-summary-item { display: flex; align-items: center; justify-content: space-between; padding: 9px 0; border-bottom: 1px solid #f1f5f9; }
.usp-summary-item:last-child { border-bottom: none; }
.usp-summary-label { font-size: .78rem; font-weight: 600; color: #64748b; display: flex; align-items: center; gap: 8px; }
.usp-summary-value { font-size: .95rem; font-weight: 800; color: #1e293b; }

@media (max-width: 1200px) {
  .usp-summary-col { display: none; }
}
@media (max-width: 992px) {
  .usp-emp-col { display: none; }
  .usp-summary-row { grid-template-columns: 1fr; }
}
</style>

<div class="usp-page">
  <div class="hitech-page-hero usp-hero animate__animated animate__fadeIn">
    <div class="hitech-page-hero-text">
      <a href="{{ route('roles.index') }}" class="usp-back-link"><i class="bx bx-arrow-back"></i> @lang('Roles & Permissions')</a>
      <h1 class="greeting mt-2">@lang('User Special Permissions')</h1>
      <p class="sub-text">@lang('See exactly what each person can do — from their role, from a direct override, or both — and adjust it.')</p>
    </div>
    <div class="emp-hero-meta">
      <div class="hero-quick-stat">
        <div class="stat-value">{{ $employees->count() }}</div>
        <div class="stat-label">@lang('Employees')</div>
      </div>
      <div class="hero-quick-stat">
        <div class="stat-value">{{ $employees->where('permissions_count', '>', 0)->count() }}</div>
        <div class="stat-label">@lang('With Overrides')</div>
      </div>
    </div>
  </div>

  <div class="usp-body">
    {{-- Employee picker --}}
    <aside class="usp-emp-col">
      <div class="search-wrapper-hitech usp-emp-search">
        <i class='bx bx-search ms-2 text-muted'></i>
        <input type="text" id="uspEmpSearch" class="form-control border-0 shadow-none" placeholder="@lang('Search employee…')">
      </div>
      <div class="usp-emp-list" id="uspEmpList">
        @foreach($employees as $emp)
          @php $empRole = optional($emp->roles->first())->display_name ?? 'No Role'; @endphp
          <div class="usp-emp-row" data-id="{{ $emp->id }}" data-name="{{ strtolower($emp->getFullName()) }}">
            @if($emp->profile_picture)
              <img class="usp-emp-avatar" src="{{ $emp->getProfilePicture() }}" alt="">
            @else
              <span class="usp-emp-avatar-fallback">{{ $emp->getInitials() }}</span>
            @endif
            <div class="usp-emp-info">
              <div class="usp-emp-name">{{ $emp->getFullName() }}</div>
              <div class="usp-emp-role">{{ $empRole }}</div>
            </div>
            @if($emp->permissions_count > 0)
              <span class="usp-emp-badge">{{ $emp->permissions_count }}</span>
            @endif
          </div>
        @endforeach
      </div>
    </aside>

    {{-- Detail panel --}}
    <main class="usp-main">
      <div id="uspEmpty" class="usp-empty">
        <div class="opacity-25 mb-2"><i class="bx bx-user-circle fs-1"></i></div>
        <p class="mb-0">@lang('Pick an employee on the left to see their effective access.')</p>
      </div>

      <div id="uspDetail" class="d-none">
        <div class="hitech-card usp-profile-card">
          <div id="uspAvatarWrap"></div>
          <div class="usp-profile-meta">
            <div class="usp-profile-name"><span id="uspName"></span> <span id="uspStatusBadge" class="badge bg-label-success rounded-pill"></span></div>
            <div class="usp-profile-sub"><span id="uspRoleLabel"></span> &nbsp;•&nbsp; <span id="uspEmail"></span> &nbsp;•&nbsp; <span id="uspPhone"></span></div>
          </div>
          <div class="usp-profile-actions">
            <a id="uspProfileLink" href="#" target="_blank" class="btn btn-label-secondary btn-sm"><i class="bx bx-id-card me-1"></i>@lang('View Full Employee Profile')</a>
            <button type="button" id="uspPreviewBtn" class="btn btn-label-primary btn-sm"><i class="bx bx-show-alt me-1"></i>@lang('Preview Access')</button>
          </div>
        </div>

        <div class="usp-summary-row">
          <div class="hitech-card usp-info-card">
            <div class="usp-info-card-head">
              <span class="usp-info-card-title">@lang('Base Role')</span>
              <a id="uspRoleUrl" href="#" class="btn btn-icon btn-label-primary btn-sm"><i class="bx bx-link-external"></i></a>
            </div>
            <div class="usp-info-card-value" id="uspRoleName"></div>
            <div class="usp-info-card-sub" id="uspRoleDesc"></div>
          </div>
          <div class="hitech-card usp-info-card">
            <div class="usp-info-card-head">
              <span class="usp-info-card-title">@lang('Special Permissions')</span>
              <button type="button" id="uspEditBtn" class="btn btn-icon btn-label-warning btn-sm"><i class="bx bx-edit-alt"></i></button>
            </div>
            <div class="usp-info-card-value"><span id="uspSpecialCount"></span> @lang('Extra Permissions')</div>
            <div class="usp-info-card-sub">@lang('Added directly, on top of the role')</div>
          </div>
        </div>

        <div class="usp-tabs">
          <div class="usp-tab usp-tab-active" data-tab="modules">@lang('Module / Feature Permissions')</div>
          <div class="usp-tab" data-tab="advanced">@lang('Advanced Settings')</div>
        </div>

        <div id="uspTabModules">
          <div class="usp-toolbar">
            <div class="search-wrapper-hitech usp-search">
              <i class='bx bx-search ms-2 text-muted'></i>
              <input type="text" id="uspTableSearch" class="form-control border-0 shadow-none" placeholder="@lang('Search modules or permissions…')">
            </div>
            <div class="usp-legend">
              <span class="usp-legend-item"><span class="usp-dot usp-dot-full"></span> @lang('Full Access')</span>
              <span class="usp-legend-item"><span class="usp-dot usp-dot-view"></span> @lang('View Only')</span>
              <span class="usp-legend-item"><span class="usp-dot usp-dot-partial"></span> @lang('Partial')</span>
              <span class="usp-legend-item"><span class="usp-dot usp-dot-none"></span> @lang('Not Granted')</span>
            </div>
          </div>

          <div class="hitech-card">
            <div class="table-responsive">
              <table class="usp-recon-table">
                <thead>
                  <tr>
                    <th>@lang('Module / Feature')</th>
                    <th class="text-center">@lang('Role Permission')</th>
                    <th class="text-center">@lang('Special Permission')</th>
                    <th class="text-center">@lang('Final Access')</th>
                    <th class="text-center">@lang('Source')</th>
                  </tr>
                </thead>
                <tbody id="uspReconBody"></tbody>
              </table>
            </div>
          </div>
        </div>

        <div id="uspTabAdvanced" class="d-none">
          <div class="hitech-card p-4">
            <p class="text-muted small mb-3"><i class="bx bx-info-circle me-1"></i>@lang('These come from the role and cannot be overridden per-person.')</p>
            <div class="row g-3" id="uspAdvancedGrid"></div>
          </div>
        </div>
      </div>
    </main>

    {{-- Summary --}}
    <aside class="usp-summary-col">
      <div class="hitech-card p-4" id="uspSummaryCard" style="display: none;">
        <h6 class="fw-bold text-heading mb-3">@lang('Permission Summary')</h6>
        <div class="usp-summary-item"><span class="usp-summary-label"><span class="usp-dot usp-dot-full"></span>@lang('Total Modules')</span><span class="usp-summary-value" id="sumTotal"></span></div>
        <div class="usp-summary-item"><span class="usp-summary-label"><span class="usp-dot usp-dot-full"></span>@lang('Full Access')</span><span class="usp-summary-value" id="sumFull"></span></div>
        <div class="usp-summary-item"><span class="usp-summary-label"><span class="usp-dot usp-dot-view"></span>@lang('View Only')</span><span class="usp-summary-value" id="sumView"></span></div>
        <div class="usp-summary-item"><span class="usp-summary-label"><span class="usp-dot usp-dot-partial"></span>@lang('Partial Access')</span><span class="usp-summary-value" id="sumPartial"></span></div>
        <div class="usp-summary-item"><span class="usp-summary-label"><span class="usp-dot usp-dot-none"></span>@lang('Not Granted')</span><span class="usp-summary-value" id="sumNone"></span></div>
        <div class="usp-summary-item"><span class="usp-summary-label">@lang('Special Permissions')</span><span class="usp-summary-value" id="sumSpecial"></span></div>
      </div>
    </aside>
  </div>
</div>

@include('_partials._modals.role.addOrUpdate-user-permission', ['groupedPermissions' => $groupedPermissions, 'permissionMatrix' => $permissionMatrix, 'wiredPermissions' => $wiredPermissions, 'users' => $users])
@include('_partials._modals.role.preview-access', ['roles' => $roles, 'users' => $users])
@endsection
