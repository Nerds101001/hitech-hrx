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

.usp-person-card { padding: 1.3rem 1.5rem; }
.usp-profile-card { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
.usp-sep { color: #cbd5e1; margin: 0 6px; }

/* Base role + special permissions strip */
.usp-role-strip { display: flex; align-items: stretch; gap: .75rem; margin-top: 1.1rem; }
.usp-role-box {
  flex: 1; display: flex; align-items: center; gap: 12px; padding: .85rem 1rem;
  background: #f8fafc; border: 1px solid #eef2f6; border-radius: 12px; min-width: 0;
}
.usp-role-icon { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
.usp-role-icon-blue { background: rgba(77,108,240,.1); color: #4d6cf0; }
.usp-role-icon-purple { background: rgba(147,51,234,.1); color: #9333ea; }
.usp-role-text { flex: 1; min-width: 0; }
.usp-role-kicker { font-size: .68rem; font-weight: 600; color: #64748b; }
.usp-role-value { font-size: .92rem; font-weight: 800; color: #1e293b; }
.usp-role-value-green { color: #127464; }
.usp-role-sub { font-size: .68rem; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.usp-role-plus { display: flex; align-items: center; color: #94a3b8; font-size: 1.2rem; }
.usp-profile-avatar { width: 56px; height: 56px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.usp-profile-avatar-fallback {
  width: 56px; height: 56px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center;
  justify-content: center; background: #127464; color: #fff; font-weight: 700; font-size: 1.1rem;
}
.usp-profile-meta { flex: 1; min-width: 200px; }
.usp-profile-name { font-size: 1.1rem; font-weight: 800; color: #1e293b; display: flex; align-items: center; gap: 8px; }
.usp-profile-sub { font-size: .78rem; color: #64748b; margin-top: 2px; }
.usp-profile-actions { display: flex; gap: 10px; flex-wrap: wrap; }


/* Pill tabs */
.usp-tabs { display: inline-flex; gap: 0; margin-top: 1.25rem; background: #fff; border: 1px solid var(--hitech-border); border-radius: 10px; overflow: hidden; }
.usp-tab {
  padding: 9px 18px; font-size: .78rem; font-weight: 600; color: #475569;
  cursor: pointer; border-right: 1px solid var(--hitech-border); transition: all .15s;
}
.usp-tab:last-child { border-right: none; }
.usp-tab:hover { background: #f8fafc; }
.usp-tab.usp-tab-active { background: #127464; color: #fff; }

.usp-toolbar { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; margin: 1.1rem 0; }
.usp-search { flex: 1 1 220px; max-width: 280px; }
.usp-tool-btn { font-size: .74rem; font-weight: 600; border-radius: 8px; }
.usp-legend { display: flex; gap: 1rem; flex-wrap: wrap; }
.usp-legend-item { display: flex; align-items: center; gap: 6px; font-size: .72rem; font-weight: 600; color: #64748b; white-space: nowrap; }

/* ✓ / ■ / ✗ marks - the design's checkbox-style states */
.usp-mark { display: inline-flex; align-items: center; gap: 7px; font-size: .76rem; font-weight: 600; color: #334155; white-space: nowrap; }
.usp-mark-box { width: 16px; height: 16px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: .8rem; flex-shrink: 0; }
.usp-mark-yes { background: #16a34a; }
.usp-mark-view { background: #3b82f6; }
.usp-mark-partial { background: #f59e0b; }
.usp-mark-no { background: #ef4444; }
.usp-mark-dash { color: #cbd5e1; font-weight: 700; }
.usp-src { font-size: .66rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; background: #f1f5f9; color: #64748b; }
.usp-src-special { background: rgba(18,116,100,.1); color: #127464; }
.usp-src-both { background: rgba(147,51,234,.1); color: #7e22ce; }
.usp-mod-icon { width: 26px; height: 26px; border-radius: 7px; display: inline-flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }
.usp-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
.usp-dot-full { background: #127464; }
.usp-dot-view { background: #4d6cf0; }
.usp-dot-partial { background: #f5a623; }
.usp-dot-none { background: #e2e8f0; }

.usp-recon-table { width: 100%; border-collapse: collapse; }
.usp-recon-table thead th {
  background: #f8fafc; padding: 11px 14px; color: #334155; font-weight: 700;
  font-size: .72rem; text-align: left; border-bottom: 1px solid #eef2f6;
}
.usp-recon-table thead th + th { border-left: 1px solid #eef2f6; }
.usp-recon-table thead th.text-center { text-align: center; }
.usp-recon-row { border-top: 1px solid #f1f5f9; cursor: pointer; }
.usp-recon-row:hover { background: #fbfdfd; }
.usp-recon-row.usp-expanded { background: rgba(18,116,100,.04); }
.usp-recon-row td { padding: 9px 14px; vertical-align: middle; }
.usp-recon-row td + td { border-left: 1px solid #f1f5f9; }
.usp-recon-module { display: flex; align-items: center; gap: 10px; font-size: .8rem; font-weight: 700; color: #1e293b; }
.usp-recon-module .usp-mod-name { flex: 1; }
.usp-recon-module i.usp-chev { transition: transform .15s; color: #64748b; font-size: 1.1rem; }
.usp-recon-row.usp-expanded i.usp-chev { transform: rotate(180deg); }
.usp-state-pill { display: inline-flex; align-items: center; gap: 5px; font-size: .7rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
.usp-state-full { background: #dcfce7; color: #166534; }
.usp-state-view_only { background: #dbeafe; color: #1e40af; }
.usp-state-partial { background: rgba(245,166,35,.15); color: #92610f; }
.usp-state-none { background: #f1f5f9; color: #94a3b8; }
.usp-source-badge { font-size: .68rem; font-weight: 700; color: #64748b; }
.usp-detail-row td { padding: 0 14px 14px 40px; background: #fbfdfd; border-top: none; }
.usp-detail-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 8px; padding-top: 8px; }
.usp-detail-item { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 6px 10px; background: #fff; border: 1px solid #f1f5f9; border-radius: 8px; }
.usp-detail-label { font-size: .74rem; font-weight: 600; color: #475569; }
.usp-detail-tags { display: flex; gap: 4px; flex-shrink: 0; }
.usp-tag { font-size: .6rem; font-weight: 800; padding: 2px 6px; border-radius: 6px; }
.usp-tag-role { background: rgba(77,108,240,.12); color: #4d6cf0; }
.usp-tag-special { background: rgba(18,116,100,.12); color: #127464; }
.usp-tag-none { background: #f1f5f9; color: #cbd5e1; }
.usp-hidden-by-search { display: none !important; }

/* Headline: the two questions, side by side */
.usp-headline { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem; }
.usp-headline-item { background: #fff; border: 1px solid var(--hitech-border); border-radius: 14px; padding: .9rem 1.2rem; }
.usp-headline-value { font-size: 1.35rem; font-weight: 800; color: #127464; line-height: 1.1; }
.usp-headline-label { font-size: .74rem; font-weight: 800; color: #1e293b; margin-top: 2px; }
.usp-headline-sub { font-size: .68rem; color: #94a3b8; margin-top: 1px; }
.usp-table-note { display: flex; gap: 7px; align-items: flex-start; font-size: .72rem; color: #64748b; margin: -.4rem 0 .8rem; }
.usp-table-note .bx { color: #4d6cf0; font-size: .9rem; margin-top: 1px; flex-shrink: 0; }
.usp-count { font-size: .7rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; white-space: nowrap; }

/* Guard warning */
.usp-warning {
  display: flex; align-items: flex-start; gap: 10px; margin-top: 1rem; padding: .8rem 1rem; border-radius: 12px;
  background: rgba(245,166,35,.1); border: 1px solid rgba(245,166,35,.35); color: #92610f; font-size: .78rem; font-weight: 600;
}
.usp-warning .bx { font-size: 1.1rem; flex-shrink: 0; margin-top: 1px; }

/* One permission inside an opened module */
.usp-eff-item { display: flex; align-items: flex-start; gap: 9px; padding: 8px 10px; margin: 0; background: #fff; border: 1px solid #f1f5f9; border-radius: 8px; cursor: pointer; }
.usp-eff-item:hover { border-color: rgba(18,116,100,.3); }
.usp-eff-locked { cursor: not-allowed; background: #fafbfc; }
.usp-eff-locked:hover { border-color: #f1f5f9; }
.usp-eff-item input { margin-top: 3px; accent-color: #127464; width: 15px; height: 15px; flex-shrink: 0; cursor: pointer; }
.usp-eff-item input:disabled { cursor: not-allowed; opacity: .6; }
.usp-eff-text { flex: 1; min-width: 0; }
.usp-eff-name { font-size: .76rem; font-weight: 600; color: #334155; }
.usp-eff-meta { font-size: .64rem; color: #94a3b8; margin-top: 1px; }
.usp-eff-badge { font-size: .6rem; font-weight: 800; padding: 2px 8px; border-radius: 20px; flex-shrink: 0; white-space: nowrap; }
.usp-eff-badge-role { background: rgba(77,108,240,.12); color: #4d6cf0; }
.usp-eff-badge-special { background: rgba(18,116,100,.12); color: #127464; }
.usp-eff-badge-none { background: #f1f5f9; color: #94a3b8; }

@media (max-width: 992px) {
  .usp-emp-col { display: none; }
  .usp-headline { grid-template-columns: 1fr; }
  .usp-role-strip { flex-direction: column; }
  .usp-role-plus { justify-content: center; }
}

/* These badge/meta/sub-label classes repeat per permission row/module --
   the main source of the 294 sub-12px elements flagged on this page. */
@media (max-width: 480px) {
  .usp-emp-role, .usp-role-kicker, .usp-role-sub, .usp-src,
  .usp-state-pill, .usp-source-badge, .usp-tag, .usp-headline-sub,
  .usp-eff-meta, .usp-eff-badge, .usp-recon-table thead th {
    font-size: .75rem;
  }
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
              {{-- lazy: each photo is a PHP request that decrypts the file;
                   loading all ~240 at once made this page crawl on prod. --}}
              <img class="usp-emp-avatar" src="{{ $emp->getProfilePicture() }}" alt="" loading="lazy" decoding="async" width="34" height="34">
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
        <div class="hitech-card usp-person-card">
          <div class="usp-profile-card">
            <div id="uspAvatarWrap"></div>
            <div class="usp-profile-meta">
              <div class="usp-profile-name"><span id="uspName"></span> <span id="uspStatusBadge" class="badge bg-label-success rounded-pill"></span></div>
              <div class="usp-profile-sub"><span id="uspRoleLabel"></span> <span class="usp-sep">|</span> <span id="uspEmail"></span> <span class="usp-sep">|</span> <span id="uspPhone"></span></div>
            </div>
            <div class="usp-profile-actions">
              <a id="uspProfileLink" href="#" target="_blank" class="btn btn-outline-secondary btn-sm">@lang('View Full Employee Profile')</a>
              <button type="button" id="uspPreviewBtn" class="btn btn-outline-primary btn-sm"><i class="bx bx-show-alt me-1"></i>@lang('Preview Access')</button>
            </div>
          </div>

          {{-- Base role + special permissions, the way the design reads:
               what the role gives, plus what this person gets on top. --}}
          <div class="usp-role-strip">
            <div class="usp-role-box">
              <span class="usp-role-icon usp-role-icon-blue"><i class="bx bx-user"></i></span>
              <div class="usp-role-text">
                <div class="usp-role-kicker">@lang('Base Role')</div>
                <div class="usp-role-value" id="uspRoleName"></div>
                <div class="usp-role-sub" id="uspRoleDesc"></div>
              </div>
              <a id="uspRoleUrl" href="#" class="btn btn-sm btn-outline-secondary">@lang('View Role')</a>
            </div>
            <div class="usp-role-plus"><i class="bx bx-plus"></i></div>
            <div class="usp-role-box">
              <span class="usp-role-icon usp-role-icon-purple"><i class="bx bx-shield-plus"></i></span>
              <div class="usp-role-text">
                <div class="usp-role-kicker">@lang('Special Permissions')</div>
                <div class="usp-role-value usp-role-value-green"><span id="uspSpecialCount"></span> @lang('Extra Permissions')</div>
                <div class="usp-role-sub" id="uspLastChange">@lang('Given to this person only, on top of the role')</div>
              </div>
            </div>
          </div>
        </div>

        {{-- The two different questions this page answers, side by side and
             labelled, so a 0 in one never reads as contradicting the other. --}}
        <div class="usp-headline">
          <div class="usp-headline-item">
            <div class="usp-headline-value" id="uspHeadPages"></div>
            <div class="usp-headline-label">@lang('Pages they can open')</div>
            <div class="usp-headline-sub">@lang('Sidebar sections — includes pages open to every signed-in person')</div>
          </div>
          <div class="usp-headline-item">
            <div class="usp-headline-value" id="uspHeadPerms"></div>
            <div class="usp-headline-label">@lang('Action permissions')</div>
            <div class="usp-headline-sub" id="uspHeadPermsSub"></div>
          </div>
        </div>

        <div id="uspGuardWarning" class="usp-warning d-none">
          <i class="bx bx-error-circle"></i>
          <span id="uspGuardWarningText"></span>
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
            <button type="button" id="uspExpandAll" class="btn btn-sm btn-outline-secondary usp-tool-btn"><i class="bx bx-expand-alt me-1"></i>@lang('Expand All')</button>
            <button type="button" id="uspCollapseAll" class="btn btn-sm btn-outline-secondary usp-tool-btn"><i class="bx bx-collapse-alt me-1"></i>@lang('Collapse All')</button>
            <div class="usp-legend ms-auto">
              <span class="usp-legend-item"><span class="usp-mark-box usp-mark-yes"><i class="bx bx-check"></i></span>@lang('Granted')</span>
              <span class="usp-legend-item"><span class="usp-mark-box usp-mark-view"><i class="bx bx-check"></i></span>@lang('View Only')</span>
              <span class="usp-legend-item"><span class="usp-mark-box usp-mark-partial"><i class="bx bx-minus"></i></span>@lang('Partial')</span>
              <span class="usp-legend-item"><span class="usp-mark-box usp-mark-no"><i class="bx bx-x"></i></span>@lang('Not Granted')</span>
            </div>
          </div>
          <p class="usp-table-note"><i class="bx bx-info-circle"></i>@lang('Open a module to change it. Tick a box to allow it for this person; untick a Special one to remove it. Greyed boxes come from their role and can\'t be changed here. Saves instantly.')</p>

          <div class="hitech-card">
            <div class="table-responsive">
              <table class="usp-recon-table">
                <thead>
                  <tr>
                    <th>@lang('Module / Feature')</th>
                    <th>@lang('Role Permission')</th>
                    <th>@lang('Special Permission')</th>
                    <th>@lang('Final Access')</th>
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
  </div>
</div>

@include('_partials._modals.role.preview-access', ['roles' => $roles, 'users' => $users])
@endsection
