@extends('layouts/layoutMaster')

@section('title', 'My Attendance')

@section('vendor-style')
@vite(['resources/assets/vendor/libs/animate-css/animate.scss'])
@vite(['resources/assets/vendor/scss/pages/hitech-portal.scss'])
@endsection

@section('content')



<div class="container-xxl flex-grow-1 container-p-y">
    
    {{-- HERO SECTION --}}
    <div class="attendance-hero animate__animated animate__fadeIn">
        <div class="attendance-hero-text">
            <div class="greeting">Attendance Tracking</div>
            <div class="sub-text">Monitor your working hours and daily attendance logs.</div>
        </div>
        <div>
            <div class="text-white text-end">
                <div style="font-size:0.75rem; font-weight:700; opacity:0.7; text-transform:uppercase;">Today's Date</div>
                <div style="font-size:1.25rem; font-weight:800;">{{ now()->format('l, d M') }}</div>
            </div>
        </div>
    </div>

    {{-- STATS SECTION --}}
    <div class="row g-4 mb-6">
        <div class="col-sm-6 col-lg-3 animate__animated animate__fadeInUp" style="animation-delay: 0.15s">
            <div class="hitech-stat-card">
                <div class="stat-icon-wrap icon-teal"><i class="bx bx-check-double"></i></div>
                <div class="stat-label">Present Days</div>
                <div class="stat-value">{{ $presentDays }}</div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3 animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
            <div class="hitech-stat-card">
                <div class="stat-icon-wrap icon-blue"><i class="bx bx-time"></i></div>
                <div class="stat-label">Avg. Work Hours</div>
                <div class="stat-value">{{ $avgHours }}h</div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3 animate__animated animate__fadeInUp" style="animation-delay: 0.25s">
            <div class="hitech-stat-card">
                <div class="stat-icon-wrap icon-amber"><i class="bx bx-calendar-exclamation"></i></div>
                <div class="stat-label">Late / Short Leave</div>
                <div class="stat-value">{{ $lateDays }}</div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3 animate__animated animate__fadeInUp" style="animation-delay: 0.3s">
            <div class="hitech-stat-card">
                <div class="stat-icon-wrap icon-red"><i class="bx bx-calendar-x"></i></div>
                <div class="stat-label">Absences</div>
                <div class="stat-value">{{ $absentDays }}</div>
            </div>
        </div>
    </div>

    {{-- MASTER HORIZONTAL TOOLBAR --}}
    <div class="hitech-card animate__animated animate__fadeInUp" style="animation-delay: 0.35s">
        <div class="hitech-card-header border-bottom p-2 px-3">
            <div class="d-flex flex-wrap align-items-center gap-2 w-100" id="masterToolbar">
                {{-- 1. View Tabs --}}
                <div class="segmented-control-hitech-wrapper me-1">
                    <ul class="nav nav-pills segmented-control-hitech" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" id="list-view-tab" data-bs-toggle="tab" data-bs-target="#listViewTab">
                                <i class="bx bx-list-ul me-1"></i> List VIEW
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="calendar-view-tab" data-bs-toggle="tab" data-bs-target="#calendarViewTab">
                                <i class="bx bx-calendar me-1"></i> Monthly CALENDAR
                            </button>
                        </li>
                    </ul>
                </div>

                {{-- 2. Unified Navigation & Filter Group --}}
                <div class="d-flex align-items-center gap-2">
                    <div class="calendar-nav-wrap-mini d-flex align-items-center bg-light bg-opacity-50 p-1 px-2 rounded-3 border">
                        <button type="button" class="btn btn-icon btn-sm btn-hitech-nav-mini prev-month"><i class="bx bx-chevron-left"></i></button>
                        <span id="currentCalendarMonth" class="fw-bold mx-2 text-dark" style="font-size: 0.85rem; min-width: 90px; text-align: center;">
                            {{ \Carbon\Carbon::create($year, $month, 1)->format('M Y') }}
                        </span>
                        <button type="button" class="btn btn-icon btn-sm btn-hitech-nav-mini next-month"><i class="bx bx-chevron-right"></i></button>
                    </div>

                    <div class="year-select-wrap-mini">
                        <select id="yearSelect" class="form-select form-select-sm filter-item-hitech-ghost py-0" style="width: 75px; height: 34px !important;">
                            @for($y = now()->year; $y >= now()->year - 2; $y--)
                                <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                            @endfor
                        </select>
                    </div>

                    <div class="d-flex align-items-center gap-1 ms-1">
                        <a href="{{ route('user.attendance.index', ['filter' => 'today']) }}" class="btn btn-label-teal hitech-btn-sm-compact px-2" title="Today">
                            Today
                        </a>
                        <a href="{{ route('user.attendance.index', ['filter' => 'this_week']) }}" class="btn btn-label-secondary hitech-btn-sm-compact px-2 d-none d-md-flex" title="This Week">
                            Week
                        </a>
                    </div>
                </div>

                <div class="calendar-only-controls d-none align-items-center gap-2 ms-1 border-start ps-3">
                    <div class="calendar-legend-mini d-none d-xl-flex gap-2">
                        <span class="l-dot bg-teal" title="Present"></span>
                        <span class="l-dot bg-orange" title="Late"></span>
                        <span class="l-dot bg-red" title="Absent"></span>
                        <span class="l-dot bg-indigo" title="Leave"></span>
                    </div>
                </div>

                {{-- 4. Options (Right) --}}
                <div class="ms-auto d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-label-secondary hitech-btn-sm-compact px-2" title="Export as Excel">
                        <i class="bx bx-download"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="tab-content border-0 p-0">
            {{-- LIST VIEW --}}
            <div class="tab-pane fade show active" id="listViewTab" role="tabpanel">
                <div class="card-body p-0">
            <div class="table-responsive text-nowrap">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Working Hours</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($attendances as $attendance)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon-wrap icon-teal me-3 mb-0" style="width:32px; height:32px; font-size:0.9rem;">
                                        <i class="bx bx-calendar-event"></i>
                                    </div>
                                    <span class="fw-bold text-dark">{{ $attendance->created_at->format('D, d M Y') }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="text-dark fw-semibold">
                                    <i class="bx bx-log-in-circle text-success i-fw"></i>
                                    {{ $attendance->check_in_time ? $attendance->check_in_time->format('h:i A') : '--:--' }}
                                </div>
                            </td>
                            <td>
                                <div class="text-dark fw-semibold">
                                    <i class="bx bx-log-out-circle text-danger i-fw"></i>
                                    {{ $attendance->check_out_time ? $attendance->check_out_time->format('h:i A') : '--:--' }}
                                </div>
                            </td>
                            <td>
                                @if($attendance->check_in_time && $attendance->check_out_time)
                                    @php
                                        $duration = $attendance->check_in_time->diff($attendance->check_out_time);
                                        $hours = $duration->h + ($duration->i / 60);
                                        $barWidth = min(100, ($hours / 9) * 100);
                                    @endphp
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-bold text-primary">{{ $duration->format('%H:%I') }}</span>
                                        <div class="progress w-px-75" style="height: 4px;">
                                            <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $barWidth }}%"></div>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-muted">--:--</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $ds = strtolower($attendance->dynamic_status);
                                    $bgClass = 'success';
                                    $label = 'Present';
                                    if ($ds === 'absent') { $bgClass = 'danger'; $label = 'Absent'; }
                                    elseif ($ds === 'late') { $bgClass = 'warning'; $label = 'Late'; }
                                    elseif ($ds === 'half-day') { $bgClass = 'warning'; $label = 'Half Day'; }
                                    elseif ($ds === 'leave' || $ds === 'on_leave') { $bgClass = 'info'; $label = 'On Leave'; }
                                    elseif ($ds === 'work_from_home' || $ds === 'wfh') { $bgClass = 'primary'; $label = 'WFH'; }
                                    
                                    $adminBadge = $attendance->admin_reason ? '<i class="bx bxs-edit-alt ms-1 text-white opacity-75" title="Manual Adjustment"></i>' : '';
                                @endphp
                                <span class="badge badge-hitech bg-label-{{ $bgClass }}">
                                    <i class="bx bxs-circle me-1" style="font-size:0.5rem;"></i>
                                    {{ $label }} {!! $adminBadge !!}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="bx bx-info-circle fs-2 d-block mb-2 opacity-50"></i>
                                No attendance logs found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div> {{-- table-responsive --}}
        </div> {{-- card-body --}}
    </div> {{-- listViewTab --}}
    
            <div class="tab-pane fade" id="calendarViewTab" role="tabpanel">
                <div class="card-body bg-calendar-grid p-3 p-md-4">
                    <div class="calendar-grid-header">
                        <div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div><div>Sun</div>
                    </div>
                    <div id="attendanceCalendarGrid" class="calendar-grid">
                        {{-- Days will be injected here --}}
                        <div class="text-center p-5 w-100 grid-full-width">
                            <div class="spinner-grow text-primary" role="status" style="width: 3rem; height: 3rem;"></div>
                            <p class="mt-3 text-muted fw-bold">Synchronizing your attendance logs...</p>
                        </div>
                    </div>
                </div>
            </div>
</div> {{-- tab-content --}}
</div> {{-- hitech-card --}}
</div> {{-- container-xxl --}}

@endsection

@section('page-script')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const filterSelect = document.getElementById('filterSelect');
        const customDateRanges = document.querySelectorAll('.custom-date-range');

        // Initial check on load
        if (filterSelect && filterSelect.value === 'custom') {
            customDateRanges.forEach(el => el.classList.remove('d-none'));
        }

        if (filterSelect) {
            filterSelect.addEventListener('change', function() {
               if (this.value === 'custom') {
                   customDateRanges.forEach(el => el.classList.remove('d-none'));
               } else {
                   customDateRanges.forEach(el => el.classList.add('d-none'));
               }
            });
        }

        // --- CALENDAR & UNIFIED NAVIGATION ---
        let currentMonth = {{ $month }};
        let currentYear = {{ $year }};

        const calendarGrid = document.getElementById('attendanceCalendarGrid');
        const monthTitle = document.getElementById('currentCalendarMonth');
        const yearSelect = document.getElementById('yearSelect');

        function fetchCalendarData(month, year) {
            if (!calendarGrid) return;
            calendarGrid.innerHTML = `
                <div class="text-center p-5 w-100 grid-full-width">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted small fw-bold">Syncing attendance logs...</p>
                </div>
            `;

            fetch(`{{ route('user.attendance.registry') }}?month=${month}&year=${year}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderCalendar(data);
                    }
                })
                .catch(error => {
                    console.error('Error fetching calendar data:', error);
                    calendarGrid.innerHTML = `<div class="alert alert-danger m-3">Failed to load data.</div>`;
                });
        }

        function renderCalendar(data) {
            monthTitle.textContent = `${data.monthName.substring(0, 3)} ${data.year}`;
            calendarGrid.innerHTML = '';
            // (existing render logic remains similar, but using dynamic markers)
            const firstDay = new Date(data.year, data.month - 1, 1).getDay();
            const startOffset = (firstDay === 0 ? 6 : firstDay - 1);

            for (let i = 0; i < startOffset; i++) {
                const emptyCell = document.createElement('div');
                emptyCell.className = 'calendar-day empty';
                calendarGrid.appendChild(emptyCell);
            }

            Object.values(data.calendar).forEach(day => {
                const dayCell = document.createElement('div');
                dayCell.className = `calendar-day ${day.class} animate__animated animate__fadeIn`;
                
                let dayContent = '';
                if (day.in) {
                    dayContent = `
                        <div class="day-in-out">
                            <span class="time-block"><i class="bx bx-down-arrow-alt text-success"></i> ${day.in}</span>
                            <span class="time-block"><i class="bx bx-up-arrow-alt text-danger"></i> ${day.out || '--:--'}</span>
                        </div>
                    `;
                } else if (day.status === 'Leave' || day.status === 'Holiday') {
                    dayContent = `<div class="day-status-label">${day.holiday_name || day.status}</div>`;
                } else if (day.status === 'Scheduled') {
                    dayContent = `<div class="text-center py-2"><i class="bx bx-calendar-event opacity-25" style="font-size: 1.2rem;"></i></div>`;
                } else if (day.status === 'Today' && !day.in) {
                    dayContent = `<div class="text-center py-2 animate__animated animate__pulse animate__infinite"><span class="badge bg-label-teal">Clock In Now</span></div>`;
                }

                dayCell.innerHTML = `
                    <div class="day-top">
                        <span class="day-number">${day.day}</span>
                        <div class="status-marker ${day.class.split(' ')[0]}"></div>
                    </div>
                    <div class="day-info">
                        <div class="status-pill-text">${day.status}</div>
                        ${dayContent}
                    </div>
                `;
                calendarGrid.appendChild(dayCell);
            });
        }

        // UNIFIED NAVIGATION LOGIC
        function updateNavigation(newMonth, newYear) {
            const currentTab = document.querySelector('.segmented-control-hitech .nav-link.active').id;
            
            if (currentTab === 'calendar-view-tab') {
                // Calendar View: AJAX Update
                currentMonth = newMonth;
                currentYear = newYear;
                fetchCalendarData(currentMonth, currentYear);
            } else {
                // List View: Page Reload with params
                window.location.href = `{{ route('user.attendance.index') }}?month=${newMonth}&year=${newYear}`;
            }
        }

        document.querySelector('.prev-month').addEventListener('click', function() {
            let m = currentMonth - 1;
            let y = currentYear;
            if (m < 1) { m = 12; y--; }
            updateNavigation(m, y);
        });

        document.querySelector('.next-month').addEventListener('click', function() {
            let m = currentMonth + 1;
            let y = currentYear;
            if (m > 12) { m = 1; y++; }
            updateNavigation(m, y);
        });

        yearSelect.addEventListener('change', function() {
            updateNavigation(currentMonth, this.value);
        });

        // Tab Context Toggle
        const listTab = document.getElementById('list-view-tab');
        const calendarTab = document.getElementById('calendar-view-tab');
        const calendarControls = document.querySelector('.calendar-only-controls');

        listTab.addEventListener('shown.bs.tab', function () {
            calendarControls.classList.add('d-none');
        });

        calendarTab.addEventListener('shown.bs.tab', function () {
            calendarControls.classList.remove('d-none');
            calendarControls.classList.add('d-flex');
            fetchCalendarData(currentMonth, currentYear);
        });

        // Initialize display for current state
        if (calendarTab.classList.contains('active')) {
            calendarControls.classList.remove('d-none');
            calendarControls.classList.add('d-flex');
            fetchCalendarData(currentMonth, currentYear);
        }
    });
</script>

<style>
    /* PREMIUM TABS SEGMENTED CONTROL */
    .segmented-control-hitech-wrapper {
        background: #f1f5f9;
        padding: 5px;
        border-radius: 50px;
        border: 1px solid #e2e8f0;
    }
    .segmented-control-hitech.nav-pills {
        gap: 0;
    }
    .segmented-control-hitech .nav-link {
        font-weight: 800;
        text-transform: uppercase;
        font-size: 0.65rem;
        letter-spacing: 1px;
        padding: 10px 20px;
        border-radius: 50px;
        color: #64748b;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: none;
    }
    .segmented-control-hitech .nav-link.active {
        background-color: var(--bs-primary) !important;
        color: #fff !important;
        box-shadow: 0 4px 12px rgba(18, 116, 100, 0.25);
    }
    .segmented-control-hitech .nav-link:hover:not(.active) {
        background: rgba(18, 116, 100, 0.05);
        color: var(--bs-primary);
    }

    /* CALENDAR GRID OVERHAUL */
    .bg-calendar-grid {
        background-color: #fcfdfe;
        background-image: radial-gradient(#e2e8f0 0.5px, transparent 0.5px);
        background-size: 20px 20px;
    }

    .calendar-nav-wrap {
        display: flex;
        align-items: center;
        gap: 1.5rem;
        background: #ffffff;
        padding: 8px 24px;
        border-radius: 50px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        border: 1px solid #f1f5f9;
    }

    .btn-hitech-nav {
        background: #f8fafc !important;
        border: 1px solid #e2e8f0 !important;
        color: #475569 !important;
        border-radius: 50% !important;
        transition: all 0.2s;
    }
    .btn-hitech-nav:hover {
        background: var(--bs-primary) !important;
        color: #fff !important;
        transform: scale(1.1);
        border-color: var(--bs-primary) !important;
    }

    .calendar-current-display {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .calendar-legend-hitech {
        background: rgba(255,255,255,0.8);
        backdrop-filter: blur(5px);
        padding: 8px 20px;
        border-radius: 50px;
        border: 1px solid rgba(226, 232, 240, 0.5);
        gap: 20px;
    }
    .legend-item {
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
        color: #64748b;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .year-select-hitech {
        background: #ffffff;
        border-radius: 50px !important;
        padding-left: 15px;
        font-weight: 700;
        border: 1px solid #e2e8f0;
    }

    /* CALENDAR DAYS ELITE STYLING */
    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 15px;
    }
    .calendar-grid-header {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 15px;
        margin-bottom: 15px;
        text-align: center;
    }
    .calendar-grid-header div {
        font-size: 0.75rem;
        font-weight: 900;
        text-transform: uppercase;
        color: #94a3b8;
        letter-spacing: 1.5px;
    }

    .calendar-day {
        min-height: 140px;
        padding: 16px;
        border-radius: 24px;
        background: #ffffff;
        border: 1px solid #f1f5f9;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        position: relative;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0,0,0,0.02);
    }

    .calendar-day:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: 0 20px 40px rgba(0,0,0,0.08);
        z-index: 5;
    }

    .calendar-day.empty {
        background: rgba(248, 250, 252, 0.4) !important;
        border: 1px dashed #e2e8f0;
        box-shadow: none;
    }

    .day-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }
    .day-number {
        font-size: 1.4rem;
        font-weight: 900;
        color: #1e293b;
        opacity: 0.3;
        line-height: 1;
        transition: all 0.3s;
    }
    .calendar-day:hover .day-number {
        opacity: 1;
        color: var(--bs-primary);
    }

    .status-marker {
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }

    .status-pill-text {
        font-size: 0.6rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 10px;
        opacity: 0.7;
    }

    .day-in-out {
        display: flex;
        flex-direction: column;
        gap: 4px;
        margin-bottom: 8px;
    }
    .time-block {
        font-size: 0.75rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 4px;
        color: #334155;
    }
    .day-duration-badge {
        font-size: 0.8rem;
        color: #1e293b;
        text-align: right;
        margin-top: auto;
    }

    .day-status-label {
        font-size: 0.65rem;
        font-weight: 700;
        line-height: 1.3;
        padding: 5px 10px;
        background: rgba(255,255,255,0.25);
        backdrop-filter: blur(4px);
        border-radius: 8px;
        margin-top: 10px;
    }

    .day-missing-dash {
        font-size: 1.5rem;
        color: #cbd5e1;
        text-align: center;
        font-weight: 300;
    }

    .border-dashed {
        border-style: dashed !important;
        border-width: 2px !important;
    }

    .opacity-50 {
        opacity: 0.5;
    }

    .text-primary, .border-primary, .bg-label-primary { color: #127464 !important; border-color: #127464 !important; background-color: rgba(18, 116, 100, 0.1) !important; }

    /* STATUS COLORS - VIBRANT & GLASS */
    .bg-teal { background: linear-gradient(135deg, #0d9488 0%, #14b8a6 100%) !important; color: white !important; border: none !important; }
    .bg-teal .day-number, .bg-teal .status-pill-text, .bg-teal .time-block { color: white !important; opacity: 0.9; }
    
    .bg-orange { background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%) !important; color: white !important; border: none !important; }
    .bg-orange .day-number, .bg-orange .status-pill-text, .bg-orange .time-block { color: white !important; opacity: 0.9; }

    .bg-red { background: linear-gradient(135deg, #ef4444 0%, #f87171 100%) !important; color: white !important; border: none !important; }
    .bg-red .day-number, .bg-red .status-pill-text, .bg-red .time-block { color: white !important; opacity: 0.9; }

    .bg-indigo-vibrant, .bg-indigo { background: linear-gradient(135deg, #127464 0%, #14b8a6 100%) !important; color: white !important; border: none !important; }
    .bg-primary-hitech { background: linear-gradient(135deg, #127464 0%, #14b8a6 100%) !important; color: white !important; }
    .text-primary-hitech { color: #127464 !important; }
    .bg-purple-vibrant, .bg-purple { background: linear-gradient(135deg, #8b5cf6 0%, #a78bfa 100%) !important; color: white !important; border: none !important; }
    
    .bg-light.bg-opacity-50 {
        background-color: #ffffff !important;
        border: 1px solid #f1f5f9 !important;
    }

    .bg-secondary.bg-opacity-10 {
        background: #f8fafc !important;
        border: 1px solid #e2e8f0 !important;
        color: #94a3b8 !important;
    }
    .bg-secondary.bg-opacity-10 .day-number { color: #cbd5e1 !important; }
    .bg-secondary.bg-opacity-10 .status-pill-text { color: #94a3b8 !important; }

    @media (max-width: 1200px) {
        #masterToolbar {
            flex-wrap: nowrap !important;
            overflow-x: auto;
            overflow-y: hidden;
            padding-bottom: 8px;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }
        #masterToolbar::-webkit-scrollbar { height: 4px; }
        #masterToolbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    }
    @media (max-width: 576px) {
        .calendar-grid { grid-template-columns: repeat(2, 1fr); }
        .calendar-nav-wrap { gap: 0.5rem; padding: 8px 12px; }
        #currentCalendarMonth { font-size: 0.8rem !important; min-width: 70px !important; }
    }

    .text-teal { color: #0d9488 !important; }
    .text-warning { color: #f59e0b !important; }
    .text-danger { color: #ef4444 !important; }
    .text-indigo { color: #6366f1 !important; }

    .grid-full-width {
        grid-column: 1 / -1;
    }

    /* COMPACT FILTER COMPONENTS */
    .filter-item-hitech-ghost {
        background-color: transparent !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 12px !important;
        font-size: 0.75rem !important;
        font-weight: 700 !important;
        padding: 5px 12px !important;
        height: 38px !important;
        color: #475569 !important;
        transition: all 0.2s;
    }
    .filter-item-hitech-ghost:hover {
        background-color: #fff !important;
        border-color: #cbd5e1 !important;
    }
    .filter-item-hitech-ghost:focus {
        background-color: #fff !important;
        border-color: var(--bs-primary) !important;
        box-shadow: 0 0 0 3px rgba(18, 116, 100, 0.1) !important;
    }

    .hitech-btn-sm-compact {
        height: 38px;
        padding: 0 16px;
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
        border-radius: 12px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    /* MINI CALENDAR CONTROLS */
    .calendar-nav-wrap-mini {
        display: flex;
        align-items: center;
        background: #f8fafc;
        padding: 4px 8px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
    }
    .btn-hitech-nav-mini {
        width: 24px;
        height: 24px;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        color: #64748b;
        transition: all 0.2s;
    }
    .btn-hitech-nav-mini.next-month, .btn-hitech-nav-mini.prev-month {
        background: #127464 !important;
        color: white !important;
        border-color: #127464 !important;
    }
    .btn-hitech-nav-mini:hover {
        background: #14b8a6 !important;
        color: white !important;
        border-color: #14b8a6 !important;
    }
    .btn-label-teal, .bg-label-teal { 
        background-color: rgba(18, 116, 100, 0.1) !important;
        color: #127464 !important;
        border: 1px solid rgba(18, 116, 100, 0.2) !important;
    }
    .btn-label-teal:hover, .bg-label-teal:hover {
        background-color: #127464 !important;
        color: #fff !important;
    }
    .segmented-control-hitech .nav-link.active {
        background: #127464 !important;
        color: #fff !important;
        box-shadow: 0 4px 12px rgba(18, 116, 100, 0.3) !important;
    }
    .l-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
    }
    .x-small { font-size: 0.65rem; }

    /* SPIN FIX */
    .spinner-grow {
        display: inline-block;
        width: 3rem;
        height: 3rem;
        vertical-align: text-bottom;
        background-color: var(--bs-primary);
        border-radius: 50%;
        opacity: 0;
        animation: spinner-grow .75s linear infinite;
    }
    @keyframes spinner-grow {
      0% { transform: scale(0); }
      50% { opacity: 1; transform: none; }
    }
</style>
@endsection
