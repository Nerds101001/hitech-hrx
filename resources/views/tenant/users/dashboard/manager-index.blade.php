@extends('layouts/layoutMaster')

@section('title', 'Team Manager Dashboard | Hi-Tech HR')

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/animate-css/animate.scss',
  'resources/assets/vendor/scss/pages/hitech-portal.scss'
])
<style>
    :root {
        --keka-primary: #004D54;
        --keka-secondary: #008080;
        --keka-bg: #f4f7f7;
    }
    .keka-wrapper {
        font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
        background: var(--keka-bg);
        margin: -1.5rem;
        padding: 1.5rem;
        min-height: 100vh;
    }
    /* Hero / Action Bar */
    .keka-hero {
        background: white;
        border-radius: 16px;
        padding: 2rem;
        border: 1px solid #e2e8f0;
        margin-bottom: 2rem;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    }
    .keka-greeting {
        font-size: 1.5rem;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 0.25rem;
    }
    /* Tab System */
    .keka-tabs {
        display: flex;
        gap: 2rem;
        border-bottom: 1px solid #e2e8f0;
        margin-bottom: 2rem;
    }
    .keka-tab {
        padding: 0.75rem 0.25rem;
        font-weight: 700;
        color: #64748b;
        cursor: pointer;
        position: relative;
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 0.05em;
    }
    .keka-tab.active {
        color: var(--keka-primary);
    }
    .keka-tab.active::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 0;
        width: 100%;
        height: 3px;
        background: var(--keka-primary);
        border-radius: 3px 3px 0 0;
    }

    /* Grid Layout */
    .keka-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1.25rem;
        margin-bottom: 2rem;
    }
    .keka-card-stat {
        background: white;
        border-radius: 12px;
        padding: 1.25rem;
        border: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .stat-val { font-size: 1.75rem; font-weight: 800; color: #1e293b; line-height: 1; }
    .stat-tit { font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-top: 0.5rem; }

    /* Info Sections */
    .keka-section-title {
        font-size: 1rem;
        font-weight: 800;
        color: #334155;
        margin-bottom: 1.25rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .keka-panel {
        background: white;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        height: 100%;
    }
    
    /* Hover Effects */
    .hover-grow { transition: transform 0.2s ease; cursor: pointer; }
    .hover-grow:hover { transform: scale(1.02); }

    /* Celebration Items */
    .celeb-card {
        padding: 1rem;
        border-radius: 12px;
        background: #f8fafc;
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        border: 1px solid #f1f5f9;
        transition: all 0.2s;
    }
    .celeb-card:hover { background: white; border-color: #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    .celeb-icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
</style>
@endsection

@section('content')
<div class="keka-wrapper">
    
    <!-- Hero / Top Bar -->
    <div class="keka-hero d-flex align-items-center justify-content-between animate__animated animate__fadeIn">
        <div>
            <div class="keka-greeting">Good {{ now()->hour < 12 ? 'Morning' : (now()->hour < 17 ? 'Afternoon' : 'Evening') }}, {{ auth()->user()->first_name }}!</div>
            <div class="text-muted fw-bold small"><i class="bx bx-calendar-event me-1"></i> Today is {{ now()->format('l, jS F Y') }}</div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#onboardingInviteModal">
                <i class="bx bx-user-plus me-1"></i> Add Member
            </button>
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-icon rounded-circle" data-bs-toggle="dropdown"><i class="bx bx-dots-vertical-rounded"></i></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="{{ route('reports.index') }}">Team Reports</a>
                    <a class="dropdown-item" href="{{ route('settings.index') }}">Settings</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="keka-tabs animate__animated animate__fadeIn">
        <div class="keka-tab active">Overview</div>
        <div class="keka-tab" onclick="window.location.href='{{ route('attendance.index') }}'">Team Tracker</div>
        <div class="keka-tab" onclick="window.location.href='{{ route('approvals.index') }}'">Approvals <span class="badge bg-danger ms-1" style="font-size: 0.6rem;">{{ $pendingLeaveRequests + $pendingExpenseRequests }}</span></div>
        <div class="keka-tab">Org Directory</div>
    </div>

    <!-- Stats Grid -->
    <div class="keka-grid animate__animated animate__fadeInUp">
        <div class="keka-card-stat border-start border-4 border-info">
            <div class="stat-val">{{ $activeEmployees }}</div>
            <div class="stat-tit">Team Members</div>
        </div>
        <div class="keka-card-stat border-start border-4 border-success">
            <div class="stat-val text-success">{{ $todayPresentUsers }}</div>
            <div class="stat-tit">Clocked In</div>
        </div>
        <div class="keka-card-stat border-start border-4 border-warning">
            <div class="stat-val text-warning">{{ $todayOnLeaveCount }}</div>
            <div class="stat-tit">On Leave</div>
        </div>
        <div class="keka-card-stat border-start border-4 border-danger">
            <div class="stat-val text-danger">{{ $todayAbsentUsers }}</div>
            <div class="stat-tit">Not Logged In</div>
        </div>
    </div>

    <div class="row g-4 mb-5">
        <!-- Main Column: Team Management -->
        <div class="col-lg-8 animate__animated animate__fadeInLeft">
            
            <!-- REQUIRED APPROVALS -->
            <div class="keka-panel mb-4 shadow-sm border-0">
                <div class="p-4 border-bottom d-flex align-items-center justify-content-between">
                    <div class="keka-section-title mb-0"><i class="bx bx-check-shield text-primary"></i> Pending Approvals</div>
                    <a href="{{ route('approvals.index') }}" class="small fw-bold text-primary">Manage All <i class="bx bx-right-arrow-alt"></i></a>
                </div>
                <div class="p-0">
                    <div class="list-group list-group-flush">
                        <a href="{{ route('leaveRequests.index') }}" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between p-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon-wrap icon-teal" style="width:34px; height:34px;"><i class="bx bx-calendar"></i></div>
                                <div>
                                    <div class="fw-bold text-dark">Leave Requests</div>
                                    <div class="small text-muted">Awaiting your approval</div>
                                </div>
                            </div>
                            <span class="badge bg-teal rounded-pill px-3">{{ $pendingLeaveRequests }}</span>
                        </a>
                        <a href="{{ route('expenseRequests.index') }}" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between p-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon-wrap icon-amber" style="width:34px; height:34px;"><i class="bx bx-wallet"></i></div>
                                <div>
                                    <div class="fw-bold text-dark">Expense Invoices</div>
                                    <div class="small text-muted">Reimbursement claims</div>
                                </div>
                            </div>
                            <span class="badge bg-warning rounded-pill px-3">{{ $pendingExpenseRequests }}</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- TEAM AT A GLANCE -->
            <div class="keka-panel shadow-sm border-0">
                <div class="p-4 border-bottom">
                    <div class="keka-section-title mb-0"><i class="bx bx-group text-info"></i> Team At A Glance</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 text-muted small fw-bold">EMPLOYEE</th>
                                <th class="text-muted small fw-bold">DESIGNATION</th>
                                <th class="text-muted small fw-bold text-end pe-4">STATUS</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($teamOutToday as $req)
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar avatar-sm">
                                            <span class="avatar-initial rounded-circle bg-label-teal">{{ $req->user->getInitials() }}</span>
                                        </div>
                                        <div class="fw-bold text-dark">{{ $req->user->full_name }}</div>
                                    </div>
                                </td>
                                <td><span class="small fw-bold text-muted">{{ $req->user->designation->name ?? 'Staff' }}</span></td>
                                <td class="text-end pe-4">
                                    <span class="badge bg-label-warning rounded-pill px-3">On Leave</span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center py-5">
                                    <img src="{{ asset('assets/img/illustrations/page-pricing-enterprise.png') }}" width="120" class="mb-3 opacity-25">
                                    <div class="text-muted fw-bold">Your entire team is available today! 🚀</div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- Sidebar: Celebrations & Organization -->
        <div class="col-lg-4 animate__animated animate__fadeInRight">
            
            <!-- WHOLE ORGANIZATION CELEBRATIONS -->
            <div class="keka-panel shadow-sm border-0 mb-4">
                <div class="p-4 border-bottom bg-primary bg-opacity-10">
                    <div class="keka-section-title mb-0 text-primary"><i class="bx bx-cake"></i> Organization Hub</div>
                    <small class="text-muted fw-bold">Birthdays & Anniversaries</small>
                </div>
                <div class="p-4">
                    <h6 class="text-uppercase small fw-extrabold text-muted mb-3 opacity-75">Upcoming Birthdays</h6>
                    @forelse($orgBirthdays as $user)
                    <div class="celeb-card">
                        <div class="celeb-icon bg-label-warning"><i class="bx bx-party"></i></div>
                        <div class="flex-grow-1">
                            <div class="fw-bold text-dark small">{{ $user->full_name }}</div>
                            <div class="small text-muted">{{ \Carbon\Carbon::parse($user->dob)->format('M d') }} ({{ $user->department->name ?? 'Admin' }})</div>
                        </div>
                        @if(\Carbon\Carbon::parse($user->dob)->format('md') == now()->format('md'))
                            <span class="badge bg-danger pulse">TODAY</span>
                        @endif
                    </div>
                    @empty
                    <div class="text-center py-3 text-muted small">No upcoming birthdays</div>
                    @endforelse

                    <h6 class="text-uppercase small fw-extrabold text-muted mb-3 mt-4 opacity-75">Work Anniversaries</h6>
                    @forelse($orgAnniversaries as $user)
                    <div class="celeb-card" style="background: rgba(0, 77, 84, 0.03);">
                        <div class="celeb-icon bg-label-info"><i class="bx bx-award"></i></div>
                        <div class="flex-grow-1">
                            <div class="fw-bold text-dark small">{{ $user->full_name }}</div>
                            <div class="small text-muted">{{ \Carbon\Carbon::parse($user->date_of_joining)->format('M d') }} • {{ now()->year - \Carbon\Carbon::parse($user->date_of_joining)->year }} Years</div>
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-3 text-muted small">No upcoming anniversaries</div>
                    @endforelse
                </div>
            </div>

            <!-- ANNOUNCEMENTS -->
            <div class="keka-panel shadow-sm border-0">
                <div class="p-4 border-bottom">
                    <div class="keka-section-title mb-0"><i class="bx bx-news"></i> Announcements</div>
                </div>
                <div class="p-4">
                    @forelse($recentNotices as $notice)
                        <div class="mb-4">
                            <div class="fw-bold text-dark small">{{ $notice->title }}</div>
                            <div class="small text-muted mb-2">{{ $notice->created_at?->diffForHumans() }}</div>
                            <div class="small text-muted lh-base">{{ \Illuminate\Support\Str::limit($notice->description, 80) }}</div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted small">No recent announcements</div>
                    @endforelse
                </div>
            </div>

        </div>
    </div>
</div>

{{-- MODALS --}}
@include('tenant.employees.onboarding_invite_modal')

@endsection
