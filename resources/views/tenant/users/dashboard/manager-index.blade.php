@extends('layouts/layoutMaster')

@section('title', 'Team Manager Dashboard')

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/animate-css/animate.scss',
  'resources/assets/vendor/scss/pages/hitech-portal.scss'
])
<style>
    .keka-manager-wrapper {
        font-family: 'Plus Jakarta Sans', sans-serif;
    }
    /* Hero Section */
    .manager-hero {
        background: linear-gradient(135deg, #004D54 0%, #008080 100%);
        border-radius: 24px;
        padding: 2.5rem;
        position: relative;
        overflow: hidden;
        color: white;
        box-shadow: 0 20px 40px rgba(0, 77, 84, 0.2);
    }
    .manager-hero::after {
        content: '';
        position: absolute;
        top: -50px;
        right: -50px;
        width: 200px;
        height: 200px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
    }
    /* Team Stats Bar */
    .team-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 1.5rem;
        margin-top: -3rem;
        padding: 0 1.5rem;
    }
    .keka-stat-card {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        border: 1px solid rgba(0, 77, 84, 0.05);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        text-align: left;
        box-shadow: 0 10px 30px -10px rgba(0,0,0,0.1);
    }
    .keka-stat-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 25px 50px -12px rgba(0, 77, 84, 0.15);
    }
    .stat-icon {
        width: 45px;
        height: 45px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        margin-bottom: 1rem;
    }
    .icon-team { background: rgba(0, 128, 128, 0.1); color: #008080; }
    .icon-present { background: rgba(16, 185, 129, 0.1); color: #10b981; }
    .icon-leave { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
    .icon-absent { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

    /* Approval Widgets */
    .approval-widget {
        background: white;
        border-radius: 20px;
        border: 1px solid #f1f5f9;
        overflow: hidden;
    }
    .approval-header {
        padding: 1.25rem 1.5rem;
        background: #f8fafc;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid #f1f5f9;
    }
    .approval-item {
        padding: 1rem 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid #f8fafc;
    }
    .approval-count {
        background: #004D54;
        color: white;
        font-size: 0.75rem;
        font-weight: 700;
        padding: 2px 10px;
        border-radius: 10px;
    }

    /* Birthday Widget */
    .birthday-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 15px;
        border-radius: 12px;
        background: rgba(245, 158, 11, 0.05);
        margin-bottom: 8px;
    }
</style>
@endsection

@section('content')
<div class="keka-manager-wrapper container-xxl">
    
    <!-- Hero Section -->
    <div class="row mb-5 animate__animated animate__fadeIn">
        <div class="col-12">
            <div class="manager-hero">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <span class="badge rounded-pill px-3 py-2 mb-3" style="background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.2);">
                            SYSTEM CONTROL CENTER : MANAGER ACCESS
                        </span>
                        <h1 class="display-6 fw-bold text-white mb-2">Hello, {{ auth()->user()->first_name }}!</h1>
                        <p class="fs-5 opacity-75 mb-4">Here is what your team, <b>{{ auth()->user()->team->name ?? 'Direct Reports' }}</b>, is up to today.</p>
                        
                        <div class="d-flex gap-3 mt-4">
                            <a href="javascript:void(0)" class="btn btn-white rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#onboardingInviteModal">
                                <i class="bx bx-plus-circle me-1"></i> Invite Member
                            </a>
                            <a href="{{ route('leaveRequests.index') }}" class="btn btn-outline-white rounded-pill px-4 fw-bold">
                                View Full Attendance
                            </a>
                        </div>
                    </div>
                    <div class="col-lg-4 d-none d-lg-block text-center">
                        <img src="{{ asset('assets/img/illustrations/man-with-laptop-light.png') }}" width="200" class="animate__animated animate__pulse animate__infinite animate__slow">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Team Stats Bar -->
    <div class="team-stats-grid mb-5 animate__animated animate__fadeInUp">
        <div class="keka-stat-card">
            <div class="stat-icon icon-team"><i class="bx bx-group"></i></div>
            <h2 class="fw-extrabold text-dark mb-0">{{ $activeEmployees }}</h2>
            <div class="text-muted small fw-bold text-uppercase">Total Team Staff</div>
        </div>
        <div class="keka-stat-card">
            <div class="stat-icon icon-present"><i class="bx bx-user-check"></i></div>
            <h2 class="fw-extrabold text-dark mb-0">{{ $todayPresentUsers }}</h2>
            <div class="text-muted small fw-bold text-uppercase">Present Today</div>
        </div>
        <div class="keka-stat-card">
            <div class="stat-icon icon-leave"><i class="bx bx-calendar-event"></i></div>
            <h2 class="fw-extrabold text-dark mb-0">{{ $todayOnLeaveCount }}</h2>
            <div class="text-muted small fw-bold text-uppercase">On Leave</div>
        </div>
        <div class="keka-stat-card">
            <div class="stat-icon icon-absent"><i class="bx bx-user-x"></i></div>
            <h2 class="fw-extrabold text-danger mb-0">{{ $todayAbsentUsers }}</h2>
            <div class="text-muted small fw-bold text-uppercase">Absent / Unaccounted</div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Main Content (Left) -->
        <div class="col-xl-8 col-lg-7">
            
            <!-- Pending Approvals Widget -->
            <div class="approval-widget mb-4 animate__animated animate__fadeInLeft">
                <div class="approval-header">
                    <h6 class="mb-0 fw-bold"><i class="bx bx-check-shield me-2 text-primary"></i>Required Approvals</h6>
                    <span class="small text-muted fw-bold">Action Required</span>
                </div>
                <div class="p-0">
                    <a href="{{ route('leaveRequests.index') }}" class="approval-item text-decoration-none">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon-wrap icon-teal" style="width:36px; height:36px;"><i class="bx bx-calendar"></i></div>
                            <span class="fw-bold text-dark">Leave Requests</span>
                        </div>
                        <span class="approval-count">{{ $pendingLeaveRequests }}</span>
                    </a>
                    <a href="{{ route('expenseRequests.index') }}" class="approval-item text-decoration-none">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon-wrap icon-amber" style="width:36px; height:36px;"><i class="bx bx-wallet"></i></div>
                            <span class="fw-bold text-dark">Expense Invoices</span>
                        </div>
                        <span class="approval-count">{{ $pendingExpenseRequests }}</span>
                    </a>
                    <a href="{{ route('approvals.index') }}" class="approval-item text-decoration-none">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon-wrap icon-primary" style="width:36px; height:36px;"><i class="bx bx-file"></i></div>
                            <span class="fw-bold text-dark">Document Requests</span>
                        </div>
                        <span class="approval-count">{{ $pendingDocumentRequests }}</span>
                    </a>
                </div>
                <div class="p-3 bg-light-soft text-center">
                    <a href="{{ route('approvals.index') }}" class="small fw-bold text-teal text-decoration-none">VIEW ALL PENDING QUEUE <i class="bx bx-right-arrow-alt"></i></a>
                </div>
            </div>

            <!-- Team Visibility Table -->
            <div class="hitech-card animate__animated animate__fadeInUp">
                <div class="hitech-card-header">
                    <h6 class="title mb-0">Team Attendance Highlights</h6>
                    <a href="{{ route('attendance.index') }}" class="btn btn-sm btn-link text-primary fw-bold">Full Log</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="bg-light-soft">
                            <tr>
                                <th class="text-uppercase small fw-bold">Team Member</th>
                                <th class="text-uppercase small fw-bold">Department</th>
                                <th class="text-uppercase small fw-bold text-end">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($teamOutToday->take(5) as $request)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar avatar-sm">
                                            <span class="avatar-initial rounded-circle bg-label-teal">{{ $request->user->initials }}</span>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark">{{ $request->user->full_name }}</div>
                                            <div class="small text-muted">{{ $request->user->designation->name ?? 'Staff' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="small fw-bold text-muted">{{ $request->user->department->name ?? 'General' }}</span></td>
                                <td class="text-end">
                                    <span class="badge bg-label-warning rounded-pill px-3">On Leave</span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center py-5">
                                    <i class="bx bx-cool fs-1 text-teal opacity-50 mb-2 d-block"></i>
                                    <div class="text-muted fw-bold">Entire team is present and accounted for today!</div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- Sidebar Items (Right) -->
        <div class="col-xl-4 col-lg-5">
            
            <!-- Holiday Widget -->
            <div class="holiday-card mb-4 animate__animated animate__fadeInRight">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <i class="bx bx-party fs-3 text-white"></i>
                    <span class="badge bg-white text-teal rounded-pill fw-bold">CALENDAR</span>
                </div>
                @if($nextHoliday)
                    <h4 class="text-white fw-bold mb-1">{{ $nextHoliday->name }}</h4>
                    <p class="text-white opacity-75 mb-3">{{ $nextHoliday->date->format('l, F d') }}</p>
                    <div class="d-flex align-items-center gap-2">
                        <div class="flex-grow-1 bg-white bg-opacity-20 rounded-pill" style="height: 6px;">
                            <div class="bg-white rounded-pill" style="height: 6px; width: 65%;"></div>
                        </div>
                        <span class="text-white small fw-bold">{{ now()->diffInDays($nextHoliday->date) }} Days Left</span>
                    </div>
                @else
                    <p class="text-white opacity-75">No upcoming holidays scheduled.</p>
                @endif
            </div>

            <!-- Team Birthdays -->
            <div class="hitech-card animate__animated animate__fadeInRight" style="animation-delay: 0.1s">
                <div class="hitech-card-header border-bottom">
                    <h6 class="title mb-0">Team Celebrations</h6>
                    <i class="bx bx-cake text-warning"></i>
                </div>
                <div class="card-body p-3">
                    @forelse($teamBirthdays as $member)
                    <div class="birthday-item">
                        <div class="avatar avatar-sm">
                            <span class="avatar-initial rounded-circle bg-label-warning">{{ $member->initials }}</span>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-bold text-dark small">{{ $member->full_name }}</div>
                            <div class="text-muted" style="font-size: 10px;">{{ \Carbon\Carbon::parse($member->dob)->format('d M') }} • Birthday</div>
                        </div>
                        <button class="btn btn-sm btn-icon rounded-pill bg-white shadow-sm border"><i class="bx bx-heart text-danger"></i></button>
                    </div>
                    @empty
                    <div class="text-center py-4 text-muted small">No upcoming celebrations in the next 30 days.</div>
                    @endforelse
                </div>
            </div>

        </div>
    </div>
</div>

{{-- MODALS --}}
@include('tenant.employees.onboarding_invite_modal')

@endsection
