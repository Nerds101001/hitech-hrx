@php
    use App\Enums\IncentiveType;
    use App\Enums\UserAccountStatus;
    use App\Services\AddonService\IAddonService;
    use Carbon\Carbon;
    use App\Helpers\StaticDataHelpers;
    $role = $user->roles()->first()->name ?? '';
    $addonService = app(IAddonService::class);
@endphp
@extends('layouts.layoutMaster')

@section('title', 'Employee Details')

@section('vendor-style')
    @vite([
        'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
        'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
        'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
        'resources/assets/vendor/libs/animate-css/animate.scss',
        'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
        'resources/assets/vendor/libs/select2/select2.scss',
        'resources/assets/vendor/libs/@form-validation/form-validation.scss',
        'resources/assets/vendor/libs/bootstrap-datepicker/bootstrap-datepicker.scss',
        'resources/assets/vendor/libs/flatpickr/flatpickr.scss',
        'resources/assets/vendor/scss/pages/hitech-portal.scss'
    ])
@endsection

@section('page-style')
    @vite(['resources/assets/vendor/scss/pages/page-user-view.scss', 'resources/assets/css/employee-view.css'])
@endsection

@section('vendor-script')
    @vite([
        'resources/assets/vendor/libs/moment/moment.js',
        'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
        'resources/assets/vendor/libs/sweetalert2/sweetalert2.js',
        'resources/assets/vendor/libs/cleavejs/cleave.js',
        'resources/assets/vendor/libs/cleavejs/cleave-phone.js',
        'resources/assets/vendor/libs/select2/select2.js',
        'resources/assets/vendor/libs/@form-validation/popular.js',
        'resources/assets/vendor/libs/@form-validation/bootstrap5.js',
        'resources/assets/vendor/libs/@form-validation/auto-focus.js',
        'resources/assets/vendor/libs/bootstrap-datepicker/bootstrap-datepicker.js',
        'resources/assets/vendor/libs/flatpickr/flatpickr.js'
    ])
@endsection
@section('content')

@php
    $settings = \App\Models\Settings::first();
@endphp

<div class="">
    <div class="animate__animated animate__fadeIn">
        <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">{{ $user->getFullName() }}</h4>
            <span class="text-muted" style="font-size: 0.85rem;">View and update your personal, contact, and banking information.</span>
        </div>
        <div>
            <a href="{{ route('user.dashboard.index') }}" class="btn btn-outline-secondary rounded-pill btn-sm d-flex align-items-center" style="font-size: 0.8rem; font-weight: 500;">
                <i class="bx bx-arrow-back me-1" style="font-size: 1rem;"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <div class="row">
        <!-- User Sidebar -->
        <div class="col-xl-3 col-lg-3 col-md-4 col-12 mb-4">
            <!-- User Card -->
            <div class="card mb-4 border-0 shadow-sm position-relative overflow-hidden" style="border-radius: 12px;">
                <div style="height: 6px; background-color: #127464; position: absolute; top: 0; left: 0; right: 0;"></div>
                <div class="card-body pt-5">
                    <div class="user-avatar-section text-center position-relative mb-4">
                        <!-- Profile Picture -->
                        <div class="profile-picture-container position-relative d-inline-block" style="width: 110px; height: 110px;">
                            @if ($user->profile_picture)
                                <img class="img-fluid rounded-circle w-100 h-100 border border-4 border-white shadow-sm" src="{{ $user->getProfilePicture() }}" alt="User avatar" id="userProfilePicture" style="object-fit: cover;" />
                            @else
                                <div class="rounded-circle w-100 h-100 d-flex align-items-center justify-content-center border border-4 border-white shadow-sm" style="background-color: #127464; color: white;">
                                    <h2 class="mb-0 text-white fw-bold">{{ $user->getInitials() }}</h2>
                                </div>
                            @endif
                        </div>
                        <h5 class="mt-3 mb-1 fw-bold" style="color: #1E293B; font-size: 1.25rem;">{{ $user->first_name }} {{ $user->last_name }}</h5>
                        <p class="text-muted mb-2" style="font-size: 0.9rem; font-weight: 500;">{{ $user->designation ? $user->designation->name : 'N/A' }}</p>
                        
                        @if($user->status == UserAccountStatus::ACTIVE)
                            <span class="badge" style="background-color: #E0F2F1; color: #127464; font-size: 0.7rem; font-weight: 700; padding: 0.4em 1em; border-radius: 50px;">ACTIVE</span>
                        @elseif($user->status == UserAccountStatus::TERMINATED)
                            <span class="badge bg-label-danger" style="font-size: 0.7rem; font-weight: 700; padding: 0.4em 1em; border-radius: 50px;">TERMINATED</span>
                        @else
                            <span class="badge bg-label-warning" style="font-size: 0.7rem; font-weight: 700; padding: 0.4em 1em; border-radius: 50px;">{{ strtoupper($user->status->value) }}</span>
                        @endif
                    </div>

                    <div class="border-top pt-4">
                        <ul class="list-unstyled mb-0" style="font-size: 0.85rem; color: #64748B;">
                            <li class="mb-2 d-flex align-items-center">
                                <i class="bx bx-qr text-muted me-2" style="font-size: 1.1rem; width: 20px;"></i>
                                ID: {{ $user->code }}
                            </li>
                            <li class="mb-2 d-flex align-items-center">
                                <i class="bx bx-fingerprint text-muted me-2" style="font-size: 1.1rem; width: 20px;"></i>
                                Biometric ID: {{ $user->biometric_id ?? 'Not Linked' }}
                            </li>
                            <li class="mb-2 d-flex align-items-center">
                                <i class="bx bx-envelope text-muted me-2" style="font-size: 1.1rem; width: 20px;"></i>
                                {{ $user->email }}
                            </li>
                            <li class="mb-2 d-flex align-items-center">
                                <i class="bx bx-phone text-muted me-2" style="font-size: 1.1rem; width: 20px;"></i>
                                {{ $user->phone ?? 'N/A' }}
                            </li>
                            <li class="mb-2 d-flex align-items-center">
                                <i class="bx bx-calendar text-muted me-2" style="font-size: 1.1rem; width: 20px;"></i>
                                {{ $user->dob ? \Carbon\Carbon::parse($user->dob)->format('d M Y') : 'N/A' }}
                            </li>
                            <li class="mb-2 d-flex align-items-center">
                                <i class="bx bx-building-house text-muted me-2" style="font-size: 1.1rem; width: 20px;"></i>
                                {{ $user->department ? $user->department->name : 'N/A Dept.' }} | Head Office
                            </li>
                            <li class="mb-0 d-flex align-items-center">
                                <i class="bx bx-file text-muted me-2" style="font-size: 1.1rem; width: 20px;"></i>
                                Contract: Full-Time
                            </li>
                        </ul>
                    </div>

                    <!-- Hidden File Input for Profile Picture Upload -->
                    <form id="profilePictureForm" action="{{ route('employee.changeEmployeeProfilePicture') }}" method="POST" enctype="multipart/form-data" style="display: none;">
                        @csrf
                        <input type="hidden" name="userId" id="userId" value="{{ $user->id }}">
                        <input type="file" id="file" name="file" accept="image/*">
                    </form>
                </div>
            </div>
            <!-- /User Card -->


            <!-- No Management Control for Employee Self-Profile -->

            <div class="text-center mb-4">
                <p class="text-muted" style="font-size: 0.75rem;"> Account created on <strong>{{ Carbon::parse($user->created_at)->format('d M Y') }}</strong> by <strong>{{ $user->createdBy != null ? $user->createdBy->getFullName() : 'Admin' }}.</strong></p>
            </div>
            <!-- /Work Card -->


        </div>
        <!-- User Content -->
        <div class="col-xl-9 col-lg-9 col-md-8 col-12">
            <style>
                /* =================== TAB NAVIGATION =================== */
                .rosemary-nav-tabs-wrapper {
                    background-color: #F8FAFC;
                    border-radius: 50px;
                    border: 1px solid #E2E8F0;
                    padding: 8px;
                    width: 100%;
                    max-width: 100%;
                    margin-bottom: 2rem;
                    box-shadow: 0 4px 15px rgba(0,0,0,0.02);
                    display: flex;
                    align-items: center;
                    position: relative;
                    z-index: 10;
                }
                .rosemary-nav-tabs {
                    display: flex;
                    justify-content: center;
                    flex-wrap: nowrap !important;
                    overflow-x: auto !important;
                    gap: 1.5rem;
                    border: none !important;
                    width: 100%;
                    -ms-overflow-style: none;
                    scrollbar-width: none;
                }
                .rosemary-nav-tabs::-webkit-scrollbar { display: none; }
                .rosemary-nav-tabs .nav-link {
                    color: #718096 !important;
                    font-weight: 700;
                    font-size: 0.75rem;
                    border: none;
                    padding: 0.75rem 1.5rem !important;
                    border-radius: 50px !important;
                    transition: all 0.3s ease;
                    text-transform: capitalize;
                    letter-spacing: 0.3px;
                    background-color: transparent !important;
                    display: flex;
                    align-items: center;
                    white-space: nowrap !important;
                    flex-shrink: 0;
                }
                .rosemary-nav-tabs .nav-link.active {
                    background-color: #127464 !important;
                    color: #fff !important;
                    box-shadow: 0 4px 12px rgba(18, 116, 100, 0.25);
                }
                .rosemary-nav-tabs .nav-link:hover:not(.active) {
                    background-color: rgba(18, 116, 100, 0.05) !important;
                    color: #127464 !important;
                }

                @media (max-width: 991px) {
                    .rosemary-nav-tabs-wrapper {
                        display: none !important; /* Hide full header on mobile */
                    }
                    .mobile-tab-navigation {
                        display: flex !important;
                        justify-content: space-between;
                        padding: 1rem;
                        background: #fff;
                        border-top: 1px solid #eee;
                        position: fixed;
                        bottom: 0;
                        left: 0;
                        right: 0;
                        z-index: 100;
                        box-shadow: 0 -5px 15px rgba(0,0,0,0.05);
                    }
                }
                .mobile-tab-navigation { display: none; }

                .emp-field-box:hover { 
                    border-color: #008080; 
                    background-color: rgba(0, 128, 128, 0.02); 
                    box-shadow: 0 4px 12px rgba(0, 128, 128, 0.05); 
                    transform: translateY(-2px);
                }




                /* =================== STATUS BOX IN SIDEBAR =================== */
                .emp-status-box {
                    border: 1px solid #E2E8F0 !important;
                    border-radius: 12px;
                    padding: 1.25rem;
                    background: #fff;
                    margin-bottom: 1.5rem;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.02);
                }
            </style>


            <!-- Tabs Navigation -->
            <div class="rosemary-nav-tabs-wrapper mb-4">
                <ul class="nav nav-pills border-0 flex-column flex-md-row rosemary-nav-tabs" id="employeeTabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#basic-info"><i class="bx bx-user me-1"></i> Basic Info</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#employment"><i class="bx bx-briefcase me-1"></i> Employment</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#contact"><i class="bx bx-phone me-1"></i> Contact</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#banking"><i class="bx bx-credit-card me-1"></i> Banking</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#documents"><i class="bx bx-file me-1"></i> Documents</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#payroll"><i class="bx bx-wallet me-1"></i> Payroll</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#kpi"><i class="bx bx-trending-up me-1"></i> KPI</a>
                    </li>
                </ul>
            </div>
            <!-- /Tabs Navigation -->

            <!-- Tab Content -->
            <div class="tab-content p-0 m-0 border-0 shadow-none">

                <!-- Basic Info Tab -->
                <div class="tab-pane fade show active" id="basic-info">
                    <div class="card mb-4 emp-card">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="d-flex align-items-center">
                                    <i class="bx bx-info-circle me-2 fs-5" style="color: #127464;"></i>
                                    <h6 class="mb-0 fw-bold" style="color: #1E293B;">Basic Information</h6>
                                </div>
                                <button class="btn btn-sm text-white px-4 rounded-pill shadow-sm" style="background-color: #127464;" data-bs-toggle="modal" data-bs-target="#offcanvasEditBasicInfo">
                                    <i class="bx bx-edit-alt me-1"></i> Edit Basic Info
                                </button>
                            </div>

                            <div class="row g-4">
                                <!-- First & Last Name -->
                                <div class="col-md-6">
                                    <div class="emp-field-box">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-white rounded p-2 me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="bx bx-user text-muted fs-4"></i>
                                            </div>
                                            <div>
                                                <p class="mb-0 text-muted small fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;">First Name</p>
                                                <p class="mb-0 fw-bold text-dark">{{ $user->first_name }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="emp-field-box">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-white rounded p-2 me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="bx bx-user text-muted fs-4"></i>
                                            </div>
                                            <div>
                                                <p class="mb-0 text-muted small fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;">Last Name</p>
                                                <p class="mb-0 fw-bold text-dark">{{ $user->last_name }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- MARITAL STATUS -->
                                <div class="col-md-6">
                                    <div class="emp-field-box border-0 shadow-xs" style="background: #fdf2f2; border-start: 4px solid #ef4444 !important;">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-white rounded p-2 me-3 shadow-xs d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="bx bx-heart text-danger fs-4"></i>
                                            </div>
                                            <div>
                                                <p class="mb-0 text-muted smallest fw-bold text-uppercase">Marital Status</p>
                                                <p class="mb-0 fw-bold text-dark">{{ ucfirst($user->marital_status ?? 'Single') }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- BLOOD GROUP -->
                                <div class="col-md-6">
                                    <div class="emp-field-box border-0 shadow-xs" style="background: #f0fdf4; border-start: 4px solid #22c55e !important;">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-white rounded p-2 me-3 shadow-xs d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="bx bx-droplet text-success fs-4"></i>
                                            </div>
                                            <div>
                                                <p class="mb-0 text-muted smallest fw-bold text-uppercase">Blood Group</p>
                                                <p class="mb-0 fw-extrabold text-dark">{{ $user->blood_group ?? 'O+' }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- PARENTS DETAILS -->
                                <div class="col-md-6">
                                    <div class="emp-field-box">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-white rounded p-2 me-3 shadow-xs d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="bx bx-male text-muted fs-4"></i>
                                            </div>
                                            <div>
                                                <p class="mb-0 text-muted smallest fw-bold text-uppercase">Father's Name</p>
                                                <p class="mb-0 fw-bold text-dark">{{ $user->father_name ?? 'N/A' }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="emp-field-box">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-white rounded p-2 me-3 shadow-xs d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="bx bx-female text-muted fs-4"></i>
                                            </div>
                                            <div>
                                                <p class="mb-0 text-muted smallest fw-bold text-uppercase">Mother's Name</p>
                                                <p class="mb-0 fw-bold text-dark">{{ $user->mother_name ?? 'N/A' }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                @if($user->marital_status == 'married')
                                <div class="col-md-6">
                                    <div class="emp-field-box border-primary" style="background: #eff6ff;">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-white rounded p-2 me-3 shadow-xs d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="bx bx-user-circle text-primary fs-4"></i>
                                            </div>
                                            <div>
                                                <p class="mb-0 text-primary smallest fw-bold text-uppercase">Spouse Name</p>
                                                <p class="mb-0 fw-bold text-dark">{{ $user->spouse_name ?? 'N/A' }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="emp-field-box">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-white rounded p-2 me-3 shadow-xs d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="bx bx-group text-muted fs-4"></i>
                                            </div>
                                            <div>
                                                <p class="mb-0 text-muted smallest fw-bold text-uppercase">No. of Children</p>
                                                <p class="mb-0 fw-bold text-dark">{{ $user->no_of_children ?? '0' }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endif

                                <!-- NATIONALITY -->
                                <div class="col-md-6">
                                    <div class="emp-field-box">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-white rounded p-2 me-3 shadow-xs d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="bx bx-globe text-muted fs-4"></i>
                                            </div>
                                            <div>
                                                <p class="mb-0 text-muted smallest fw-bold text-uppercase">Birth Country</p>
                                                <p class="mb-0 fw-bold text-dark">{{ $user->birth_country ?? 'N/A' }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="emp-field-box">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-white rounded p-2 me-3 shadow-xs d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="bx bx-flag text-muted fs-4"></i>
                                            </div>
                                            <div>
                                                <p class="mb-0 text-muted smallest fw-bold text-uppercase">Citizenship</p>
                                                <p class="mb-0 fw-bold text-dark">{{ $user->citizenship ?? 'N/A' }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Employment Tab -->
                <div class="tab-pane fade" id="employment">
                    <div class="card mb-4 emp-card">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="d-flex align-items-center">
                                    <i class="bx bx-briefcase me-2 fs-5" style="color: #127464;"></i>
                                    <h6 class="mb-0 fw-bold" style="color: #1E293B;">Work Information</h6>
                                </div>
                            </div>

                            <div class="row g-4">
                                <!-- Designation -->
                                <div class="col-md-6">
                                    <div class="emp-field-box">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-white rounded p-2 me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="bx bx-award text-muted fs-4"></i>
                                            </div>
                                            <div>
                                                <p class="mb-0 text-muted small fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;">Designation</p>
                                                <p class="mb-0 fw-bold text-dark">{{ $user->designation?->name ?? 'N/A' }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Department/Team -->
                                <div class="col-md-6">
                                    <div class="emp-field-box">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-white rounded p-2 me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="bx bx-group text-muted fs-4"></i>
                                            </div>
                                            <div>
                                                <p class="mb-0 text-muted small fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;">Department / Team</p>
                                                <p class="mb-0 fw-bold text-dark">{{ $user->team != null ? $user->team->name : 'N/A' }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row g-4 mt-2">
                                <div class="col-md-6">
                                    <div class="emp-field-box">
                                        <p class="mb-1 text-muted smallest fw-bold text-uppercase">Reporting Manager</p>
                                        <p class="mb-0 fw-bold text-dark">{{ $user->reporting_to_id ? $user->getReportingToUserName() : 'N/A' }}</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="emp-field-box">
                                        <p class="mb-1 text-muted smallest fw-bold text-uppercase">Access Role</p>
                                        <p class="mb-0 fw-bold text-dark">{{ ucfirst($user->getRoleNames()->first() ?? 'Employee') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Device Information Card -->
                    <div class="card mb-4 emp-card">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="d-flex align-items-center">
                                    <i class="bx bx-devices me-2 fs-5" style="color: #127464;"></i>
                                    <h6 class="mb-0 fw-bold" style="color: #1E293B;">Device Information</h6>
                                </div>
                            </div>

                            @if ($user->userDevice)
                                <div class="row g-4">
                                    <div class="col-md-3">
                                        <div class="p-3 rounded-4" style="background-color: #F8FAFC; border: 1px solid #E2E8F0;">
                                            <p class="mb-1 text-muted smallest fw-bold text-uppercase">Device ID</p>
                                            <p class="mb-0 fw-bold text-dark small text-truncate">{{ $user->userDevice->device_id }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="p-3 rounded-4" style="background-color: #F8FAFC; border: 1px solid #E2E8F0;">
                                            <p class="mb-1 text-muted smallest fw-bold text-uppercase">Brand / Model</p>
                                            <p class="mb-0 fw-bold text-dark small text-truncate">{{ $user->userDevice->brand ?? 'N/A' }}</p>
                                        </div>
                                    </div>
                                    @php
                                        $assignedAsset = $user->currentAssets()->first();
                                    @endphp
                                    <div class="col-md-3">
                                        <div class="p-3 rounded-4" style="background-color: #F0FAFA; border: 1px solid #127464; border-style: dashed;">
                                            <p class="mb-1 text-success smallest fw-bold text-uppercase">Serial Number</p>
                                            <p class="mb-0 fw-bold text-dark small text-truncate">{{ $assignedAsset->serial_number ?? 'N/A' }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="p-3 rounded-4" style="background-color: #F0FAFA; border: 1px solid #127464; border-style: dashed;">
                                            <p class="mb-1 text-success smallest fw-bold text-uppercase">Service Tag</p>
                                            <p class="mb-0 fw-bold text-dark small text-truncate">{{ $assignedAsset->notes ?? 'N/A' }}</p>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="text-center py-5 rounded-3" style="border: 2px dashed #E2E8F0; background-color: #F8FAFC;">
                                    <i class="bx bx-mobile-alt text-muted mb-2" style="font-size: 2.5rem;"></i>
                                    <p class="text-muted small mb-0">No device linked to this employee yet.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>





                <!-- Contact Tab -->
                <div class="tab-pane fade" id="contact">
                    <div class="card mb-4 emp-card border-0">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="d-flex align-items-center">
                                    <i class="bx bx-phone-call me-2 fs-5" style="color: #127464;"></i>
                                    <h6 class="mb-0 fw-bold" style="color: #1E293B;">Contact & Address Details</h6>
                                </div>
                                <button class="btn btn-sm text-white px-4 rounded-pill shadow-sm" style="background-color: #127464;" data-bs-toggle="modal" data-bs-target="#offcanvasEditContactInfo">
                                    <i class="bx bx-edit-alt me-1"></i> Edit Contact Info
                                </button>
                            </div>

                            <div class="row g-4">
                                <!-- EMAIL & PHONE -->
                                <div class="col-md-4">
                                    <div class="emp-field-box border-dashed">
                                        <p class="mb-1 text-muted smallest fw-bold text-uppercase">Login / Official Email</p>
                                        <p class="mb-0 fw-bold text-dark">{{ $user->email }}</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="emp-field-box">
                                        <p class="mb-1 text-muted smallest fw-bold text-uppercase">Personal Email</p>
                                        <p class="mb-0 fw-bold text-dark">{{ $user->personal_email ?? 'Not Provided' }}</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="emp-field-box">
                                        <p class="mb-1 text-muted smallest fw-bold text-uppercase">Primary / Personal Phone</p>
                                        <p class="mb-0 fw-bold text-dark">{{ $user->phone }}</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="emp-field-box">
                                        <p class="mb-1 text-muted smallest fw-bold text-uppercase">Official Phone</p>
                                        <p class="mb-0 fw-bold text-dark">{{ $user->official_phone ?? 'Not Provided' }}</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="emp-field-box">
                                        <p class="mb-1 text-muted smallest fw-bold text-uppercase">Alternate Phone</p>
                                        <p class="mb-0 fw-bold text-dark">{{ $user->alternate_number ?? 'Not Provided' }}</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="emp-field-box">
                                        <p class="mb-1 text-muted smallest fw-bold text-uppercase">Home Phone</p>
                                        <p class="mb-0 fw-bold text-dark">{{ $user->home_phone ?? 'Not Provided' }}</p>
                                    </div>
                                </div>

                                <!-- DUAL ADDRESS SECTION -->
                                <div class="col-12 mt-5">
                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <div class="p-4 rounded-4" style="background: #f8fafc; border: 1px solid #eef2f6;">
                                                <div class="d-flex align-items-center mb-3">
                                                    <div class="bg-white shadow-sm rounded-pill p-2 me-3" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;"><i class="bx bx-map-pin" style="color: #127464;"></i></div>
                                                    <h6 class="mb-0 fw-bold text-dark">Current Address</h6>
                                                </div>
                                                <p class="mb-0 text-muted lh-base">
                                                    @if($user->temp_street || $user->temp_building)
                                                        {{ $user->temp_building }}<br>
                                                        {{ $user->temp_street }}<br>
                                                        {{ $user->temp_city }}, {{ $user->temp_state }} {{ $user->temp_zip }}<br>
                                                        {{ $user->temp_country }}
                                                    @else
                                                        <span class="fst-italic text-sm">No current address recorded.</span>
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="p-4 rounded-4" style="background: #fdf2f2; border: 1px solid #fee2e2;">
                                                <div class="d-flex align-items-center mb-3">
                                                    <div class="bg-white shadow-sm rounded-pill p-2 me-3" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;"><i class="bx bx-home-heart" style="color: #127464;"></i></div>
                                                    <h6 class="mb-0 fw-bold text-dark">Permanent Address</h6>
                                                </div>
                                                <p class="mb-0 text-muted lh-base">
                                                    @if($user->perm_street || $user->perm_building)
                                                        {{ $user->perm_building }}<br>
                                                        {{ $user->perm_street }}<br>
                                                        {{ $user->perm_city }}, {{ $user->perm_state }} {{ $user->perm_zip }}<br>
                                                        {{ $user->perm_country }}
                                                    @else
                                                        <span class="fst-italic text-sm">Same as current or not provided.</span>
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- EMERGENCY CONTACT SECTION -->
                                <div class="col-12 mt-4">
                                    <div class="card bg-label-danger border-0 p-4">
                                        <div class="d-flex align-items-center mb-3">
                                            <i class="bx bxs-ambulance me-2 fs-3" style="color: #127464;"></i>
                                            <h6 class="mb-0 fw-bold" style="color: #127464;">Emergency Contact Details</h6>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <span class="d-block smallest text-muted mb-1">CONTACT PERSON</span>
                                                <strong class="text-dark">{{ $user->emergency_contact_name ?? 'N/A' }}</strong>
                                            </div>
                                            <div class="col-md-4">
                                                <span class="d-block smallest text-muted mb-1">RELATIONSHIP</span>
                                                <strong class="text-dark">{{ $user->emergency_contact_relation ?? 'N/A' }}</strong>
                                            </div>
                                            <div class="col-md-4">
                                                <span class="d-block smallest text-muted mb-1">CONTACT PHONE</span>
                                                <strong class="text-dark">{{ $user->emergency_contact_phone ?? 'N/A' }}</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Banking Tab -->
                <div class="tab-pane fade" id="banking">
                    <div class="card mb-4 emp-card">
                        <div class="card-body p-4">
                 <!-- Banking Tab Card Header -->
                                 <div class="d-flex justify-content-between align-items-center mb-4">
                                 <div class="d-flex align-items-center">
                                     <i class="bx bx-credit-card me-2 fs-5" style="color: #127464;"></i>
                                     <h6 class="mb-0 fw-bold" style="color: #1E293B;">Bank Account Details</h6>
                                 </div>
                                 <button class="btn btn-sm text-white rounded-pill px-4 shadow-sm" style="background-color: #127464;" data-bs-toggle="modal" data-bs-target="#editBankAccountModal">
                                     <i class="bx bx-edit-alt me-1"></i> Update Bank Details
                                 </button>
                             </div>

                            @php
                                $bank = $user->bankAccount ?: $user->bank_account;
                                // Even more defensive check to prevent 'property on null'
                                $hasBankData = $bank && (is_object($bank) || is_array($bank)) && (isset($bank->bank_name) || isset($bank->account_number));
                            @endphp

                            @if ($hasBankData)
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <div class="emp-field-box">
                                            <p class="mb-1 text-muted smallest fw-bold text-uppercase">Beneficiary Name</p>
                                            <p class="mb-0 fw-bold text-dark">{{ $bank->account_name }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="emp-field-box">
                                            <p class="mb-1 text-muted smallest fw-bold text-uppercase">Bank Name</p>
                                            <p class="mb-0 fw-bold text-dark">{{ $bank->bank_name }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="emp-field-box border-dashed">
                                            <p class="mb-1 text-muted smallest fw-bold text-uppercase">Account Number</p>
                                            <p class="mb-0 fw-extrabold text-dark">•••• •••• •••• {{ substr($bank->account_number, -4) }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="emp-field-box">
                                            <p class="mb-1 text-muted smallest fw-bold text-uppercase">IFSC / Bank Code</p>
                                            <p class="mb-0 fw-bold text-dark">{{ $bank->bank_code }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="emp-field-box">
                                            <p class="mb-1 text-muted smallest fw-bold text-uppercase">Branch Name</p>
                                            <p class="mb-0 fw-bold text-dark">{{ $bank->branch_name ?? 'N/A' }}</p>
                                        </div>
                                    </div>
                                    @if($bank->passbook_path)
                                    <div class="col-md-6">
                                        <div class="emp-field-box border-dashed" style="background-color: #F0FAFA;">
                                            <p class="mb-1 text-muted smallest fw-bold text-uppercase">Bank Document</p>
                                            <div class="d-flex align-items-center justify-content-between">
                                                <span class="small fw-bold text-dark"><i class="bx bx-file me-1"></i> Bank Passbook / Cheque</span>
                                                <a href="javascript:void(0)" class="btn btn-xs rounded-pill px-3" style="font-size: 0.65rem; background:#127464; color:#fff;" onclick="viewDocumentPopup('{{ \App\Helpers\FileSecurityHelper::generateSecureUrl($bank->passbook_path) }}', 'Bank Passbook')">View</a>
                                            </div>
                                        </div>
                                    </div>
                                    @endif
                                </div>
                            @else
                                <div class="text-center py-5 rounded-4" style="background: #f8fafc; border: 2px dashed #e2e8f0;">
                                    <i class="bx bx-landmark text-muted mb-2" style="font-size: 3rem;"></i>
                                    <p class="text-muted fw-bold mb-0">No bank details added yet.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                    
                    {{-- Security Notice (Banking) --}}
                    <div class="d-flex align-items-center p-3 mt-3 emp-card" style="background-color: #F0FAFA; border: 1px solid #CCECEC !important;">
                        <div class="rounded-pill p-2 me-3 d-flex align-items-center justify-content-center" style="background-color: #CCECEC; width: 40px; height: 40px;">
                            <i class="bx bx-shield-quarter" style="color: #127464; font-size: 1.25rem;"></i>
                        </div>
                        <div>
                            <h6 class="mb-1 fw-bold" style="color: #127464; font-size: 0.85rem;">Security Notice</h6>
                            <p class="mb-0 text-muted" style="font-size: 0.78rem;">Banking details are encrypted and auditing is enabled for all modifications.</p>
                        </div>
                    </div>

                    @php
                        $pendingBankRequest = \App\Models\ProfileUpdateApproval::where('user_id', $user->id)
                            ->where('type', 'bank_details')
                            ->where('status', 'pending')
                            ->latest()
                            ->first();
                    @endphp
                    @if($pendingBankRequest)
                    <div class="d-flex align-items-center p-3 mt-3 emp-card" style="background-color: #FFF7ED; border: 1px solid #FED7AA !important;">
                        <div class="rounded-pill p-2 me-3 d-flex align-items-center justify-content-center" style="background-color: #FED7AA; width: 40px; height: 40px;">
                            <i class="bx bx-time-five" style="color: #d97706; font-size: 1.25rem;"></i>
                        </div>
                        <div>
                            <h6 class="mb-1 fw-bold" style="color: #d97706; font-size: 0.85rem;">Update Pending Approval</h6>
                            <p class="mb-0 text-muted" style="font-size: 0.78rem;">Your bank details update request is awaiting HR/Admin approval. Submitted {{ $pendingBankRequest->created_at->diffForHumans() }}.</p>
                        </div>
                    </div>
                    @endif
                </div>

                <!-- Documents Tab -->
                <div class="tab-pane fade" id="documents">
                    <div class="card mb-4 emp-card">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="d-flex align-items-center">
                                    <i class="bx bx-file me-2 fs-5" style="color: #127464;"></i>
                                    <h6 class="mb-0 fw-bold" style="color: #1E293B;">Employee Documents</h6>
                                </div>
                                <button class="btn btn-sm text-white rounded-pill px-4 shadow-sm" style="background-color: #127464;" data-bs-toggle="modal" data-bs-target="#modalAddUserDocument" onclick="setDocModal('Other Document', '')">
                                    <i class="bx bx-plus me-1"></i> Add New Document
                                </button>
                            </div>

                            @php
                                $pendingDocCount = $user->documentRequests->where('status', 'pending')->count();
                            @endphp
                            @if($pendingDocCount > 0)
                            <div class="d-flex align-items-center p-3 mb-4 rounded-3" style="background-color: #FFF7ED; border: 1px solid #FED7AA;">
                                <i class="bx bx-time-five me-3 fs-4" style="color: #d97706;"></i>
                                <div>
                                    <span class="fw-bold" style="color: #d97706; font-size: 0.85rem;">{{ $pendingDocCount }} document update(s) pending HR/Admin approval.</span>
                                    <p class="mb-0 text-muted" style="font-size: 0.75rem;">Documents will appear as verified once approved.</p>
                                </div>
                            </div>
                            @endif

                            @php
                                $mandatoryDocs = [
                                    ['name' => 'Aadhar Card', 'key' => 'aadhaar_no', 'icon' => 'bx-id-card'],
                                    ['name' => 'Pan Card', 'key' => 'pan_no', 'icon' => 'bx-credit-card-front'],
                                    ['name' => '10th Marksheet', 'key' => null, 'icon' => 'bx-certification'],
                                    ['name' => 'Intermediate Marksheet', 'key' => null, 'icon' => 'bx-certification'],
                                    ['name' => 'Graduation Marksheet', 'key' => null, 'icon' => 'bx-certification'],
                                ];
                            @endphp

                            <h6 class="fw-bold mb-3 small text-muted text-uppercase" style="letter-spacing: 1px; font-size: 0.7rem;">Essential Verification Documents</h6>
                            <div class="row g-3">
                                @foreach($mandatoryDocs as $doc)
                                    @php
                                        $isSubmitted = false;
                                        $docFile = null;
                                        $docNumber = 'N/A';

                                        if ($doc['key'] && $user->{$doc['key']}) {
                                            $isSubmitted = true;
                                            $docNumber = $user->{$doc['key']};
                                        }

                                        $request = $user->documentRequests->where('status', 'approved')->filter(function($r) use ($doc) {
                                            return $r->documentType && strtolower($r->documentType->name) == strtolower($doc['name']);
                                        })->first();

                                        if ($request) {
                                            $isSubmitted = true;
                                            $docFile = $request->generated_file;
                                            if ($request->remarks) $docNumber = $request->remarks;
                                        }
                                    @endphp
                                    <div class="col-md-6">
                                        <div class="p-3 rounded-3 d-flex align-items-center justify-content-between" style="background-color: #F8FAFC; border: 1px solid #E2E8F0;">
                                            <div class="d-flex align-items-center overflow-hidden">
                                                <div class="rounded-3 p-2 me-3 d-flex align-items-center justify-content-center flex-shrink-0" style="background-color: {{ $isSubmitted ? '#E6F4F1' : '#F1F5F9' }}; width: 44px; height: 44px; border: 1px solid {{ $isSubmitted ? '#A7D9CF' : '#E2E8F0' }};">
                                                    <i class="bx {{ $doc['icon'] }} {{ $isSubmitted ? '' : 'text-muted' }} fs-4" style="{{ $isSubmitted ? 'color:#127464' : '' }}"></i>
                                                </div>
                                                <div class="overflow-hidden">
                                                    <p class="mb-0 fw-bold text-dark small" style="line-height: 1.2;">{{ $doc['name'] }}</p>
                                                    <span class="badge" style="font-size: 0.55rem; padding: 0.2rem 0.5rem; border-radius: 4px; background-color: {{ $isSubmitted ? '#127464' : '#94A3B8' }}; color:#fff;">{{ $isSubmitted ? 'SUBMITTED' : 'NOT SUBMITTED' }}</span>
                                                </div>
                                            </div>
                                            <div class="d-flex gap-2">
                                                                                                @if($isSubmitted)
                                                    @if($docFile)
                                                        <a href="javascript:void(0)" class="btn btn-xs rounded-pill px-3" style="font-size: 0.65rem; background:#127464; color:#fff; border:1px solid #127464;" onclick="viewDocumentPopup('{{ \App\Helpers\FileSecurityHelper::generateSecureUrl($docFile) }}', '{{ $doc['name'] }}')"><i class="bx bx-show me-1"></i>View</a>
                                                    @elseif($docNumber && $docNumber !== 'N/A')
                                                        <a href="javascript:void(0)" class="btn btn-xs rounded-pill px-3" style="font-size: 0.65rem; background:#127464; color:#fff; border:1px solid #127464;" onclick="viewDocumentNumber('{{ $doc['name'] }}', '{{ $docNumber }}')"><i class="bx bx-show me-1"></i>View</a>
                                                    @endif
                                                    <button class="btn btn-xs btn-outline-hitech rounded-pill px-3" style="font-size: 0.65rem;" data-bs-toggle="modal" data-bs-target="#modalAddUserDocument" onclick="setDocModal('{{ $doc['name'] }}', '{{ $docNumber }}')">Update</button>
                                                @else
                                                    <button class="btn btn-xs btn-hitech rounded-pill px-3" style="font-size: 0.65rem; background-color: #127464; color: #fff;" data-bs-toggle="modal" data-bs-target="#modalAddUserDocument" onclick="setDocModal('{{ $doc['name'] }}', '')">Upload</button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            @if($user->passport_no || $user->visa_type || $user->frro_registration || $user->documentRequests->where('status', 'approved')->count() > 0)
                            <hr class="my-4" style="border-style: dashed; opacity: 0.1;">
                            <!-- Other Identity Proofs -->
                            <h6 class="fw-bold mb-3 small text-muted text-uppercase" style="letter-spacing: 1px; font-size: 0.7rem;">Other Identity Proofs</h6>
                            <div class="row g-3">
                                @if($user->passport_no)
                                <div class="col-md-4">
                                    <div class="emp-field-box">
                                        <p class="mb-1 text-muted small fw-bold text-uppercase" style="font-size: 0.6rem;">Passport No.</p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="fw-bold text-dark small text-truncate">{{ $user->passport_no }}</span>
                                            <i class="bx bxs-check-shield text-success"></i>
                                        </div>
                                    </div>
                                </div>
                                @endif
                                @if($user->visa_type)
                                <div class="col-md-4">
                                    <div class="emp-field-box">
                                        <p class="mb-1 text-muted small fw-bold text-uppercase" style="font-size: 0.6rem;">Visa Status</p>
                                        <span class="small fw-semibold text-dark">{{ $user->visa_type }}</span>
                                    </div>
                                </div>
                                @endif
                                @if($user->documentRequests->where('status', 'approved')->count() > 0)
                                <div class="col-md-4">
                                    <div class="emp-field-box">
                                        <p class="mb-1 text-muted small fw-bold text-uppercase" style="font-size: 0.6rem;">Additional Docs</p>
                                        <span class="badge" style="background:#127464;color:#fff;">{{ $user->documentRequests->where('status', 'approved')->count() }} Added</span>
                                    </div>
                                </div>
                                @endif
                                @if($user->visa_type || $user->frro_registration)
                                <div class="col-12 mt-2 pt-3 border-top">
                                    <div class="row g-4">
                                        @if($user->visa_type)
                                        <div class="col-md-6">
                                            <h6 class="fw-bold mb-2 small text-muted text-uppercase" style="font-size: 0.65rem;">Visa Information</h6>
                                            <div class="p-3 rounded-3" style="background-color: #F8FAFC; border: 1px solid #F1F5F9;">
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span class="text-muted small">Type: <strong>{{ $user->visa_type }}</strong></span>
                                                    <span class="text-muted small">Expires: <strong>{{ $user->visa_expiry_date ?? 'N/A' }}</strong></span>
                                                </div>
                                            </div>
                                        </div>
                                        @endif
                                        @if($user->frro_registration)
                                        <div class="col-md-6">
                                            <h6 class="fw-bold mb-2 small text-muted text-uppercase" style="font-size: 0.65rem;">FRRO Registration</h6>
                                            <div class="p-3 rounded-3" style="background-color: #F8FAFC; border: 1px solid #F1F5F9;">
                                                <div class="d-flex justify-content-between">
                                                    <span class="text-muted small">Number: <strong>{{ $user->frro_registration }}</strong></span>
                                                    <span class="text-muted small">Expires: <strong>{{ $user->frro_expiry_date ?? 'N/A' }}</strong></span>
                                                </div>
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                                @endif
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Payroll Tab -->
                <div class="tab-pane fade" id="payroll">
                    <div class="row g-4">
                        <div class="col-12">
                            <!-- Compensation Summary - Cards Style -->
                            <div class="card mb-4 emp-card shadow-sm border-0" style="border-radius: 12px;">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center justify-content-between mb-4 mt-0">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary bg-opacity-10 p-2 rounded-circle me-3">
                                                <i class="bx bx-money fs-4 text-primary"></i>
                                            </div>
                                            <h6 class="mb-0 fw-bold" style="color: #1E293B;">Compensation Details</h6>
                                        </div>
                                    </div>
        
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <div class="emp-field-box p-3 rounded-hitech h-100" style="background: #fff; border: 1px solid #eef2f6; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                                                <p class="mb-1 text-muted small fw-bold text-uppercase" style="font-size: 0.6rem;">Base Monthly Salary</p>
                                                <p class="mb-0 fw-bold text-dark h5">₹{{ number_format($user->base_salary) }}</p>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="emp-field-box p-3 rounded-hitech h-100" style="background: #fff; border: 1px solid #eef2f6; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                                                <p class="mb-1 text-muted small fw-bold text-uppercase" style="font-size: 0.6rem;">CTC Offered (Annum)</p>
                                                <p class="mb-0 fw-bold text-dark h5">₹{{ number_format($user->ctc_offered) }}</p>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="emp-field-box p-3 rounded-3 h-100" style="background: #fff; border: 1px solid #eef2f6; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                                                <p class="mb-1 text-muted small fw-bold text-uppercase" style="font-size: 0.6rem;">Payroll Status</p>
                                                <div class="d-flex align-items-center gap-2">
                                                    <p class="mb-0 fw-bold text-success">Active</p>
                                                    <span class="badge rounded-pill" style="font-size: 0.55rem; background-color: #E0F2F1; color: #127464;">ON CYCLE</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="emp-field-box p-3 rounded-3 h-100" style="background: #fff; border: 1px solid #eef2f6; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                                                <p class="mb-1 text-muted small fw-bold text-uppercase" style="font-size: 0.6rem;">Pay Frequency</p>
                                                <p class="mb-0 fw-bold text-dark h5">Monthly</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Allowances & Deductions section -->
                            <div class="card mb-4 emp-card shadow-sm border-0" style="border-radius: 12px;">
                                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-success bg-opacity-10 p-2 rounded-circle me-3">
                                            <i class="bx bx-list-check fs-4 text-success"></i>
                                        </div>
                                        <h6 class="mb-0 fw-bold" style="color: #1E293B;">Allowances & Deductions</h6>
                                    </div>
                                </div>
                                <div class="card-body p-4">
                                    @if ($user->payrollAdjustments->count() > 0)
                                        <div class="row g-3">
                                        @foreach ($user->payrollAdjustments as $adjustment)
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center justify-content-between p-3 rounded-3" style="background-color: #F8FAFC; border: 1px solid #F1F5F9;">
                                                    <div class="d-flex align-items-center">
                                                        <div class="bg-white p-2 rounded-pill me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                                            <i class="bx {{ $adjustment->type === 'benefit' ? 'bx-trending-up text-success' : 'bx-trending-down text-danger' }} fs-5"></i>
                                                        </div>
                                                        <div>
                                                            <p class="mb-0 fw-bold small text-dark">{{ $adjustment->name }}</p>
                                                            <span class="text-muted text-uppercase" style="font-size: 0.55rem;">{{ $adjustment->type }}</span>
                                                        </div>
                                                    </div>
                                                    <div class="text-end">
                                                        <p class="mb-0 fw-bold {{ $adjustment->type === 'benefit' ? 'text-success' : 'text-danger' }}">
                                                            {{ $adjustment->type === 'benefit' ? '+' : '-' }}{{ $settings->currency_symbol }}{{ number_format($adjustment->amount ?? (($adjustment->percentage / 100) * ($user->base_salary ?? 0)), 2) }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                        </div>
                                    @else
                                        <div class="text-center py-4 rounded-3" style="border: 2px dashed #E2E8F0; background-color: #F8FAFC;">
                                            <p class="text-muted mb-0 small">No active adjustments or allowances found for this employee.</p>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Salary Structure Breakdown Toggle -->
                            <div class="card emp-card shadow-sm border-0 mb-4" style="border-radius: 12px; overflow: hidden;">
                                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between cursor-pointer" data-bs-toggle="collapse" data-bs-target="#salaryBreakdownCollapse" aria-expanded="false" style="cursor: pointer;">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary bg-opacity-10 p-2 rounded-circle me-3">
                                            <i class="bx bx-calculator fs-4 text-primary"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0 fw-bold" style="color: #1E293B;">Salary Structure Breakdown</h6>
                                            <p class="mb-0 text-muted smallest italic">Click to view detailed components</p>
                                        </div>
                                    </div>
                                    <i class="bx bx-chevron-down fs-3 text-muted transition-all"></i>
                                </div>
                                <div class="collapse" id="salaryBreakdownCollapse">
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            @php
                                                $ctcAnnum = $user->ctc_offered ?? 0;
                                                $ctcMonth = $ctcAnnum / 12;
                                                
                                                // Breakdown Logic
                                                $basicMonth = $ctcMonth * 0.5;
                                                $hraMonth = $ctcMonth * 0.25;
                                                $medicalMonth = 2500;
                                                $eduMonth = 200;
                                                $ltaMonth = 2500;
                                                
                                                $sumA = $basicMonth + $hraMonth + $medicalMonth + $eduMonth + $ltaMonth;
                                                $specialAllowance = max(0, $ctcMonth - $sumA);
                                                
                                                $profTax = 200;
                                                $pfAmount = 1800; // Standard PF
                                                $deductions = $profTax + $pfAmount;
                                                
                                                $netSalary = $ctcMonth - $deductions;
                                            @endphp
                                            <table class="table table-hover mb-0 align-middle">
                                                <thead class="bg-light">
                                                    <tr>
                                                        <th class="ps-4 py-3 text-muted smallest fw-bold text-uppercase">Component</th>
                                                        <th class="text-end py-3 text-muted smallest fw-bold text-uppercase">Per Month ({{ $settings->currency_symbol }})</th>
                                                        <th class="pe-4 text-end py-3 text-muted smallest fw-bold text-uppercase">Per Annum ({{ $settings->currency_symbol }})</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td class="ps-4 py-3 fw-semibold text-dark">Basic Salary (50% of CTC)</td>
                                                        <td class="text-end py-3 fw-bold">{{ number_format($basicMonth, 2) }}</td>
                                                        <td class="pe-4 text-end py-3 text-muted">{{ number_format($basicMonth * 12, 2) }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="ps-4 py-3 fw-semibold text-dark">HRA (25% of CTC)</td>
                                                        <td class="text-end py-3 fw-bold">{{ number_format($hraMonth, 2) }}</td>
                                                        <td class="pe-4 text-end py-3 text-muted">{{ number_format($hraMonth * 12, 2) }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="ps-4 py-3 fw-semibold text-dark">Medical Reimbursement (Flat)</td>
                                                        <td class="text-end py-3 fw-bold">{{ number_format($medicalMonth, 2) }}</td>
                                                        <td class="pe-4 text-end py-3 text-muted">{{ number_format($medicalMonth * 12, 2) }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="ps-4 py-3 fw-semibold text-dark">Educational Allowance (Flat)</td>
                                                        <td class="text-end py-3 fw-bold">{{ number_format($eduMonth, 2) }}</td>
                                                        <td class="pe-4 text-end py-3 text-muted">{{ number_format($eduMonth * 12, 2) }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="ps-4 py-3 fw-semibold text-dark">LTA (Flat)</td>
                                                        <td class="text-end py-3 fw-bold">{{ number_format($ltaMonth, 2) }}</td>
                                                        <td class="pe-4 text-end py-3 text-muted">{{ number_format($ltaMonth * 12, 2) }}</td>
                                                    </tr>
                                                    <tr class="bg-light bg-opacity-50">
                                                        <td class="ps-4 py-2 text-muted small italic">Total of (A)</td>
                                                        <td class="text-end py-2 fw-medium text-muted small">{{ number_format($sumA, 2) }}</td>
                                                        <td class="pe-4 text-end py-2 text-muted small">{{ number_format($sumA * 12, 2) }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="ps-4 py-3 fw-semibold text-dark">Special Allowance (CTC - A)</td>
                                                        <td class="text-end py-3 fw-bold text-primary">{{ number_format($specialAllowance, 2) }}</td>
                                                        <td class="pe-4 text-end py-3 text-muted">{{ number_format($specialAllowance * 12, 2) }}</td>
                                                    </tr>
                                                    <tr style="background-color: #f8fafc;">
                                                        <td class="ps-4 py-3 fw-extrabold text-dark">Total Monthly CTC</td>
                                                        <td class="text-end py-3 fw-extrabold text-dark fs-5">₹{{ number_format($ctcMonth, 2) }}</td>
                                                        <td class="pe-4 text-end py-3 fw-bold text-dark">₹{{ number_format($ctcAnnum, 2) }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="ps-4 py-3 text-danger fw-semibold">Standard PF (DED)</td>
                                                        <td class="text-end py-3 fw-bold text-danger">-{{ number_format($pfAmount, 2) }}</td>
                                                        <td class="pe-4 text-end py-3 text-muted">-{{ number_format($pfAmount * 12, 2) }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="ps-4 py-3 text-danger fw-semibold">Professional Tax (DED)</td>
                                                        <td class="text-end py-3 fw-bold text-danger">-{{ number_format($profTax, 2) }}</td>
                                                        <td class="pe-4 text-end py-3 text-muted">-{{ number_format($profTax * 12, 2) }}</td>
                                                    </tr>
                                                </tbody>
                                                <tfoot class="bg-dark text-white">
                                                    <tr>
                                                        <td class="ps-4 py-3 fw-bold">NET SALARY (TAKE HOME)</td>
                                                        <td class="text-end py-3 fw-bold fs-5 text-white">₹{{ number_format($netSalary, 2) }}</td>
                                                        <td class="pe-4 text-end py-3 fw-bold text-white">₹{{ number_format($netSalary * 12, 2) }}</td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div> {{-- Close for Compensation Detail card --}}
                        </div>
                    </div>
                </div>


                <!-- KPI Tab -->
                <div class="tab-pane fade" id="kpi">
                    <div class="card border-0 shadow-sm rounded-3 mb-4">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center justify-content-between mb-4">
                                <div class="d-flex align-items-center">
                                    <div class="bg-success bg-opacity-10 p-2 rounded-circle me-3">
                                        <i class="bx bx-line-chart fs-4 text-success"></i>
                                    </div>
                                    <h6 class="mb-0 fw-bold" style="color: #1E293B;">Performance Metrics (KPIs)</h6>
                                </div>
                            </div>
                            
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <div class="emp-field-box text-center h-100 p-4">
                                        <div class="icon-stat-success mb-3 mx-auto" style="width: 50px; height: 50px; background: #e6f4f1; border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid #127464;">
                                            <i class="bx bxs-calendar-check text-success fs-3" style="color: #127464 !important;"></i>
                                        </div>
                                        <h3 class="fw-bold mb-1 text-dark">98.5%</h3>
                                        <p class="text-success mb-2 fw-medium small"><i class="bx bx-trending-up me-1"></i> Excellent</p>
                                        <span class="text-uppercase text-muted fw-bold" style="font-size: 0.65rem; letter-spacing: 1px;">Attendance Rate</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="emp-field-box text-center h-100 p-4">
                                         <div class="icon-stat-primary mb-3 mx-auto" style="width: 50px; height: 50px; background: #f0f4ff; border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid #6366f1;">
                                            <i class="bx bx-target-lock text-primary fs-3" style="color: #6366f1 !important;"></i>
                                        </div>
                                        <h3 class="fw-bold mb-1 text-dark">4.8<span class="fs-6 text-muted">/5</span></h3>
                                        <p class="text-primary mb-2 fw-medium small"><i class="bx bxs-star me-1"></i> Top Performer</p>
                                        <span class="text-uppercase text-muted fw-bold" style="font-size: 0.65rem; letter-spacing: 1px;">Efficiency Score</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="emp-field-box text-center h-100 p-4">
                                         <div class="icon-stat-info mb-3 mx-auto" style="width: 50px; height: 50px; background: #e0f2fe; border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid #0ea5e9;">
                                            <i class="bx bx-check-double text-info fs-3" style="color: #0ea5e9 !important;"></i>
                                        </div>
                                        <h3 class="fw-bold mb-1 text-dark">{{ $user->tasks->where('status', 'completed')->count() }}</h3>
                                        <p class="text-info mb-2 fw-medium small"><i class="bx bx-list-check me-1"></i> Tasks Closed</p>
                                        <span class="text-uppercase text-muted fw-bold" style="font-size: 0.65rem; letter-spacing: 1px;">Tasks Completed</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tasks & Productivity Management -->
                    <div class="hitech-card-white p-0 overflow-hidden mb-6">
                        <div class="card-header bg-white py-4 border-bottom d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <div class="bg-primary bg-opacity-10 p-2 rounded-circle me-3">
                                    <i class="bx bx-task fs-4 text-primary"></i>
                                </div>
                                <h6 class="mb-0 fw-bold text-dark">Tasks & Productivity Tracking</h6>
                            </div>
                        </div>
                            <div class="table-responsive rounded-3 border" style="background-color: #fff;">
                                <table class="table table-hover table-borderless mb-0 align-middle">
                                    <thead style="background-color: #F8FAFC; border-bottom: 1px solid #E2E8F0;">
                                        <tr>
                                            <th class="ps-4 text-muted fw-bold py-3" style="width: 50px; font-size: 0.75rem; text-transform: uppercase;">#</th>
                                            <th class="text-muted fw-bold py-3" style="font-size: 0.75rem; text-transform: uppercase;">Task Title</th>
                                            <th class="text-muted fw-bold py-3" style="font-size: 0.75rem; text-transform: uppercase;">Assigned Date</th>
                                            <th class="text-muted fw-bold py-3" style="font-size: 0.75rem; text-transform: uppercase;">Due Date</th>
                                            <th class="text-muted fw-bold py-3" style="font-size: 0.75rem; text-transform: uppercase;">Status</th>
                                            <th class="pe-4 text-end text-muted fw-bold py-3" style="font-size: 0.75rem; text-transform: uppercase;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($user->tasks as $index => $task)
                                            <tr style="border-bottom: 1px solid #F1F5F9; transition: background-color 0.2s;">
                                                <td class="ps-4 fw-medium text-muted">{{ $index + 1 }}</td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar avatar-sm me-3" style="width: 38px; height: 38px;">
                                                             <span class="avatar-initial rounded-circle {{ ['bg-label-primary', 'bg-label-success', 'bg-label-info', 'bg-label-warning'][rand(0,3)] }} fw-bold" style="font-size: 0.85rem;">{{ substr($task->title, 0, 2) }}</span>
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.9rem;">{{ $task->title }}</h6>
                                                            <small class="text-muted d-block text-truncate" style="max-width: 200px;">{{ \Illuminate\Support\Str::limit(strip_tags($task->description), 30) }}</small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-dark small">{{ $task->created_at->format('d M, Y') }}</td>
                                                <td class="text-dark small">{{ $task->due_date ? $task->due_date->format('d M, Y') : 'N/A' }}</td>
                                                <td>
                                                    @php
                                                        $badgeData = match($task->status) {
                                                            'new' => ['class' => 'bg-info', 'text' => 'New', 'bg' => '#E0F2FE', 'color' => '#0284C7'],
                                                            'in_progress' => ['class' => 'bg-warning', 'text' => 'In Progress', 'bg' => '#FEF3C7', 'color' => '#D97706'],
                                                            'completed' => ['class' => 'bg-success', 'text' => 'Completed', 'bg' => '#DCFCE7', 'color' => '#16A34A'],
                                                            'closed' => ['class' => 'bg-secondary', 'text' => 'Closed', 'bg' => '#F1F5F9', 'color' => '#475569'],
                                                            'late' => ['class' => 'bg-danger', 'text' => 'Late', 'bg' => '#FEE2E2', 'color' => '#DC2626'],
                                                            default => ['class' => 'bg-primary', 'text' => ucfirst($task->status), 'bg' => '#E0E7FF', 'color' => '#4F46E5'],
                                                        };
                                                    @endphp
                                                    <span class="badge rounded-pill fw-bold px-3 py-2" style="background-color: {{ $badgeData['bg'] }}; color: {{ $badgeData['color'] }}; font-size: 0.70rem; letter-spacing: 0.5px;">
                                                        {{ $badgeData['text'] }}
                                                    </span>
                                                </td>
                                                <td class="pe-4 text-end">
                                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                                        <button class="btn btn-sm btn-icon rounded-circle" style="background-color: #F0FAFA; border: 1px solid #127464; color: #127464;" title="View" onclick="viewTaskDetails('{{ addslashes($task->title) }}', '{{ addslashes(strip_tags($task->description)) }}', '{{ $task->due_date ? $task->due_date->format('d M, Y') : 'N/A' }}', '{{ strtoupper(str_replace('_', ' ', $task->status)) }}')">
                                                            <i class="bx bx-show-alt" style="font-size: 1.1rem;"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center py-5">
                                                    <div class="my-4">
                                                        <i class="bx bx-task text-muted" style="font-size: 3rem; opacity: 0.3;"></i>
                                                        <h6 class="mt-3 text-muted fw-medium">No tasks assigned yet</h6>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <!-- /Tab Content -->

            <!-- Mobile Tab Navigation (Previous/Next) -->
            <div class="mobile-tab-navigation d-md-none mt-4 pb-4">
                <div class="card border-0 shadow-sm" style="background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); border-radius: 20px;">
                    <div class="card-body p-3 d-flex justify-content-between gap-3">
                        <button class="btn btn-outline-secondary rounded-pill flex-fill py-2 fw-bold" onclick="navigateTabs('prev')">
                            <i class="bx bx-chevron-left me-1"></i> Previous
                        </button>
                        <button class="btn btn-primary rounded-pill flex-fill py-2 fw-bold" style="background: #127464;" onclick="navigateTabs('next')">
                            Next Stage <i class="bx bx-chevron-right ms-1"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div> {{-- Close for animate__fadeIn --}}
</div>




    {{-- Document View Popup Modal --}}
    <div class="modal fade" id="modalViewDocument" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content modal-content-hitech">
                <div class="modal-header modal-header-hitech">
                    <div class="modal-icon-header me-3"><i class="bx bx-file-find"></i></div>
                    <h5 class="modal-title modal-title-hitech mb-0" id="docViewModalTitle">View Document</h5>
                    <a id="docViewDownloadBtn" href="#" download class="btn btn-sm ms-auto me-2" style="background: rgba(255,255,255,0.2); color: #fff; border-radius: 10px; font-size: 0.8rem; padding: 0.4rem 1rem;">
                        <i class="bx bx-download me-1"></i> Download
                    </a>
                    <button type="button" class="btn-close-hitech" data-bs-dismiss="modal" style="position:relative;top:auto;right:auto;transform:none;"><i class="bx bx-x"></i></button>
                </div>
                <div class="modal-body p-0" style="background: #1a1a2e; min-height: 75vh;">
                    <div id="docViewContainer" class="w-100 h-100 d-flex align-items-center justify-content-center" style="min-height: 75vh;">
                        {{-- Content injected by JS --}}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- NEW: Add Document Modal --}}
    <div class="modal fade" id="modalAddUserDocument" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-content-hitech">
                <div class="modal-header modal-header-hitech">
                    <div class="d-flex align-items-center">
                        <div class="modal-icon-header me-3">
                            <i class="bx bx-file-plus"></i>
                        </div>
                        <h5 class="modal-title modal-title-hitech mb-0">Manage Document</h5>
                    </div>
                    <button type="button" class="btn-close-hitech" data-bs-dismiss="modal">
                        <i class="bx bx-x"></i>
                    </button>
                </div>
                <form action="{{ route('employees.addOrUpdateDocument') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="userId" value="{{ $user->id }}">
                    <div class="modal-body modal-body-hitech">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label-hitech">Document Name <span class="text-danger">*</span></label>
                                <input type="text" id="docModalName" name="documentName" class="form-control form-control-hitech" placeholder="e.g. Aadhar Card" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-hitech">Document Number <span class="text-danger">*</span></label>
                                <input type="text" id="docModalNumber" name="remarks" class="form-control form-control-hitech" placeholder="Enter ID number" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label-hitech">Attachment Upload <span class="text-danger">*</span></label>
                                <div class="p-4 border-2 rounded-3 text-center" style="border: 2px dashed #E2E8F0; background-color: #F8FAFC;">
                                    <i class="bx bx-cloud-upload text-muted mb-2" style="font-size: 2.5rem;"></i>
                                    <p class="small text-muted mb-3">Click to select or drag and drop file here</p>
                                    <input type="file" name="file" class="form-control form-control-hitech" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 px-4 pb-4 d-flex justify-content-end gap-3">
                        <button type="button" class="btn btn-hitech-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-hitech-modal-submit">Upload & Save <i class="bx bx-cloud-upload ms-1"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>



    {{-- Modals Inclusion --}}
    @include('_partials._modals.employees.edit_basic_info')
    @include('_partials._modals.employees.edit_contact_info')
    @include('_partials._modals.employees.add_orUpdate_bankAccount')



@endsection

@section('page-script')
    <script>
        // Global variables for employee context
        var user = @json($user);
        var role = @json($role);
        var attendanceType = @json($user->attendance_type ?? 'open');

        // Employee Action Handlers (Status Toggle, Relieve, Retire)
        // Employee Action Handlers (Status Toggle, Relieve, Retire)
        function loadBankDetails() {
            console.log("loadBankDetails called for user:", user?.id);
            try {
                // Support both snake_case and camelCase (Laravel relation vs serialization)
                const bank = user.bank_account || user.bankAccount;
                
                if(!user || !bank) {
                    console.log("No bank account found or user object incomplete.");
                    // Reset fields for fresh entry
                    $('#bankName, #bankCode, #accountName, #accountNumber, #confirmAccountNumber, #branchName, #branchCode').val('');
                    return;
                }
                console.log("Found bank account:", bank);
                $('#bankName').val(bank.bank_name || '');
                $('#bankCode').val(bank.bank_code || '');
                $('#accountName').val(bank.account_name || '');
                $('#accountNumber').val(bank.account_number || '');
                $('#confirmAccountNumber').val(bank.account_number || '');
                $('#branchName').val(bank.branch_name || '');
                $('#branchCode').val(bank.branch_code || '');
                console.log("Bank fields populated.");
            } catch (e) {
                console.error("Critical error in loadBankDetails:", e);
            }
        }

        // View Task Details function (moved here for completeness)
        function viewTaskDetails(title, desc, due, status) {
            Swal.fire({
                title: title,
                html: `<div class="text-start mt-3">
                        <div class="mb-2"><strong>Status:</strong> <span class="badge bg-label-primary">${status}</span></div>
                        <div class="mb-2"><strong>Due Date:</strong> ${due}</div>
                        <hr>
                        <div class="mt-2" style="max-height: 300px; overflow-y: auto;">${desc}</div>
                    </div>`,
                icon: 'info',
                confirmButtonText: 'Got it',
                customClass: {
                    confirmButton: 'btn btn-primary rounded-pill px-4'
                },
                buttonsStyling: false
            });
        }

        function viewDocumentNumber(name, num) {
            Swal.fire({
                title: name,
                text: "Document Number: " + num,
                icon: 'info',
                confirmButtonText: 'OK',
                customClass: {
                    confirmButton: 'btn btn-primary rounded-pill px-4'
                },
                buttonsStyling: false
            });
        }

        function viewDocumentPopup(url, title) {
            $('#docViewModalTitle').text(title);
            $('#docViewDownloadBtn').attr('href', url);
            
            let html = '';
            if (url.toLowerCase().endsWith('.pdf')) {
                html = `<iframe src="${url}" class="w-100 h-100" style="border:none; min-height: 75vh;"></iframe>`;
            } else {
                html = `<img src="${url}" class="img-fluid" style="max-height: 75vh; object-fit: contain;">`;
            }
            
            $('#docViewContainer').html(html);
            $('#modalViewDocument').modal('show');
        }

        function setDocModal(name, num) {
            $('#docModalName').val(name);
            $('#docModalNumber').val(num === 'N/A' ? '' : num);
        }

    </script>
    @vite(['resources/js/main-helper.js', 'resources/assets/js/app/employee-view.js'])
@endsection

