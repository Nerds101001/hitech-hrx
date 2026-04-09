@php
  $configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Roles - Apps')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
    'resources/assets/vendor/libs/@form-validation/form-validation.scss',
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
    ])
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
    'resources/assets/vendor/libs/@form-validation/popular.js',
    'resources/assets/vendor/libs/@form-validation/bootstrap5.js',
    'resources/assets/vendor/libs/@form-validation/auto-focus.js',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
    ])
@endsection

@section('page-script')
  @vite([
    'resources/assets/js/app/role-index.js',
    ])
@endsection

@section('content')
  {{-- 1. HITECH HERO SECTION --}}
  <div class="hitech-page-hero animate__animated animate__fadeIn">
      <div class="hitech-page-hero-text">
          <h1 class="greeting">@lang('Roles & Permissions')</h1>
          <p class="sub-text">@lang('Manage system access levels, security roles, and functional permissions.')</p>
      </div>
      <div class="emp-hero-meta">
          <div class="hero-quick-stat">
              <div class="stat-value">{{ count($roles) }}</div>
              <div class="stat-label">@lang('Roles')</div>
          </div>
          <div class="hero-quick-stat">
              <div class="stat-value">{{ \App\Models\User::count() }}</div>
              <div class="stat-label">@lang('Users')</div>
          </div>
      </div>
  </div>
  
  {{-- 2. ROLE CARDS GRID --}}
  <div class="row g-6 animate__animated animate__fadeInUp">
    @foreach($roles as $role)
      <div class="col-xl-4 col-lg-6 col-md-6">
        <div class="hitech-card h-100 shadow-sm border-0 transition-3d">
          <div class="role-card-content h-100 d-flex flex-column">
            <div class="hitech-card-header border-bottom-0 p-0 mb-4 d-flex justify-content-between align-items-center">
                <h5 class="title mb-0 fw-bold" style="color: #005a5a; font-size: 1.15rem;">{{$role->display_name}}</h5>
                @if(in_array($role->name, Constants::BuiltInRoles))
                    <span class="badge bg-label-success rounded-pill px-3">System</span>
                @else
                    <span class="badge bg-label-warning rounded-pill px-3">Custom</span>
                @endif
            </div>
            
            <div class="d-flex justify-content-between align-items-center mb-5">
              <div class="d-flex flex-column">
                  <h6 class="fw-bold mb-1 text-heading" style="font-size: 0.95rem;">@lang('Users Assigned')</h6>
                  <small class="text-muted">{{$role->users()->count()}} @lang('Total')</small>
              </div>
              <ul class="list-unstyled d-flex align-items-center avatar-group mb-0 ms-2">
                @foreach($role->users()->limit(4)->get() as $user)
                  @php
                    $randomStatusColor = ['primary', 'success', 'danger', 'warning', 'info', 'dark'];
                    $randomColor = $randomStatusColor[array_rand($randomStatusColor)];
                  @endphp
                  <li data-bs-toggle="tooltip" data-popup="tooltip-custom" data-bs-placement="top"
                      title="{{$user->getFullName()}}"
                      class="avatar avatar-sm pull-up">
                    @if($user->profile_picture)
                      <img class="rounded-circle"
                           src="{{$user->getProfilePicture()}}"
                           alt="Avatar">
                    @else
                      <span
                        class="avatar-initial rounded-circle bg-label-{{$randomColor}}">{{ $user->getInitials() }}</span>
                    @endif
                  </li>
                @endforeach
                @if($role->users()->count() > 4)
                  <li class="avatar avatar-sm">
                    <span class="avatar-initial rounded-circle pull-up bg-label-secondary" data-bs-toggle="tooltip"
                          data-bs-placement="bottom"
                          title="{{$role->users()->count() - 4}} more">+{{$role->users()->count() - 4}}</span>
                  </li>
                @endif
              </ul>
            </div>
            
            <div class="d-flex justify-content-end gap-3 pt-4 border-top mt-auto">
                <a href="javascript:void(0);" class="btn btn-icon btn-label-warning edit rounded-circle shadow-sm" data-value="{{$role}}" style="width: 38px; height: 38px;">
                    <i class="bx bx-pencil fs-5"></i>
                </a>
                @if(!in_array($role->name, Constants::BuiltInRoles))
                <a href="javascript:void(0);" class="btn btn-icon btn-label-danger rounded-circle shadow-sm" onclick="deleteRole({{$role->id}})" style="width: 38px; height: 38px;">
                    <i class="bx bx-trash fs-5"></i>
                </a>
                @endif
            </div>
          </div>
        </div>
      </div>
    @endforeach

    
    <div class="col-xl-4 col-lg-6 col-md-6">
      <div class="hitech-card h-100 d-flex align-items-center justify-content-center" style="border: 2px dashed #e2e8f0 !important;">
        <div class="card-body text-center d-flex flex-column align-items-center justify-content-center h-100 py-5">
            <div class="stat-icon-wrap icon-teal mb-3" style="width: 50px; height: 50px; font-size: 1.5rem;">
                <i class="bx bx-plus"></i>
            </div>
            <button data-bs-target="#addOrUpdateRoleModal" data-bs-toggle="modal"
                  class="btn btn-primary btn-hitech-glow mb-2 text-nowrap add-new-role">
              @lang('Create New Role')
            </button>
            <p class="mb-0 text-muted small px-4">Define new access levels for your team.</p>
        </div>
      </div>
    </div>
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

  {{-- 2. USER SPECIAL PERMISSIONS SECTION --}}
  <div class="row mt-6">
    <div class="col-12">
      <div class="hitech-card p-5" style="border: 1px dashed #00ced1; background: rgba(0, 206, 209, 0.02);">
        <div class="d-flex align-items-center justify-content-between mb-4">
          <div>
            <h4 class="fw-bold text-heading mb-1"><i class="bx bx-user-check me-2 text-teal"></i>User Special Permissions</h4>
            <p class="text-muted mb-0">Assign additional permissions to specific employees beyond their role defaults.</p>
          </div>
          <button class="btn btn-primary btn-hitech-glow add-user-permission">
            <i class="bx bx-plus me-1"></i> Add Special Access
          </button>
        </div>

        <div class="row g-4">
          @foreach($users->filter(fn($u) => $u->permissions->count() > 0) as $user)
          <div class="col-md-4">
            <div class="d-flex align-items-center p-3 rounded" style="background: #fff; border: 1px solid #eee;">
              <div class="avatar avatar-sm me-3">
                <img src="{{ $user->profile_photo_url }}" alt="Avatar" class="rounded-circle">
              </div>
              <div class="me-auto">
                <span class="d-block fw-bold text-heading">{{ $user->name }}</span>
                <span class="badge bg-label-info x-small">{{ $user->permissions->count() }} specialized perms</span>
              </div>
              <button class="btn btn-icon btn-label-primary edit-user-perm" data-id="{{ $user->id }}">
                <i class="bx bx-edit-alt"></i>
              </button>
            </div>
          </div>
          @endforeach

          @if($users->filter(fn($u) => $u->permissions->count() > 0)->isEmpty())
          <div class="col-12 text-center py-5">
            <div class="opacity-25 mb-2"><i class="bx bx-info-circle fs-1"></i></div>
            <p class="text-muted">No users have direct special permissions yet.</p>
          </div>
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- 3. MODALS --}}
  @include('_partials._modals.role.addOrUpdate-role', ['groupedPermissions' => $groupedPermissions])
  @include('_partials._modals.role.addOrUpdate-user-permission', ['groupedPermissions' => $groupedPermissions, 'users' => $users])
@endsection
