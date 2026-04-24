@extends('layouts/layoutMaster')

@section('title', 'Manager Dashboard | Hi-Tech HR')

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/animate-css/animate.scss',
  'resources/assets/vendor/scss/pages/hitech-portal.scss'
])
<style>
    /* HITECH GLOBAL THEME OVERRIDES */
    .manager-wrapper {
        padding: 1.5rem;
        background: #f8fafc;
        min-height: 100vh;
    }
    
    .hitech-card {
        border-radius: 20px !important;
        border: 1px solid #e2e8f0 !important;
        background: #ffffff !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03) !important;
        transition: all 0.3s ease;
    }
    .hitech-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.06) !important;
    }

    /* Hero Styling (Matching HR) */
    .manager-hero {
        background: linear-gradient(135deg, #004D4D 0%, #008080 100%);
        border-radius: 20px;
        padding: 2.5rem;
        color: white;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
    }
    .manager-hero::after {
        content: '';
        position: absolute;
        top: -50px;
        right: -50px;
        width: 150px;
        height: 150px;
        background: rgba(255,255,255,0.1);
        border-radius: 50%;
    }

    /* Tab Switcher */
    .theme-tabs {
        display: flex;
        gap: 1.5rem;
        margin-bottom: 2rem;
        border-bottom: 2px solid #e2e8f0;
    }
    .theme-tab {
        padding: 0.75rem 0.5rem;
        font-weight: 700;
        color: #64748b;
        cursor: pointer;
        position: relative;
        font-size: 0.9rem;
        transition: all 0.2s;
    }
    .theme-tab.active { color: #004D4D; }
    .theme-tab.active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 100%;
        height: 3px;
        background: #004D4D;
        border-radius: 3px;
    }

    /* Stat Cards */
    .card-stat-inner {
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .stat-label { font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
    .stat-value { font-size: 2rem; font-weight: 800; color: #1e293b; line-height: 1; }

    /* Celebrations */
    .celeb-item {
        padding: 1rem;
        border-radius: 15px;
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    .today-pulse {
        border-left: 4px solid #ef4444;
        background: #fff5f5;
    }
</style>
@endsection

@section('content')
<div class="manager-wrapper">
    
    <!-- Hero Section -->
    <div class="manager-hero animate__animated animate__fadeIn">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="text-white fw-extrabold mb-1">Good {{ now()->hour < 12 ? 'Morning' : (now()->hour < 17 ? 'Afternoon' : 'Evening') }}, {{ auth()->user()->first_name }}!</h2>
                <p class="text-white text-opacity-75 mb-0">Managing {{ $activeEmployees }} team members across global operations.</p>
            </div>
            <div class="col-md-4 text-md-end">
                <button class="btn btn-white text-primary fw-bold rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#onboardingInviteModal">
                    <i class="bx bx-plus-circle me-1"></i> Add Member
                </button>
            </div>
        </div>
    </div>

    <!-- Theme Tabs -->
    <div class="theme-tabs animate__animated animate__fadeIn">
        <div class="theme-tab active">Team Overview</div>
        <div class="theme-tab" onclick="window.location.href='{{ route('attendance.index') }}'">Attendance logs</div>
        <div class="theme-tab" onclick="window.location.href='{{ route('approvals.index') }}'">Pending Approvals</div>
    </div>

    <!-- Core Metrics -->
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="hitech-card animate__animated animate__fadeInUp" style="border-bottom: 4px solid #0ea5e9;">
                <div class="card-stat-inner">
                    <div class="stat-label">Total Strength</div>
                    <div class="stat-value">{{ $activeEmployees }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="hitech-card animate__animated animate__fadeInUp" style="border-bottom: 4px solid #10b981; animation-delay: 0.1s">
                <div class="card-stat-inner">
                    <div class="stat-label">Clocked In</div>
                    <div class="stat-value text-success">{{ $todayPresentUsers }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="hitech-card animate__animated animate__fadeInUp" style="border-bottom: 4px solid #f59e0b; animation-delay: 0.2s">
                <div class="card-stat-inner">
                    <div class="stat-label">On Leave</div>
                    <div class="stat-value text-warning">{{ $todayOnLeaveCount }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="hitech-card animate__animated animate__fadeInUp" style="border-bottom: 4px solid #ef4444; animation-delay: 0.3s">
                <div class="card-stat-inner">
                    <div class="stat-label">Not Sighted</div>
                    <div class="stat-value text-danger">{{ $todayAbsentUsers }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Pending Approvals Widget -->
            <div class="hitech-card mb-4">
                <div class="p-4 border-bottom d-flex align-items-center justify-content-between">
                    <h5 class="mb-0 fw-extrabold"><i class="bx bx-task text-primary me-2"></i> Action Required</h5>
                    <a href="{{ route('approvals.index') }}" class="text-primary small fw-bold">View Ledger <i class="bx bx-right-arrow-alt"></i></a>
                </div>
                <div class="p-0">
                    <div class="list-group list-group-flush">
                        <a href="{{ route('leaveRequests.index') }}" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between p-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="icon-wrap-sm bg-teal-light p-2"><i class="bx bx-calendar"></i></div>
                                <div>
                                    <h6 class="mb-0 fw-bold">Leave Requests</h6>
                                    <small class="text-muted">Awaiting your validation</small>
                                </div>
                            </div>
                            <span class="badge bg-primary rounded-pill">{{ $pendingLeaveRequests }}</span>
                        </a>
                        <a href="{{ route('expenseRequests.index') }}" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between p-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="icon-wrap-sm bg-purple-light p-2"><i class="bx bx-receipt"></i></div>
                                <div>
                                    <h6 class="mb-0 fw-bold">Expense Claims</h6>
                                    <small class="text-muted">Financial reimbursements</small>
                                </div>
                            </div>
                            <span class="badge bg-primary rounded-pill">{{ $pendingExpenseRequests }}</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Team List -->
            <div class="hitech-card">
                <div class="p-4 border-bottom">
                    <h5 class="mb-0 fw-extrabold"><i class="bx bx-group text-info me-2"></i> Team Deployment</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Staff Member</th>
                                <th>Designation</th>
                                <th class="text-end pe-4">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($teamOutToday as $req)
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar avatar-sm">
                                            <span class="avatar-initial rounded-circle bg-label-primary">{{ $req->user->getInitials() }}</span>
                                        </div>
                                        <div>
                                            <div class="fw-bold">{{ $req->user->full_name }}</div>
                                            <small class="text-muted">{{ $req->user->code }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="small fw-bold text-muted">{{ $req->user->designation->name ?? 'Staff' }}</span></td>
                                <td class="text-end pe-4">
                                    <span class="badge bg-label-warning">Away (On Leave)</span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center py-5">
                                    <div class="text-muted">All team members are present or active.</div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Organization Hub (Celebrations) -->
            <div class="hitech-card mb-4" style="background: linear-gradient(to bottom, #f0fdfa, #ffffff) !important;">
                <div class="p-4 border-bottom">
                    <h5 class="mb-0 fw-extrabold text-teal"><i class="bx bx-party me-2"></i> Organization Hub</h5>
                </div>
                <div class="p-4">
                    <!-- BIRTHDAYS -->
                    <div class="mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="text-uppercase small fw-extrabold text-muted mb-0">Birthdays</h6>
                            <i class="bx bx-cake text-warning"></i>
                        </div>
                        
                        {{-- Today's Birthdays (Show ALL) --}}
                        @foreach($todayBirthdays as $user)
                            <div class="celeb-item today-pulse">
                                <div class="avatar avatar-sm">
                                    <img src="{{ $user->profile_picture ? asset('storage/'.$user->profile_picture) : 'https://ui-avatars.com/api/?name='.urlencode($user->full_name).'&background=fecdd3&color=be123c' }}" class="rounded-circle" onerror="this.src='https://ui-avatars.com/api/?name={{ urlencode($user->full_name) }}&background=fecdd3&color=be123c'">
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-bold text-dark small">{{ $user->full_name }}</div>
                                    <div class="small text-danger fw-bold">TODAY 🎉</div>
                                </div>
                            </div>
                        @endforeach

                        {{-- Upcoming Birthdays (Show MAX 2) --}}
                        @foreach($upcomingBirthdays as $user)
                            <div class="celeb-item">
                                <div class="avatar avatar-sm">
                                    <img src="{{ $user->profile_picture ? asset('storage/'.$user->profile_picture) : 'https://ui-avatars.com/api/?name='.urlencode($user->full_name).'&background=f1f5f9&color=64748b' }}" class="rounded-circle" onerror="this.src='https://ui-avatars.com/api/?name={{ urlencode($user->full_name) }}&background=f1f5f9&color=64748b'">
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-bold text-dark small">{{ $user->full_name }}</div>
                                    <div class="small text-muted">{{ \Carbon\Carbon::parse($user->dob)->format('M d') }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- ANNIVERSARIES -->
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="text-uppercase small fw-extrabold text-muted mb-0">Work Anniversaries</h6>
                            <i class="bx bx-award text-info"></i>
                        </div>

                        {{-- Today's Anniversaries (Show ALL) --}}
                        @foreach($todayAnniversaries as $user)
                            <div class="celeb-item today-pulse">
                                <div class="avatar avatar-sm">
                                    <span class="avatar-initial rounded-circle bg-label-info">{{ $user->getInitials() }}</span>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-bold text-dark small">{{ $user->full_name }}</div>
                                    <div class="small text-info fw-bold">WORK ANNIVERSARY TODAY! 🎊</div>
                                </div>
                            </div>
                        @endforeach

                        {{-- Upcoming Anniversaries (Show MAX 2) --}}
                        @foreach($upcomingAnniversaries as $user)
                            <div class="celeb-item">
                                <div class="avatar avatar-sm">
                                    <span class="avatar-initial rounded-circle bg-label-secondary">{{ $user->getInitials() }}</span>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-bold text-dark small">{{ $user->full_name }}</div>
                                    <div class="small text-muted">{{ \Carbon\Carbon::parse($user->date_of_joining)->format('M d') }} • {{ now()->year - \Carbon\Carbon::parse($user->date_of_joining)->year }} Years</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Announcements -->
            <div class="hitech-card">
                <div class="p-4 border-bottom">
                    <h5 class="mb-0 fw-extrabold"><i class="bx bx-megaphone me-2"></i> Bulletin Board</h5>
                </div>
                <div class="p-4">
                    @forelse($recentNotices as $notice)
                        <div class="mb-4 pb-4 border-bottom">
                            <div class="fw-bold text-primary small">{{ $notice->title }}</div>
                            <div class="small text-muted mb-2 lh-base">{{ \Illuminate\Support\Str::limit($notice->description, 100) }}</div>
                            <div class="text-muted" style="font-size: 0.65rem;">{{ $notice->created_at?->diffForHumans() }}</div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted small">No active bulletins.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

</div>

@include('tenant.employees.onboarding_invite_modal')

@endsection
