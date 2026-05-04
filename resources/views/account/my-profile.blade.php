@php
    use App\Enums\IncentiveType;
    use App\Enums\UserAccountStatus;
    use App\Services\AddonService\IAddonService;
    use Carbon\Carbon;
    use App\Helpers\StaticDataHelpers;
    $roleName = $user->getRoleNames()->first() ?? 'Employee';
    $addonService = app(IAddonService::class);
    $settings = \App\Models\Settings::first();
@endphp

@extends('layouts.layoutMaster')

@section('title', 'My Profile')

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
        'resources/assets/vendor/libs/bs-stepper/bs-stepper.scss'
    ])
@endsection

@section('page-style')
    @vite(['resources/assets/vendor/scss/pages/page-user-view.scss', 'resources/assets/css/employee-view.css', 'resources/assets/vendor/scss/pages/hitech-portal.scss'])

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
            overflow: hidden;
        }
        .rosemary-tab-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 34px;
            height: 34px;
            border-radius: 50%;
            border: 1px solid #DDE7EE;
            background: #fff;
            color: #127464;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 14px rgba(18, 116, 100, 0.12);
            cursor: pointer;
            transition: all 0.2s ease;
            z-index: 12;
        }
        .rosemary-tab-arrow:hover { transform: translateY(-50%) scale(1.05); }

        .rosemary-nav-tabs {
            display: flex;
            justify-content: flex-start;
            flex-flow: row nowrap !important;
            flex-wrap: nowrap !important;
            overflow-x: auto !important;
            overflow-y: hidden;
            gap: 0.65rem;
            border: none !important;
            width: 100%;
            padding-inline: 2.6rem;
            min-width: 0;
            flex: 1 1 auto;
            white-space: nowrap;
            align-items: center;
            scroll-snap-type: x proximity;
            -webkit-overflow-scrolling: touch;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .rosemary-nav-tabs::-webkit-scrollbar {
            display: none;
        }

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


        .emp-field-box {
            background-color: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 1rem 1.2rem;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .emp-field-box:hover { 
            border-color: #008080; 
            background-color: rgba(0, 128, 128, 0.02); 
            box-shadow: 0 4px 12px rgba(0, 128, 128, 0.05); 
            transform: translateY(-2px);
        }

    </style>
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
        'resources/assets/vendor/libs/flatpickr/flatpickr.js',
        'resources/assets/vendor/libs/bs-stepper/bs-stepper.js'
    ])
@endsection

@section('content')

<div class="">
    <div class="animate__animated animate__fadeIn">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h4 class="fw-extrabold mb-1" style="color: #1E293B; letter-spacing: -0.5px;">{{ $user->getFullName() }}</h4>
                <span class="text-muted fs-6">Manage employee details and financial information.</span>
            </div>
            <div>
                <a href="{{ auth()->user()->hasRole(['admin', 'hr', 'manager']) ? route('tenant.dashboard') : route('user.dashboard.index') }}" class="btn btn-hitech rounded-pill px-4 d-flex align-items-center">
                    <i class="bx bx-left-arrow-alt me-2 fs-5"></i> Back
                </a>
            </div>
        </div>

        <div class="row">
            <!-- User Sidebar -->
            <div class="col-xl-3 col-lg-3 col-md-4 col-12 mb-4">
                <!-- User Card -->
                <div class="card mb-4 border-0 shadow-sm position-relative overflow-hidden" style="border-radius: 12px;">
                    <div style="height: 6px; background-color: #127464; position: absolute; top: 0; left: 0; right: 0;"></div>
                    <div class="card-body pt-4">
                        <div class="user-avatar-section text-center position-relative mb-4">
                            <!-- Profile Picture -->
                            <div class="profile-picture-container position-relative d-inline-block" style="width: 110px; height: 110px;">
                                @if ($user->profile_picture)
                                    <img class="img-fluid rounded-circle w-100 h-100 border border-4 border-white shadow-sm" src="{{ $user->getProfilePicture() }}" alt="{{ $user->name }}" id="userProfilePicture" style="object-fit: cover;" onerror="this.src='https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&background=127464&color=fff'" />
                                @else
                                    <div class="rounded-circle w-100 h-100 d-flex align-items-center justify-content-center border border-4 border-white shadow-sm" style="background-color: #127464; color: white;">
                                        <h2 class="mb-0 text-white fw-bold">{{ $user->getInitials() }}</h2>
                                    </div>
                                @endif
                                <!-- Change Button Overlay -->
                                <div class="position-absolute bottom-0 end-0">
                                    <button class="btn btn-sm btn-icon rounded-circle shadow-sm" style="background: #127464; color: #fff; width: 32px; height: 32px;" onclick="document.getElementById('file').click()">
                                        <i class="bx bx-camera fs-6"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="mt-2 text-center">
                                @if($user->status == UserAccountStatus::ACTIVE)
                                    <span class="badge rounded-pill px-4 py-2 fw-bold" style="background-color: #E0F2F1; color: #127464; font-size: 0.65rem; letter-spacing: 0.5px;">ACTIVE</span>
                                @elseif($user->status == UserAccountStatus::TERMINATED)
                                    <span class="badge rounded-pill px-4 py-2 fw-bold" style="background-color: #FEE2E2; color: #DC2626; font-size: 0.65rem; letter-spacing: 0.5px;">TERMINATED</span>
                                @else
                                    <span class="badge rounded-pill px-4 py-2 fw-bold" style="background-color: #FEF3C7; color: #D97706; font-size: 0.65rem; letter-spacing: 0.5px;">{{ strtoupper($user->status->value ?? $user->status) }}</span>
                                @endif
                            </div>

                            <h5 class="mt-2 mb-1 fw-bold fs-4" style="color: #1E293B;">{{ $user->first_name }} {{ $user->last_name }}</h5>
                            <p class="text-muted mb-3 fs-6">{{ $user->designation ? $user->designation->name : 'N/A' }}</p>
                        </div>

                        <div class="border-top pt-4">
                            <ul class="list-unstyled mb-0 fs-6" style="color: #475569;">
                                <li class="mb-3 d-flex align-items-center">
                                    <i class="bx bx-qr text-muted me-3 fs-5"></i>
                                    <span class="fw-bold">ID:</span>&nbsp;{{ $user->code ?? 'N/A' }}
                                </li>
                                <li class="mb-3 d-flex align-items-center">
                                    <i class="bx bx-envelope text-muted me-3 fs-5"></i>
                                    {{ $user->email }}
                                </li>
                                <li class="mb-3 d-flex align-items-center">
                                    <i class="bx bx-phone text-muted me-3 fs-5"></i>
                                    {{ $user->phone ?? 'N/A' }}
                                </li>
                                <li class="mb-3 d-flex align-items-center">
                                    <i class="bx bx-calendar text-muted me-3 fs-5"></i>
                                    <span class="fw-bold">DOB:</span>&nbsp;{{ $user->dob ? \Carbon\Carbon::parse($user->dob)->format('d M Y') . ' (' . \Carbon\Carbon::parse($user->dob)->age . ' Years)' : 'N/A' }}
                                </li>
                                <li class="mb-0 d-flex align-items-center">
                                    <i class="bx bx-building-house text-muted me-3 fs-5"></i>
                                    {{ $user->department ? $user->department->name : 'N/A Dept.' }}
                                </li>
                            </ul>
                        </div>

                        <!-- Hidden File Input for Profile Picture Upload -->
                        <form id="profilePictureForm" action="{{ route('employee.changeEmployeeProfilePicture') }}" method="POST" enctype="multipart/form-data" style="display: none;">
                            @csrf
                            <input type="hidden" name="userId" value="{{ $user->id }}">
                            <input type="file" id="file" name="file" accept="image/*" onchange="this.form.submit()">
                        </form>
                    </div>
                </div>
                <!-- /User Card -->

                <!-- Management Control Section -->
                <div class="card emp-card mb-4 border-0 shadow-sm" style="border-radius: 16px; overflow: hidden;">
                    <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between" style="background: linear-gradient(135deg, #127464 0%, #0E5A4E 100%);">
                        <h6 class="fw-bold mb-0 text-white small text-uppercase" style="letter-spacing: 1.5px;"><i class="bx bx-shield-quarter me-2"></i>Management Control</h6>
                        <span class="badge {{ $user->status == UserAccountStatus::ACTIVE ? 'bg-success' : 'bg-danger' }} rounded-pill" style="font-size: 0.6rem; letter-spacing: 1px;">{{ strtoupper($user->status->name ?? $user->status) }}</span>
                    </div>
                    <div class="card-body p-4 bg-white">
                        @if ($user->status == \App\Enums\UserAccountStatus::TERMINATED || $user->status == \App\Enums\UserAccountStatus::RELIEVED || $user->status == \App\Enums\UserAccountStatus::RETIRED)
                            <div class="p-4 rounded-4 mb-0 text-center border" style="background-color: #f8fafc; border-style: dashed !important; border-width: 2px !important; border-color: #e2e8f0 !important;">
                                @if($user->status == \App\Enums\UserAccountStatus::TERMINATED)
                                    <div class="icon-stat-danger mb-3 mx-auto" style="width: 50px; height: 50px; background: #fee2e2; color: #ef4444; border-radius: 12px; display: flex; align-items: center; justify-content: center;"><i class="bx bx-block fs-3"></i></div>
                                    <h6 class="fw-extrabold text-danger mb-1">TERMINATED</h6>
                                    <p class="text-muted mb-0 small">Access Revoked: {{ $user->exit_date ? Carbon::parse($user->exit_date)->format('d M Y') : 'N/A' }}</p>
                                @else
                                    <div class="icon-stat-warning mb-3 mx-auto" style="width: 50px; height: 50px; background: #fef3c7; color: #f59e0b; border-radius: 12px; display: flex; align-items: center; justify-content: center;"><i class="bx bx-exit fs-3"></i></div>
                                    <h6 class="fw-extrabold text-warning mb-1">{{ strtoupper($user->status->name ?? $user->status) }}</h6>
                                    <p class="text-muted mb-0 small">Effective On: {{ Carbon::parse($user->relieved_at ?? $user->retired_at)->format('d M Y') }}</p>
                                @endif
                            </div>
                        @else
                            <!-- Active Employment Selection -->
                        <div class="p-3 rounded-3 mb-4 border" style="background: rgba(18, 116, 100, 0.03); border-color: rgba(18, 116, 100, 0.1) !important;">
                             <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <p class="mb-0 fw-bold text-dark fs-6">Active Employment</p>
                                    <span class="smallest text-muted fw-medium">Status managed by HR</span>
                                </div>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input hitech-awesome-toggle" style="width: 3em !important; height: 1.5em !important;" type="checkbox" id="employeeStatusToggle" 
                                        @if ($user->status == \App\Enums\UserAccountStatus::ACTIVE) checked @endif 
                                        disabled>
                                </div>
                            </div>
                        </div>
                            <!-- Probation Section (ReadOnly for Self) -->
                            <div class="probation-awesome-box p-3 rounded-3 mb-4" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="fw-bold text-dark small"><i class="bx bx-time-five me-1 text-primary"></i> Probation Period</div>
                                </div>
                                
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="smallest text-muted">Status</span>
                                    <span class="badge {{ $user->isUnderProbation() ? 'bg-label-warning' : 'bg-label-success' }} rounded-pill" style="font-size: 0.6rem;">{{ $user->probation_status_display }}</span>
                                </div>
                                @if($user->probation_end_date)
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="smallest text-muted">Ends On</span>
                                    <span class="fw-bold text-dark smallest">{{ Carbon::parse($user->probation_end_date)->format('d M Y') }}</span>
                                </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <div class="text-center mb-4">
                    <p class="text-muted" style="font-size: 0.75rem;"> Account created on <strong>{{ Carbon::parse($user->created_at)->format('d M Y') }}</strong> by <strong>{{ $user->createdBy != null ? $user->createdBy->getFullName() : 'Admin' }}.</strong></p>
                </div>
            </div>

            <!-- User Content -->
            <div class="col-xl-9 col-lg-9 col-md-8 col-12">
                <!-- Tabs Navigation -->
                <div class="rosemary-nav-tabs-wrapper mb-4">
                    <button class="rosemary-tab-arrow left" type="button" aria-label="Scroll tabs left" data-dir="-1">
                        <i class="bx bx-chevron-left"></i>
                    </button>
                    <ul class="nav nav-pills border-0 flex-md-row rosemary-nav-tabs" id="employeeTabs">
                        <li class="nav-item rosemary-nav-item">
                                        <a class="nav-link rosemary-nav-link d-flex align-items-center active" data-bs-toggle="tab" href="#basic-info">
                                            <i class="bx bx-user me-2 fs-5"></i> Basic Details
                                        </a>
                                    </li>
                        <li class="nav-item rosemary-nav-item">
                                        <a class="nav-link rosemary-nav-link d-flex align-items-center" data-bs-toggle="tab" href="#contact">
                                            <i class="bx bx-phone-call me-2 fs-5"></i> Contact
                                        </a>
                                    </li>
                        <li class="nav-item rosemary-nav-item">
                            <a class="nav-link rosemary-nav-link d-flex align-items-center" data-bs-toggle="tab" href="#employment-assets">
                                <i class="bx bx-briefcase me-2 fs-5"></i> Employment & Assets
                            </a>
                        </li>
                        <li class="nav-item rosemary-nav-item">
                            <a class="nav-link rosemary-nav-link d-flex align-items-center" data-bs-toggle="tab" href="#banking-payroll">
                                <i class="bx bx-credit-card me-2 fs-5"></i> Banking & Payroll
                            </a>
                        </li>
                        <li class="nav-item rosemary-nav-item">
                            <a class="nav-link rosemary-nav-link d-flex align-items-center" data-bs-toggle="tab" href="#documents">
                                <i class="bx bx-file me-2 fs-5"></i> Documents
                            </a>
                        </li>
                        <li class="nav-item rosemary-nav-item">
                            <a class="nav-link rosemary-nav-link d-flex align-items-center" data-bs-toggle="tab" href="#kpi">
                                <i class="bx bx-trending-up me-2 fs-5"></i> KPI
                            </a>
                        </li>
                        <li class="nav-item rosemary-nav-item">
                            <a class="nav-link rosemary-nav-link d-flex align-items-center" data-bs-toggle="tab" href="#activity">
                                <i class="bx bx-pulse me-2 fs-5"></i> Activity
                            </a>
                        </li>
                    </ul>
                    <button class="rosemary-tab-arrow right" type="button" aria-label="Scroll tabs right" data-dir="1">
                        <i class="bx bx-chevron-right"></i>
                    </button>
                </div>
                <!-- /Tabs Navigation -->

                <!-- Tab Content -->
                <div class="tab-content p-0 m-0 border-0 shadow-none">

                    <!-- Basic Info Tab -->
                    <div class="tab-pane fade show active" id="basic-info">
                        <div class="card mb-4 emp-card border-0">
                            <div class="card-body p-5">
                                <div class="d-flex justify-content-between align-items-center mb-5">
                                    <div class="d-flex align-items-center">
                                        <div class="hitech-icon-wrap me-3" style="background: rgba(18, 116, 100, 0.1); color: #127464; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 10px;">
                                            <i class="bx bx-info-circle fs-4"></i>
                                        </div>
                                        <h6 class="mb-0 fw-bold fs-5" style="color: #1E293B;">Primary Information</h6>
                                    </div>
                                    <button class="btn btn-hitech-primary px-4 rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#offcanvasEditBasicInfo" onclick="loadUserOnboardingData()">
                                        <i class="bx bx-edit-alt me-1"></i> Update Details
                                    </button>
                                </div>

                                <div class="row g-4">
                                    <!-- NAME PLATES -->
                                    <div class="col-md-6">
                                        <div class="emp-field-box h-100">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-white rounded-circle p-2 me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                                    <i class="bx bx-user text-primary fs-4"></i>
                                                </div>
                                                <div>
                                                    <p class="mb-1 text-muted smallest fw-bold text-uppercase" style="font-size: 0.65rem;">First Name</p>
                                                    <p class="mb-0 fw-bold text-dark fs-6">{{ $user->first_name }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="emp-field-box h-100">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-white rounded-circle p-2 me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                                    <i class="bx bx-user text-primary fs-4"></i>
                                                </div>
                                                <div>
                                                    <p class="mb-1 text-muted smallest fw-bold text-uppercase" style="font-size: 0.65rem;">Last Name</p>
                                                    <p class="mb-0 fw-bold text-dark fs-6">{{ $user->last_name }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- MARITAL & BLOOD -->
                                    <div class="col-md-6">
                                        <div class="emp-field-box h-100" style="background: rgba(239, 68, 68, 0.04); border: 1px solid rgba(239, 68, 68, 0.1);">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-white rounded-circle p-2 me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                                    <i class="bx bx-heart text-danger fs-4"></i>
                                                </div>
                                                <div>
                                                    <p class="mb-1 text-muted smallest fw-bold text-uppercase" style="font-size: 0.65rem;">Marital Status</p>
                                                    <p class="mb-0 fw-bold text-dark fs-6">{{ ucfirst($user->marital_status ?? 'Single') }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="emp-field-box h-100" style="background: rgba(34, 197, 94, 0.04); border: 1px solid rgba(34, 197, 94, 0.1);">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-white rounded-circle p-2 me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                                    <i class="bx bx-droplet text-success fs-4"></i>
                                                </div>
                                                <div>
                                                    <p class="mb-1 text-muted smallest fw-bold text-uppercase" style="font-size: 0.65rem;">Blood Group</p>
                                                    <p class="mb-0 fw-extrabold text-dark fs-6">{{ $user->blood_group ?? 'Not Set' }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- PARENTS -->
                                    <div class="col-md-6">
                                        <div class="emp-field-box h-100">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-white rounded-circle p-2 me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                                    <i class="bx bx-male-sign text-muted fs-4"></i>
                                                </div>
                                                <div>
                                                    <p class="mb-1 text-muted smallest fw-bold text-uppercase" style="font-size: 0.65rem;">Father's Name</p>
                                                    <p class="mb-0 fw-bold text-dark fs-6">{{ $user->father_name ?? 'Not Provided' }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="emp-field-box h-100">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-white rounded-circle p-2 me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                                    <i class="bx bx-female-sign text-muted fs-4"></i>
                                                </div>
                                                <div>
                                                    <p class="mb-1 text-muted smallest fw-bold text-uppercase" style="font-size: 0.65rem;">Mother's Name</p>
                                                    <p class="mb-0 fw-bold text-dark fs-6">{{ $user->mother_name ?? 'Not Provided' }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- IDENTITY & ORIGIN -->
                                    <div class="col-md-4">
                                        <div class="emp-field-box h-100" style="background: #F1F5F9; border: 1px solid #E2E8F0;">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-white rounded-circle p-2 me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                                    <i class="bx bx-world text-info fs-4"></i>
                                                </div>
                                                <div>
                                                    <p class="mb-1 text-muted smallest fw-bold text-uppercase" style="font-size: 0.65rem;">Birth Country</p>
                                                    <p class="mb-0 fw-bold text-dark fs-6">{{ $user->birth_country ?? 'N/A' }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="emp-field-box h-100" style="background: #F1F5F9; border: 1px solid #E2E8F0;">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-white rounded-circle p-2 me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                                    <i class="bx bx-flag text-warning fs-4"></i>
                                                </div>
                                                <div>
                                                    <p class="mb-1 text-muted smallest fw-bold text-uppercase" style="font-size: 0.65rem;">Citizenship</p>
                                                    <p class="mb-0 fw-bold text-dark fs-6">{{ $user->citizenship ?? 'N/A' }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="emp-field-box h-100" style="background: #F1F5F9; border: 1px solid #E2E8F0;">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-white rounded-circle p-2 me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                                    <i class="bx bx-user-circle text-secondary fs-4"></i>
                                                </div>
                                                <div>
                                                    <p class="mb-1 text-muted smallest fw-bold text-uppercase" style="font-size: 0.65rem;">Gender</p>
                                                    <p class="mb-0 fw-bold text-dark fs-6">{{ ucfirst($user->gender?->value ?? $user->gender) }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Tab -->
                    <div class="tab-pane fade" id="contact">
                        <div class="card mb-4 emp-card border-0">
                            <div class="card-body p-5">
                                <div class="d-flex justify-content-between align-items-center mb-5">
                                    <div class="d-flex align-items-center">
                                        <div class="hitech-icon-wrap me-3" style="background: rgba(18, 116, 100, 0.1); color: #127464; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 10px;">
                                            <i class="bx bx-map-pin fs-4"></i>
                                        </div>
                                        <h6 class="mb-0 fw-bold fs-5" style="color: #1E293B;">Contact & Address Details</h6>
                                    </div>
                                    <button class="btn btn-hitech-primary px-4 rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#offcanvasEditContactInfo">
                                        <i class="bx bx-edit-alt me-1"></i> Update Info
                                    </button>
                                </div>

                                <div class="row g-4">
                                    <!-- EMAIL & PHONE -->
                                    <div class="col-md-4">
                                        <div class="emp-field-box border-dashed h-100 p-3" style="transition: all 0.3s ease; border-radius: 12px; cursor: default; background: #F8FAFC; border: 1px solid #E2E8F0;">
                                            <p class="mb-1 text-muted smallest fw-bold text-uppercase" style="font-size: 0.65rem;">Login / Official Email</p>
                                            <p class="mb-0 fw-bold text-dark fs-6">{{ $user->email }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="emp-field-box h-100 p-3" style="transition: all 0.3s ease; border-radius: 12px; cursor: default; background: #F8FAFC; border: 1px solid #E2E8F0;">
                                            <p class="mb-1 text-muted smallest fw-bold text-uppercase" style="font-size: 0.65rem;">Personal Email</p>
                                            <p class="mb-0 fw-bold text-dark fs-6">{{ $user->personal_email ?? 'Not Provided' }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="emp-field-box h-100 p-3" style="transition: all 0.3s ease; border-radius: 12px; cursor: default; background: #F8FAFC; border: 1px solid #E2E8F0;">
                                            <p class="mb-1 text-muted smallest fw-bold text-uppercase" style="font-size: 0.65rem;">Primary / Personal Phone</p>
                                            <p class="mb-0 fw-bold text-dark fs-6">{{ $user->phone }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="emp-field-box h-100 p-3" style="transition: all 0.3s ease; border-radius: 12px; cursor: default; background: #F8FAFC; border: 1px solid #E2E8F0;">
                                            <p class="mb-1 text-muted smallest fw-bold text-uppercase" style="font-size: 0.65rem;">Official Phone</p>
                                            <p class="mb-0 fw-bold text-dark fs-6">{{ $user->official_phone ?? 'Not Provided' }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="emp-field-box h-100 p-3" style="transition: all 0.3s ease; border-radius: 12px; cursor: default; background: #F8FAFC; border: 1px solid #E2E8F0;">
                                            <p class="mb-1 text-muted smallest fw-bold text-uppercase" style="font-size: 0.65rem;">Alternate Phone</p>
                                            <p class="mb-0 fw-bold text-dark fs-6">{{ $user->alternate_number ?? 'Not Provided' }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="emp-field-box h-100 p-3" style="transition: all 0.3s ease; border-radius: 12px; cursor: default; background: #F8FAFC; border: 1px solid #E2E8F0;">
                                            <p class="mb-1 text-muted smallest fw-bold text-uppercase" style="font-size: 0.65rem;">Home Phone</p>
                                            <p class="mb-0 fw-bold text-dark fs-6">{{ $user->home_phone ?? 'Not Provided' }}</p>
                                        </div>
                                    </div>

                                    <!-- DUAL ADDRESS SECTION -->
                                    <div class="col-md-6">
                                        <div class="p-4 rounded-5 address-card h-100" style="background: linear-gradient(135deg, #F0FAFA 0%, #E6F4F1 100%); border: 1px solid rgba(18, 116, 100, 0.15); box-shadow: 0 10px 30px rgba(18, 116, 100, 0.05); transition: all 0.3s ease; border-radius: 20px;">
                                            <div class="d-flex align-items-center mb-4">
                                                <div class="bg-white shadow-sm rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width:46px;height:46px; border: 2px solid #12746433;">
                                                    <i class="bx bxs-map-pin fs-4" style="color: #127464;"></i>
                                                </div>
                                                <h6 class="mb-0 fw-extrabold text-dark fs-6" style="letter-spacing: 0.5px;">RESIDENTIAL ADDRESS</h6>
                                            </div>
                                            <div class="p-3 bg-white bg-opacity-60 rounded-3 border border-white">
                                                <p class="mb-0 text-dark lh-base fw-medium small">
                                                    @if($user->temp_street || $user->temp_building)
                                                        <span class="d-block mb-1 text-muted smallest fw-bold text-uppercase">Current Location</span>
                                                        {{ $user->temp_building }}, {{ $user->temp_street }}<br>
                                                        {{ $user->temp_city }}, {{ $user->temp_state }}<br>
                                                        {{ $user->temp_zip }}, {{ $user->temp_country }}
                                                    @else
                                                        <span class="fst-italic text-muted">No residential address recorded.</span>
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="p-4 rounded-5 address-card h-100" style="background: linear-gradient(135deg, #F8FAFC 0%, #F1F5F9 100%); border: 1px solid rgba(100, 116, 139, 0.15); box-shadow: 0 10px 30px rgba(100, 116, 139, 0.05); transition: all 0.3s ease; border-radius: 20px;">
                                            <div class="d-flex align-items-center mb-4">
                                                <div class="bg-white shadow-sm rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width:46px;height:46px; border: 2px solid #E2E8F0;">
                                                    <i class="bx bxs-home-heart fs-4" style="color: #6366f1;"></i>
                                                </div>
                                                <h6 class="mb-0 fw-extrabold text-dark fs-6" style="letter-spacing: 0.5px;">PERMANENT ADDRESS</h6>
                                            </div>
                                            <div class="p-3 bg-white bg-opacity-60 rounded-3 border border-white">
                                                <p class="mb-0 text-dark lh-base fw-medium small">
                                                    @if($user->perm_street || $user->perm_building)
                                                        <span class="d-block mb-1 text-muted smallest fw-bold text-uppercase">Legal Residence</span>
                                                        {{ $user->perm_building }}, {{ $user->perm_street }}<br>
                                                        {{ $user->perm_city }}, {{ $user->perm_state }}<br>
                                                        {{ $user->perm_zip }}, {{ $user->perm_country }}
                                                    @else
                                                        <span class="fst-italic text-muted">Same as residential address.</span>
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- EMERGENCY CONTACT SECTION -->
                                    <div class="col-12 mt-2">
                                        <div class="card border-0 p-4" style="background: linear-gradient(135deg, #FFF5F5 0%, #FFF0F0 100%); border-radius: 16px; border-left: 4px solid #EF4444 !important;">
                                            <div class="d-flex align-items-center mb-4">
                                                <div class="bg-white p-2 rounded-circle me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                    <i class="bx bxs-ambulance fs-4 text-danger"></i>
                                                </div>
                                                <h6 class="mb-0 fw-extrabold fs-6" style="color: #991B1B; letter-spacing: 0.5px;">EMERGENCY CONTACT DETAILS</h6>
                                            </div>
                                            <div class="row g-4">
                                                <div class="col-md-4">
                                                    <div class="p-3 bg-white bg-opacity-50 rounded-3 border border-white">
                                                        <span class="d-block smallest text-muted mb-1 fw-bold text-uppercase">Contact Person</span>
                                                        <strong class="text-dark fs-6">{{ $user->emergency_contact_name ?? 'Not Provided' }}</strong>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="p-3 bg-white bg-opacity-50 rounded-3 border border-white">
                                                        <span class="d-block smallest text-muted mb-1 fw-bold text-uppercase">Relationship</span>
                                                        <strong class="text-dark fs-6">{{ $user->emergency_contact_relation ?? 'Not Provided' }}</strong>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="p-3 bg-white bg-opacity-50 rounded-3 border border-white">
                                                        <span class="d-block smallest text-muted mb-1 fw-bold text-uppercase">Contact Number</span>
                                                        <strong class="text-dark fs-6 text-danger">{{ $user->emergency_contact_phone ?? 'N/A' }}</strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Employment Tab -->
                    <div class="tab-pane fade" id="employment-assets">
                        <div class="card mb-4 emp-card border-0">
                            <div class="card-body p-5">
                                <div class="d-flex justify-content-between align-items-center mb-5">
                                    <div class="d-flex align-items-center">
                                        <div class="hitech-icon-wrap me-3" style="background: rgba(18, 116, 100, 0.1); color: #127464; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 10px;">
                                            <i class="bx bx-briefcase fs-4"></i>
                                        </div>
                                        <h6 class="mb-0 fw-bold fs-5" style="color: #1E293B;">Work Information</h6>
                                    </div>
                                    
                                </div>

                                <div class="row g-4">
                                    <!-- Designation -->
                                    <div class="col-md-4">
                                        <div class="emp-field-box">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-white rounded p-2 me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                    <i class="bx bx-award text-muted fs-4"></i>
                                                </div>
                                                <div>
                                                    <p class="mb-0 text-muted smallest fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;">Designation</p>
                                                    <p class="mb-0 fw-bold text-dark fs-6">{{ $user->designation?->name ?? 'N/A' }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Department/Team -->
                                    <div class="col-md-4">
                                        <div class="emp-field-box">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-white rounded p-2 me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                    <i class="bx bx-group text-muted fs-4"></i>
                                                </div>
                                                <div>
                                                    <p class="mb-0 text-muted smallest fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;">Department / Team</p>
                                                    <p class="mb-0 fw-bold text-dark fs-6">{{ $user->team != null ? $user->team->name : 'N/A' }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Role -->
                                    <div class="col-md-4">
                                        <div class="emp-field-box">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-white rounded p-2 me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                    <i class="bx bx-shield text-muted fs-4"></i>
                                                </div>
                                                <div>
                                                    <p class="mb-0 text-muted smallest fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;">System Role</p>
                                                    <p class="mb-0 fw-bold text-dark fs-6">{{ $role->name ?? 'Employee' }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row g-4 mt-2">
                                    <!-- Site / Unit -->
                                    <div class="col-md-4">
                                        <div class="emp-field-box">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-white rounded p-2 me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                    <i class="bx bx-map-pin text-muted fs-4"></i>
                                                </div>
                                                <div>
                                                    <p class="mb-0 text-muted smallest fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;">Site / Unit</p>
                                                    <p class="mb-0 fw-bold text-dark fs-6">{{ $user->site?->name ?? 'Not Assigned' }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Leave Policy Profile -->
                                    <div class="col-md-4">
                                        <div class="emp-field-box">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-white rounded p-2 me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                    <i class="bx bx-calendar-event text-muted fs-4"></i>
                                                </div>
                                                <div>
                                                    <p class="mb-0 text-muted smallest fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;">Leave Policy Profile</p>
                                                    <p class="mb-0 fw-bold text-dark fs-6">{{ $user->leavePolicyProfile?->name ?? 'Not Assigned' }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Reporting Manager -->
                                    <div class="col-md-4">
                                        <div class="emp-field-box">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-white rounded p-2 me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                    <i class="bx bx-user-voice text-muted fs-4"></i>
                                                </div>
                                                <div>
                                                    <p class="mb-0 text-muted smallest fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;">Reporting Manager</p>
                                                    <p class="mb-0 fw-bold text-dark fs-6">{{ $user->reporting_to_id ? $user->getReportingToUserName() : 'N/A' }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row g-4 mt-2">
                                    <!-- Joining Date -->
                                    <div class="col-md-4">
                                        <div class="emp-field-box">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-white rounded p-2 me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                    <i class="bx bx-calendar text-muted fs-4"></i>
                                                </div>
                                                <div>
                                                    <p class="mb-0 text-muted smallest fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;">Joining Date</p>
                                                    <p class="mb-0 fw-bold text-dark fs-6">{{ $user->joining_date ? Carbon\Carbon::parse($user->joining_date)->format('d M Y') : 'N/A' }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Attendance Type -->
                                    <div class="col-md-4">
                                        <div class="emp-field-box">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-white rounded p-2 me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                    <i class="bx bx-check-shield text-muted fs-4"></i>
                                                </div>
                                                <div>
                                                    <p class="mb-0 text-muted smallest fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;">Attendance Type</p>
                                                    <p class="mb-0 fw-bold text-dark fs-6">{{ ucfirst($user->attendance_type ?? 'Open') }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Biometric ID -->
                                    <div class="col-md-4">
                                        <div class="emp-field-box">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-white rounded p-2 me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                    <i class="bx bx-fingerprint text-muted fs-4"></i>
                                                </div>
                                                <div>
                                                    <p class="mb-0 text-muted smallest fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;">Biometric ID</p>
                                                    <p class="mb-0 fw-bold text-dark fs-6">{{ $user->biometric_id ?? 'Not Mapped' }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Leave Balances Section -->
                        @php
                            $leaveBalances = $user->leaveBalances;
                            // Find primary paid leave balance (PL, CL, or any paid type)
                            $plBalance = $leaveBalances->where('leaveType.is_paid', true)->first();
                            
                            $totalLeaves = $plBalance ? $plBalance->balance : 0;
                            $usedLeaves = $plBalance ? $plBalance->used : 0;
                            $remainingLeaves = $totalLeaves - $usedLeaves;
                            
                            $carryForwardLastYear = $plBalance ? $plBalance->carry_forward_last_year : 0;
                            $accruedThisYear = $plBalance ? $plBalance->accrued_this_year : 0;
                            
                            // Fallback for existing data: if breakdown is 0 but balance > 0, assume it was all accrued this year
                            if ($totalLeaves > 0 && $carryForwardLastYear == 0 && $accruedThisYear == 0) {
                                $accruedThisYear = $totalLeaves;
                            }
                            
                            // Monthly breakdown logic
                            $currentMonthAccrual = 1.0; 
                            $carryForwardThisYear = max(0, $accruedThisYear - $currentMonthAccrual - $usedLeaves);
                        @endphp
                        <div class="card mb-4 emp-card border-0 overflow-hidden" style="border-radius: 16px;">
                            <div class="card-header border-0 pb-0 pt-4 px-5 bg-white">
                                <div class="d-flex align-items-center">
                                    <div class="hitech-icon-wrap me-3" style="background: rgba(18, 116, 100, 0.1); color: #127464; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 10px;">
                                        <i class="bx bx-calendar-check fs-4"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 fw-bold fs-5" style="color: #1E293B;">Detailed Leave History</h6>
                                        <small class="text-muted">Fiscal Cycle: April {{ now()->month < 4 ? now()->year - 1 : now()->year }} - March {{ now()->month < 4 ? now()->year : now()->year + 1 }}</small>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-5">
                                <div class="row g-4">
                                    <!-- Carry Forward Last Year -->
                                    <div class="col-md-3">
                                        <div class="p-3 rounded-4 text-center border h-100" style="background: #F1F5F9;">
                                            <div class="smallest text-muted fw-bold text-uppercase mb-1" style="font-size: 0.6rem;">Carry Forward</div>
                                            <div class="h4 mb-0 fw-bold text-dark">{{ number_format($carryForwardLastYear, 1) }}</div>
                                            <div class="smallest text-muted mt-1">From Last Year</div>
                                        </div>
                                    </div>
                                    <!-- Accrued This Year -->
                                    <div class="col-md-3">
                                        <div class="p-3 rounded-4 text-center border h-100" style="background: #E0F2FE;">
                                            <div class="smallest text-muted fw-bold text-uppercase mb-1" style="font-size: 0.6rem;">Accrued</div>
                                            <div class="h4 mb-0 fw-bold" style="color: #0369A1;">{{ number_format($accruedThisYear, 1) }}</div>
                                            <div class="smallest text-muted mt-1">Earned This Year</div>
                                        </div>
                                    </div>
                                    <!-- Used Leaves -->
                                    <div class="col-md-3">
                                        <div class="p-3 rounded-4 text-center border h-100" style="background: #FEF2F2;">
                                            <div class="smallest text-muted fw-bold text-uppercase mb-1" style="font-size: 0.6rem;">Leave Taken</div>
                                            <div class="h4 mb-0 fw-bold text-danger">{{ number_format($usedLeaves, 1) }}</div>
                                            <div class="smallest text-muted mt-1">Consumed to date</div>
                                        </div>
                                    </div>
                                    <!-- Total Available -->
                                    <div class="col-md-3">
                                        <div class="p-3 rounded-4 text-center border h-100" style="background: #127464; color: white;">
                                            <div class="smallest text-white bg-white bg-opacity-20 rounded-pill px-2 py-0 fw-bold text-uppercase mb-1 mx-auto d-inline-block" style="font-size: 0.55rem;">Available</div>
                                            <div class="h4 mb-0 fw-bold text-white">{{ number_format($remainingLeaves, 1) }}</div>
                                            <div class="smallest text-white text-opacity-75 mt-1">Combined Total</div>
                                        </div>
                                    </div>
                                </div>
                                
                                @php
                                    $shortLeaveBalance = $leaveBalances->where('leaveType.is_short_leave', true)->first();
                                    $remainingShort = $shortLeaveBalance ? ($shortLeaveBalance->balance - $shortLeaveBalance->used) : 0;
                                @endphp
                                @if($shortLeaveBalance)
                                <div class="row g-4 mt-2">
                                    <div class="col-md-3">
                                        <div class="p-3 rounded-4 text-center border h-100" style="background: #FFFBEB;">
                                            <div class="smallest text-muted fw-bold text-uppercase mb-1" style="font-size: 0.6rem;">Short Leave Quota</div>
                                            <div class="h4 mb-0 fw-bold text-warning">{{ number_format($remainingShort, 1) }}</div>
                                            <div class="smallest text-muted mt-1">Available this month</div>
                                        </div>
                                    </div>
                                </div>
                                @endif
                                
                                <!-- Usage Bar -->
                                <div class="mt-5">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="small fw-bold text-dark">Leave Consumption Progress</span>
                                        <span class="small fw-bold text-muted">{{ number_format($usedLeaves, 1) }} used out of {{ number_format($totalLeaves, 1) }} allocated</span>
                                    </div>
                                    <div class="progress" style="height: 10px; border-radius: 5px;">
                                        <div class="progress-bar" role="progressbar" style="width: {{ $totalLeaves > 0 ? ($usedLeaves / $totalLeaves) * 100 : 0 }}%; background-color: #127464;" aria-valuenow="{{ $usedLeaves }}" aria-valuemin="0" aria-valuemax="{{ $totalLeaves }}"></div>
                                    </div>
                                </div>

                                <!-- Transaction Log Table -->
                                <div class="mt-5 pt-4 border-top">
                                    <h6 class="fw-bold mb-4 d-flex align-items-center">
                                        <i class="bx bx-list-ul me-2 text-teal"></i> Detailed Transaction Log
                                    </h6>
                                    <div class="table-responsive rounded-3 border">
                                        <table class="table table-sm table-hover mb-0 align-middle">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th class="ps-3 py-2 small fw-bold text-uppercase">Event</th>
                                                    <th class="py-2 small fw-bold text-uppercase">Type</th>
                                                    <th class="py-2 small fw-bold text-uppercase text-center">Amount</th>
                                                    <th class="py-2 small fw-bold text-uppercase">Reason / Period</th>
                                                    <th class="pe-3 py-2 small fw-bold text-uppercase text-end">Date</th>
                                                </tr>
                                            </thead>
                                            <tbody style="font-size: 0.85rem;">
                                                @forelse($leaveHistory as $item)
                                                <tr>
                                                    <td class="ps-3 py-2">
                                                        <span class="fw-bold text-dark">{{ $item->leave_type }}</span>
                                                    </td>
                                                    <td class="py-2">
                                                        @php
                                                            $typeColor = 'secondary';
                                                            if($item->type == 'Credit' || $item->type == 'Accrued' || $item->type == 'Carry Forward') $typeColor = 'success';
                                                            elseif($item->type == 'Request') $typeColor = 'info';
                                                            elseif($item->type == 'Deduction') $typeColor = 'danger';
                                                        @endphp
                                                        <span class="badge bg-label-{{ $typeColor }} py-0 px-2" style="font-size: 0.7rem;">{{ $item->type }}</span>
                                                    </td>
                                                    <td class="py-2 text-center fw-bold">
                                                        @if($item->is_adjustment)
                                                            <span class="{{ $item->amount > 0 ? 'text-success' : 'text-danger' }}">
                                                                {{ $item->amount > 0 ? '+' : '' }}{{ number_format($item->amount, 1) }}
                                                            </span>
                                                        @else
                                                            @php
                                                                $from = \Carbon\Carbon::parse($item->from_date);
                                                                $to = \Carbon\Carbon::parse($item->to_date);
                                                                $days = $from->diffInDays($to) + 1;
                                                            @endphp
                                                            <span class="text-info">-{{ number_format($days, 1) }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="py-2">
                                                        <div class="text-muted small">
                                                            @if($item->from_date)
                                                                {{ \Carbon\Carbon::parse($item->from_date)->format('d M') }} - {{ \Carbon\Carbon::parse($item->to_date)->format('d M') }}
                                                                @if($item->notes) <span class="mx-1">|</span> {{ \Illuminate\Support\Str::limit($item->notes, 30) }} @endif
                                                            @else
                                                                {{ \Illuminate\Support\Str::limit($item->notes, 50) }}
                                                            @endif
                                                        </div>
                                                    </td>
                                                    <td class="pe-3 py-2 text-end text-muted">
                                                        {{ $item->created_at->format('d M, Y') }}
                                                    </td>
                                                </tr>
                                                @empty
                                                <tr>
                                                    <td colspan="5" class="text-center py-4 text-muted small">No transactions recorded</td>
                                                </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Assets Card -->
                        <div class="card mb-4 emp-card border-0">
                            <div class="card-body p-5">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div class="d-flex align-items-center">
                                        <i class="bx bx-laptop me-2 fs-4 text-primary"></i>
                                        <h6 class="mb-0 fw-bold">Assigned Assets</h6>
                                    </div>
                                    <span class="badge rounded-pill" style="background: #E6F4F1; color: #127464; font-size: 0.75rem;">
                                        {{ $user->assets->count() }} Items
                                    </span>
                                </div>
                                <div class="table-responsive rounded-3 border">
                                    <table class="table table-hover mb-0 align-middle">
                                        <thead class="bg-light">
                                            <tr>
                                                <th class="ps-4 py-3 text-muted fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Asset</th>
                                                <th class="py-3 text-muted fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Code</th>
                                                <th class="py-3 text-muted fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Category</th>
                                                <th class="py-3 text-muted fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Serial / Tag</th>
                                                <th class="pe-4 py-3 text-muted fw-bold" style="font-size: 0.7rem; text-transform: uppercase;">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($user->assets as $asset)
                                            <tr style="border-bottom: 1px solid #F1F5F9;">
                                                <td class="ps-4 py-3 text-dark fw-bold fs-6">{{ $asset->name ?? 'N/A' }}</td>
                                                <td class="py-3 text-muted small">{{ $asset->asset_code ?? 'N/A' }}</td>
                                                <td class="py-3 text-dark small">{{ $asset->category->name ?? 'N/A' }}</td>
                                                <td class="py-3 text-muted small">{{ $asset->serial_number ?? ($asset->service_tag ?? 'N/A') }}</td>
                                                <td class="pe-4 py-3 text-end">
                                                    <span class="badge rounded-pill" style="background: #F1F5F9; color: #334155; font-size: 0.65rem;">
                                                        {{ strtoupper($asset->status ?? 'N/A') }}
                                                    </span>
                                                </td>
                                            </tr>
                                            @empty
                                            <tr><td colspan="5" class="text-center py-4 text-muted small">No assets assigned yet.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Banking Tab -->
                    <div class="tab-pane fade" id="banking-payroll">
                        <div class="card mb-4 emp-card border-0">
                            <div class="card-body p-5">
                                <div class="d-flex justify-content-between align-items-center mb-5">
                                    <div class="d-flex align-items-center">
                                        <div class="hitech-icon-wrap me-3" style="background: rgba(18, 116, 100, 0.1); color: #127464; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 10px;">
                                            <i class="bx bxs-bank fs-4"></i>
                                        </div>
                                        <h6 class="mb-0 fw-bold fs-5" style="color: #1E293B;">Bank Account Details</h6>
                                    </div>
                                    <button class="btn btn-hitech-primary px-4 rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#editBankAccountModal">
                                        <i class="bx bx-edit-alt me-1"></i> Request Update
                                    </button>
                                </div>

                                @php
                                    $bank = $user->bankAccount;
                                @endphp

                                @if($bank)
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <div class="emp-field-box">
                                            <p class="mb-1 text-muted smallest fw-bold text-uppercase" style="font-size: 0.65rem;">Bank Name</p>
                                            <p class="mb-0 fw-bold text-dark fs-6">{{ $bank->bank_name }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="emp-field-box">
                                            <p class="mb-1 text-muted smallest fw-bold text-uppercase" style="font-size: 0.65rem;">Account Number</p>
                                            <p class="mb-0 fw-extrabold text-dark fs-6">{{ $bank->account_number }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="emp-field-box">
                                            <p class="mb-1 text-muted smallest fw-bold text-uppercase" style="font-size: 0.65rem;">IFSC / Bank Code</p>
                                            <p class="mb-0 fw-bold text-dark fs-6">{{ $bank->bank_code }}</p>
                                        </div>
                                    </div>
                                    @if(isset($bank->branch_name))
                                    <div class="col-md-6">
                                        <div class="emp-field-box">
                                            <p class="mb-1 text-muted smallest fw-bold text-uppercase" style="font-size: 0.65rem;">Branch Name</p>
                                            <p class="mb-0 fw-bold text-dark fs-6">{{ $bank->branch_name }}</p>
                                        </div>
                                    </div>
                                    @endif
                                    @if($user->getChequeUrl())
                                    <div class="col-12">
                                        <div class="p-3 rounded-4 d-flex align-items-center justify-content-between" style="background: rgba(18,116,100,0.05); border: 1px dashed rgba(18,116,100,0.2);">
                                            <div class="d-flex align-items-center">
                                                <i class="bx bx-file-blank fs-4 text-primary me-2"></i>
                                                <span class="fw-bold text-dark small">Cancelled Cheque / Passbook Attachment</span>
                                            </div>
                                            <button type="button" class="btn btn-xs btn-hitech-primary rounded-pill px-4" onclick="viewDocumentPopup('{{ $user->getChequeUrl() }}', 'Cancelled Cheque')">
                                                <i class="bx bx-show me-1"></i> View Attachment
                                            </button>
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
                                
                                <!-- Approval Notice If Pending -->
                                @php
                                    $hasPendingBank = $pendingApprovals->where('section', 'bank')->count() > 0;
                                @endphp
                                @if($hasPendingBank)
                                <div class="alert alert-warning d-flex align-items-center mt-4 rounded-4 border-0 shadow-sm" role="alert">
                                    <i class="bx bx-time-five fs-4 me-3"></i>
                                    <div>
                                        <strong class="d-block">Pending Approval</strong>
                                        <span class="small">Your recent bank detail changes are currently being reviewed by HR.</span>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>

                        <!-- Compensation Section (Admins/Self View) -->
                        <div class="card mb-4 emp-card border-0">
                            <div class="card-body p-5">
                                <div class="d-flex align-items-center mb-5">
                                    <div class="hitech-icon-wrap me-3" style="background: rgba(18, 116, 100, 0.1); color: #127464; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 10px;">
                                        <i class="bx bx-money fs-4"></i>
                                    </div>
                                    <h6 class="mb-0 fw-bold fs-5" style="color: #1E293B;">Compensation Details</h6>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="emp-field-box p-3 rounded-4 h-100" style="background: #fff; border: 1px solid #eef2f6;">
                                            <p class="mb-1 text-muted smallest fw-bold text-uppercase" style="font-size: 0.65rem;">Base Monthly</p>
                                            <p class="mb-0 fw-bold text-dark fs-5">₹{{ number_format($user->base_salary) }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="emp-field-box p-3 rounded-4 h-100" style="background: #fff; border: 1px solid #eef2f6;">
                                            <p class="mb-1 text-muted smallest fw-bold text-uppercase" style="font-size: 0.65rem;">CTC (Annum)</p>
                                            <p class="mb-0 fw-bold text-dark fs-5">₹{{ number_format($user->ctc_offered) }}</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="emp-field-box p-3 rounded-4 h-100" style="background: #fff; border: 1px solid #eef2f6;">
                                            <p class="mb-1 text-muted smallest fw-bold text-uppercase" style="font-size: 0.65rem;">Pay Frequency</p>
                                            <p class="mb-0 fw-bold text-dark fs-5">Monthly</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Salary Structure Breakdown -->
                        <div class="card emp-card shadow-sm border-0 mb-4" style="border-radius: 12px; overflow: hidden;">
                            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between cursor-pointer" data-bs-toggle="collapse" data-bs-target="#salaryBreakdownCollapse" style="cursor: pointer;">
                                <div class="d-flex align-items-center">
                                    <div class="hitech-icon-wrap me-3" style="background: rgba(18, 116, 100, 0.1); color: #127464; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 10px;">
                                        <i class="bx bx-calculator fs-4"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 fw-bold fs-5" style="color: #1E293B;">Salary Structure Breakdown</h6>
                                        <p class="mb-0 text-muted smallest italic">Click to view detailed components</p>
                                    </div>
                                </div>
                                <i class="bx bx-chevron-down fs-3 text-muted"></i>
                            </div>
                            <div class="collapse" id="salaryBreakdownCollapse">
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        @php
                                            $ctcAnnum = $user->ctc_offered ?? 0;
                                            $ctcMonth = $ctcAnnum / 12;
                                            $basicMonth = $ctcMonth * 0.5;
                                            $hraMonth = $ctcMonth * 0.25;
                                            $medicalMonth = 2500; $eduMonth = 200; $ltaMonth = 2500;
                                            $sumA = $basicMonth + $hraMonth + $medicalMonth + $eduMonth + $ltaMonth;
                                            $specialAllowance = max(0, $ctcMonth - $sumA);
                                            $profTax = 200; $pfAmount = 1800;
                                            $deductions = $profTax + $pfAmount;
                                            $netSalary = $ctcMonth - $deductions;
                                        @endphp
                                        <table class="table table-hover mb-0 align-middle">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th class="ps-4 py-3 text-muted smallest fw-bold text-uppercase">Component</th>
                                                    <th class="text-end py-3 text-muted smallest fw-bold text-uppercase">Per Month</th>
                                                    <th class="pe-4 text-end py-3 text-muted smallest fw-bold text-uppercase">Per Annum</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr><td class="ps-4 py-3 fw-semibold text-dark">Basic Salary</td><td class="text-end py-3">₹{{ number_format($basicMonth, 2) }}</td><td class="pe-4 text-end py-3 text-muted">₹{{ number_format($basicMonth * 12, 2) }}</td></tr>
                                                <tr><td class="ps-4 py-3 fw-semibold text-dark">HRA</td><td class="text-end py-3">₹{{ number_format($hraMonth, 2) }}</td><td class="pe-4 text-end py-3 text-muted">₹{{ number_format($hraMonth * 12, 2) }}</td></tr>
                                                <tr class="bg-light bg-opacity-50"><td class="ps-4 py-2 text-muted small italic">Total Monthly CTC</td><td class="text-end py-2 fw-bold text-dark fs-6">₹{{ number_format($ctcMonth, 2) }}</td><td class="pe-4 text-end py-2 fw-bold text-dark">₹{{ number_format($ctcAnnum, 2) }}</td></tr>
                                            </tbody>
                                            <tfoot style="background: #127464; color: #fff;">
                                                <tr><td class="ps-4 py-3 fw-bold">NET TAKE HOME</td><td class="text-end py-3 fw-bold fs-5 text-white">₹{{ number_format($netSalary, 2) }}</td><td class="pe-4 text-end py-3 fw-bold text-white">₹{{ number_format($netSalary * 12, 2) }}</td></tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Allowances & Deductions section -->
                        <div class="card mb-4 emp-card shadow-sm border-0" style="border-radius: 12px;">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center justify-content-between mb-4">
                                    <div class="d-flex align-items-center">
                                        <div class="hitech-icon-wrap me-3" style="background: rgba(18, 116, 100, 0.1); color: #127464; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 10px;">
                                            <i class="bx bx-list-check fs-4"></i>
                                        </div>
                                        <h6 class="mb-0 fw-bold fs-5" style="color: #1E293B;">Allowances & Deductions</h6>
                                    </div>
                                </div>
                                @if ($user->payrollAdjustments->count() > 0)
                                    <div class="row g-3">
                                    @foreach ($user->payrollAdjustments as $adjustment)
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center justify-content-between p-3 rounded-4 hitech-adjustment-card" style="background-color: #F8FAFC; border: 1px solid #F1F5F9; cursor: pointer; transition: all 0.3s ease;">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-white p-2 rounded-circle me-3 shadow-sm d-flex align-items-center justify-content-center" style="width: 42px; height: 42px; border: 1px solid {{ $adjustment->type === 'benefit' ? '#D1FAE5' : '#FEE2E2' }};">
                                                        <i class="bx {{ $adjustment->type === 'benefit' ? 'bx-trending-up text-success' : 'bx-trending-down text-danger' }} fs-5"></i>
                                                    </div>
                                                    <div>
                                                        <p class="mb-0 fw-bold small text-dark">{{ $adjustment->name }}</p>
                                                        <span class="text-muted text-uppercase" style="font-size: 0.55rem; letter-spacing: 0.5px;">{{ $adjustment->type }}</span>
                                                    </div>
                                                </div>
                                                <div class="text-end">
                                                    <p class="mb-0 fw-extrabold {{ $adjustment->type === 'benefit' ? 'text-success' : 'text-danger' }} fs-6">
                                                        {{ $adjustment->type === 'benefit' ? '+' : '-' }}{{ $settings->currency_symbol ?? '₹' }}{{ number_format($adjustment->amount ?? (($adjustment->percentage / 100) * ($user->base_salary ?? 0)), 2) }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                    </div>
                                @else
                                    <div class="text-center py-4 rounded-3" style="border: 2px dashed #E2E8F0; background-color: #F8FAFC;">
                                        <p class="text-muted mb-0 small italic">No active adjustments or allowances found.</p>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Payslip History Section -->
                        <div class="card mb-4 emp-card border-0">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div class="d-flex align-items-center">
                                        <div class="hitech-icon-wrap me-3" style="background: rgba(18, 116, 100, 0.1); color: #127464; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 10px;">
                                            <i class="bx bx-spreadsheet fs-4"></i>
                                        </div>
                                        <h6 class="mb-0 fw-bold fs-5" style="color: #1E293B;">Month-wise Payroll History</h6>
                                    </div>
                                    <span class="badge rounded-pill" style="background: rgba(18, 116, 100, 0.1); color: #127464; font-size: 0.75rem; padding: 0.5rem 1rem;">
                                        {{ $user->payslips->count() }} Records
                                    </span>
                                </div>

                                @if($user->payslips->count() > 0)
                                    <div class="table-responsive rounded-4 border border-light overflow-hidden shadow-xs" style="background: #fff;">
                                        <table class="table table-hover mb-0 align-middle">
                                            <thead style="background-color: #F8FAFC; border-bottom: 2px solid #F1F5F9;">
                                                <tr>
                                                    <th class="ps-4 py-3 text-muted smallest fw-bold text-uppercase" style="letter-spacing: 0.5px;">Period</th>
                                                    <th class="py-3 text-muted smallest fw-bold text-uppercase" style="letter-spacing: 0.5px;">Basic</th>
                                                    <th class="py-3 text-muted smallest fw-bold text-uppercase" style="letter-spacing: 0.5px;">Net Salary</th>
                                                    <th class="py-3 text-muted smallest fw-bold text-uppercase text-center" style="letter-spacing: 0.5px;">Status</th>
                                                    <th class="pe-4 py-3 text-muted smallest fw-bold text-uppercase text-end" style="letter-spacing: 0.5px;">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($user->payslips->sortByDesc('created_at')->take(12) as $payslip)
                                                    <tr style="border-bottom: 1px solid #F8FAFC; transition: all 0.2s ease;">
                                                        <td class="ps-4 py-3 fw-bold text-dark fs-6">{{ $payslip->created_at->format('M Y') }}</td>
                                                        <td class="py-3 text-muted small">₹{{ number_format($payslip->basic_salary, 2) }}</td>
                                                        <td class="py-3 fw-extrabold text-primary fs-6">₹{{ number_format($payslip->net_salary, 2) }}</td>
                                                        <td class="py-3 text-center">
                                                            <span class="badge rounded-pill fw-bold" style="background: {{ $payslip->status == 'paid' ? '#ECFDF3; color: #15803D;' : '#FFF7ED; color: #C2410C;' }}; font-size: 0.6rem; padding: 0.4rem 0.8rem;">
                                                                {{ strtoupper($payslip->status) }}
                                                            </span>
                                                        </td>
                                                        <td class="pe-4 py-3 text-end">
                                                            <button class="btn btn-xs btn-hitech-primary rounded-pill px-3" onclick="viewDocumentPopup('{{ route('user.payroll.show_ajax', $payslip->id) }}', 'Payslip {{ $payslip->created_at->format('M Y') }}')">Download</button>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="text-center py-5 rounded-4" style="border: 2px dashed #E2E8F0; background-color: #F8FAFC;">
                                        <i class="bx bxs-file-blank text-muted mb-2" style="font-size: 2.8rem; opacity: 0.4;"></i>
                                        <p class="text-muted small mb-0 fw-medium">No payslip records found for this employee.</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Documents Tab -->
                    <div class="tab-pane fade" id="documents">
                        <div class="card mb-4 emp-card border-0">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div class="d-flex align-items-center">
                                        <i class="bx bx-file me-2 fs-5" style="color: #127464;"></i>
                                        <h6 class="mb-0 fw-bold" style="color: #1E293B;">Employee Documents</h6>
                                    </div>
                                    <button class="btn btn-hitech-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddUserDocument">
                                        <i class="bx bx-plus me-1"></i> Request Update
                                    </button>
                                </div>

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
                                            $docFileUrl = null;
                                            $docNumber = 'N/A';

                                            if ($doc['key'] && $user->{$doc['key']}) {
                                                $isSubmitted = true;
                                                $docNumber = $user->{$doc['key']};
                                            }

                                            if ($doc['name'] == 'Aadhar Card' && $user->getAadhaarUrl()) {
                                                $isSubmitted = true;
                                                $docFileUrl = $user->getAadhaarUrl();
                                            } elseif ($doc['name'] == 'Pan Card' && $user->getPanUrl()) {
                                                $isSubmitted = true;
                                                $docFileUrl = $user->getPanUrl();
                                            } elseif ($doc['name'] == '10th Marksheet' && $user->getMatricUrl()) {
                                                $isSubmitted = true;
                                                $docFileUrl = $user->getMatricUrl();
                                            } elseif ($doc['name'] == 'Intermediate Marksheet' && $user->getInterUrl()) {
                                                $isSubmitted = true;
                                                $docFileUrl = $user->getInterUrl();
                                            } elseif ($doc['name'] == 'Graduation Marksheet' && $user->getBachelorUrl()) {
                                                $isSubmitted = true;
                                                $docFileUrl = $user->getBachelorUrl();
                                            }

                                            $request = $user->documentRequests->where('status', 'approved')->filter(function($r) use ($doc) {
                                                return $r->documentType && strtolower($r->documentType->name) == strtolower($doc['name']);
                                            })->first();

                                            if ($request) {
                                                $isSubmitted = true;
                                                if ($request->generated_file) $docFileUrl = $request->getSecureUrl();
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
                                                        <div class="d-flex align-items-center gap-2 mt-1">
                                                            <span class="badge" style="font-size: 0.5rem; padding: 0.2rem 0.4rem; border-radius: 4px; background-color: {{ $isSubmitted ? '#127464' : '#94A3B8' }}; color:#fff;">{{ $isSubmitted ? 'SUBMITTED' : 'NOT SUBMITTED' }}</span>
                                                            @if($isSubmitted && $docNumber && $docNumber !== 'N/A')
                                                                <span class="smallest text-muted fw-bold" style="font-size: 0.6rem;"># {{ $docNumber }}</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="d-flex gap-2">
                                                    @if($isSubmitted)
                                                        @if($docFileUrl)
                                                            <a href="javascript:void(0)" class="btn btn-hitech-secondary btn-xs rounded-pill px-3" style="font-size: 0.65rem;" onclick="viewDocumentPopup('{{ $docFileUrl }}', '{{ $doc['name'] }}')"><i class="bx bx-show me-1"></i>View</a>
                                                        @endif
                                                        <button class="btn btn-xs btn-outline-hitech rounded-pill px-3" style="font-size: 0.65rem;" 
                                                                data-bs-toggle="modal" data-bs-target="#modalAddUserDocument" 
                                                                onclick="setDocModal('{{ $doc['name'] }}', '{{ $docNumber ?? '' }}')">Request Update</button>
                                                    @else
                                                        <button class="btn btn-xs btn-hitech rounded-pill px-3" style="font-size: 0.65rem; background-color: #127464; color: #fff;" 
                                                                data-bs-toggle="modal" data-bs-target="#modalAddUserDocument" 
                                                                onclick="setDocModal('{{ $doc['name'] }}', '')">Upload</button>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                @if($user->passport_no || $user->visa_type || $user->frro_registration || $user->documentRequests->where('status', 'approved')->count() > 0)
                                <hr class="my-4" style="border-style: dashed; opacity: 0.1;">
                                <h6 class="fw-bold mb-3 small text-muted text-uppercase" style="letter-spacing: 1px; font-size: 0.7rem;">Other Identity Proofs</h6>
                                <div class="row g-3">
                                    @if($user->passport_no)
                                    <div class="col-md-4">
                                        <div class="emp-field-box p-3 rounded-4" style="background: #fff; border: 1px solid #eef2f6;">
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
                                        <div class="emp-field-box p-3 rounded-4" style="background: #fff; border: 1px solid #eef2f6;">
                                            <p class="mb-1 text-muted small fw-bold text-uppercase" style="font-size: 0.6rem;">Visa Status</p>
                                            <span class="small fw-semibold text-dark">{{ $user->visa_type }}</span>
                                        </div>
                                    </div>
                                    @endif
                                    @php
                                        $approvedCount = $user->documentRequests->where('status', 'approved')->count();
                                    @endphp
                                    @if($approvedCount > 0)
                                    <div class="col-md-4">
                                        <div class="emp-field-box p-3 rounded-4" style="background: #fff; border: 1px solid #eef2f6;">
                                            <p class="mb-1 text-muted small fw-bold text-uppercase" style="font-size: 0.6rem;">Additional Docs</p>
                                            <span class="badge" style="background:#127464;color:#fff;">{{ $approvedCount }} Added</span>
                                        </div>
                                    </div>
                                    @endif
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- KPI Tab -->
                    <div class="tab-pane fade" id="kpi">
                        <div class="card border-0 shadow-sm rounded-4 mb-5 overflow-hidden" style="background: #fff;">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center justify-content-between mb-5">
                                    <div class="d-flex align-items-center">
                                        <div class="hitech-icon-wrap me-3" style="background:rgba(18,116,100,0.1);color:#127464;width:42px;height:42px;display:flex;align-items:center;justify-content:center;border-radius:10px;">
                                            <i class="bx bx-line-chart fs-4 text-success"></i>
                                        </div>
                                        <h6 class="mb-0 fw-bold fs-5" style="color: #1E293B;">Performance Metrics (KPIs)</h6>
                                    </div>
                                </div>
                                
                                @php
                                    $tasksCompleted = $user->tasks->where('status', 'completed')->count();
                                    $settings = \App\Models\Settings::first();
                                @endphp

                                <div class="row g-4 mb-6">
                                    <div class="col-md-4">
                                        <div class="emp-field-box text-center h-100 p-4 rounded-4" style="background: #F8FAFC; border: 1px solid #E2E8F0;">
                                            <div class="icon-stat-success mb-3 mx-auto" style="width: 50px; height: 50px; background: #e6f4f1; border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid #127464;">
                                                <i class="bx bxs-calendar-check text-success fs-3" style="color: #127464 !important;"></i>
                                            </div>
                                            <h3 class="fw-extrabold mb-1 text-dark fs-3">98.5%</h3>
                                            <p class="text-success mb-2 fw-bold fs-6"><i class="bx bx-trending-up me-1"></i> Excellent</p>
                                            <span class="text-uppercase text-muted fw-extrabold" style="font-size: 0.65rem; letter-spacing: 1px;">Attendance Rate</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="emp-field-box text-center h-100 p-4 rounded-4" style="background: #F8FAFC; border: 1px solid #E2E8F0;">
                                            <div class="icon-stat-primary mb-3 mx-auto" style="width: 50px; height: 50px; background: #EEF2FF; border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid #4F46E5;">
                                                <i class="bx bxs-star text-primary fs-3" style="color: #4F46E5 !important;"></i>
                                            </div>
                                            <h3 class="fw-extrabold mb-1 text-dark fs-3">4.8/5.0</h3>
                                            <p class="text-primary mb-2 fw-bold fs-6">Top Performer</p>
                                            <span class="text-uppercase text-muted fw-extrabold" style="font-size: 0.65rem; letter-spacing: 1px;">Efficiency Rating</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="emp-field-box text-center h-100 p-4 rounded-4" style="background: #F8FAFC; border: 1px solid #E2E8F0;">
                                            <div class="icon-stat-warning mb-3 mx-auto" style="width: 50px; height: 50px; background: #FFFBEB; border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid #D97706;">
                                                <i class="bx bxs-check-double text-warning fs-3" style="color: #D97706 !important;"></i>
                                            </div>
                                            <h3 class="fw-extrabold mb-1 text-dark fs-3">{{ $tasksCompleted }}</h3>
                                            <p class="text-warning mb-2 fw-bold fs-6">Tasks Closed</p>
                                            <span class="text-uppercase text-muted fw-extrabold" style="font-size: 0.65rem; letter-spacing: 1px;">Overall Output</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- KPI Targets Table --}}
                                <div class="mb-5">
                                    <div class="d-flex align-items-center mb-4 pt-4 border-top">
                                        <i class="bx bx-target-lock me-2 text-primary fs-4"></i>
                                        <h6 class="mb-0 fw-bold" style="color: #1E293B;">KPI Targets & Goals</h6>
                                    </div>
                                    <div class="table-responsive rounded-3 border" style="background: #fff;">
                                        <table class="table table-hover table-borderless mb-0 align-middle">
                                            <thead style="background:#F8FAFC;border-bottom:1px solid #E2E8F0;">
                                                <tr>
                                                    <th class="ps-4 py-3 text-muted fw-bold" style="font-size:0.72rem;text-transform:uppercase;">#</th>
                                                    <th class="py-3 text-muted fw-bold" style="font-size:0.72rem;text-transform:uppercase;">Type / Period</th>
                                                    <th class="py-3 text-muted fw-bold" style="font-size:0.72rem;text-transform:uppercase;">Target</th>
                                                    <th class="py-3 text-muted fw-bold" style="font-size:0.72rem;text-transform:uppercase;">Achieved</th>
                                                    <th class="py-3 text-muted fw-bold" style="font-size:0.72rem;text-transform:uppercase;">Incentive</th>
                                                    <th class="pe-4 py-3 text-muted fw-bold text-end" style="font-size:0.72rem;text-transform:uppercase;">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($user->salesTargets as $i => $target)
                                                    @php
                                                        $statusVal = $target->status?->value ?? $target->status ?? 'assigned';
                                                        $statusLabel = match($statusVal) {
                                                            'assigned'     => ['label' => 'Assigned',     'bg' => '#F1F5F9', 'color' => '#475569'],
                                                            'in_progress'  => ['label' => 'In Progress',  'bg' => '#FEF3C7', 'color' => '#D97706'],
                                                            'under_review' => ['label' => 'Under Review', 'bg' => '#E0F2FE', 'color' => '#0284C7'],
                                                            'achieved','completed' => ['label' => 'Achieved', 'bg' => '#DCFCE7', 'color' => '#16A34A'],
                                                            'missed','expired'    => ['label' => 'Missed',   'bg' => '#FEE2E2', 'color' => '#DC2626'],
                                                            'cancelled'    => ['label' => 'Cancelled',    'bg' => '#F1F5F9', 'color' => '#94A3B8'],
                                                            default        => ['label' => ucfirst($statusVal), 'bg' => '#F1F5F9', 'color' => '#475569'],
                                                        };
                                                        $targetAmount = $target->target_amount ?? $target->target_value ?? 0;
                                                        $achievedAmount = $target->achieved_amount ?? $target->achieved_value ?? 0;
                                                        $progress = $targetAmount > 0 ? min(100, round(($achievedAmount / $targetAmount) * 100)) : 0;
                                                    @endphp
                                                    <tr style="border-bottom:1px solid #F1F5F9;">
                                                        <td class="ps-4 text-muted fw-medium">{{ $i + 1 }}</td>
                                                        <td>
                                                            <span class="fw-bold text-dark d-block" style="font-size:0.875rem;">{{ ucfirst(str_replace('_', ' ', $target->target_type?->value ?? $target->target_type)) }}</span>
                                                            <small class="text-muted">FY {{ $target->period }}</small>
                                                        </td>
                                                        <td>
                                                            <span class="fw-bold text-dark">{{ $settings->currency_symbol ?? '₹' }}{{ number_format($targetAmount, 0) }}</span>
                                                            <div class="progress mt-1" style="height:4px;width:70px;">
                                                                <div class="progress-bar" style="width:{{ $progress }}%;background:#127464;"></div>
                                                            </div>
                                                        </td>
                                                        <td class="fw-semibold" style="color:#127464;">{{ $settings->currency_symbol ?? '₹' }}{{ number_format($achievedAmount, 0) }}</td>
                                                        <td class="text-muted small">
                                                            @if(($target->incentive_type?->value ?? $target->incentive_type) === 'fixed')
                                                                {{ $settings->currency_symbol ?? '₹' }}{{ number_format($target->incentive_amount ?? 0, 0) }}
                                                            @elseif(($target->incentive_type?->value ?? $target->incentive_type) === 'percentage')
                                                                {{ $target->incentive_percentage ?? 0 }}%
                                                            @else — @endif
                                                        </td>
                                                        <td class="pe-4 text-end">
                                                            <span class="badge rounded-pill fw-bold px-3 py-2" style="background:{{ $statusLabel['bg'] }};color:{{ $statusLabel['color'] }};font-size:0.65rem;">
                                                                {{ $statusLabel['label'] }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="6" class="text-center py-5">
                                                            <i class="bx bx-target-lock text-muted" style="font-size:3rem;opacity:0.3;"></i>
                                                            <p class="mt-2 text-muted small">No KPI targets assigned for this period.</p>
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                {{-- Tasks Table --}}
                                <div>
                                    <div class="d-flex align-items-center mb-4 pt-4 border-top">
                                        <i class="bx bx-task me-2 text-info fs-4"></i>
                                        <h6 class="mb-0 fw-bold" style="color: #1E293B;">Ongoing Tasks & Output</h6>
                                    </div>
                                    <div class="table-responsive rounded-3 border" style="background: #fff;">
                                        <table class="table table-hover table-borderless mb-0 align-middle">
                                            <thead style="background:#F8FAFC;border-bottom:1px solid #E2E8F0;">
                                                <tr>
                                                    <th class="ps-4 text-muted fw-bold py-3" style="font-size:0.75rem;text-transform:uppercase;">Task</th>
                                                    <th class="text-muted fw-bold py-3" style="font-size:0.75rem;text-transform:uppercase;">Assigned</th>
                                                    <th class="text-muted fw-bold py-3" style="font-size:0.75rem;text-transform:uppercase;">Due Date</th>
                                                    <th class="pe-4 text-end text-muted fw-bold py-3" style="font-size:0.75rem;text-transform:uppercase;">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($user->tasks as $task)
                                                    <tr style="border-bottom:1px solid #F1F5F9;">
                                                        <td class="ps-4">
                                                            <h6 class="mb-0 fw-bold text-dark" style="font-size:0.85rem;">{{ $task->title }}</h6>
                                                            <small class="text-muted d-block text-truncate" style="max-width:250px;">{{ \Illuminate\Support\Str::limit(strip_tags($task->description), 40) }}</small>
                                                        </td>
                                                        <td class="text-dark small">{{ $task->created_at->format('d M, Y') }}</td>
                                                        <td class="text-dark small">{{ $task->due_date ? $task->due_date->format('d M, Y') : 'N/A' }}</td>
                                                        <td class="pe-4 text-end">
                                                            @php
                                                                $badgeData = match($task->status) {
                                                                    'new'         => ['bg' => '#E0F2FE', 'color' => '#0284C7', 'text' => 'New'],
                                                                    'in_progress' => ['bg' => '#FEF3C7', 'color' => '#D97706', 'text' => 'In Progress'],
                                                                    'completed'   => ['bg' => '#DCFCE7', 'color' => '#16A34A', 'text' => 'Completed'],
                                                                    'closed'      => ['bg' => '#F1F5F9', 'color' => '#475569', 'text' => 'Closed'],
                                                                    'late'        => ['bg' => '#FEE2E2', 'color' => '#DC2626', 'text' => 'Late'],
                                                                    default       => ['bg' => '#E0E7FF', 'color' => '#4F46E5', 'text' => ucfirst($task->status)],
                                                                };
                                                            @endphp
                                                            <span class="badge rounded-pill fw-bold px-3 py-2" style="background:{{ $badgeData['bg'] }};color:{{ $badgeData['color'] }};font-size:0.65rem;">
                                                                {{ $badgeData['text'] }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="4" class="text-center py-5">
                                                            <i class="bx bx-task text-muted" style="font-size:3.5rem;opacity:0.2;"></i>
                                                            <p class="mt-2 text-muted small">No active tasks found in your profile.</p>
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

                    <!-- Activity Tab -->
                    <div class="tab-pane fade" id="activity">
                        <div class="card mb-4 emp-card border-0">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div class="d-flex align-items-center">
                                        <i class="bx bx-pulse me-2 fs-5" style="color: #127464;"></i>
                                        <h6 class="mb-0 fw-bold" style="color: #1E293B;">Recent Activity</h6>
                                    </div>
                                    <span class="badge rounded-pill" style="background: #E6F4F1; color: #127464; font-size: 0.7rem;">
                                        {{ $auditLogs->count() }} logs
                                    </span>
                                </div>

                                @if($auditLogs->count() > 0)
                                    <div class="table-responsive rounded-3 border" style="background: #fff;">
                                        <table class="table table-hover mb-0 align-middle">
                                            <thead style="background-color: #F8FAFC; border-bottom: 1px solid #E2E8F0;">
                                                <tr>
                                                    <th class="ps-4 py-3 text-muted fw-bold" style="font-size: 0.75rem; text-transform: uppercase;">Timestamp</th>
                                                    <th class="py-3 text-muted fw-bold" style="font-size: 0.75rem; text-transform: uppercase;">Event</th>
                                                    <th class="py-3 text-muted fw-bold" style="font-size: 0.75rem; text-transform: uppercase;">Module</th>
                                                    <th class="pe-4 py-3 text-muted fw-bold" style="font-size: 0.75rem; text-transform: uppercase;">Summary</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($auditLogs as $log)
                                                    @php
                                                        $eventLabel = strtoupper(str_replace('_', ' ', $log->event ?? 'updated'));
                                                        $moduleLabel = class_basename($log->auditable_type ?? 'Profile');
                                                        $newValueKeys = array_keys((array) ($log->new_values ?? []));
                                                        $summary = count($newValueKeys) > 0 ? implode(', ', array_slice($newValueKeys, 0, 3)) : 'Record updated';
                                                    @endphp
                                                    <tr style="border-bottom: 1px solid #F1F5F9;">
                                                        <td class="ps-4 py-3 text-dark small">{{ $log->created_at ? \Carbon\Carbon::parse($log->created_at)->format('d M Y, h:i A') : 'N/A' }}</td>
                                                        <td class="py-3">
                                                            <span class="badge rounded-pill" style="background: #F1F5F9; color: #334155; font-size: 0.65rem;">
                                                                {{ $eventLabel }}
                                                            </span>
                                                        </td>
                                                        <td class="py-3 text-dark small fw-semibold">{{ $moduleLabel }}</td>
                                                        <td class="pe-4 py-3 text-muted small">{{ $summary }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="text-center py-5 rounded-3" style="border: 2px dashed #E2E8F0; background-color: #F8FAFC;">
                                        <i class="bx bx-time text-muted mb-2" style="font-size: 2.4rem;"></i>
                                        <p class="text-muted small mb-0">No activity logs available yet.</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                </div>
                <!-- /Tab Content -->
            </div>
        </div>
    </div>
</div>

<!-- Modal Definitions -->

<!-- Include Shared Admin Modals -->
@include('_partials._modals.employees.edit_basic_info')
@include('_partials._modals.employees.edit_contact_info')
@include('_partials._modals.employees.edit_work_info')
@include('_partials._modals.employees.add_orUpdate_bankAccount')

<!-- Document Upload Modal (Matches Admin addOrUpdateDocument logic) -->
<div class="modal fade" id="modalAddUserDocument" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-hitech">
            <div class="modal-header modal-header-hitech">
                <div class="d-flex align-items-center">
                    <div class="modal-icon-header me-3"><i class="bx bx-upload"></i></div>
                    <h5 class="modal-title modal-title-hitech mb-0">Upload Verification Document</h5>
                </div>
                <button type="button" class="btn-close-hitech" data-bs-dismiss="modal"><i class="bx bx-x"></i></button>
            </div>
            <div class="modal-body modal-body-hitech">
                <form action="{{ route('employees.addOrUpdateDocument') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="userId" value="{{ $user->id }}">
                    <div class="mb-4">
                        <label class="form-label-hitech">Document Type</label>
                        <select name="documentName" id="docModalName" class="form-select form-select-hitech" required>
                            @foreach($documentTypes as $type)
                                <option value="{{ $type->name }}">{{ $type->name }}</option>
                            @endforeach
                            <option value="Other">Other Document</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label-hitech">Select File</label>
                        <input type="file" name="file" class="form-control form-control-hitech" accept=".pdf,.jpg,.jpeg,.png" required>
                        <p class="smallest text-muted mt-1 italic">Allowed: PDF, JPG, PNG (Max 10MB)</p>
                    </div>
                    <div class="mb-4">
                        <label class="form-label-hitech">Document Number / Remarks <span class="text-danger">*</span></label>
                        <input type="text" name="documentNumber" id="docModalNumber" class="form-control form-control-hitech" placeholder="Ex: Aadhaar No or PAN No" required>
                    </div>
                    <div class="modal-footer border-0 p-0 pt-3">
                        <button type="button" class="btn btn-hitech-modal-cancel me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-hitech-modal-submit flex-grow-1">Submit for Approval <i class="bx bx-send ms-1"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Document Preview Modal (Dark Mode Premium) -->
<div class="modal fade" id="modalViewDocument" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="background-color: #0f172a; border-radius: 20px;">
            <div class="modal-header border-0 px-4 py-3 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <div class="p-2 bg-info bg-opacity-10 rounded-3 me-3"><i class="bx bx-shield-quarter text-info fs-4"></i></div>
                    <h5 class="modal-title text-white fw-bold mb-0" id="docViewModalTitle">Secure Document Preview</h5>
                </div>
                <div class="d-flex gap-2">
                    <a id="docViewDownloadBtn" href="#" class="btn btn-sm btn-icon btn-outline-info rounded-circle" download><i class="bx bx-download"></i></a>
                    <button type="button" class="btn btn-sm btn-icon btn-outline-danger rounded-circle" data-bs-dismiss="modal"><i class="bx bx-x"></i></button>
                </div>
            </div>
            <div class="modal-body p-4 text-center" id="docViewContainer">
                <!-- Content loaded via JS -->
            </div>
        </div>
    </div>
</div>

@endsection

@section('page-script')
    @vite(['resources/js/main-helper.js', 'resources/assets/js/app/employee-view.js'])
    <script>
        // Global variables for employee context
        var user = @json($user);
        var role = @json($role);
        var attendanceType = @json($user->attendance_type);

        document.addEventListener('DOMContentLoaded', function() {
            // Tab scroll logic
            const tabsWrapper = document.querySelector('.rosemary-nav-tabs-wrapper');
            const nav = document.querySelector('.rosemary-nav-tabs');
            if (tabsWrapper && nav) {
                tabsWrapper.querySelectorAll('.rosemary-tab-arrow').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const dir = parseInt(btn.dataset.dir);
                        nav.scrollBy({ left: 300 * dir, behavior: 'smooth' });
                    });
                });
            }
        });

        // Fallback for missing function in some environments
        window.loadUserOnboardingData = function() {
            if (typeof window.loadEditBasicInfo === 'function') {
                window.loadEditBasicInfo();
            } else {
                console.warn("loadUserOnboardingData was called but neither it nor loadEditBasicInfo were found.");
            }
        };

        window.viewDocumentPopup = function(url, title) {
            const container = document.getElementById('docViewContainer');
            document.getElementById('docViewModalTitle').textContent = title;
            document.getElementById('docViewDownloadBtn').href = url;
            
            container.innerHTML = `<div class="text-white py-5"><div class="spinner-border text-info mb-3"></div><p>Loading document...</p></div>`;
            
            const modal = new bootstrap.Modal(document.getElementById('modalViewDocument'));
            modal.show();

            const isImage = /\.(jpg|jpeg|png|webp|gif)$/i.test(url);
            setTimeout(() => {
                if (isImage) {
                    container.innerHTML = `<img src="${url}" class="img-fluid rounded" style="max-height: 75vh;">`;
                } else {
                    container.innerHTML = `<iframe src="${url}" style="width:100%; height:75vh; border:none; background:white; border-radius: 8px;"></iframe>`;
                }
            }, 500);
        };

        // Document Number Viewer (Match view.blade.php)
        window.viewDocumentNumber = function(docName, docNumber) {
            document.getElementById('docViewModalTitle').textContent = docName;
            document.getElementById('docViewDownloadBtn').style.display = 'none';
            document.getElementById('docViewContainer').innerHTML = `
                <div style="text-align:center;padding:3rem 2rem;">
                    <div style="background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.15);border-radius:20px;padding:2.5rem 3rem;display:inline-block;min-width:300px;">
                        <div style="width:64px;height:64px;background:linear-gradient(135deg,#127464,#0e5a4e);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.2rem;">
                            <i class="bx bx-id-card" style="font-size:2rem;color:#fff;"></i>
                        </div>
                        <p style="color:rgba(255,255,255,0.55);font-size:0.72rem;text-transform:uppercase;letter-spacing:2px;margin-bottom:0.5rem;">${docName}</p>
                        <h2 style="color:#fff;font-weight:800;font-size:2rem;letter-spacing:5px;margin:0;font-family:monospace;">${docNumber}</h2>
                        <p style="color:rgba(255,255,255,0.35);font-size:0.7rem;margin-top:0.8rem;">Reference number on file</p>
                    </div>
                </div>`;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalViewDocument')).show();
        };

        // Document Management Modal Handler
        window.setDocModal = function(name, number) {
            document.getElementById('docModalName').value = name;
            document.getElementById('docModalNumber').value = (number === 'N/A' || number === 'undefined' ? '' : number);
        };

        function loadBankDetails() {
            const bank = user.bank_account || user.bankAccount;
            const $jq = window.jQuery;
            if(!user || !bank || !$jq) {
                if ($jq) {
                    $jq('#bankName, #bankCode, #accountName, #accountNumber, #confirmAccountNumber, #branchName, #branchCode').val('');
                }
                return;
            }
            $jq('#bankName').val(bank.bank_name || '');
            $jq('#bankCode').val(bank.bank_code || '');
            $jq('#accountName').val(bank.account_name || '');
            $jq('#accountNumber').val(bank.account_number || '');
            $jq('#confirmAccountNumber').val(bank.account_number || '');
            $jq('#branchName').val(bank.branch_name || '');
            $jq('#branchCode').val(bank.branch_code || '');
        }

        // Initialize on Load
        document.addEventListener('DOMContentLoaded', () => {
            const timer = setInterval(() => {
                if (window.jQuery) {
                    clearInterval(timer);
                    const $jq = window.jQuery;
                    if ($jq('#editBankAccountModal').length) {
                        $jq('#editBankAccountModal').on('show.bs.modal', loadBankDetails);
                    }
                }
            }, 100);
        });
    </script>
@endsection
