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
        'resources/assets/vendor/libs/bs-stepper/bs-stepper.scss',
        'resources/assets/vendor/scss/pages/hitech-portal.scss'
    ])
@endsection

@section('page-style')
    @vite(['resources/assets/vendor/scss/pages/page-user-view.scss', 'resources/assets/css/employee-view.css'])
    <style>
        .hitech-toggle-opt,
        .onboarding-tab-pill,
        .step-trigger {
            cursor: pointer !important;
        }

        .hitech-toggle-pill {
            display: flex;
            gap: 8px;
            background: rgba(0, 0, 0, 0.03);
            padding: 4px;
            border-radius: 50px;
        }

        .hitech-toggle-opt.active.opt-approve {
            background-color: #059669 !important;
            color: white !important;
        }

        .hitech-toggle-opt.active.opt-flag {
            background-color: #e11d48 !important;
            color: white !important;
        }
    </style>
@endsection

@section('vendor-script')
    @vite(['resources/assets/vendor/libs/moment/moment.js', 'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js', 'resources/assets/vendor/libs/sweetalert2/sweetalert2.js', 'resources/assets/vendor/libs/cleavejs/cleave.js', 'resources/assets/vendor/libs/cleavejs/cleave-phone.js', 'resources/assets/vendor/libs/select2/select2.js', 'resources/assets/vendor/libs/@form-validation/popular.js', 'resources/assets/vendor/libs/@form-validation/bootstrap5.js', 'resources/assets/vendor/libs/@form-validation/auto-focus.js', 'resources/assets/vendor/libs/bootstrap-datepicker/bootstrap-datepicker.js', 'resources/assets/vendor/libs/flatpickr/flatpickr.js', 'resources/assets/vendor/libs/bs-stepper/bs-stepper.js'])
@endsection
@section('content')

    @php
        $settings = \App\Models\Settings::first();
        $allPerformance = $user->salesTargets;
        $kraPerformance = $allPerformance->filter(fn($t) => str_contains($t->description, 'Type: KRA'));
        $kpiPerformance = $allPerformance->filter(fn($t) => !str_contains($t->description, 'Type: KRA'));

        // Helper to extract metric from description
        if (!function_exists('getMetric')) {
            function getMetric($desc)
            {
                if (preg_match('/Metric:\s*(.*?)\n/', $desc, $matches))
                    return $matches[1];
                if (preg_match('/Metric:\s*(.*?)($|\|)/', $desc, $matches))
                    return $matches[1];
                return 'Standard Target';
            }
        }
    @endphp

    <div class="">
        <div class="animate__animated animate__fadeIn">
            <div class="d-flex justify-content-between align-items-center mb-5">
                <div>
                    <h4 class="fw-extrabold mb-1" style="color: #1E293B; letter-spacing: -0.5px;">{{ $user->getFullName() }}
                    </h4>
                    <span class="text-muted fs-6">Manage employee details and financial information.</span>
                </div>
                <div>
                    <a href="{{ route('employees.index') }}"
                        class="btn btn-hitech rounded-pill px-4 d-flex align-items-center">
                        <i class="bx bx-left-arrow-alt me-2 fs-5"></i> Back
                    </a>
                </div>
            </div>

            <div class="row">
                <!-- User Sidebar -->
                <div class="col-xl-3 col-lg-3 col-md-4 col-12 mb-4">
                    <!-- User Card -->
                    <div class="card mb-4 border-0 shadow-sm position-relative overflow-hidden"
                        style="border-radius: 12px;">
                        <div style="height: 6px; background-color: #127464; position: absolute; top: 0; left: 0; right: 0;">
                        </div>
                        <div class="card-body pt-4">
                            <div class="user-avatar-section text-center position-relative mb-4">
                                <!-- Profile Picture -->
                                <div class="profile-picture-container position-relative d-inline-block"
                                    style="width: 110px; height: 110px;">
                                    @if ($user->profile_picture)
                                        <img class="img-fluid rounded-circle w-100 h-100 border border-4 border-white shadow-sm"
                                            src="{{ $user->getProfilePicture() }}" alt="{{ $user->name }}"
                                            id="userProfilePicture" style="object-fit: cover;"
                                            onerror="this.src='https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&background=127464&color=fff'" />
                                    @else
                                        <div class="rounded-circle w-100 h-100 d-flex align-items-center justify-content-center border border-4 border-white shadow-sm"
                                            style="background-color: #127464; color: white;">
                                            <h2 class="mb-0 text-white fw-bold">{{ $user->getInitials() }}</h2>
                                        </div>
                                    @endif
                                </div>

                                <div class="mt-2 text-center">
                                    @if($user->status == UserAccountStatus::ACTIVE)
                                        <span class="badge rounded-pill px-4 py-2 fw-bold"
                                            style="background-color: #E0F2F1; color: #127464; font-size: 0.65rem; letter-spacing: 0.5px;">ACTIVE</span>
                                    @elseif($user->status == UserAccountStatus::TERMINATED)
                                        <span class="badge rounded-pill px-4 py-2 fw-bold"
                                            style="background-color: #FEE2E2; color: #DC2626; font-size: 0.65rem; letter-spacing: 0.5px;">TERMINATED</span>
                                    @else
                                        <span class="badge rounded-pill px-4 py-2 fw-bold"
                                            style="background-color: #FEF3C7; color: #D97706; font-size: 0.65rem; letter-spacing: 0.5px;">{{ strtoupper($user->status->value) }}</span>
                                    @endif
                                </div>

                                <h5 class="mt-2 mb-1 fw-bold fs-4" style="color: #1E293B;">{{ $user->first_name }}
                                    {{ $user->last_name }}</h5>
                                <p class="text-muted mb-3 fs-6">{{ $user->designation ? $user->designation->name : 'N/A' }}
                                </p>
                            </div>

                            <div class="border-top pt-4">
                                <ul class="list-unstyled mb-0 fs-6" style="color: #475569;">
                                    <li class="mb-3 d-flex align-items-center">
                                        <i class="bx bx-qr text-muted me-3 fs-5"></i>
                                        <span class="fw-bold">ID:</span>&nbsp;{{ $user->code }}
                                    </li>
                                    <li class="mb-3 d-flex align-items-center">
                                        <i class="bx bx-fingerprint text-muted me-3 fs-5"></i>
                                        <span class="fw-bold">Biometric
                                            ID:</span>&nbsp;{{ $user->biometric_id ?? 'Not Mapped' }}
                                    </li>
                                    <li class="mb-3 d-flex align-items-center">
                                        <i class="bx bx-envelope text-muted me-3 fs-5"></i>
                                        {{ $user->email }}
                                    </li>
                                    <li class="mb-3 d-flex align-items-center">
                                        <i class="bx bx-phone text-muted me-3 fs-5"></i>
                                        {{ $user->official_phone ?? $user->phone ?? 'N/A' }}
                                    </li>
                                    <li class="mb-3 d-flex align-items-start">
                                        <i class="bx bx-calendar text-muted me-3 mt-1 fs-5"></i>
                                        <div class="flex-column">
                                            <small class="text-muted d-block text-uppercase fw-extrabold"
                                                style="font-size: 0.65rem; letter-spacing: 0.5px;">Date of Birth</small>
                                            <span
                                                class="text-dark fw-bold">{{ $user->dob ? \Carbon\Carbon::parse($user->dob)->format('d M Y') : 'N/A' }}</span>
                                            @if($user->dob)
                                                <span class="badge badge-hitech-success ms-1"
                                                    style="font-size: 0.6rem; padding: 2px 6px;">{{ \Carbon\Carbon::parse($user->dob)->age }}
                                                    YRS</span>
                                            @endif
                                        </div>
                                    </li>
                                    <li class="mb-3 d-flex align-items-start">
                                        <i class="bx bx-calendar-check text-muted me-3 mt-1 fs-5"></i>
                                        <div class="flex-column">
                                            <small class="text-muted d-block text-uppercase fw-extrabold"
                                                style="font-size: 0.65rem; letter-spacing: 0.5px;">Join Date</small>
                                            <span
                                                class="text-dark fw-bold">{{ $user->date_of_joining ? \Carbon\Carbon::parse($user->date_of_joining)->format('d M Y') : 'N/A' }}</span>
                                        </div>
                                    </li>
                                    <li class="mb-0 d-flex align-items-center">
                                        <i class="bx bx-building-house text-muted me-3 fs-5"></i>
                                        {{ $user->department ? $user->department->name : ($user->team ? $user->team->name : 'N/A Dept.') }}
                                    </li>
                                    <li class="mb-3 d-flex align-items-center">
                                        <i class="bx bx-shield-alt text-muted me-3 fs-5"></i>
                                        <span class="badge"
                                            style="background-color: #E0F2F1; color: #127464; font-size: 0.7rem;">{{ $user->leavePolicyProfile?->name ?? 'No Policy' }}</span>
                                    </li>
                                </ul>
                            </div>

                            <!-- Hidden File Input for Profile Picture Upload -->
                            <form id="profilePictureForm" action="{{ route('employee.changeEmployeeProfilePicture') }}"
                                method="POST" enctype="multipart/form-data" style="display: none;">
                                @csrf
                                <input type="hidden" name="userId" id="userId" value="{{ $user->id }}">
                                <input type="file" id="file" name="file" accept="image/*">
                            </form>
                        </div>
                    </div>
                    <!-- /User Card -->


                    <!-- Management Control Section -->
                    <div class="card emp-card mb-4 border-0 shadow-sm" style="border-radius: 16px; overflow: hidden;">
                        <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between"
                            style="background: linear-gradient(135deg, #127464 0%, #0E5A4E 100%);">
                            <h6 class="fw-bold mb-0 text-white small text-uppercase" style="letter-spacing: 1.5px;"><i
                                    class="bx bx-shield-quarter me-2"></i>Management Control</h6>
                            <span
                                class="badge {{ $user->status == UserAccountStatus::ACTIVE ? 'bg-success' : 'bg-danger' }} rounded-pill"
                                style="font-size: 0.6rem; letter-spacing: 1px;">{{ strtoupper($user->status->name ?? $user->status) }}</span>
                        </div>
                        <div class="card-body p-4 bg-white">
                            @if ($user->status == \App\Enums\UserAccountStatus::TERMINATED || $user->status == \App\Enums\UserAccountStatus::RELIEVED || $user->status == \App\Enums\UserAccountStatus::RETIRED)
                                <div class="p-4 rounded-4 mb-0 text-center border"
                                    style="background-color: #f8fafc; border-style: dashed !important; border-width: 2px !important; border-color: #e2e8f0 !important;">
                                    @if($user->status == \App\Enums\UserAccountStatus::TERMINATED)
                                        <div class="icon-stat-danger mb-3 mx-auto"
                                            style="width: 50px; height: 50px; background: #fee2e2; color: #ef4444; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                            <i class="bx bx-block fs-3"></i></div>
                                        <h6 class="fw-extrabold text-danger mb-1">TERMINATED</h6>
                                        <p class="text-muted mb-0 small">Access Revoked:
                                            {{ $user->exit_date ? Carbon::parse($user->exit_date)->format('d M Y') : 'N/A' }}</p>
                                    @else
                                        <div class="icon-stat-warning mb-3 mx-auto"
                                            style="width: 50px; height: 50px; background: #fef3c7; color: #f59e0b; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                            <i class="bx bx-exit fs-3"></i></div>
                                        <h6 class="fw-extrabold text-warning mb-1">
                                            {{ strtoupper($user->status->name ?? $user->status) }}</h6>
                                        <p class="text-muted mb-0 small">Effective On:
                                            {{ Carbon::parse($user->relieved_at ?? $user->retired_at)->format('d M Y') }}</p>
                                    @endif
                                </div>
                            @else
                                <!-- Status Selection -->
                                <div class="p-3 rounded-3 mb-4 border"
                                    style="background: rgba(18, 116, 100, 0.03); border-color: rgba(18, 116, 100, 0.1) !important;">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <p class="mb-0 fw-bold text-dark fs-6">Active Employment</p>
                                            <span class="smallest text-muted fw-medium">Toggle access to HR portal</span>
                                        </div>
                                        <div class="hitech-toggle-wrapper">
                                            <input type="checkbox" id="employeeStatusToggle" class="hitech-switch-input" @if ($user->status == \App\Enums\UserAccountStatus::ACTIVE) checked @endif
                                                onchange="toggleEmployeeStatus({{ $user->id }}, this.checked)">
                                            <label for="employeeStatusToggle" class="hitech-switch-label"></label>
                                        </div>
                                    </div>
                                </div>

                                @if ($user->status == \App\Enums\UserAccountStatus::ONBOARDING_SUBMITTED || $user->status == \App\Enums\UserAccountStatus::ONBOARDING_REQUESTED)
                                    <!-- Onboarding Actions -->
                                    <div class="d-flex gap-2 mb-4">
                                        <button type="button" class="btn btn-success btn-sm flex-fill rounded-pill fw-bold"
                                            onclick="approveOnboarding({{ $user->id }})">
                                            <i class="bx bx-check-circle me-1"></i> Approve
                                        </button>
                                        <button type="button" class="btn btn-sm flex-fill rounded-pill fw-bold text-white shadow-sm"
                                            style="background-color: #f97316; border-color: #ea580c;" data-bs-toggle="modal"
                                            data-bs-target="#modalReviewOnboarding">
                                            <i class="bx bx-show-alt me-1"></i> Review Onboarding
                                        </button>
                                    </div>
                                @endif

                                <!-- Probation Section Rewritten -->
                                <div class="probation-awesome-box p-3 rounded-3 mb-4"
                                    style="background: #f8fafc; border: 1px solid #e2e8f0;">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="fw-bold text-dark small"><i class="bx bx-time-five me-1 text-primary"></i>
                                            Probation Period</div>
                                        @if ($user->isUnderProbation())
                                            <div class="dropdown">
                                                <button class="btn btn-xs bg-white border text-dark shadow-xs rounded-pill"
                                                    type="button" data-bs-toggle="dropdown">Process <i
                                                        class="bx bx-chevron-down ms-1"></i></button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-2"
                                                    style="border-radius: 12px;">
                                                    <li><a class="dropdown-item py-2 rounded-2" href="javascript:void(0);"
                                                            data-bs-toggle="modal" data-bs-target="#confirmProbationModal"><i
                                                                 class="bx bx-check-circle me-2 text-success"></i>Confirm Success</a>
                                                    </li>
                                                    <li><a class="dropdown-item py-2 rounded-2" href="javascript:void(0);"
                                                            data-bs-toggle="modal" data-bs-target="#extendProbationModal"><i
                                                                 class="bx bx-calendar-plus me-2 text-warning"></i>Extend Period</a>
                                                    </li>
                                                    <li>
                                                        <hr class="dropdown-divider">
                                                    </li>
                                                    <li><a class="dropdown-item py-2 rounded-2 text-danger"
                                                            href="javascript:void(0);" data-bs-toggle="modal"
                                                            data-bs-target="#failProbationModal"><i
                                                                 class="bx bx-x-circle me-2"></i>Mark Failure</a></li>
                                                </ul>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="smallest text-muted">Status</span>
                                        <span
                                            class="badge {{ $user->isUnderProbation() ? 'bg-label-warning' : 'bg-label-success' }} rounded-pill"
                                            style="font-size: 0.6rem;">{{ $user->probation_status_display }}</span>
                                    </div>
                                    @if($user->probation_end_date)
                                        <div class="d-flex align-items-center justify-content-between mb-3">
                                            <span class="smallest text-muted">Ends On</span>
                                            <span
                                                class="fw-bold text-dark smallest">{{ Carbon::parse($user->probation_end_date)->format('d M Y') }}</span>
                                        </div>
                                    @endif

                                    @if($user->status === UserAccountStatus::ACTIVE && !$user->probation_confirmed_at && (auth()->id() === $user->reporting_to_id || auth()->user()->hasRole(['hr', 'admin', 'Admin', 'HR', 'Manager'])))
                                        <a href="{{ route('probation.evaluate', $user->id) }}" class="btn btn-primary btn-sm w-100 rounded-pill shadow-sm">
                                            <i class="bx bx-edit-alt me-1"></i> Fill Evaluation Form
                                        </a>
                                    @endif
                                </div>

                                <!-- Separation Action -->
                                <button type="button" class="btn btn-outline-danger w-100 rounded-pill fw-bold small p-2"
                                    data-bs-toggle="modal" data-bs-target="#terminateEmployeeModal"
                                    style="border-style: dashed; border-width: 2px;">
                                    <i class="bx bx-user-x me-1"></i> Initiate Termination
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="text-center mb-4">
                        <p class="text-muted" style="font-size: 0.75rem;"> Account created on
                            <strong>{{ Carbon::parse($user->created_at)->format('d M Y') }}</strong> by
                            <strong>{{ $user->createdBy != null ? $user->createdBy->getFullName() : 'Admin' }}.</strong></p>
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
                            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
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

                        .rosemary-tab-arrow:hover {
                            transform: translateY(-50%) scale(1.05);
                        }

                        .rosemary-tab-arrow:disabled {
                            opacity: 0.35;
                            cursor: not-allowed;
                            box-shadow: none;
                        }

                        .rosemary-tab-arrow.left {
                            left: 8px;
                        }

                        .rosemary-tab-arrow.right {
                            right: 8px;
                        }

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

                        .rosemary-nav-tabs .nav-item {
                            flex: 0 0 auto;
                            scroll-snap-align: start;
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

                        @media (max-width: 1400px) and (min-width: 992px) {
                            .rosemary-nav-tabs-wrapper {
                                border-radius: 18px;
                                padding: 10px;
                            }

                            .rosemary-nav-tabs {
                                flex-wrap: nowrap !important;
                                overflow-x: auto !important;
                                overflow-y: hidden;
                                justify-content: flex-start;
                                gap: 0.5rem;
                            }

                            .rosemary-nav-tabs .nav-link {
                                font-size: 0.72rem;
                                padding: 0.62rem 0.95rem !important;
                            }
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

                        @media (max-width: 767.98px) {
                            .rosemary-nav-tabs-wrapper {
                                display: none !important;
                                /* Hide full header on mobile */
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
                                box-shadow: 0 -5px 15px rgba(0, 0, 0, 0.05);
                            }
                        }

                        .mobile-tab-navigation {
                            display: none;
                        }

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
                            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
                        }
                    </style>


                    <!-- Tabs Navigation -->
                    <div class="rosemary-nav-tabs-wrapper mb-4">
                        <button class="rosemary-tab-arrow left" type="button" aria-label="Scroll tabs left" data-dir="-1">
                            <i class="bx bx-chevron-left"></i>
                        </button>
                        <ul class="nav nav-pills border-0 flex-column flex-md-row rosemary-nav-tabs" id="employeeTabs">
                            <li class="nav-item">
                                <a class="nav-link rosemary-nav-link d-flex align-items-center active" data-bs-toggle="tab"
                                    href="#basic-info">
                                    <i class="bx bx-user me-2 fs-5"></i> Basic Details
                                </a>
                            </li>
                            <li class="nav-item rosemary-nav-item">
                                <a class="nav-link rosemary-nav-link d-flex align-items-center" data-bs-toggle="tab"
                                    href="#contact">
                                    <i class="bx bx-phone-call me-2 fs-5"></i> Contact
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#employment-assets"><i
                                        class="bx bx-briefcase me-1"></i> Employment & Assets</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#banking-payroll"><i
                                        class="bx bx-credit-card me-1"></i> Banking & Payroll</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#documents"><i class="bx bx-file me-1"></i>
                                    Documents</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#tasks-tab"><i
                                        class="bx bx-bullseye me-1"></i> Key Result Areas (KRAs)</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#kpi"><i
                                        class="bx bx-bar-chart-alt-2 me-1"></i> Strategic KPIs</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#activity"><i class="bx bx-history me-1"></i>
                                    Activity Logs</a>
                            </li>
                        </ul>
                        <button class="rosemary-tab-arrow right" type="button" aria-label="Scroll tabs right" data-dir="1">
                            <i class="bx bx-chevron-right"></i>
                        </button>
                    </div>
                    <!-- /Tabs Navigation -->

                    <!-- Tab Content -->
                    <div class="tab-content p-0 m-0 border-0 shadow-none">

                        <!-- Details Tab -->
                        <div class="tab-pane fade show active" id="basic-info">
                            <div class="card mb-4 emp-card border-0">
                                <div class="card-body p-5">
                                    <div class="d-flex justify-content-between align-items-center mb-5">
                                        <div class="d-flex align-items-center">
                                            <div class="hitech-icon-wrap me-3"
                                                style="background: rgba(18, 116, 100, 0.1); color: #127464; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 10px;">
                                                <i class="bx bx-info-circle fs-4"></i>
                                            </div>
                                            <h6 class="mb-0 fw-bold fs-5" style="color: #1E293B;">Primary Information</h6>
                                        </div>
                                        <button class="btn btn-hitech px-4 rounded-pill shadow-sm" data-bs-toggle="modal"
                                            data-bs-target="#offcanvasEditBasicInfo" onclick="loadUserOnboardingData()">
                                            <i class="bx bx-edit-alt me-1"></i> Update Details
                                        </button>
                                    </div>

                                    <div class="row g-4">
                                        <!-- NAME PLATES -->
                                        <div class="col-md-6">
                                            <div class="p-3 h-100 rounded-4"
                                                style="background: #F8FAFC; border: 1px solid #E2E8F0; transition: all 0.3s ease;">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-white rounded-circle p-2 me-3 shadow-sm d-flex align-items-center justify-content-center"
                                                        style="width: 44px; height: 44px;">
                                                        <i class="bx bx-user text-primary fs-4"></i>
                                                    </div>
                                                    <div>
                                                        <p class="mb-1 text-muted smallest fw-bold text-uppercase"
                                                            style="font-size: 0.65rem;">First Name</p>
                                                        <p class="mb-0 fw-bold text-dark fs-6">{{ $user->first_name }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="p-3 h-100 rounded-4"
                                                style="background: #F8FAFC; border: 1px solid #E2E8F0; transition: all 0.3s ease;">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-white rounded-circle p-2 me-3 shadow-sm d-flex align-items-center justify-content-center"
                                                        style="width: 44px; height: 44px;">
                                                        <i class="bx bx-user text-primary fs-4"></i>
                                                    </div>
                                                    <div>
                                                        <p class="mb-1 text-muted smallest fw-bold text-uppercase"
                                                            style="font-size: 0.65rem;">Last Name</p>
                                                        <p class="mb-0 fw-bold text-dark fs-6">{{ $user->last_name }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- MARITAL & BLOOD -->
                                        <div class="col-md-6">
                                            <div class="p-3 h-100 rounded-4"
                                                style="background: rgba(239, 68, 68, 0.04); border: 1px solid rgba(239, 68, 68, 0.1);">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-white rounded-circle p-2 me-3 shadow-sm d-flex align-items-center justify-content-center"
                                                        style="width: 44px; height: 44px;">
                                                        <i class="bx bx-heart text-danger fs-4"></i>
                                                    </div>
                                                    <div>
                                                        <p class="mb-1 text-muted smallest fw-bold text-uppercase"
                                                            style="font-size: 0.65rem;">Marital Status</p>
                                                        <p class="mb-0 fw-bold text-dark fs-6">
                                                            {{ ucfirst($user->marital_status ?? 'Single') }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="p-3 h-100 rounded-4"
                                                style="background: rgba(34, 197, 94, 0.04); border: 1px solid rgba(34, 197, 94, 0.1);">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-white rounded-circle p-2 me-3 shadow-sm d-flex align-items-center justify-content-center"
                                                        style="width: 44px; height: 44px;">
                                                        <i class="bx bx-droplet text-success fs-4"></i>
                                                    </div>
                                                    <div>
                                                        <p class="mb-1 text-muted smallest fw-bold text-uppercase"
                                                            style="font-size: 0.65rem;">Blood Group</p>
                                                        <p class="mb-0 fw-extrabold text-dark fs-6">
                                                            {{ $user->blood_group ?? 'Not Set' }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- PARENTS -->
                                        <div class="col-md-6">
                                            <div class="p-3 h-100 rounded-4"
                                                style="background: #F8FAFC; border: 1px solid #E2E8F0;">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-white rounded-circle p-2 me-3 shadow-sm d-flex align-items-center justify-content-center"
                                                        style="width: 44px; height: 44px;">
                                                        <i class="bx bx-male-sign text-muted fs-4"></i>
                                                    </div>
                                                    <div>
                                                        <p class="mb-1 text-muted smallest fw-bold text-uppercase"
                                                            style="font-size: 0.65rem;">Father's Name</p>
                                                        <p class="mb-0 fw-bold text-dark fs-6">
                                                            {{ $user->father_name ?? 'Not Provided' }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="p-3 h-100 rounded-4"
                                                style="background: #F8FAFC; border: 1px solid #E2E8F0;">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-white rounded-circle p-2 me-3 shadow-sm d-flex align-items-center justify-content-center"
                                                        style="width: 44px; height: 44px;">
                                                        <i class="bx bx-female-sign text-muted fs-4"></i>
                                                    </div>
                                                    <div>
                                                        <p class="mb-1 text-muted smallest fw-bold text-uppercase"
                                                            style="font-size: 0.65rem;">Mother's Name</p>
                                                        <p class="mb-0 fw-bold text-dark fs-6">
                                                            {{ $user->mother_name ?? 'Not Provided' }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- SPOUSE/CHILDREN -->
                                        @if($user->marital_status == 'married')
                                            <div class="col-md-6">
                                                <div class="p-3 h-100 rounded-4"
                                                    style="background: rgba(37, 99, 235, 0.04); border: 1px solid rgba(37, 99, 235, 0.1);">
                                                    <div class="d-flex align-items-center">
                                                        <div class="bg-white rounded-circle p-2 me-3 shadow-sm d-flex align-items-center justify-content-center"
                                                            style="width: 44px; height: 44px;">
                                                            <i class="bx bx-user-pin text-primary fs-4"></i>
                                                        </div>
                                                        <div>
                                                            <p class="mb-1 text-muted smallest fw-bold text-uppercase"
                                                                style="font-size: 0.65rem;">Spouse Name</p>
                                                            <p class="mb-0 fw-bold text-dark fs-6">
                                                                {{ $user->spouse_name ?? 'N/A' }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 h-100 rounded-4"
                                                    style="background: #F8FAFC; border: 1px solid #E2E8F0;">
                                                    <div class="d-flex align-items-center">
                                                        <div class="bg-white rounded-circle p-2 me-3 shadow-sm d-flex align-items-center justify-content-center"
                                                            style="width: 44px; height: 44px;">
                                                            <i class="bx bx-group text-muted fs-4"></i>
                                                        </div>
                                                        <div>
                                                            <p class="mb-1 text-muted smallest fw-bold text-uppercase"
                                                                style="font-size: 0.65rem;">No. of Children</p>
                                                            <p class="mb-0 fw-bold text-dark fs-6">
                                                                {{ $user->no_of_children ?? '0' }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif

                                        <!-- NATIONALITY -->
                                        <div class="col-md-6">
                                            <div class="emp-field-box">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-white rounded p-2 me-3 shadow-xs d-flex align-items-center justify-content-center"
                                                        style="width: 40px; height: 40px;">
                                                        <i class="bx bx-globe text-muted fs-4"></i>
                                                    </div>
                                                    <div>
                                                        <p class="mb-0 text-muted smallest fw-bold text-uppercase">Birth
                                                            Country</p>
                                                        <p class="mb-0 fw-bold text-dark">
                                                            {{ $user->birth_country ?? 'N/A' }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="emp-field-box">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-white rounded p-2 me-3 shadow-xs d-flex align-items-center justify-content-center"
                                                        style="width: 40px; height: 40px;">
                                                        <i class="bx bx-flag text-muted fs-4"></i>
                                                    </div>
                                                    <div>
                                                        <p class="mb-0 text-muted smallest fw-bold text-uppercase">
                                                            Citizenship</p>
                                                        <p class="mb-0 fw-bold text-dark">{{ $user->citizenship ?? 'N/A' }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>


                        <!-- Employment & Assets Tab -->
                        <div class="tab-pane fade" id="employment-assets">
                            <div class="card mb-4 emp-card">
                                <div class="card-body p-5">
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <div class="d-flex align-items-center">
                                            <div class="hitech-icon-wrap me-3"
                                                style="background: rgba(18, 116, 100, 0.1); color: #127464; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 10px;">
                                                <i class="bx bx-briefcase fs-4"></i>
                                            </div>
                                            <h6 class="mb-0 fw-bold fs-5" style="color: #1E293B;">Work Information</h6>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-hitech px-4 rounded-pill shadow-sm"
                                                data-bs-toggle="modal" data-bs-target="#offcanvasEditWorkInfo"
                                                onclick="loadSelectList()">
                                                <i class="bx bx-edit-alt me-1"></i> Update Details
                                            </button>
                                        </div>
                                    </div>

                                    <div class="row g-4">
                                        <!-- Designation -->
                                        <div class="col-md-4">
                                            <div class="emp-field-box">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-white rounded p-2 me-3 shadow-sm d-flex align-items-center justify-content-center"
                                                        style="width: 40px; height: 40px;">
                                                        <i class="bx bx-award text-muted fs-4"></i>
                                                    </div>
                                                    <div>
                                                        <p class="mb-0 text-muted small fw-bold text-uppercase"
                                                            style="font-size: 0.65rem; letter-spacing: 0.05em;">Designation
                                                        </p>
                                                        <p class="mb-0 fw-bold text-dark fs-6">
                                                            {{ $user->designation?->name ?? 'N/A' }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Department -->
                                        <div class="col-md-4">
                                            <div class="emp-field-box">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-white rounded p-2 me-3 shadow-sm d-flex align-items-center justify-content-center"
                                                        style="width: 40px; height: 40px;">
                                                        <i class="bx bx-building text-muted fs-4"></i>
                                                    </div>
                                                    <div>
                                                        <p class="mb-0 text-muted small fw-bold text-uppercase"
                                                            style="font-size: 0.65rem; letter-spacing: 0.05em;">Department
                                                        </p>
                                                        <p class="mb-0 fw-bold text-dark fs-6">
                                                            {{ $user->department?->name ?? 'N/A' }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- System Role -->
                                        <div class="col-md-4">
                                            <div class="emp-field-box">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-white rounded p-2 me-3 shadow-sm d-flex align-items-center justify-content-center"
                                                        style="width: 40px; height: 40px;">
                                                        <i class="bx bx-shield-alt text-muted fs-4"></i>
                                                    </div>
                                                    <div>
                                                        <p class="mb-0 text-muted small fw-bold text-uppercase"
                                                            style="font-size: 0.65rem; letter-spacing: 0.05em;">System Role</p>
                                                        <p class="mb-0 fw-bold text-dark fs-6">
                                                            {{ $user->getRoleNames()->first() ?? 'Employee' }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Reporting To -->
                                        <div class="col-md-4">
                                            <div class="emp-field-box">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-white rounded p-2 me-3 shadow-sm d-flex align-items-center justify-content-center"
                                                        style="width: 40px; height: 40px;">
                                                        <i class="bx bx-user-voice text-muted fs-4"></i>
                                                    </div>
                                                    <div>
                                                        <p class="mb-0 text-muted small fw-bold text-uppercase"
                                                            style="font-size: 0.65rem; letter-spacing: 0.05em;">Reporting To
                                                        </p>
                                                        <p class="mb-0 fw-bold text-dark fs-6">
                                                            {{ $user->reportingTo?->getFullName() ?? 'N/A' }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Site / Unit -->
                                        <div class="col-md-4">
                                            <div class="emp-field-box">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-white rounded p-2 me-3 shadow-sm d-flex align-items-center justify-content-center"
                                                        style="width: 40px; height: 40px;">
                                                        <i class="bx bx-map-pin text-muted fs-4"></i>
                                                    </div>
                                                    <div>
                                                        <p class="mb-0 text-muted small fw-bold text-uppercase"
                                                            style="font-size: 0.65rem; letter-spacing: 0.05em;">Site / Unit
                                                        </p>
                                                        <p class="mb-0 fw-bold text-dark fs-6">
                                                            {{ $user->site?->name ?? 'Not Assigned' }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Leave Policy -->
                                        <div class="col-md-4">
                                            <div class="emp-field-box">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-white rounded p-2 me-3 shadow-sm d-flex align-items-center justify-content-center"
                                                        style="width: 40px; height: 40px;">
                                                        <i class="bx bx-calendar-event text-muted fs-4"></i>
                                                    </div>
                                                    <div>
                                                        <p class="mb-0 text-muted small fw-bold text-uppercase"
                                                            style="font-size: 0.65rem; letter-spacing: 0.05em;">Leave Policy</p>
                                                        <p class="mb-0 fw-bold text-dark fs-6">
                                                            @if($user->leavePolicyProfile)
                                                                <span class="badge bg-label-success px-3">{{ $user->leavePolicyProfile->name }}</span>
                                                            @else
                                                                <span class="text-muted small">Not Assigned</span>
                                                            @endif
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Date of Joining -->
                                        <div class="col-md-4">
                                            <div class="emp-field-box">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-white rounded p-2 me-3 shadow-sm d-flex align-items-center justify-content-center"
                                                        style="width: 40px; height: 40px;">
                                                        <i class="bx bx-calendar text-muted fs-4"></i>
                                                    </div>
                                                    <div>
                                                        <p class="mb-0 text-muted small fw-bold text-uppercase"
                                                            style="font-size: 0.65rem; letter-spacing: 0.05em;">Date of
                                                            Joining</p>
                                                        <p class="mb-0 fw-bold text-dark fs-6">
                                                            {{ $user->date_of_joining ? \Carbon\Carbon::parse($user->date_of_joining)->format('d M Y') : 'N/A' }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Attendance Type -->
                                        <div class="col-md-4">
                                            <div class="emp-field-box">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-white rounded p-2 me-3 shadow-sm d-flex align-items-center justify-content-center"
                                                        style="width: 40px; height: 40px;">
                                                        <i class="bx bx-check-shield text-muted fs-4"></i>
                                                    </div>
                                                    <div>
                                                        <p class="mb-0 text-muted small fw-bold text-uppercase"
                                                            style="font-size: 0.65rem; letter-spacing: 0.05em;">Attendance
                                                            Type</p>
                                                        <p class="mb-0 fw-bold text-dark fs-6">
                                                            {{ ucfirst($user->attendance_type ?? 'Open') }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Biometric ID -->
                                        <div class="col-md-4">
                                            <div class="emp-field-box">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-white rounded p-2 me-3 shadow-sm d-flex align-items-center justify-content-center"
                                                        style="width: 40px; height: 40px;">
                                                        <i class="bx bx-fingerprint text-muted fs-4"></i>
                                                    </div>
                                                    <div>
                                                        <p class="mb-0 text-muted small fw-bold text-uppercase"
                                                            style="font-size: 0.65rem; letter-spacing: 0.05em;">Biometric ID
                                                        </p>
                                                        <p class="mb-0 fw-bold text-dark fs-6">
                                                            {{ $user->biometric_id ?? 'Not Mapped' }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Leave Balances Section -->
                            @php
                                $leaveBalances = $user->leaveBalances()->with('leaveType')->get();
                                $totalLeaves = $leaveBalances->sum('balance');
                                $usedLeaves = $leaveBalances->sum('used');
                                $remainingLeaves = $totalLeaves - $usedLeaves;
                            @endphp
                            <div class="card mb-4 emp-card">
                                <div class="card-body p-5">
                                    <div class="d-flex align-items-center justify-content-between mb-4">
                                        <div class="d-flex align-items-center">
                                            <div class="hitech-icon-wrap me-3"
                                                style="background: rgba(18, 116, 100, 0.1); color: #127464; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 10px;">
                                                <i class="bx bx-calendar-check fs-4"></i>
                                            </div>
                                            <h6 class="mb-0 fw-bold fs-5" style="color: #1E293B;">Leave Summary</h6>
                                        </div>
                                        <button
                                            class="btn btn-hitech-primary px-4 rounded-pill shadow-sm d-flex align-items-center"
                                            data-bs-toggle="modal" data-bs-target="#modalManualLeaveCredit">
                                            <i class="bx bx-plus-circle me-2"></i> Add Comp Off
                                        </button>
                                    </div>

                                    <div class="row g-6 mb-5">
                                        <div class="col-md-4">
                                            <div class="p-3 rounded-3"
                                                style="background: #F8FAFC; border: 1px solid #E2E8F0;">
                                                <p class="mb-1 text-muted smallest fw-bold text-uppercase"
                                                    style="font-size: 0.65rem;">Total Allocated</p>
                                                <h5 class="mb-0 fw-bold text-dark">{{ number_format($totalLeaves, 1) }}</h5>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="p-3 rounded-3"
                                                style="background: #FFF7ED; border: 1px solid #FED7AA;">
                                                <p class="mb-1 text-muted smallest fw-bold text-uppercase"
                                                    style="font-size: 0.65rem;">Total Used</p>
                                                <h5 class="mb-0 fw-bold" style="color: #C2410C;">
                                                    {{ number_format($usedLeaves, 1) }}</h5>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="p-3 rounded-3"
                                                style="background: #ECFDF3; border: 1px solid #BBF7D0;">
                                                <p class="mb-1 text-muted smallest fw-bold text-uppercase"
                                                    style="font-size: 0.65rem;">Available Balance</p>
                                                <h5 class="mb-0 fw-bold" style="color: #15803D;">
                                                    {{ number_format($remainingLeaves, 1) }}</h5>
                                            </div>
                                        </div>
                                    </div>

                                    @if($leaveBalances->count() > 0)
                                        <div class="table-responsive rounded-3 border">
                                            <table class="table table-hover mb-0 align-middle">
                                                <thead class="bg-light">
                                                    <tr>
                                                        <th class="ps-4 py-2 small fw-bold text-uppercase">Leave Type</th>
                                                        <th class="py-2 small fw-bold text-uppercase text-center">Carry Fwd</th>
                                                        <th class="py-2 small fw-bold text-uppercase text-center">Accrued</th>
                                                        <th class="py-2 small fw-bold text-uppercase text-center">Allocated</th>
                                                        <th class="py-2 small fw-bold text-uppercase text-center">Used</th>
                                                        <th class="pe-4 py-2 small fw-bold text-uppercase text-end">Available</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($leaveBalances as $bal)
                                                        @php
                                                            $c_fwd = $bal->carry_forward_last_year ?? 0;
                                                            $c_accrued = $bal->accrued_this_year ?? 0;
                                                            // Fallback for existing data: if breakdown is missing but balance exists
                                                            if ($bal->balance > 0 && $c_fwd == 0 && $c_accrued == 0) {
                                                                $c_accrued = $bal->balance;
                                                            }
                                                        @endphp
                                                        <tr>
                                                            <td class="ps-4 py-3">
                                                                <div class="d-flex align-items-center">
                                                                    <div class="bg-label-primary rounded p-1 me-2"><i
                                                                            class="bx bx-calendar-event"></i></div>
                                                                    <span
                                                                        class="fw-bold text-dark">{{ $bal->leaveType->name ?? 'Unknown' }}</span>
                                                                </div>
                                                            </td>
                                                            <td class="py-3 text-center text-muted">
                                                                {{ number_format($c_fwd, 1) }}</td>
                                                            <td class="py-3 text-center text-muted">
                                                                {{ number_format($c_accrued, 1) }}</td>
                                                            <td class="py-3 text-center fw-bold">
                                                                {{ number_format($bal->balance, 1) }}</td>
                                                            <td class="py-3 text-center text-danger">
                                                                {{ number_format($bal->used, 1) }}</td>
                                                            <td class="pe-4 py-3 text-end"><span
                                                                    class="badge bg-label-success">{{ number_format($bal->balance - $bal->used, 1) }}</span>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif

                                    <!-- Transaction Ledger -->
                                    <div class="mt-5 pt-4 border-top">
                                        <h6 class="fw-bold mb-4 d-flex align-items-center" style="color: #1E293B;">
                                            <div class="hitech-icon-wrap me-2" style="background: rgba(18, 116, 100, 0.1); color: #127464; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                                                <i class="bx bx-history fs-5"></i>
                                            </div>
                                            Detailed Transaction Ledger
                                        </h6>
                                        <div class="table-responsive rounded-3 border bg-white">
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
                                                                $typeName = $item->type;
                                                                if($typeName == 'Credit' || $typeName == 'Accrued' || $typeName == 'Carry Forward') $typeColor = 'success';
                                                                elseif($typeName == 'Request') $typeColor = 'info';
                                                                elseif($typeName == 'Deduction') $typeColor = 'danger';
                                                            @endphp
                                                            <span class="badge bg-label-{{ $typeColor }} py-0 px-2" style="font-size: 0.7rem;">{{ $typeName }}</span>
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

                            <!-- Assets Table -->
                            @php
                                $assignedAssets = $user->assets()->with('category')->latest()->get();
                            @endphp
                            <div class="card mb-4 emp-card">
                                <div class="card-body p-5">
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <div class="d-flex align-items-center">
                                            <div class="hitech-icon-wrap me-3"
                                                style="background: rgba(18, 116, 100, 0.1); color: #127464; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 10px;">
                                                <i class="bx bx-laptop fs-4"></i>
                                            </div>
                                            <h6 class="mb-0 fw-bold fs-5" style="color: #1E293B;">Assigned Assets</h6>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <button class="btn btn-hitech-secondary btn-sm px-3 rounded-pill shadow-sm"
                                                data-bs-toggle="modal" data-bs-target="#modalAllotDevice">
                                                <i class="bx bx-plus me-1"></i> Allot Device
                                            </button>
                                            <span class="badge rounded-pill"
                                                style="background: #E6F4F1; color: #127464; font-size: 0.75rem;">
                                                {{ $assignedAssets->count() }} Items
                                            </span>
                                        </div>
                                    </div>

                                    @if($assignedAssets->count() > 0)
                                        <div class="table-responsive rounded-3 border" style="background: #fff;">
                                            <table class="table table-hover mb-0 align-middle">
                                                <thead style="background-color: #F8FAFC; border-bottom: 1px solid #E2E8F0;">
                                                    <tr>
                                                        <th class="ps-4 py-3 text-muted fw-bold"
                                                            style="font-size: 0.7rem; text-transform: uppercase;">Asset (Brand /
                                                            Model)</th>
                                                        <th class="py-3 text-muted fw-bold"
                                                            style="font-size: 0.7rem; text-transform: uppercase;">Asset Code /
                                                            ID</th>
                                                        <th class="py-3 text-muted fw-bold"
                                                            style="font-size: 0.7rem; text-transform: uppercase;">Category /
                                                            Type</th>
                                                        <th class="py-3 text-muted fw-bold"
                                                            style="font-size: 0.7rem; text-transform: uppercase;">Serial / Tag
                                                        </th>
                                                        <th class="pe-4 py-3 text-muted fw-bold"
                                                            style="font-size: 0.7rem; text-transform: uppercase;">Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($assignedAssets as $asset)
                                                        <tr style="border-bottom: 1px solid #F1F5F9;">
                                                            <td class="ps-4 py-3 text-dark fw-bold fs-6">{{ $asset->name ?? 'N/A' }}
                                                            </td>
                                                            <td class="py-3 text-muted small">{{ $asset->asset_code ?? 'N/A' }}</td>
                                                            <td class="py-3 text-dark small">{{ $asset->category->name ?? 'N/A' }}
                                                            </td>
                                                            <td class="py-3 text-muted small">
                                                                {{ $asset->serial_number ?? ($asset->service_tag ?? 'N/A') }}</td>
                                                            <td class="pe-4 py-3">
                                                                <span class="badge rounded-pill"
                                                                    style="background: #F1F5F9; color: #334155; font-size: 0.65rem;">
                                                                    {{ strtoupper($asset->status ?? 'N/A') }}
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @else
                                        <div class="text-center py-5 rounded-3"
                                            style="border: 2px dashed #E2E8F0; background-color: #F8FAFC;">
                                            <i class="bx bx-devices text-muted mb-2" style="font-size: 2.4rem;"></i>
                                            <p class="text-muted small mb-0">No assets are assigned to this employee.</p>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Asset History Section --}}
                            @php
                                $assetHistory = \Illuminate\Support\Facades\Schema::hasTable('asset_assignments')
                                    ? \App\Models\AssetAssignment::where('user_id', $user->id)->with('asset')->latest()->get()
                                    : collect();
                            @endphp
                            @if(\Illuminate\Support\Facades\Schema::hasTable('asset_assignments'))
                                <div class="card mb-4 emp-card shadow-sm border-0">
                                    <div class="card-body p-4">
                                        <div class="d-flex align-items-center mb-4">
                                            <div class="hitech-icon-wrap me-3"
                                                style="background: rgba(108, 117, 125, 0.1); color: #6c757d; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 10px;">
                                                <i class="bx bx-history fs-4"></i>
                                            </div>
                                            <h6 class="mb-0 fw-bold fs-5" style="color: #1E293B;">Asset Assignment History</h6>
                                        </div>

                                        @if($assetHistory->count() > 0)
                                            <div class="table-responsive rounded-3 border">
                                                <table class="table table-hover mb-0 align-middle">
                                                    <thead style="background-color: #F8FAFC;">
                                                        <tr>
                                                            <th class="ps-4 py-3 text-muted fw-bold"
                                                                style="font-size: 0.7rem; text-transform: uppercase;">Asset</th>
                                                            <th class="py-3 text-muted fw-bold"
                                                                style="font-size: 0.7rem; text-transform: uppercase;">Assigned At
                                                            </th>
                                                            <th class="py-3 text-muted fw-bold"
                                                                style="font-size: 0.7rem; text-transform: uppercase;">Returned At
                                                            </th>
                                                            <th class="pe-4 py-3 text-muted fw-bold"
                                                                style="font-size: 0.7rem; text-transform: uppercase;">Notes</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($assetHistory as $log)
                                                            <tr>
                                                                <td class="ps-4 py-3">
                                                                    <div class="fw-bold text-dark">
                                                                        {{ $log->asset->name ?? 'Inventory Asset' }}</div>
                                                                    <div class="smallest text-muted">
                                                                        {{ $log->asset->asset_code ?? 'N/A' }}</div>
                                                                </td>
                                                                <td class="py-3 text-dark small">
                                                                    {{ $log->assigned_at ? $log->assigned_at->format('d M Y, h:i A') : 'N/A' }}
                                                                </td>
                                                                <td class="py-3 small">
                                                                    @if($log->returned_at)
                                                                        <span
                                                                            class="text-success fw-bold">{{ $log->returned_at->format('d M Y, h:i A') }}</span>
                                                                    @else
                                                                        <span
                                                                            class="badge bg-label-primary px-2 py-1 rounded-pill">Currently
                                                                            Held</span>
                                                                    @endif
                                                                </td>
                                                                <td class="pe-4 py-3 text-muted small">{{ $log->notes ?: 'N/A' }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @else
                                            <div class="text-center py-5 bg-light rounded-3" style="border: 1px dashed #ced4da;">
                                                <i class="bx bx-info-circle text-muted fs-2 mb-2"></i>
                                                <p class="text-muted small mb-0">No historical assignment logs found for this
                                                    employee.</p>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>


                        <!-- Contact Tab -->
                        <div class="tab-pane fade" id="contact">
                            <div class="card mb-4 emp-card border-0">
                                <div class="card-body p-5">
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <div class="d-flex align-items-center">
                                            <div class="hitech-icon-wrap me-3"
                                                style="background: rgba(18, 116, 100, 0.1); color: #127464; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 10px;">
                                                <i class="bx bx-map-pin fs-4"></i>
                                            </div>
                                            <h6 class="mb-0 fw-bold fs-5" style="color: #1E293B;">Contact & Address Details
                                            </h6>
                                        </div>
                                        <button class="btn btn-hitech-primary px-4 rounded-pill shadow-sm"
                                            data-bs-toggle="modal" data-bs-target="#offcanvasEditContactInfo">
                                            <i class="bx bx-edit-alt me-1"></i> Update Info
                                        </button>
                                    </div>

                                    <div class="row g-4">
                                        <!-- EMAIL & PHONE -->
                                        <div class="col-md-4">
                                            <div class="emp-field-box border-dashed h-100 p-3"
                                                style="transition: all 0.3s ease; border-radius: 12px; cursor: default;">
                                                <p class="mb-1 text-muted smallest fw-bold text-uppercase"
                                                    style="font-size: 0.65rem;">Login / Official Email</p>
                                                <p class="mb-0 fw-bold text-dark fs-6">{{ $user->email }}</p>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="emp-field-box h-100 p-3"
                                                style="transition: all 0.3s ease; border-radius: 12px; cursor: default;">
                                                <p class="mb-1 text-muted smallest fw-bold text-uppercase"
                                                    style="font-size: 0.65rem;">Personal Email</p>
                                                <p class="mb-0 fw-bold text-dark fs-6">
                                                    {{ $user->personal_email ?? 'Not Provided' }}</p>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="emp-field-box h-100 p-3"
                                                style="transition: all 0.3s ease; border-radius: 12px; cursor: default;">
                                                <p class="mb-1 text-muted smallest fw-bold text-uppercase"
                                                    style="font-size: 0.65rem;">Primary / Personal Phone</p>
                                                <p class="mb-0 fw-bold text-dark fs-6">{{ $user->phone }}</p>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="emp-field-box h-100 p-3"
                                                style="transition: all 0.3s ease; border-radius: 12px; cursor: default;">
                                                <p class="mb-1 text-muted smallest fw-bold text-uppercase"
                                                    style="font-size: 0.65rem;">Official Phone</p>
                                                <p class="mb-0 fw-bold text-dark fs-6">
                                                    {{ $user->official_phone ?? 'Not Provided' }}</p>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="emp-field-box h-100 p-3"
                                                style="transition: all 0.3s ease; border-radius: 12px; cursor: default;">
                                                <p class="mb-1 text-muted smallest fw-bold text-uppercase"
                                                    style="font-size: 0.65rem;">Alternate Phone</p>
                                                <p class="mb-0 fw-bold text-dark fs-6">
                                                    {{ $user->alternate_number ?? 'Not Provided' }}</p>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="emp-field-box h-100 p-3"
                                                style="transition: all 0.3s ease; border-radius: 12px; cursor: default;">
                                                <p class="mb-1 text-muted smallest fw-bold text-uppercase"
                                                    style="font-size: 0.65rem;">Home Phone</p>
                                                <p class="mb-0 fw-bold text-dark fs-6">
                                                    {{ $user->home_phone ?? 'Not Provided' }}</p>
                                            </div>
                                        </div>

                                        <!-- DUAL ADDRESS SECTION -->
                                        <div class="col-md-6">
                                            <div class="p-4 rounded-5 address-card h-100"
                                                style="background: linear-gradient(135deg, #F0FAFA 0%, #E6F4F1 100%); border: 1px solid rgba(18, 116, 100, 0.15); box-shadow: 0 10px 30px rgba(18, 116, 100, 0.05); transition: all 0.3s ease; border-radius: 20px;">
                                                <div class="d-flex align-items-center mb-4">
                                                    <div class="bg-white shadow-sm rounded-circle p-2 me-3 d-flex align-items-center justify-content-center"
                                                        style="width:46px;height:46px; border: 2px solid #12746433;">
                                                        <i class="bx bxs-map-pin fs-4" style="color: #127464;"></i>
                                                    </div>
                                                    <h6 class="mb-0 fw-extrabold text-dark fs-6"
                                                        style="letter-spacing: 0.5px;">RESIDENTIAL ADDRESS</h6>
                                                </div>
                                                <div class="p-3 bg-white bg-opacity-60 rounded-3 border border-white">
                                                    <p class="mb-0 text-dark lh-base fw-medium small">
                                                        @if($user->temp_street || $user->temp_building)
                                                            <span
                                                                class="d-block mb-1 text-muted smallest fw-bold text-uppercase">Current
                                                                Location</span>
                                                            {{ $user->temp_building }}, {{ $user->temp_street }}<br>
                                                            {{ $user->temp_city }}, {{ $user->temp_state }}<br>
                                                            {{ $user->temp_zip }}, {{ $user->temp_country }}
                                                        @else
                                                            <span class="fst-italic text-muted">No residential address
                                                                recorded.</span>
                                                        @endif
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="p-4 rounded-5 address-card h-100"
                                                style="background: linear-gradient(135deg, #F8FAFC 0%, #F1F5F9 100%); border: 1px solid rgba(100, 116, 139, 0.15); box-shadow: 0 10px 30px rgba(100, 116, 139, 0.05); transition: all 0.3s ease; border-radius: 20px;">
                                                <div class="d-flex align-items-center mb-4">
                                                    <div class="bg-white shadow-sm rounded-circle p-2 me-3 d-flex align-items-center justify-content-center"
                                                        style="width:46px;height:46px; border: 2px solid #E2E8F0;">
                                                        <i class="bx bxs-home-heart fs-4" style="color: #6366f1;"></i>
                                                    </div>
                                                    <h6 class="mb-0 fw-extrabold text-dark fs-6"
                                                        style="letter-spacing: 0.5px;">PERMANENT ADDRESS</h6>
                                                </div>
                                                <div class="p-3 bg-white bg-opacity-60 rounded-3 border border-white">
                                                    <p class="mb-0 text-dark lh-base fw-medium small">
                                                        @if($user->perm_street || $user->perm_building)
                                                            <span
                                                                class="d-block mb-1 text-muted smallest fw-bold text-uppercase">Legal
                                                                Residence</span>
                                                            {{ $user->perm_building }}, {{ $user->perm_street }}<br>
                                                            {{ $user->perm_city }}, {{ $user->perm_state }}<br>
                                                            {{ $user->perm_zip }}, {{ $user->perm_country }}
                                                        @else
                                                            <span class="fst-italic text-muted">Same as residential
                                                                address.</span>
                                                        @endif
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- EMERGENCY CONTACT SECTION -->
                                        <div class="col-12 mt-2">
                                            <div class="card border-0 p-4"
                                                style="background: linear-gradient(135deg, #FFF5F5 0%, #FFF0F0 100%); border-radius: 16px; border-left: 4px solid #EF4444 !important;">
                                                <div class="d-flex align-items-center mb-4">
                                                    <div class="bg-white p-2 rounded-circle me-3 shadow-sm d-flex align-items-center justify-content-center"
                                                        style="width: 40px; height: 40px;">
                                                        <i class="bx bxs-ambulance fs-4 text-danger"></i>
                                                    </div>
                                                    <h6 class="mb-0 fw-extrabold fs-6"
                                                        style="color: #991B1B; letter-spacing: 0.5px;">EMERGENCY CONTACT
                                                        DETAILS</h6>
                                                </div>
                                                <div class="row g-4">
                                                    <div class="col-md-4">
                                                        <div
                                                            class="p-3 bg-white bg-opacity-50 rounded-3 border border-white">
                                                            <span
                                                                class="d-block smallest text-muted mb-1 fw-bold text-uppercase">Contact
                                                                Person</span>
                                                            <strong
                                                                class="text-dark fs-6">{{ $user->emergency_contact_name ?? 'Not Provided' }}</strong>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div
                                                            class="p-3 bg-white bg-opacity-50 rounded-3 border border-white">
                                                            <span
                                                                class="d-block smallest text-muted mb-1 fw-bold text-uppercase">Relationship</span>
                                                            <strong
                                                                class="text-dark fs-6">{{ $user->emergency_contact_relation ?? 'Not Provided' }}</strong>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div
                                                            class="p-3 bg-white bg-opacity-50 rounded-3 border border-white">
                                                            <span
                                                                class="d-block smallest text-muted mb-1 fw-bold text-uppercase">Contact
                                                                Phone</span>
                                                            <strong
                                                                class="text-danger fs-6 fw-extrabold">{{ $user->emergency_contact_phone ?? 'Not Provided' }}</strong>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Banking & Payroll Tab -->
                        <div class="tab-pane fade" id="banking-payroll">
                            <div class="card mb-4 emp-card">
                                <div class="card-body p-5">
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <div class="d-flex align-items-center">
                                            <div class="hitech-icon-wrap me-3"
                                                style="background: rgba(18, 116, 100, 0.1); color: #127464; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 10px;">
                                                <i class="bx bxs-bank fs-4"></i>
                                            </div>
                                            <h6 class="mb-0 fw-bold fs-5" style="color: #1E293B;">Bank Account Details</h6>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-hitech-primary px-4 rounded-pill shadow-sm"
                                                data-bs-toggle="modal" data-bs-target="#editBankAccountModal">
                                                <i class="bx bx-plus me-1"></i> Edit Banking
                                            </button>
                                        </div>
                                    </div>

                                    @php
                                        $bank = $user->bankAccount ?: $user->bank_account;
                                        $hasBankData = $bank && (is_object($bank) || is_array($bank)) && (isset($bank->bank_name) || isset($bank->account_number));
                                    @endphp

                                    @if ($hasBankData)
                                        <div class="row g-4">
                                            <div class="col-md-6">
                                                <div class="emp-field-box">
                                                    <p class="mb-1 text-muted smallest fw-bold text-uppercase"
                                                        style="font-size: 0.65rem;">Beneficiary Name</p>
                                                    <p class="mb-0 fw-bold text-dark fs-6">{{ $bank->account_name }}</p>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="emp-field-box">
                                                    <p class="mb-1 text-muted smallest fw-bold text-uppercase"
                                                        style="font-size: 0.65rem;">Bank Name</p>
                                                    <p class="mb-0 fw-bold text-dark fs-6">{{ $bank->bank_name }}</p>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="emp-field-box">
                                                    <p class="mb-1 text-muted smallest fw-bold text-uppercase"
                                                        style="font-size: 0.65rem;">Account Number</p>
                                                    <p class="mb-0 fw-extrabold text-dark fs-6">{{ $bank->account_number }}</p>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="emp-field-box">
                                                    <p class="mb-1 text-muted smallest fw-bold text-uppercase"
                                                        style="font-size: 0.65rem;">IFSC / Bank Code</p>
                                                    <p class="mb-0 fw-bold text-dark fs-6">{{ $bank->bank_code }}</p>
                                                </div>
                                            </div>
                                            @if(isset($bank->branch_name))
                                                <div class="col-md-6">
                                                    <div class="emp-field-box">
                                                        <p class="mb-1 text-muted smallest fw-bold text-uppercase"
                                                            style="font-size: 0.65rem;">Branch Name</p>
                                                        <p class="mb-0 fw-bold text-dark fs-6">{{ $bank->branch_name }}</p>
                                                    </div>
                                                </div>
                                            @endif
                                            @if($user->getChequeUrl())
                                                <div class="col-12">
                                                    <div class="p-3 rounded-4 d-flex align-items-center justify-content-between"
                                                        style="background: rgba(18,116,100,0.05); border: 1px dashed rgba(18,116,100,0.2);">
                                                        <div class="d-flex align-items-center">
                                                            <i class="bx bx-file-blank fs-4 text-primary me-2"></i>
                                                            <span class="fw-bold text-dark small">Cancelled Cheque / Passbook
                                                                Attachment</span>
                                                        </div>
                                                        <button type="button"
                                                            class="btn btn-xs btn-hitech-primary rounded-pill px-4"
                                                            onclick="viewDocumentPopup('{{ $user->getChequeUrl() }}', 'Cancelled Cheque', '{{ $bank->account_number }}')">
                                                            <i class="bx bx-show me-1"></i> View Attachment
                                                        </button>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    @else
                                        <div class="text-center py-5 rounded-4"
                                            style="background: #f8fafc; border: 2px dashed #e2e8f0;">
                                            <i class="bx bx-landmark text-muted mb-2" style="font-size: 3rem;"></i>
                                            <p class="text-muted fw-bold mb-0">No bank details added yet.</p>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Compensation & Payroll Section -->
                            <div class="card mb-4 emp-card shadow-sm border-0" style="border-radius: 12px;">
                                <div class="card-body p-5">
                                    <div class="d-flex align-items-center justify-content-between mb-4">
                                        <div class="d-flex align-items-center">
                                            <div class="hitech-icon-wrap me-3"
                                                style="background: rgba(18, 116, 100, 0.1); color: #127464; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 10px;">
                                                <i class="bx bx-money fs-4"></i>
                                            </div>
                                            <h6 class="mb-0 fw-bold fs-5" style="color: #1E293B;">Compensation Details</h6>
                                        </div>
                                        <button class="btn btn-hitech-primary px-4 rounded-pill shadow-sm"
                                            data-bs-toggle="modal" data-bs-target="#offcanvasEditCompInfo">
                                            <i class="bx bx-pencil me-1"></i> Update Details
                                        </button>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <div class="emp-field-box p-3 rounded-hitech h-100"
                                                style="background: #fff; border: 1px solid #eef2f6;">
                                                <p class="mb-1 text-muted smallest fw-bold text-uppercase"
                                                    style="font-size: 0.65rem;">Base Monthly</p>
                                                <p class="mb-0 fw-bold text-dark fs-5">
                                                    ₹{{ number_format($user->base_salary) }}</p>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="emp-field-box p-3 rounded-hitech h-100"
                                                style="background: #fff; border: 1px solid #eef2f6;">
                                                <p class="mb-1 text-muted smallest fw-bold text-uppercase"
                                                    style="font-size: 0.65rem;">CTC (Annum)</p>
                                                <p class="mb-0 fw-bold text-dark fs-5">
                                                    ₹{{ number_format($user->ctc_offered) }}</p>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="emp-field-box p-3 rounded-hitech h-100"
                                                style="background: #fff; border: 1px solid #eef2f6;">
                                                <p class="mb-0 fw-bold text-success fs-5">Active</p>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="emp-field-box p-3 rounded-hitech h-100"
                                                style="background: #fff; border: 1px solid #eef2f6;">
                                                <p class="mb-1 text-muted smallest fw-bold text-uppercase"
                                                    style="font-size: 0.65rem;">Pay Frequency</p>
                                                <p class="mb-0 fw-bold text-dark fs-5">Monthly</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Salary Structure Breakdown -->
                            <div class="card emp-card shadow-sm border-0 mb-4"
                                style="border-radius: 12px; overflow: hidden;">
                                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between cursor-pointer"
                                    data-bs-toggle="collapse" data-bs-target="#salaryBreakdownCollapse"
                                    style="cursor: pointer;">
                                    <div class="d-flex align-items-center">
                                        <div class="hitech-icon-wrap me-3"
                                            style="background: rgba(18, 116, 100, 0.1); color: #127464; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 10px;">
                                            <i class="bx bx-calculator fs-4"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0 fw-bold fs-5" style="color: #1E293B;">Salary Structure Breakdown
                                            </h6>
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
                                                $medicalMonth = 2500;
                                                $eduMonth = 200;
                                                $ltaMonth = 2500;
                                                $sumA = $basicMonth + $hraMonth + $medicalMonth + $eduMonth + $ltaMonth;
                                                $specialAllowance = max(0, $ctcMonth - $sumA);
                                                $profTax = 200;
                                                $pfAmount = 1800;
                                                $deductions = $profTax + $pfAmount;
                                                $netSalary = $ctcMonth - $deductions;
                                            @endphp
                                            <table class="table table-hover mb-0 align-middle">
                                                <thead class="bg-light">
                                                    <tr>
                                                        <th class="ps-4 py-3 text-muted smallest fw-bold text-uppercase">
                                                            Component</th>
                                                        <th
                                                            class="text-end py-3 text-muted smallest fw-bold text-uppercase">
                                                            Per Month</th>
                                                        <th
                                                            class="pe-4 text-end py-3 text-muted smallest fw-bold text-uppercase">
                                                            Per Annum</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td class="ps-4 py-3 fw-semibold text-dark">Basic Salary</td>
                                                        <td class="text-end py-3">₹{{ number_format($basicMonth, 2) }}</td>
                                                        <td class="pe-4 text-end py-3 text-muted">
                                                            ₹{{ number_format($basicMonth * 12, 2) }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td class="ps-4 py-3 fw-semibold text-dark">HRA</td>
                                                        <td class="text-end py-3">₹{{ number_format($hraMonth, 2) }}</td>
                                                        <td class="pe-4 text-end py-3 text-muted">
                                                            ₹{{ number_format($hraMonth * 12, 2) }}</td>
                                                    </tr>
                                                    <tr class="bg-light bg-opacity-50">
                                                        <td class="ps-4 py-2 text-muted small italic">Total Monthly CTC</td>
                                                        <td class="text-end py-2 fw-bold text-dark fs-6">
                                                            ₹{{ number_format($ctcMonth, 2) }}</td>
                                                        <td class="pe-4 text-end py-2 fw-bold text-dark">
                                                            ₹{{ number_format($ctcAnnum, 2) }}</td>
                                                    </tr>
                                                </tbody>
                                                <tfoot style="background: #127464; color: #fff;">
                                                    <tr>
                                                        <td class="ps-4 py-3 fw-bold">NET TAKE HOME</td>
                                                        <td class="text-end py-3 fw-bold fs-5 text-white">
                                                            ₹{{ number_format($netSalary, 2) }}</td>
                                                        <td class="pe-4 text-end py-3 fw-bold text-white">
                                                            ₹{{ number_format($netSalary * 12, 2) }}</td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Allowances & Deductions section -->
                            <div class="card mb-4 emp-card shadow-sm border-0" style="border-radius: 12px;">
                                <div class="card-body p-5">
                                    <div class="d-flex align-items-center justify-content-between mb-4">
                                        <div class="d-flex align-items-center">
                                            <div class="hitech-icon-wrap me-3"
                                                style="background: rgba(18, 116, 100, 0.1); color: #127464; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 10px;">
                                                <i class="bx bx-list-check fs-4"></i>
                                            </div>
                                            <h6 class="mb-0 fw-bold fs-5" style="color: #1E293B;">Allowances & Deductions
                                            </h6>
                                        </div>
                                        <button class="btn btn-hitech-secondary px-4 rounded-pill shadow-sm"
                                            data-bs-toggle="modal" data-bs-target="#offcanvasPayrollAdjustment"
                                            id="addPayrollAdjustment">
                                            <i class="bx bx-plus me-1"></i> Add Item
                                        </button>
                                    </div>
                                    @if ($user->payrollAdjustments->count() > 0)
                                        <div class="row g-3">
                                            @foreach ($user->payrollAdjustments as $adjustment)
                                                <div class="col-md-6">
                                                    <div class="d-flex align-items-center justify-content-between p-3 rounded-4 hitech-adjustment-card"
                                                        style="background-color: #F8FAFC; border: 1px solid #F1F5F9; cursor: pointer; transition: all 0.3s ease;"
                                                        onclick='editAdjustment({!! json_encode($adjustment) !!})'
                                                        data-bs-toggle="modal" data-bs-target="#offcanvasPayrollAdjustment">
                                                        <div class="d-flex align-items-center">
                                                            <div class="bg-white p-2 rounded-circle me-3 shadow-sm d-flex align-items-center justify-content-center"
                                                                style="width: 42px; height: 42px; border: 1px solid {{ $adjustment->type === 'benefit' ? '#D1FAE5' : '#FEE2E2' }};">
                                                                <i
                                                                    class="bx {{ $adjustment->type === 'benefit' ? 'bx-trending-up text-success' : 'bx-trending-down text-danger' }} fs-5"></i>
                                                            </div>
                                                            <div>
                                                                <p class="mb-0 fw-bold small text-dark">{{ $adjustment->name }}</p>
                                                                <span class="text-muted text-uppercase"
                                                                    style="font-size: 0.55rem; letter-spacing: 0.5px;">{{ $adjustment->type }}</span>
                                                            </div>
                                                        </div>
                                                        <div class="text-end">
                                                            <p
                                                                class="mb-0 fw-extrabold {{ $adjustment->type === 'benefit' ? 'text-success' : 'text-danger' }} fs-6">
                                                                {{ $adjustment->type === 'benefit' ? '+' : '-' }}{{ $settings->currency_symbol }}{{ number_format($adjustment->amount ?? (($adjustment->percentage / 100) * ($user->base_salary ?? 0)), 2) }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-center py-4 rounded-3"
                                            style="border: 2px dashed #E2E8F0; background-color: #F8FAFC;">
                                            <p class="text-muted mb-0 small italic">No active adjustments or allowances found.
                                            </p>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Payslip History Section -->
                            <div class="card mb-4 emp-card border-0">
                                <div class="card-body p-5">
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <div class="d-flex align-items-center">
                                            <div class="hitech-icon-wrap me-3"
                                                style="background: rgba(18, 116, 100, 0.1); color: #127464; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 10px;">
                                                <i class="bx bx-spreadsheet fs-4"></i>
                                            </div>
                                            <h6 class="mb-0 fw-bold fs-5" style="color: #1E293B;">Month-wise Payroll History
                                            </h6>
                                        </div>
                                        <span class="badge rounded-pill"
                                            style="background: rgba(18, 116, 100, 0.1); color: #127464; font-size: 0.75rem; padding: 0.5rem 1rem;">
                                            {{ $user->payslips->count() }} Records
                                        </span>
                                    </div>

                                    @if($user->payslips->count() > 0)
                                        <div class="table-responsive rounded-4 border border-light overflow-hidden shadow-xs"
                                            style="background: #fff;">
                                            <table class="table table-hover mb-0 align-middle">
                                                <thead style="background-color: #F8FAFC; border-bottom: 2px solid #F1F5F9;">
                                                    <tr>
                                                        <th class="ps-4 py-3 text-muted smallest fw-bold text-uppercase"
                                                            style="letter-spacing: 0.5px;">Period</th>
                                                        <th class="py-3 text-muted smallest fw-bold text-uppercase"
                                                            style="letter-spacing: 0.5px;">Basic</th>
                                                        <th class="py-3 text-muted smallest fw-bold text-uppercase"
                                                            style="letter-spacing: 0.5px;">Net Salary</th>
                                                        <th class="py-3 text-muted smallest fw-bold text-uppercase text-center"
                                                            style="letter-spacing: 0.5px;">Status</th>
                                                        <th class="pe-4 py-3 text-muted smallest fw-bold text-uppercase text-end"
                                                            style="letter-spacing: 0.5px;">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($user->payslips->sortByDesc('created_at')->take(12) as $payslip)
                                                        <tr style="border-bottom: 1px solid #F8FAFC; transition: all 0.2s ease;">
                                                            <td class="ps-4 py-3 fw-bold text-dark fs-6">
                                                                {{ $payslip->created_at->format('M Y') }}</td>
                                                            <td class="py-3 text-muted small">
                                                                ₹{{ number_format($payslip->basic_salary, 2) }}</td>
                                                            <td class="py-3 fw-extrabold text-primary fs-6">
                                                                ₹{{ number_format($payslip->net_salary, 2) }}</td>
                                                            <td class="py-3 text-center">
                                                                <span class="badge rounded-pill fw-bold"
                                                                    style="background: {{ $payslip->status == 'paid' ? '#ECFDF3; color: #15803D;' : '#FFF7ED; color: #C2410C;' }}; font-size: 0.6rem; padding: 0.4rem 0.8rem;">
                                                                    {{ strtoupper($payslip->status) }}
                                                                </span>
                                                            </td>
                                                            <td class="pe-4 py-3 text-end">
                                                                <button class="btn btn-xs btn-hitech-primary rounded-pill px-3"
                                                                    onclick="viewDocumentPopup('{{ route('user.payroll.show_ajax', $payslip->id) }}', 'Payslip {{ $payslip->created_at->format('M Y') }}')">Download</button>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @else
                                        <div class="text-center py-5 rounded-4"
                                            style="background: #f8fafc; border: 2px dashed #e2e8f0;">
                                            <i class="bx bxs-file-blank text-muted mb-2"
                                                style="font-size: 2.8rem; opacity: 0.4;"></i>
                                            <p class="text-muted small mb-0 fw-medium">No payslip records found for this
                                                employee.</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Documents Tab -->
                        <div class="tab-pane fade" id="documents">
                            <div class="card mb-4 emp-card">
                                <div class="card-body p-5">
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <div class="d-flex align-items-center">
                                            <i class="bx bx-file me-2 fs-5" style="color: #127464;"></i>
                                            <h6 class="mb-0 fw-bold" style="color: #1E293B;">Employee Documents</h6>
                                        </div>
                                        <button class="btn btn-hitech-primary rounded-pill px-4 shadow-sm"
                                            data-bs-toggle="modal" data-bs-target="#modalAddUserDocument"
                                            onclick="setDocModal('Other Document', '')">
                                            <i class="bx bx-plus me-1"></i> Add New Document
                                        </button>
                                    </div>

                                    @php
                                        $mandatoryDocs = [
                                            ['name' => 'Aadhaar Card', 'key' => 'aadhaar_no', 'icon' => 'bx-id-card'],
                                            ['name' => 'PAN Card', 'key' => 'pan_no', 'icon' => 'bx-credit-card-front'],
                                            ['name' => '10th Marksheet (Matric)', 'key' => 'matric_marksheet_no', 'icon' => 'bx-certification'],
                                            ['name' => '12th Marksheet (Intermediate)', 'key' => 'inter_marksheet_no', 'icon' => 'bx-graduation'],
                                            ['name' => 'Graduation Marksheet', 'key' => 'bachelor_marksheet_no', 'icon' => 'bx-certification'],
                                            ['name' => 'Post Graduation Marksheet', 'key' => 'master_marksheet_no', 'icon' => 'bx-certification'],
                                            ['name' => 'Experience Certificate', 'key' => 'experience_certificate_no', 'icon' => 'bx-briefcase'],
                                        ];
                                    @endphp

                                    <h6 class="fw-bold mb-3 small text-muted text-uppercase"
                                        style="letter-spacing: 1px; font-size: 0.7rem;">Essential Verification Documents
                                    </h6>
                                    <div class="row g-3">
                                        @foreach($mandatoryDocs as $doc)
                                            @php
                                                $isSubmitted = false;
                                                $docFileUrl = null;
                                                $docNumber = 'N/A';

                                                // 1. Check User Model Columns
                                                if ($doc['key'] && $user->{$doc['key']}) {
                                                    $isSubmitted = true;
                                                    $docNumber = (string) $user->{$doc['key']};
                                                }

                                                // 2. Check Standard User Model URL Methods
                                                if ($doc['name'] == 'Aadhaar Card' && $user->getAadhaarUrl()) {
                                                    $isSubmitted = true;
                                                    $docFileUrl = $user->getAadhaarUrl();
                                                } elseif ($doc['name'] == 'PAN Card' && $user->getPanUrl()) {
                                                    $isSubmitted = true;
                                                    $docFileUrl = $user->getPanUrl();
                                                } elseif (str_contains($doc['name'], '10th') && $user->getMatricUrl()) {
                                                    $isSubmitted = true;
                                                    $docFileUrl = $user->getMatricUrl();
                                                } elseif (str_contains($doc['name'], '12th') && $user->getInterUrl()) {
                                                    $isSubmitted = true;
                                                    $docFileUrl = $user->getInterUrl();
                                                } elseif (str_contains($doc['name'], 'Graduation') && $user->getBachelorUrl()) {
                                                    $isSubmitted = true;
                                                    $docFileUrl = $user->getBachelorUrl();
                                                } elseif (str_contains($doc['name'], 'Post') && $user->getMasterUrl()) {
                                                    $isSubmitted = true;
                                                    $docFileUrl = $user->getMasterUrl();
                                                } elseif (str_contains($doc['name'], 'Experience') && $user->getExperienceUrl()) {
                                                    $isSubmitted = true;
                                                    $docFileUrl = $user->getExperienceUrl();
                                                }

                                                // 3. Fallback to formal Document Requests (Admin approved)
                                                if (!$isSubmitted || !$docFileUrl) {
                                                    $request = $user->documentRequests->where('status', 'approved')->filter(function ($r) use ($doc) {
                                                        return $r->documentType && (
                                                            strtolower($r->documentType->name) == strtolower($doc['name']) ||
                                                            ($doc['key'] && strtolower($r->documentType->code) == strtoupper($doc['key']))
                                                        );
                                                    })->first();

                                                    if ($request) {
                                                        $isSubmitted = true;
                                                        if ($request->generated_file)
                                                            $docFileUrl = $request->getSecureUrl();
                                                        if ($request->remarks)
                                                            $docNumber = $request->remarks;
                                                    }
                                                }
                                            @endphp
                                            <div class="col-md-6">
                                                <div class="p-3 rounded-3 d-flex align-items-center justify-content-between"
                                                    style="background-color: #F8FAFC; border: 1px solid #E2E8F0;">
                                                    <div class="d-flex align-items-center overflow-hidden">
                                                        <div class="rounded-3 p-2 me-3 d-flex align-items-center justify-content-center flex-shrink-0"
                                                            style="background-color: {{ $isSubmitted ? '#E6F4F1' : '#F1F5F9' }}; width: 44px; height: 44px; border: 1px solid {{ $isSubmitted ? '#A7D9CF' : '#E2E8F0' }};">
                                                            <i class="bx {{ $doc['icon'] }} {{ $isSubmitted ? '' : 'text-muted' }} fs-4"
                                                                style="{{ $isSubmitted ? 'color:#127464' : '' }}"></i>
                                                        </div>
                                                        <div class="overflow-hidden">
                                                            <p class="mb-0 fw-bold text-dark small" style="line-height: 1.2;">
                                                                {{ $doc['name'] }}</p>
                                                            <div class="d-flex align-items-center gap-2 mt-1">
                                                                <span class="badge"
                                                                    style="font-size: 0.5rem; padding: 0.2rem 0.4rem; border-radius: 4px; background-color: {{ $isSubmitted ? '#127464' : '#94A3B8' }}; color:#fff;">{{ $isSubmitted ? 'SUBMITTED' : 'NOT SUBMITTED' }}</span>
                                                                @if($isSubmitted && $docNumber && $docNumber !== 'N/A')
                                                                    <span class="smallest text-muted fw-bold"
                                                                        style="font-size: 0.6rem;"># {{ $docNumber }}</span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex gap-2">
                                                        @if($isSubmitted)
                                                            @if($docFileUrl)
                                                                <a href="javascript:void(0)"
                                                                    class="btn btn-hitech-secondary btn-xs rounded-pill px-3"
                                                                    style="font-size: 0.65rem;"
                                                                    onclick="viewDocumentPopup('{{ $docFileUrl }}', '{{ $doc['name'] }}', '{{ $docNumber }}')"><i
                                                                        class="bx bx-show me-1"></i>View File</a>
                                                            @elseif($docNumber && $docNumber !== 'N/A')
                                                                <a href="javascript:void(0)"
                                                                    class="btn btn-hitech-secondary btn-xs rounded-pill px-3"
                                                                    style="font-size: 0.65rem;"
                                                                    onclick="viewDocumentNumber('{{ $doc['name'] }}', '{{ $docNumber }}')"><i
                                                                        class="bx bx-show me-1"></i>View No.</a>
                                                            @endif
                                                            <button class="btn btn-xs btn-outline-hitech rounded-pill px-3"
                                                                style="font-size: 0.65rem;" data-bs-toggle="modal"
                                                                data-bs-target="#modalAddUserDocument"
                                                                onclick="setDocModal('{{ $doc['name'] }}', '{{ $docNumber }}')">Update</button>
                                                        @else
                                                            <button class="btn btn-xs btn-hitech rounded-pill px-3"
                                                                style="font-size: 0.65rem; background-color: #127464; color: #fff;"
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
                                        <!-- Other Identity Proofs -->
                                        <h6 class="fw-bold mb-3 small text-muted text-uppercase"
                                            style="letter-spacing: 1px; font-size: 0.7rem;">Other Identity Proofs</h6>
                                        <div class="row g-3">
                                            @if($user->passport_no)
                                                <div class="col-md-4">
                                                    <div class="emp-field-box">
                                                        <p class="mb-1 text-muted small fw-bold text-uppercase"
                                                            style="font-size: 0.6rem;">Passport No.</p>
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <span
                                                                class="fw-bold text-dark small text-truncate">{{ $user->passport_no }}</span>
                                                            <i class="bx bxs-check-shield text-success"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                            @if($user->visa_type)
                                                <div class="col-md-4">
                                                    <div class="emp-field-box">
                                                        <p class="mb-1 text-muted small fw-bold text-uppercase"
                                                            style="font-size: 0.6rem;">Visa Status</p>
                                                        <span class="small fw-semibold text-dark">{{ $user->visa_type }}</span>
                                                    </div>
                                                </div>
                                            @endif
                                            @if($user->documentRequests->where('status', 'approved')->count() > 0)
                                                <div class="col-md-4">
                                                    <div class="emp-field-box">
                                                        <p class="mb-1 text-muted small fw-bold text-uppercase"
                                                            style="font-size: 0.6rem;">Additional Docs</p>
                                                        <span class="badge"
                                                            style="background:#127464;color:#fff;">{{ $user->documentRequests->where('status', 'approved')->count() }}
                                                            Added</span>
                                                    </div>
                                                </div>
                                            @endif
                                            @if($user->visa_type || $user->frro_registration)
                                                <div class="col-12 mt-2 pt-3 border-top">
                                                    <div class="row g-4">
                                                        @if($user->visa_type)
                                                            <div class="col-md-6">
                                                                <h6 class="fw-bold mb-2 small text-muted text-uppercase"
                                                                    style="font-size: 0.65rem;">Visa Information</h6>
                                                                <div class="p-3 rounded-3"
                                                                    style="background-color: #F8FAFC; border: 1px solid #F1F5F9;">
                                                                    <div class="d-flex justify-content-between mb-2">
                                                                        <span class="text-muted small">Type:
                                                                            <strong>{{ $user->visa_type }}</strong></span>
                                                                        <span class="text-muted small">Expires:
                                                                            <strong>{{ $user->visa_expiry_date ?? 'N/A' }}</strong></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endif
                                                        @if($user->frro_registration)
                                                            <div class="col-md-6">
                                                                <h6 class="fw-bold mb-2 small text-muted text-uppercase"
                                                                    style="font-size: 0.65rem;">FRRO Registration</h6>
                                                                <div class="p-3 rounded-3"
                                                                    style="background-color: #F8FAFC; border: 1px solid #F1F5F9;">
                                                                    <div class="d-flex justify-content-between">
                                                                        <span class="text-muted small">Number:
                                                                            <strong>{{ $user->frro_registration }}</strong></span>
                                                                        <span class="text-muted small">Expires:
                                                                            <strong>{{ $user->frro_expiry_date ?? 'N/A' }}</strong></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    @endif

                                    @php
                                        $handledDocTypes = collect($mandatoryDocs)->pluck('name')->map(fn($n) => strtolower($n))->toArray();
                                        $otherDocs = $user->documentRequests->where('status', 'approved')->filter(function ($r) use ($handledDocTypes) {
                                            return $r->documentType && !in_array(strtolower($r->documentType->name), $handledDocTypes);
                                        });
                                    @endphp

                                    @if($otherDocs->count() > 0)
                                        <div class="col-12 mt-4 pt-4 border-top">
                                            <h6 class="fw-bold mb-3 small text-muted text-uppercase"
                                                style="letter-spacing: 1px; font-size: 0.7rem;">Additional Corporate Documents
                                            </h6>
                                            <div class="row g-3">
                                                @foreach($otherDocs as $docReq)
                                                    <div class="col-md-4">
                                                        <div class="p-3 rounded-3 d-flex align-items-center justify-content-between"
                                                            style="background-color: #F8FAFC; border: 1px solid #E2E8F0;">
                                                            <div class="d-flex align-items-center overflow-hidden">
                                                                <div class="rounded-3 p-2 me-3 d-flex align-items-center justify-content-center flex-shrink-0"
                                                                    style="background-color: #E6F4F1; width: 44px; height: 44px; border: 1px solid #A7D9CF;">
                                                                    <i class="bx bx-file text-success fs-4"></i>
                                                                </div>
                                                                <div class="overflow-hidden">
                                                                    <p class="mb-0 fw-bold text-dark small text-truncate"
                                                                        style="line-height: 1.2;">{{ $docReq->documentType->name }}
                                                                    </p>
                                                                    <span class="badge mt-1"
                                                                        style="font-size: 0.5rem; background:#127464; color:#fff;">VERIFIED</span>
                                                                </div>
                                                            </div>
                                                            <button
                                                                class="btn btn-hitech-secondary btn-xs rounded-pill px-3 shadow-sm"
                                                                style="font-size: 0.65rem;"
                                                                onclick="viewDocumentPopup('{{ $docReq->getSecureUrl() }}', '{{ $docReq->documentType->name }}', '{{ $docReq->remarks ?? '' }}')">
                                                                <i class="bx bx-show-alt me-1"></i>View
                                                            </button>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>


                        <!-- KRAs (Key Result Areas) Tab -->
                        <div class="tab-pane fade" id="tasks-tab">
                            <div class="card border-0 shadow-sm rounded-3 mb-4">
                                <div class="card-body p-5">
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <div class="d-flex align-items-center">
                                            <div class="hitech-icon-wrap me-3"
                                                style="background: rgba(18, 116, 100, 0.1); color: #127464; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 10px;">
                                                <i class="bx bx-bullseye fs-4"></i>
                                            </div>
                                            <h6 class="mb-0 fw-bold fs-5" style="color: #1E293B;">Key Result Areas (KRAs)
                                            </h6>
                                        </div>
                                        <div class="d-flex gap-2">
                                            @if(auth()->user()->hasRole(['admin', 'Admin', 'hr', 'manager', 'super_admin']) || auth()->user()->can('user-edit'))
                                                <button class="btn btn-hitech px-4 rounded-pill shadow-sm"
                                                    data-bs-toggle="modal" data-bs-target="#modalAddKpi" onclick="openAddKpi()">
                                                    <i class="bx bx-plus-circle me-1"></i> Add KRA Objective
                                                </button>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="table-responsive rounded-3 border" style="background:#fff;">
                                        <table class="table table-hover table-borderless mb-0 align-middle">
                                            <thead style="background:#F8FAFC;border-bottom:1px solid #E2E8F0;">
                                                <tr>
                                                    <th class="ps-4 text-muted fw-bold py-3"
                                                        style="font-size:0.75rem;text-transform:uppercase;">#</th>
                                                    <th class="text-muted fw-bold py-3"
                                                        style="font-size:0.75rem;text-transform:uppercase;">KRA Focus &
                                                        Objectives</th>
                                                    <th class="text-muted fw-bold py-3"
                                                        style="font-size:0.75rem;text-transform:uppercase;">Due Date</th>
                                                    <th class="text-muted fw-bold py-3"
                                                        style="font-size:0.75rem;text-transform:uppercase;">Status</th>
                                                    <th class="pe-4 text-end text-muted fw-bold py-3"
                                                        style="font-size:0.75rem;text-transform:uppercase;">Actions</th>
                                            </thead>
                                            <tbody>
                                                @forelse($kraPerformance as $index => $task)
                                                    <tr style="border-bottom:1px solid #F1F5F9;">
                                                        <td class="ps-4 fw-medium text-muted">{{ $index + 1 }}</td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="avatar avatar-sm me-3"
                                                                    style="width:38px;height:38px;">
                                                                    <span
                                                                        class="avatar-initial rounded-circle bg-label-primary fw-bold"
                                                                        style="font-size:0.85rem;">{{ substr(getMetric($task->description), 0, 2) }}</span>
                                                                </div>
                                                                <div>
                                                                    <h6 class="mb-0 fw-bold text-dark"
                                                                        style="font-size:0.9rem;">
                                                                        {{ getMetric($task->description) }}</h6>
                                                                    <small class="text-muted d-block text-truncate"
                                                                        style="max-width:250px;">{{ \Illuminate\Support\Str::limit($task->description, 50) }}</small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="text-dark small">
                                                            {{ $task->expiry_date ? $task->expiry_date->format('d M, Y') : 'N/A' }}
                                                        </td>
                                                        <td>
                                                            <span class="badge rounded-pill fw-bold px-3 py-2"
                                                                style="background:#E0F2FE;color:#0284C7;font-size:0.70rem;">
                                                                {{ strtoupper($task->status->value) }}
                                                            </span>
                                                        </td>
                                                        <td class="pe-4 text-end">
                                                            <div class="d-flex align-items-center justify-content-end gap-2">
                                                                <button
                                                                    class="btn btn-sm btn-icon rounded-circle btn-hitech-secondary"
                                                                    title="View"
                                                                    onclick="editKpi({{ $task->id }}, '{{ getMetric($task->description) }}', 'KRA', 'Standard', {{ $task->target_amount }}, '{{ $task->incentive_type->value }}', '{{ addslashes($task->description) }}')">
                                                                    <i class="bx bx-show-alt"></i>
                                                                </button>
                                                                @if(auth()->user()->can('user-edit') || auth()->user()->hasRole('hr'))
                                                                    <button
                                                                        class="btn btn-sm btn-icon rounded-circle btn-hitech-alert"
                                                                        title="Delete" onclick="deleteKpi({{ $task->id }})">
                                                                        <i class="bx bx-trash"></i>
                                                                    </button>
                                                                @endif
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="5" class="text-center py-5">
                                                            <i class="bx bx-target-lock text-muted"
                                                                style="font-size:3rem;opacity:0.3;"></i>
                                                            <h6 class="mt-3 text-muted fw-medium">No Key Result Areas (KRAs)
                                                                defined for this employee.</h6>
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>


                        <!-- KPI/Performance Tab -->
                        <div class="tab-pane fade" id="kpi">
                            <div class="card border-0 shadow-sm rounded-3 mb-4">
                                <div class="card-body p-5">
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <div class="d-flex align-items-center">
                                            <div class="hitech-icon-wrap me-3"
                                                style="background: rgba(18, 116, 100, 0.1); color: #127464; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 10px;">
                                                <i class="bx bx-bar-chart-alt-2 fs-4"></i>
                                            </div>
                                            <h6 class="mb-0 fw-bold fs-5" style="color: #1E293B;">Strategic Performance
                                                (KPIs)</h6>
                                        </div>
                                        @if(auth()->user()->hasRole(['admin', 'hr', 'manager', 'super_admin']))
                                            <button class="btn btn-hitech px-4 rounded-pill shadow-sm" onclick="openAddKpi()">
                                                <i class="bx bx-plus me-1"></i> Add KPI Target
                                            </button>
                                        @endif
                                    </div>

                                    <div class="row g-4 mb-4">
                                        <div class="col-md-3">
                                            <div class="emp-field-box text-center h-100 p-4">
                                                <p class="text-muted small fw-bold text-uppercase mb-2">Total KPIs</p>
                                                <h3 class="fw-extrabold mb-0" style="color: #127464;">
                                                    {{ count($user->salesTargets) }}</h3>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="emp-field-box text-center h-100 p-4">
                                                <p class="text-muted small fw-bold text-uppercase mb-2">Completed</p>
                                                <h3 class="fw-extrabold mb-0 text-success">
                                                    {{ $user->salesTargets->where('status', 'completed')->count() }}</h3>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="emp-field-box text-center h-100 p-4">
                                                <p class="text-muted small fw-bold text-uppercase mb-2">In Progress</p>
                                                <h3 class="fw-extrabold mb-0 text-warning">
                                                    {{ $user->salesTargets->where('status', 'in_progress')->count() }}</h3>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="emp-field-box text-center h-100 p-4">
                                                <p class="text-muted small fw-bold text-uppercase mb-2">Completion %</p>
                                                @php
                                                    $totalT = count($user->salesTargets);
                                                    $compT = $user->salesTargets->where('status', 'completed')->count();
                                                    $percT = $totalT > 0 ? round(($compT / $totalT) * 100) : 0;
                                                @endphp
                                                <h3 class="fw-extrabold mb-0 text-primary">{{ $percT }}%</h3>
                                            </div>
                                        </div>
                                    </div>
                                    @if(auth()->user()->can('user-edit') || auth()->user()->hasRole('hr'))
                                        <button class="btn btn-hitech-secondary px-4 rounded-pill shadow-sm"
                                            data-bs-toggle="modal" data-bs-target="#modalAddTask" onclick="resetKraModal()">
                                            <i class="bx bx-plus me-1"></i> Create Strategic KRA
                                        </button>
                                    @endif
                                </div>
                                <div class="card-body p-4">
                                    <div class="table-responsive rounded-3 border" style="background:#fff;">
                                        <table class="table table-hover table-borderless mb-0 align-middle">
                                            <thead style="background:#F8FAFC;border-bottom:1px solid #E2E8F0;">
                                                <tr>
                                                    <th class="ps-4 text-muted fw-bold py-3"
                                                        style="font-size:0.75rem;text-transform:uppercase;">#</th>
                                                    <th class="text-muted fw-bold py-3"
                                                        style="font-size:0.75rem;text-transform:uppercase;">KPI Target</th>
                                                    <th class="text-muted fw-bold py-3"
                                                        style="font-size:0.75rem;text-transform:uppercase;">Type</th>
                                                    <th class="text-muted fw-bold py-3"
                                                        style="font-size:0.75rem;text-transform:uppercase;">Goal</th>
                                                    <th class="text-muted fw-bold py-3"
                                                        style="font-size:0.75rem;text-transform:uppercase;">Status</th>
                                                    <th class="pe-4 text-end text-muted fw-bold py-3"
                                                        style="font-size:0.75rem;text-transform:uppercase;">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($kpiPerformance as $index => $target)
                                                    <tr style="border-bottom:1px solid #F1F5F9;">
                                                        <td class="ps-4 fw-medium text-muted">{{ $index + 1 }}</td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="avatar avatar-sm me-3"
                                                                    style="width:38px;height:38px;">
                                                                    <span
                                                                        class="avatar-initial rounded-circle bg-label-info fw-bold"
                                                                        style="font-size:0.85rem;">{{ substr(getMetric($target->description), 0, 2) }}</span>
                                                                </div>
                                                                <div>
                                                                    <h6 class="mb-0 fw-bold text-dark"
                                                                        style="font-size:0.9rem;">
                                                                        {{ getMetric($target->description) }}</h6>
                                                                    <small class="text-muted d-block text-truncate"
                                                                        style="max-width:200px;">{{ \Illuminate\Support\Str::limit($target->description, 30) }}</small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="text-dark small">
                                                            {{ ucfirst($target->incentive_type->value) }}</td>
                                                        <td class="text-dark small">
                                                            {{ $settings->currency_symbol }}{{ number_format($target->target_amount, 2) }}
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-label-success rounded-pill fw-bold px-3 py-2"
                                                                style="font-size:0.70rem;">{{ strtoupper($target->status->value) }}</span>
                                                        </td>
                                                        <td class="pe-4 text-end">
                                                            <div class="d-flex align-items-center justify-content-end gap-2">
                                                                <button
                                                                    class="btn btn-sm btn-icon rounded-circle btn-hitech-secondary"
                                                                    title="View/Edit"
                                                                    onclick="editKpi({{ $target->id }}, '{{ getMetric($target->description) }}', 'KPI', 'Standard', {{ $target->target_amount }}, '{{ $target->incentive_type->value }}', '{{ addslashes($target->description) }}')">
                                                                    <i class="bx bx-edit"></i>
                                                                </button>
                                                                <button
                                                                    class="btn btn-sm btn-icon rounded-circle btn-hitech-alert"
                                                                    title="Delete" onclick="deleteKpi({{ $target->id }})">
                                                                    <i class="bx bx-trash"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="6" class="text-center py-5">
                                                            <i class="bx bx-bar-chart-alt text-muted"
                                                                style="font-size:3rem;opacity:0.3;"></i>
                                                            <h6 class="mt-3 text-muted fw-medium">No Strategic Performance
                                                                targets defined.</h6>
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Activity Tab -->
                        <div class="tab-pane fade" id="activity">
                            <div class="card border-0 shadow-sm rounded-3">
                                <div class="card-body p-5">
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <div class="d-flex align-items-center">
                                            <div class="hitech-icon-wrap me-3"
                                                style="background: rgba(18, 116, 100, 0.1); color: #127464; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 10px;">
                                                <i class="bx bx-history fs-4"></i>
                                            </div>
                                            <h6 class="mb-0 fw-bold fs-5" style="color: #1E293B;">Recent Activity Log</h6>
                                        </div>
                                    </div>

                                    <div class="timeline ps-4 border-start border-2 border-light py-2">
                                        @forelse($auditLogs as $log)
                                            <div class="timeline-item position-relative mb-5 pb-3">
                                                <div class="timeline-dot position-absolute rounded-circle shadow-sm"
                                                    style="width: 14px; height: 14px; background: #127464; border: 3px solid #fff; left: -31px; top: 0;">
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <h6 class="mb-0 fw-bold text-dark fs-6">{{ ucfirst($log->event) }}
                                                        {{ class_basename($log->auditable_type) }}</h6>
                                                    <small
                                                        class="text-muted fw-medium small">{{ $log->created_at->diffForHumans() }}</small>
                                                </div>
                                                <p class="mb-0 text-muted smallest">Performed by:
                                                    {{ $log->user ? $log->user->getFullName() : 'System' }}</p>
                                                @if(!empty($log->old_values) || !empty($log->new_values))
                                                    <div class="mt-2 text-warning smallest bg-light p-2 rounded-3"
                                                        style="font-family: monospace;">
                                                        <i class="bx bx-info-circle me-1"></i> Data update logged securely.
                                                    </div>
                                                @endif
                                            </div>
                                        @empty
                                            <div class="text-center py-5">
                                                <i class="bx bx-ghost text-muted mb-3"
                                                    style="font-size: 3rem; opacity: 0.3;"></i>
                                                <p class="text-muted mb-0 fw-medium">No activity records found for this entry.
                                                </p>
                                            </div>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                    <!-- /Tab Content -->

                    <!-- Mobile Tab Navigation (Previous/Next) -->
                    <div class="mobile-tab-navigation d-md-none mt-4 pb-4">
                        <div class="card border-0 shadow-sm"
                            style="background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); border-radius: 20px;">
                            <div class="card-body p-3 d-flex justify-content-between gap-3">
                                <button class="btn btn-outline-secondary rounded-pill flex-fill py-2 fw-bold"
                                    onclick="navigateTabs('prev')">
                                    <i class="bx bx-chevron-left me-1"></i> Previous
                                </button>
                                <button class="btn btn-hitech-primary rounded-pill flex-fill py-2 fw-bold"
                                    onclick="navigateTabs('next')">
                                    Next Stage <i class="bx bx-chevron-right ms-1"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div> {{-- Close for animate__fadeIn --}}
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
                                    <label class="form-label-hitech">Document Name <span
                                            class="text-danger">*</span></label>
                                    <input type="text" id="docModalName" name="documentName"
                                        class="form-control form-control-hitech" placeholder="e.g. Aadhar Card" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-hitech">Document Number <span
                                            class="text-danger">*</span></label>
                                    <input type="text" id="docModalNumber" name="documentNumber"
                                        class="form-control form-control-hitech" placeholder="Enter ID number" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label-hitech">Attachment Upload <span
                                            class="text-danger">*</span></label>
                                    <div class="p-4 border-2 rounded-3 text-center"
                                        style="border: 2px dashed #E2E8F0; background-color: #F8FAFC;">
                                        <i class="bx bx-cloud-upload text-muted mb-2" style="font-size: 2.5rem;"></i>
                                        <p class="small text-muted mb-3">Click to select or drag and drop file here</p>
                                        <input type="file" name="file" class="form-control form-control-hitech" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-0 px-4 pb-4 d-flex justify-content-end gap-3">
                            <button type="button" class="btn btn-hitech-modal-cancel"
                                data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-hitech-modal-submit">Upload & Save <i
                                    class="bx bx-cloud-upload ms-1"></i></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- NEW: Add Task Modal --}}
        <div class="modal fade" id="modalAddTask" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content modal-content-hitech">
                    <div class="modal-header modal-header-hitech">
                        <div class="d-flex align-items-center">
                            <div class="modal-icon-header me-3">
                                <i class="bx bx-bullseye"></i>
                            </div>
                            <h5 class="modal-title modal-title-hitech mb-0">Define Key Result Area (KRA)</h5>
                        </div>
                        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal">
                            <i class="bx bx-x"></i>
                        </button>
                    </div>
                    <form id="addTaskForm">
                        @csrf
                        <input type="hidden" name="userId" value="{{ $user->id }}">
                        <input type="hidden" name="taskId" id="modalTaskId" value="">
                        <div class="modal-body modal-body-hitech">
                            <div class="row g-4">
                                <div class="col-12">
                                    <label class="form-label-hitech" id="taskTitleLabel">KRA Title / Objective <span
                                            class="text-danger">*</span></label>
                                    <input type="text" name="title" id="modalTaskTitle"
                                        class="form-control form-control-hitech" placeholder="e.g. Operational Excellence"
                                        required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label-hitech">Core Value & Scope</label>
                                    <textarea name="description" id="modalTaskDescription"
                                        class="form-control form-control-hitech" rows="3"
                                        placeholder="Enter key responsibilities..."></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-hitech">Due Date</label>
                                    <input type="date" name="due_date" id="modalTaskDueDate"
                                        class="form-control form-control-hitech" min="{{ date('Y-m-d') }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-hitech">KRA Category</label>
                                    <select name="type" id="modalTaskType" class="form-select form-select-hitech">
                                        <option value="open">Strategic Objective</option>
                                        <option value="site_based">Operational Focus</option>
                                        <option value="client_based">Client Success</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-0 px-4 pb-4 d-flex justify-content-end gap-3">
                            <button type="button" class="btn btn-hitech-modal-cancel"
                                data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-hitech-modal-submit">Publish KRA <i
                                    class="bx bx-check-circle ms-1"></i></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>


        {{-- NEW: Terminate Employee Modal --}}
        <div class="modal fade" id="terminateEmployeeModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content modal-content-hitech">
                    <div class="modal-header modal-header-hitech">
                        <div class="d-flex align-items-center">
                            <div class="modal-icon-header me-3">
                                <i class="bx bx-user-x"></i>
                            </div>
                            <h5 class="modal-title modal-title-hitech mb-0">Terminate Employee</h5>
                        </div>
                        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal">
                            <i class="bx bx-x"></i>
                        </button>
                    </div>
                    <form id="terminateEmployeeForm" action="{{ route('employees.terminate', $user->id) }}" method="POST"
                        onsubmit="return false;">
                        @csrf
                        <div class="modal-body modal-body-hitech">
                            <div class="alert alert-warning border-0 d-flex align-items-center mb-4"
                                style="background-color: #FFFBEB; border-radius: 12px;">
                                <i class="bx bx-error-alt fs-4 me-2 text-warning"></i>
                                <div class="small text-dark fw-bold">Warning: Initiating termination for
                                    {{ $user->getFullName() }}. This action cannot be undone easily.</div>
                            </div>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label-hitech">Termination Type <span
                                            class="text-danger">*</span></label>
                                    <select id="terminationType" name="terminationType"
                                        class="select2 form-select form-select-hitech" required>
                                        <option value="">Select Type</option>
                                        @foreach (\App\Enums\TerminationType::cases() as $type)
                                            <option value="{{ $type->value }}">
                                                {{ \Illuminate\Support\Str::title(str_replace('_', ' ', $type->value)) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-hitech">Exit Date <span class="text-danger">*</span></label>
                                    <input type="text" id="exitDate" name="exitDate"
                                        class="form-control form-control-hitech flatpickr-input" placeholder="Select Date"
                                        required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-hitech">Last Working Day <span
                                            class="text-danger">*</span></label>
                                    <input type="text" id="lastWorkingDay" name="lastWorkingDay"
                                        class="form-control form-control-hitech flatpickr-input" placeholder="Select Date"
                                        required>
                                </div>
                                <div class="col-md-6 d-flex align-items-center mt-auto pb-2">
                                    <div class="form-check form-switch custom-switch-hitech">
                                        <input class="form-check-input" type="checkbox" id="isEligibleForRehire"
                                            name="isEligibleForRehire" value="1" checked>
                                        <label class="form-check-label ms-2 fw-bold text-muted small text-uppercase"
                                            for="isEligibleForRehire">Eligible for Re-hire</label>
                                        <input type="hidden" name="isEligibleForRehire" value="0">
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label-hitech">Reason for Termination <span
                                            class="text-danger">*</span></label>
                                    <textarea id="exitReason" name="exitReason" class="form-control form-control-hitech"
                                        rows="3" placeholder="Provide detailed reason..." required></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-0 px-4 pb-4 d-flex justify-content-end gap-3">
                            <button type="button" class="btn btn-hitech-modal-cancel"
                                data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-hitech-modal-submit bg-danger border-0"
                                id="terminateSubmitBtn">Confirm Termination <i class="bx bx-check-circle ms-1"></i></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        {{-- Modals Inclusion --}}
        @include('_partials._modals.employees.edit_basic_info')
        @include('_partials._modals.employees.edit_contact_info')
        @include('_partials._modals.employees.edit_work_info')
        @include('_partials._modals.employees.edit_compensation_info')

        @include('_partials._modals.employees.add_orUpdate_bankAccount')
        @include('tenant.payroll.partials.add_orUpdate_payroll_adjustment')

        {{-- Allot Device Modal --}}
        <div class="modal fade" id="modalAllotDevice" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content modal-content-hitech">
                    <div class="modal-header modal-header-hitech">
                        <div class="modal-icon-header me-3"><i class="bx bx-mobile-alt"></i></div>
                        <h5 class="modal-title modal-title-hitech mb-0">Allot Device</h5>
                        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal"><i
                                class="bx bx-x"></i></button>
                    </div>
                    <div class="modal-body modal-body-hitech">
                        <form action="{{ route('employees.allotDevice') }}" method="POST" id="allotDeviceForm">
                            @csrf
                            <input type="hidden" name="userId" value="{{ $user->id }}">
                            <div class="row g-3">
                                <!-- Asset Selection -->
                                <div class="col-12">
                                    <label class="form-label-hitech" for="allotAssetId">Search Available Assets
                                        (Inventory)</label>
                                    <select name="assetId" id="allotAssetId" class="form-select form-select-hitech select2">
                                        <option value="">-- Manual Entry / Not in Inventory --</option>
                                        @foreach($availableAssets as $asset)
                                            <option value="{{ $asset->id }}" data-name="{{ $asset->name }}"
                                                data-code="{{ $asset->asset_code }}" data-serial="{{ $asset->serial_number }}">
                                                {{ $asset->asset_code }} - {{ $asset->name }}
                                                {{ $asset->serial_number ? "({$asset->serial_number})" : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="smallest text-muted mt-1">Selecting an asset from inventory will auto-fill
                                        details and update its status.</div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label-hitech" for="allotDeviceId">Asset Code / ID <span
                                            class="badge bg-label-info ms-2 small">Auto-Generated</span></label>
                                    <input type="text" name="deviceId" id="allotDeviceId"
                                        class="form-control form-control-hitech bg-light"
                                        placeholder="Automatically generated based on Category..." readonly>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label-hitech" for="allotDeviceBrand">Asset Name <span
                                            class="text-muted small">(optional)</span></label>
                                    <input type="text" name="brand" id="allotDeviceBrand"
                                        class="form-control form-control-hitech" placeholder="e.g. Dell Latitude 5420">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label-hitech" for="allotDeviceType">Category / Type</label>
                                    <select name="deviceType" id="allotDeviceType" class="form-select form-select-hitech">
                                        <option value="laptop">Laptop / PC</option>
                                        <option value="mobile">Mobile Phone</option>
                                        <option value="tablet">Tablet</option>
                                        <option value="peripheral">Peripheral (Mouse/Keyboard)</option>
                                        <option value="biometric">Biometric Device</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label-hitech" for="allotModelNumber">Model Number <span
                                            class="text-muted small">(optional)</span></label>
                                    <input type="text" name="modelNumber" id="allotModelNumber"
                                        class="form-control form-control-hitech" placeholder="e.g. Latitude 5420">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label-hitech" for="allotSerialNumber">Serial Number <span
                                            class="text-muted small">(optional)</span></label>
                                    <input type="text" name="serialNumber" id="allotSerialNumber"
                                        class="form-control form-control-hitech" placeholder="e.g. SN-12345678">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label-hitech" for="allotServiceTag">Service Tag <span
                                            class="text-muted small">(optional)</span></label>
                                    <input type="text" name="serviceTag" id="allotServiceTag"
                                        class="form-control form-control-hitech" placeholder="e.g. TAG-XYZ9">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label-hitech" for="allotWarrantyExpiry">Warranty Expiry <span
                                            class="text-muted small">(optional)</span></label>
                                    <input type="date" name="warrantyExpiry" id="allotWarrantyExpiry"
                                        class="form-control form-control-hitech" min="{{ date('Y-m-d') }}">
                                </div>

                            </div>
                            <div class="modal-footer border-0 px-0 pb-0 pt-4 d-flex justify-content-end gap-3">
                                <button type="button" class="btn btn-hitech-modal-cancel px-4"
                                    data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-hitech-modal-submit px-5">
                                    Allot & Sync <i class="bx bx-check-circle ms-1"></i>
                                </button>
                            </div>
                        </form>
                    </div>


                </div>
            </div>
        </div>

        @if ($addonService->isAddonEnabled(ModuleConstants::SALES_TARGET))
            @include('salestarget::partials.add_or_update_sales_target_model')
        @endif
        @include('_partials._modals.employees.add_or_update_kpi')

        {{-- 1. Confirm Probation Modal --}}
        <div class="modal fade" id="confirmProbationModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content modal-content-hitech">
                    <div class="modal-header modal-header-hitech">
                        <div class="d-flex align-items-center">
                            <div class="modal-icon-header me-3">
                                <i class="bx bx-check-circle"></i>
                            </div>
                            <h5 class="modal-title modal-title-hitech mb-0">Confirm Probation</h5>
                        </div>
                        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal">
                            <i class="bx bx-x"></i>
                        </button>
                    </div>
                    <form id="confirmProbationForm" action="{{ route('employees.confirmProbation', $user->id) }}"
                        method="POST" onsubmit="return false;">
                        @csrf
                        <div class="modal-body modal-body-hitech text-center py-4">
                            <i class="bx bx-confetti text-success mb-3" style="font-size: 3.5rem; opacity: 0.8;"></i>
                            <h6 class="fw-bold mb-2">Confirm Completion?</h6>
                            <p class="text-muted small px-4">Are you sure you want to confirm the successful completion of
                                probation for <strong>{{ $user->getFullName() }}</strong>?</p>
                        </div>
                        <div class="modal-footer border-0 px-4 pb-4 d-flex justify-content-end gap-3">
                            <button type="button" class="btn btn-hitech-modal-cancel"
                                data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-hitech-modal-submit" id="confirmProbationSubmitBtn">Confirm
                                Now</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- 2. Extend Probation Modal --}}
        <div class="modal fade" id="extendProbationModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content modal-content-hitech">
                    <div class="modal-header modal-header-hitech">
                        <div class="d-flex align-items-center">
                            <div class="modal-icon-header me-3">
                                <i class="bx bx-calendar-plus"></i>
                            </div>
                            <h5 class="modal-title modal-title-hitech mb-0">Extend Probation</h5>
                        </div>
                        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal">
                            <i class="bx bx-x"></i>
                        </button>
                    </div>
                    <form id="extendProbationForm" action="{{ route('employees.extendProbation', $user->id) }}"
                        method="POST" onsubmit="return false;">
                        @csrf
                        <div class="modal-body modal-body-hitech">
                            <div class="row g-4">
                                <div class="col-12">
                                    <label class="form-label-hitech" for="newProbationEndDate">New Probation End Date <span
                                            class="text-danger">*</span></label>
                                    <input type="text" id="newProbationEndDate" name="newProbationEndDate"
                                        class="form-control form-control-hitech flatpickr-input"
                                        placeholder="Select New Date" required>
                                    <div class="invalid-feedback"></div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label-hitech" for="extendRemarks">Extension Reason / Remarks</label>
                                    <textarea class="form-control form-control-hitech" id="extendRemarks"
                                        name="probationRemarks" rows="3"
                                        placeholder="Explain why the probation is being extended..."></textarea>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-0 px-4 pb-4 d-flex justify-content-end gap-3">
                            <button type="button" class="btn btn-hitech-modal-cancel"
                                data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-hitech-modal-submit" id="extendProbationSubmitBtn">Extend
                                Period</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- 3. Fail Probation Modal --}}
        <div class="modal fade" id="failProbationModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content modal-content-hitech">
                    <div class="modal-header modal-header-hitech">
                        <div class="d-flex align-items-center">
                            <div class="modal-icon-header me-3 bg-danger" style="background-color: #FEE2E2 !important;">
                                <i class="bx bx-user-minus text-danger"></i>
                            </div>
                            <h5 class="modal-title modal-title-hitech mb-0">Probation Non-Completion</h5>
                        </div>
                        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal">
                            <i class="bx bx-x"></i>
                        </button>
                    </div>
                    <form id="failProbationForm" action="{{ route('employees.failProbation', $user->id) }}" method="POST"
                        onsubmit="return false;">
                        @csrf
                        <div class="modal-body modal-body-hitech">
                            <div class="alert alert-danger border-0 d-flex align-items-center mb-4"
                                style="background-color: #FEF2F2; border-radius: 12px;">
                                <i class="bx bx-error fs-4 me-2 text-danger"></i>
                                <div class="small fw-bold text-danger">Warning: This will terminate the employee due to
                                    probation failure.</div>
                            </div>
                            <div class="row g-4">
                                <div class="col-12">
                                    <label class="form-label-hitech" for="failRemarks">Reason for Failure <span
                                            class="text-danger">*</span></label>
                                    <textarea class="form-control form-control-hitech" id="failRemarks"
                                        name="probationRemarks" rows="3"
                                        placeholder="Provide detailed feedback on non-completion..." required></textarea>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-0 px-4 pb-4 d-flex justify-content-end gap-3">
                            <button type="button" class="btn btn-hitech-modal-cancel"
                                data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-hitech-modal-submit bg-danger border-0"
                                id="failProbationSubmitBtn">Confirm Failure</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>


        {{-- Review Onboarding Modal --}}
        @include('_partials._modals.onboarding.review')

        {{-- Manual Leave Credit Modal --}}
        @include('_partials._modals.leavePolicyProfile.manual_credit')

        {{-- Unified Document Viewer Modal --}}
        @include('_partials._modals.employees.document_viewer')

@endsection

    @section('page-script')
        <script type="module">
            // Ensure jQuery is available in this module scope
            const $ = window.jQuery || window.$;
            const baseUrl = window.baseUrl || (document.querySelector('html').getAttribute('data-base-url') + '/');

            // Global variables for employee context
            window.user = @json($user);
            window.baseUrl = baseUrl;

            // --- MANIFEST-STYLE ROBUST HANDLER FOR MANUAL LEAVE CREDIT ---
            // Defined at top to survive any downstream JS crashes
            window.handleManualLeaveCreditSubmission = function (e, formElement) {
                e.preventDefault();
                const $form = $(formElement);
                const $btn = $form.find('button[type="submit"]');
                const originalHtml = $btn.html();

                $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Processing...');

                $.ajax({
                    url: $form.attr('action'),
                    method: 'POST',
                    data: new FormData(formElement),
                    processData: false,
                    contentType: false,
                    success: function (resp) {
                        if (resp.code === 200 || resp.success) {
                            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalManualLeaveCredit')).hide();
                            Swal.fire({
                                icon: 'success',
                                title: 'Credit Successful',
                                text: resp.message,
                                customClass: { confirmButton: 'btn btn-primary rounded-pill px-5' }
                            }).then(() => location.reload());
                        } else {
                            Swal.fire('Error', resp.message || 'Validation failed', 'error');
                            $btn.prop('disabled', false).html(originalHtml);
                        }
                    },
                    error: function (xhr) {
                        $btn.prop('disabled', false).html(originalHtml);
                        Swal.fire('System Error', xhr.responseJSON?.message || 'Unable to allot credit.', 'error');
                    }
                });
                return false;
            };
            window.role = @json($role);
            window.attendanceType = @json($user->attendance_type);
            window.terminateUrl = "{{ route('employees.terminate', $user->id) }}";

            window.loadSelectList = function() {
                window.getSites();
                window.getGeofenceGroups();
                window.getIpGroups();
                window.getQrGroups();
                window.getDynamicQrDevices();
            };

            // Re-open modal if validation failed
            @if ($errors->any())
                $(document).ready(function() {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('offcanvasEditWorkInfo')).show();
                    window.loadSelectList();
                });
            @endif

            // -------------------------------------------------------------
            // GLOBALLY ACCESSIBLE FUNCTIONS (Must be defined before any jQuery calls)
            // -------------------------------------------------------------

            // Document Viewer Popup (for file-based documents)
            window.viewDocumentPopup = function (url, title, docNumber = 'N/A') {
                const modalEl = document.getElementById('modalViewDocument');
                if (!modalEl) {
                    console.error('Modal element not found');
                    return;
                }

                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                const titleEl = document.getElementById('modalViewTitle');
                const subtitle = document.getElementById('modalViewSubtitle');
                const contentArea = document.getElementById('modalViewContent');
                const downloadBtn = document.getElementById('modalDownloadBtn');
                const iconEl = document.getElementById('modalViewIcon');

                titleEl.innerText = title;
                subtitle.innerHTML = `<span class="opacity-75">Ref No / ID:</span> <span class="fw-bold text-white">${docNumber}</span>`;
                downloadBtn.href = url;
                downloadBtn.style.display = 'inline-flex';

                contentArea.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">Loading secure preview...</p></div>';

                modal.show();

                // Type Detection
                // We assume anything that isn't cleanly an image can be handled by an iframe (like native PDF rendering or embedded dashboards).
                const isImage = /\.(jpeg|jpg|gif|png|webp|svg)/i.test(url);

                setTimeout(() => {
                    if (isImage) {
                        iconEl.className = 'bx bx-image-alt';
                        contentArea.innerHTML = `<div class="p-4 w-100 h-100 d-flex align-items-center justify-content-center" style="background:#f8fafc;"><img src="${url}" class="img-fluid rounded shadow-sm" style="max-height: 70vh; object-fit: contain;"></div>`;
                    } else {
                        iconEl.className = 'bx bxs-file-pdf';
                        contentArea.innerHTML = `<iframe id="viewerIframe" src="${url}" style="width:100%; height:75vh; border:none; background: #fff;"></iframe>`;
                    }
                }, 400);
            };

            window.viewDocumentNumber = function (title, number) {
                const modalEl = document.getElementById('modalViewDocument');
                if (!modalEl) return;

                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                const titleEl = document.getElementById('modalViewTitle');
                const subtitle = document.getElementById('modalViewSubtitle');
                const contentArea = document.getElementById('modalViewContent');
                const downloadBtn = document.getElementById('modalDownloadBtn');
                const iconEl = document.getElementById('modalViewIcon');

                titleEl.innerText = title;
                subtitle.innerHTML = `<span class="opacity-75">Ref No / ID:</span> <span class="fw-bold text-white">${number}</span>`;
                downloadBtn.style.display = 'none';
                iconEl.className = 'bx bx-id-card';

                contentArea.innerHTML = `
                    <div class="text-center py-5 bg-white w-100 h-100 rounded d-flex flex-column align-items-center justify-content-center" style="min-height: 400px;">
                        <div class="mx-auto rounded-circle d-flex align-items-center justify-content-center mb-4" style="width: 100px; height: 100px; background: rgba(18,116,100,0.1);">
                            <i class="bx bx-badge-check fs-1" style="color: #127464; font-size: 3rem !important;"></i>
                        </div>
                        <h2 class="fw-bold text-dark mb-2" style="letter-spacing: 2px;">${number}</h2>
                        <p class="text-muted text-uppercase small fw-bold">Authenticated Identity Reference</p>
                        <p class="text-muted mt-3 mb-0" style="font-size: 0.8rem;">No scanned document available for this record.</p>
                    </div>
                `;

                modal.show();
            };

            // -------------------------------------------------------------

            document.addEventListener("DOMContentLoaded", function () {
                // Helper to wait for jQuery if loaded as deferred or module
                const waitForJQuery = setInterval(function () {
                    if (typeof window.jQuery !== 'undefined') {
                        clearInterval(waitForJQuery);
                        const $ = window.jQuery;

                        // Asset Allotment Selector Logic
                        const assetSelect = $('#allotAssetId');
                        if (assetSelect.length) {
                            assetSelect.on('change', function () {
                                const selected = $(this).find(':selected');
                                if (selected.val()) {
                                    $('#allotDeviceId').val(selected.data('code') || '');
                                    $('#allotDeviceBrand').val(selected.data('name') || '');
                                    $('#allotSerialNumber').val(selected.data('serial') || '');

                                    // Auto-detect type based on name hints if possible
                                    const name = (selected.data('name') || '').toLowerCase();
                                    if (name.includes('laptop') || name.includes('thinkpad') || name.includes('macbook') || name.includes('dell')) {
                                        $('#allotDeviceType').val('laptop');
                                    } else if (name.includes('phone') || name.includes('samsung') || name.includes('iphone')) {
                                        $('#allotDeviceType').val('mobile');
                                    }
                                }
                            });
                        }
                    }
                }, 50);

                // Timeout after 5 seconds to stop interval
                setTimeout(() => clearInterval(waitForJQuery), 5000);
            });



            // Tabs Horizontal Scroller (Desktop)
            function setupTabScroller() {
                const tabWrapper = document.querySelector('.rosemary-nav-tabs');
                const leftBtn = document.querySelector('.rosemary-tab-arrow.left');
                const rightBtn = document.querySelector('.rosemary-tab-arrow.right');

                if (!tabWrapper || !leftBtn || !rightBtn) return;

                const updateArrows = () => {
                    const maxScroll = tabWrapper.scrollWidth - tabWrapper.clientWidth;
                    if (maxScroll <= 0) {
                        leftBtn.style.display = 'none';
                        rightBtn.style.display = 'none';
                        return;
                    }
                    leftBtn.style.display = 'inline-flex';
                    rightBtn.style.display = 'inline-flex';
                    leftBtn.disabled = tabWrapper.scrollLeft <= 1;
                    rightBtn.disabled = tabWrapper.scrollLeft >= maxScroll - 1;
                };

                const scrollByAmount = 220;
                leftBtn.addEventListener('click', () => {
                    tabWrapper.scrollBy({ left: -scrollByAmount, behavior: 'smooth' });
                });
                rightBtn.addEventListener('click', () => {
                    tabWrapper.scrollBy({ left: scrollByAmount, behavior: 'smooth' });
                });

                tabWrapper.addEventListener('scroll', updateArrows, { passive: true });
                window.addEventListener('resize', updateArrows);

                document.querySelectorAll('#employeeTabs .nav-link').forEach((link) => {
                    link.addEventListener('shown.bs.tab', (e) => {
                        e.target.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
                        setTimeout(updateArrows, 300);
                    });
                });

                updateArrows();
            }

            document.addEventListener('DOMContentLoaded', setupTabScroller);

            // Mobile Tab Navigation Logic
            function navigateTabs(direction) {
                const tabs = $('.rosemary-nav-tabs .nav-link');
                const activeTab = $('.rosemary-nav-tabs .nav-link.active');
                let activeIndex = tabs.index(activeTab);

                let nextIndex;
                if (direction === 'next') {
                    nextIndex = (activeIndex + 1) % tabs.length;
                } else {
                    nextIndex = (activeIndex - 1 + tabs.length) % tabs.length;
                }

                const nextTab = $(tabs[nextIndex]);
                nextTab.tab('show');

                // Smooth scroll to top of content
                window.scrollTo({ top: 0, behavior: 'smooth' });

                // Update horizontal scroll of tab header if needed
                const tabWrapper = document.querySelector('.rosemary-nav-tabs');
                const targetTab = tabs[nextIndex];
                tabWrapper.scrollTo({
                    left: targetTab.offsetLeft - (tabWrapper.offsetWidth / 2) + (targetTab.offsetWidth / 2),
                    behavior: 'smooth'
                });
            }

            // Document Management Modal Handler
            window.setDocModal = function(name, number) {
                const docNameInput = document.getElementById('docModalName');
                const docNumInput = document.getElementById('docModalNumber');
                
                docNameInput.value = name;
                docNumInput.value = (number === 'N/A' || number === 'undefined' ? '' : number);

                // List of mandatory names to lock
                const mandatoryNames = [
                    'Aadhaar Card', 
                    'PAN Card', 
                    '10th Marksheet (Matric)', 
                    '12th Marksheet (Intermediate)', 
                    'Graduation Marksheet', 
                    'Post Graduation Marksheet', 
                    'Experience Certificate'
                ];

                if (mandatoryNames.includes(name)) {
                    docNameInput.readOnly = true;
                    docNameInput.classList.add('bg-light');
                } else {
                    docNameInput.readOnly = false;
                    docNameInput.classList.remove('bg-light');
                }
            }

            // Device Unlink Confirmation
            function showDeleteDeviceConfirmation() {
                Swal.fire({
                    title: 'Unlink Device?',
                    text: "This will remove the biometric lock for this employee's mobile app.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#127464',
                    cancelButtonColor: '#64748B',
                    confirmButtonText: 'Yes, Unlink it!',
                    customClass: {
                        confirmButton: 'btn btn-danger me-3',
                        cancelButton: 'btn btn-label-secondary'
                    },
                    buttonsStyling: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById('deleteDeviceForm').submit();
                    }
                });
            }

            // AJAX Loaders for Dynamic Dropdowns
            window.getDynamicQrDevices = function() {
                var dynamicQrId = '{{ $user->dynamic_qr_device_id }}';
                $.ajax({
                    url: '{{ route('employee.getDynamicQrDevices') }}',
                    type: 'GET',
                    success: function (response) {
                        var options = '<option value="">Please select a dynamic qr device</option>';
                        response.forEach(function (item) {
                            options += '<option value="' + item.id + '" ' + (dynamicQrId == item.id ? 'selected' : '') + '>' + item.name + '</option>';
                        });
                        $('#dynamicQrId').html(options);
                    }
                });
            }
            window.getGeofenceGroups = function() {
                var geofenceId = '{{ $user->geofence_group_id }}';
                $.ajax({
                    url: '{{ route('employee.getGeofenceGroups') }}',
                    type: 'GET',
                    success: function (response) {
                        var options = '<option value="">Please select a geofence group</option>';
                        response.forEach(function (item) {
                            options += '<option value="' + item.id + '" ' + (geofenceId == item.id ? 'selected' : '') + '>' + item.name + '</option>';
                        });
                        $('#geofenceGroupId').html(options);
                    }
                });
            }
            window.getIpGroups = function() {
                var ipGroupId = '{{ $user->ip_address_group_id }}';
                $.ajax({
                    url: '{{ route('employee.getIpGroups') }}',
                    type: 'GET',
                    success: function (response) {
                        var options = '<option value="">Please select a ip group</option>';
                        response.forEach(function (item) {
                            options += '<option value="' + item.id + '" ' + (ipGroupId == item.id ? 'selected' : '') + '>' + item.name + '</option>';
                        });
                        $('#ipGroupId').html(options);
                    }
                });
            }
            window.getQrGroups = function() {
                var qrGroupId = '{{ $user->qr_group_id }}';
                $.ajax({
                    url: '{{ route('employee.getQrGroups') }}',
                    type: 'GET',
                    success: function (response) {
                        var options = '<option value="">Please select a qr group</option>';
                        response.forEach(function (item) {
                            options += '<option value="' + item.id + '" ' + (qrGroupId == item.id ? 'selected' : '') + '>' + item.name + '</option>';
                        });
                        $('#qrGroupId').html(options);
                    }
                });
            }
            window.getSites = function() {
                var siteId = '{{ $user->site_id }}';
                $.ajax({
                    url: '{{ route('employee.getSites') }}',
                    type: 'GET',
                    success: function (response) {
                        var options = '<option value="">Please select a site</option>';
                        response.forEach(function (item) {
                            options += '<option value="' + item.id + '" ' + (siteId == item.id ? 'selected' : '') + '>' + item.name + '</option>';
                        });
                        $('#siteId').html(options);
                    }
                });
            }

            // Employee Action Handlers (Status Toggle, Relieve, Retire)
            // Employee Action Handlers (Status Toggle, Relieve, Retire)
            function toggleEmployeeStatus(userId, isActive) {
                const actionText = isActive ? 'Activate' : 'Deactivate';
                const iconClass = isActive ? 'bx-user-check text-success' : 'bx-user-x text-danger';
                const bgClass = isActive ? 'bg-label-success' : 'bg-label-danger';

                Swal.fire({
                    html: `
                        <div class="text-center mb-4">
                            <div class="mx-auto ${bgClass} rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                <i class="bx ${iconClass}" style="font-size: 3rem;"></i>
                            </div>
                            <h4 class="mb-2 fw-bold text-dark">${actionText} Employee?</h4>
                            <p class="text-muted small mb-0">Are you sure you want to ${actionText.toLowerCase()} this employee's account access?</p>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: `Yes, ${actionText}`,
                    cancelButtonText: 'Cancel',
                    customClass: {
                        popup: 'rounded-4 shadow-lg border-0',
                        confirmButton: `btn btn-hitech rounded-pill px-4 fw-bold shadow-sm`,
                        cancelButton: 'btn btn-hitech-secondary rounded-pill px-4 fw-bold ms-3'
                    },
                    buttonsStyling: false,
                    showCloseButton: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.post(`/employees/toggleStatus/${userId}`, { _token: '{{ csrf_token() }}', status: isActive ? 1 : 0 }, function (response) {
                            Swal.fire({
                                html: `
                                    <div class="text-center">
                                        <div class="mx-auto bg-label-success rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                            <i class="bx bx-check text-success" style="font-size: 3rem;"></i>
                                        </div>
                                        <h4 class="mb-0 fw-bold text-dark">Updated!</h4>
                                    </div>
                                `,
                                timer: 1500,
                                showConfirmButton: false,
                                customClass: { popup: 'rounded-4 shadow-lg border-0' }
                            });
                            setTimeout(() => location.reload(), 1500);
                        }).fail(() => Swal.fire('Error', 'Unable to update status', 'error'));
                    } else {
                        // Reset the toggle if cancelled
                        document.getElementById('employeeStatusToggle').checked = !isActive;
                    }
                });
            }

            function confirmEmployeeAction(action, userId) {
                const text = action === 'relieve' ? 'Relieve' : 'Retire';
                Swal.fire({
                    html: `
                        <div class="text-center mb-4">
                            <div class="mx-auto bg-label-warning rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                <i class="bx bx-error text-warning" style="font-size: 3rem;"></i>
                            </div>
                            <h4 class="mb-2 fw-bold text-dark">${text} Employee?</h4>
                            <p class="text-muted small mb-0">This action cannot be undone!</p>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: `Yes, ${text}`,
                    cancelButtonText: 'Cancel',
                    customClass: {
                        popup: 'rounded-4 shadow-lg border-0',
                        confirmButton: 'btn btn-hitech rounded-pill px-4 fw-bold shadow-sm',
                        cancelButton: 'btn btn-hitech-secondary rounded-pill px-4 fw-bold ms-3'
                    },
                    buttonsStyling: false,
                    showCloseButton: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.post(`/employees/${action}/${userId}`, { _token: '{{ csrf_token() }}' }, function (response) {
                            Swal.fire({
                                html: `
                                    <div class="text-center">
                                        <div class="mx-auto bg-label-success rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                            <i class="bx bx-check text-success" style="font-size: 3rem;"></i>
                                        </div>
                                        <h4 class="mb-0 fw-bold text-dark">Success!</h4>
                                    </div>
                                `,
                                timer: 1500,
                                showConfirmButton: false,
                                customClass: { popup: 'rounded-4 shadow-lg border-0' }
                            });
                            setTimeout(() => location.reload(), 2000);
                        }).fail(() => Swal.fire('Error', `Unable to ${text} employee`, 'error'));
                    }
                });
            }

            function loadBankDetails() {
                try {
                    const bank = user.bank_account || user.bankAccount;
                    if (!user || !bank) {
                        $('#bankName, #bankCode, #accountName, #accountNumber, #confirmAccountNumber, #branchName, #branchCode').val('');
                        return;
                    }
                    $('#bankName').val(bank.bank_name || '');
                    $('#bankCode').val(bank.bank_code || '');
                    $('#accountName').val(bank.account_name || '');
                    $('#accountNumber').val(bank.account_number || '');
                    $('#confirmAccountNumber').val(bank.account_number || '');
                    $('#branchName').val(bank.branch_name || '');
                    $('#branchCode').val(bank.branch_code || '');
                } catch (e) {
                    console.error("loadBankDetails error:", e);
                }
            }

            // Auto-call loadBankDetails when bank modal opens
            document.addEventListener('DOMContentLoaded', function () {
                var bankModal = document.getElementById('editBankAccountModal');
                if (bankModal) {
                    bankModal.addEventListener('show.bs.modal', function () {
                        loadBankDetails();
                    });
                }
            });

            document.addEventListener('DOMContentLoaded', function () {
                // Termination Modal Setup
                const terminateModal = document.getElementById('terminateEmployeeModal');
                if (terminateModal) {
                    $(terminateModal).find('.select2').select2({ dropdownParent: $(terminateModal) });
                    flatpickr("#exitDate", { dateFormat: 'Y-m-d', altInput: true, altFormat: 'M j, Y' });
                    flatpickr("#lastWorkingDay", { dateFormat: 'Y-m-d', altInput: true, altFormat: 'M j, Y' });

                    document.getElementById('terminateEmployeeForm')?.addEventListener('submit', function (e) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Confirm Termination?',
                            text: "This action is major and processed immediately. Continue?",
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, Terminate',
                            customClass: { confirmButton: 'btn btn-danger me-3', cancelButton: 'btn btn-label-secondary' },
                            buttonsStyling: false
                        }).then(res => {
                            if (res.isConfirmed) {
                                const btn = document.getElementById('terminateSubmitBtn');
                                btn.disabled = true;
                                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';
                                $.ajax({
                                    url: terminateUrl,
                                    method: 'POST',
                                    data: new FormData(this),
                                    processData: false,
                                    contentType: false,
                                    success: (resp) => {
                                        if (resp.success) {
                                            Swal.fire({ icon: 'success', title: 'Terminated', text: resp.message, timer: 2000, showConfirmButton: false });
                                            setTimeout(() => location.reload(), 2000);
                                        }
                                    },
                                    error: (xhr) => {
                                        btn.disabled = false;
                                        btn.innerHTML = 'Confirm Termination';
                                        Swal.fire('Error', xhr.responseJSON?.message || 'Update failed', 'error');
                                    }
                                });
                            }
                        });
                    });
                }

                // Probation Forms Setup
                const probConfigs = [
                    { id: 'confirmProbationForm', btnId: 'confirmProbationSubmitBtn', text: 'Confirm Completion' },
                    { id: 'extendProbationForm', btnId: 'extendProbationSubmitBtn', text: 'Extend Probation' },
                    { id: 'failProbationForm', btnId: 'failProbationSubmitBtn', text: 'Confirm Failure' }
                ];

                flatpickr("#newProbationEndDate", {
                    dateFormat: 'Y-m-d', altInput: true, altFormat: 'M j, Y',
                    minDate: '{{ optional($user->probation_end_date)->toDateString() }}' ? new Date('{{ optional($user->probation_end_date)->toDateString() }}').fp_incr(1) : "today"
                });

                probConfigs.forEach(config => {
                    const form = document.getElementById(config.id);
                    if (form) {
                        form.addEventListener('submit', function (e) {
                            e.preventDefault();
                            const btn = document.getElementById(config.btnId);
                            btn.disabled = true;
                            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';

                            fetch(form.action, {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                                body: new FormData(form)
                            })
                                .then(r => r.json())
                                .then(data => {
                                    if (data.success) {
                                        Swal.fire({ icon: 'success', title: 'Updated', text: data.message, timer: 2000, showConfirmButton: false });
                                        setTimeout(() => location.reload(), 2000);
                                    } else {
                                        throw new Error(data.message || 'Validation failed');
                                    }
                                })
                                .catch(err => {
                                    btn.disabled = false;
                                    btn.innerHTML = config.text;
                                    Swal.fire('Error', err.message, 'error');
                                });
                        });
                    }
                });

                // KRA Management Handlers
                const addTaskForm = document.getElementById('addTaskForm');
                if (addTaskForm) {
                    addTaskForm.addEventListener('submit', function (e) {
                        e.preventDefault();
                        const btn = this.querySelector('button[type="submit"]');
                        btn.disabled = true;
                        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';

                        const taskId = document.getElementById('modalTaskId').value;
                        const url = taskId ? `${baseUrl}tasks/update/${taskId}` : '{{ route('tasks.store') }}';

                        $.post(url, $(this).serialize() + '&_token={{ csrf_token() }}', function (resp) {
                            if (resp.success) {
                                Swal.fire({ icon: 'success', title: taskId ? 'KRA Updated' : 'KRA Assigned', text: resp.message, timer: 1500, showConfirmButton: false });
                                setTimeout(() => location.reload(), 1500);
                            }
                        }).fail(xhr => {
                            btn.disabled = false;
                            btn.innerHTML = taskId ? 'Update KRA <i class="bx bx-check-circle ms-1"></i>' : 'Create KRA <i class="bx bx-check-circle ms-1"></i>';
                            Swal.fire('Error', xhr.responseJSON?.message || 'Failed to process KRA', 'error');
                        });
                    });
                }

                // Function to reset KRA modal for new objective
                window.resetKraModal = function () {
                    $('#modalTaskId').val('');
                    $('#modalTaskTitle').val('');
                    $('#modalTaskDescription').val('');
                    $('#modalTaskDueDate').val('');
                    $('#modalTaskType').val('open');
                    $('.modal-title-hitech').text('Define Key Result Area (KRA)');
                    $('#addTaskForm button[type="submit"]').html('Publish KRA <i class="bx bx-check-circle ms-1"></i>');
                };

                // Function to populate KRA modal for editing
                window.editKra = function (id, title, description, dueDate, type) {
                    $('#modalTaskId').val(id);
                    $('#modalTaskTitle').val(title);
                    $('#modalTaskDescription').val(description);
                    $('#modalTaskDueDate').val(dueDate);
                    $('#modalTaskType').val(type);
                    $('.modal-title-hitech').text('Refine KRA Details');
                    $('#addTaskForm button[type="submit"]').html('Update KRA <i class="bx bx-check-circle ms-1"></i>');
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAddTask')).show();
                };
            });

            // Global functions for task actions (outside DOMContentLoaded to be accessible via onclick)
            function updateTaskStatus(taskId, status) {
                Swal.fire({
                    title: 'Update Task Status?',
                    text: `Set this task to ${status.replace('_', ' ')}?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Update',
                    customClass: { confirmButton: 'btn btn-primary me-3', cancelButton: 'btn btn-label-secondary' },
                    buttonsStyling: false
                }).then(res => {
                    if (res.isConfirmed) {
                        $.post(`${baseUrl}tasks/updateStatus/${taskId}`, { _token: '{{ csrf_token() }}', status: status }, function (resp) {
                            if (resp.success) {
                                Swal.fire({ icon: 'success', title: 'Updated!', text: resp.message, timer: 1000, showConfirmButton: false });
                                setTimeout(() => location.reload(), 1000);
                            }
                        }).fail(() => Swal.fire('Error', 'Unable to update status', 'error'));
                    }
                });
            }

            // ── KRA & KPI Strategic Management ────────────────────────────────────

            window.openAddKpi = function (id = '', metric = '', goal = '', type = 'percentage', description = '') {
                $('#modalKpiId').val(id);
                $('#modalKpiMetric').val(metric);
                $('#modalKpiGoal').val(goal);
                $('#modalKpiType').val(type);
                $('#modalKpiDescription').val(description);

                $('.modal-title-hitech').text(id ? 'Refine Performance KPI' : 'Define Strategic KPI');
                $('.btn-hitech-modal-submit').html(id ? 'Update KPI <i class="bx bx-check-circle ms-1"></i>' : 'Deploy KPI <i class="bx bx-rocket ms-1"></i>');

                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAddKpi')).show();
            };

            // KPI Form Submission Handler
            $(document).on('submit', '#addKpiForm', function (e) {
                e.preventDefault();
                const btn = $(this).find('button[type="submit"]');
                const originalHtml = btn.html();

                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';

                const kpiId = $('#modalKpiId').val();
                const url = '{{ route('employees.addOrUpdateSalesTarget') }}';

                $.ajax({
                    url: url,
                    method: 'POST',
                    data: $(this).serialize(),
                    success: (resp) => {
                        if (resp.success) {
                            Swal.fire({ icon: 'success', title: 'Strategy Deployed', text: resp.message, timer: 1500, showConfirmButton: false });
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            btn.disabled = false;
                            btn.html(originalHtml);
                            Swal.fire('Validation Error', resp.message, 'warning');
                        }
                    },
                    error: (xhr) => {
                        btn.disabled = false;
                        btn.html(originalHtml);
                        Swal.fire('System Error', xhr.responseJSON?.message || 'Unable to deploy performance target.', 'error');
                    }
                });
            });

            function deleteKra(kraId) {
                Swal.fire({
                    html: `
                        <div class="text-center mb-4">
                            <div class="mx-auto bg-label-danger rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                <i class="bx bx-trash text-danger" style="font-size: 2.5rem;"></i>
                            </div>
                            <h4 class="mb-2 fw-bold text-dark">Delete KRA?</h4>
                            <p class="text-muted small mb-0">This action cannot be undone!</p>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Delete',
                    cancelButtonText: 'Cancel',
                    customClass: {
                        popup: 'rounded-4 shadow-lg border-0',
                        confirmButton: 'btn btn-danger rounded-pill px-4 fw-bold shadow-sm',
                        cancelButton: 'btn btn-light rounded-pill px-4 fw-bold ms-3'
                    },
                    buttonsStyling: false,
                    showCloseButton: false,
                    focusCancel: true
                }).then(res => {
                    if (res.isConfirmed) {
                        $.ajax({
                            url: `${baseUrl}tasks/delete/${kraId}`,
                            type: 'DELETE',
                            data: { _token: '{{ csrf_token() }}' },
                            success: function (resp) {
                                if (resp.success) {
                                    Swal.fire({
                                        html: `
                                            <div class="text-center">
                                                <div class="mx-auto bg-label-success rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                                    <i class="bx bx-check text-success" style="font-size: 3rem;"></i>
                                                </div>
                                                <h4 class="mb-0 fw-bold text-dark">Deleted!</h4>
                                            </div>
                                        `,
                                        timer: 1500,
                                        showConfirmButton: false,
                                        customClass: { popup: 'rounded-4 shadow-lg border-0' }
                                    });
                                    setTimeout(() => location.reload(), 1500);
                                }
                            },
                            error: () => Swal.fire('Error', 'Unable to delete KRA', 'error')
                        });
                    }
                });
            }
        </script>
        @vite(['resources/js/main-helper.js', 'resources/assets/js/app/employee-view.js'])
    @endsection