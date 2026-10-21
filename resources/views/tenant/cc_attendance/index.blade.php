@extends('layouts.layoutMaster')

@section('title', 'Salesperson Attendance')

@section('page-style')
<style>
  .cc-hero {
    background: linear-gradient(135deg, #0d7377 0%, #14a085 40%, #0a9396 70%, #00b4d8 100%);
    border-radius: 18px;
    padding: 2.5rem 2.8rem;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(13, 115, 119, 0.30);
  }
  .cc-hero::before {
    content: ''; position: absolute; top: -60px; right: -60px;
    width: 260px; height: 260px; background: rgba(255,255,255,0.07); border-radius: 50%;
  }
  .cc-hero::after {
    content: ''; position: absolute; bottom: -80px; left: -40px;
    width: 200px; height: 200px; background: rgba(255,255,255,0.05); border-radius: 50%;
  }
  .cc-hero-title { font-size: 1.85rem; font-weight: 800; color: #fff; letter-spacing: -0.5px; margin-bottom: 0.3rem; }
  .cc-hero-subtitle { color: rgba(255,255,255,0.80); font-size: 0.97rem; margin-bottom: 0; }
  
  .cc-filter-bar { background: #fff; border-radius: 14px; padding: 1.2rem 1.6rem; box-shadow: 0 2px 16px rgba(0,0,0,0.07); margin-bottom: 1.5rem; }
  
  .cc-card { border-radius: 16px; border: none; box-shadow: 0 4px 24px rgba(0,0,0,0.06); }
  .cc-card .card-header { border-bottom: 1px solid rgba(0,0,0,0.05); padding: 1.5rem 1.8rem; background: transparent; }
  
  .cc-nav-pills .nav-link { font-weight: 600; padding: 0.6rem 1.2rem; border-radius: 10px; color: #5a5f71; transition: all 0.2s; }
  .cc-nav-pills .nav-link:hover { background: rgba(13, 115, 119, 0.08); color: #0d7377; }
  .cc-nav-pills .nav-link.active { background: #0d7377; color: #fff; box-shadow: 0 4px 12px rgba(13, 115, 119, 0.3); }

  .cc-summary { display:flex; gap:.6rem; flex-wrap:wrap; }
  .cc-sum { background:#fff; border:1px solid #e9eeee; border-radius:12px; padding:.55rem .9rem;
            min-width:104px; flex:1 1 0; box-shadow:0 1px 3px rgba(0,0,0,.04); }
  .cc-sum .l { display:block; font-size:.63rem; letter-spacing:.5px; text-transform:uppercase;
               color:#8a9a9a; font-weight:700; }
  .cc-sum .v { display:block; font-size:1.3rem; font-weight:800; color:#0d7377; line-height:1.3; }
  .cc-sum.ok .v { color:#15803d; }
  .cc-sum.warn .v { color:#b45309; }

  .cc-sort { color:inherit; text-decoration:none; display:inline-flex; align-items:center; gap:.25rem; white-space:nowrap; }
  .cc-sort:hover { color:#0d7377; }
  .cc-sort.on { color:#0d7377; font-weight:700; }
  .cc-caret { opacity:.35; font-size:.8rem; }
  .cc-sort.on .cc-caret { opacity:1; }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    
    <!-- Premium Hero Header -->
    <div class="cc-hero">
        <div class="position-relative" style="z-index: 2;">
            <h2 class="cc-hero-title"><i class="ti ti-calendar-event me-2"></i> Salesperson Attendance</h2>
            <p class="cc-hero-subtitle">Mark, track, and review your assigned salesperson's daily attendance records.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    {{-- Validation failures used to be invisible here: the page rendered only
         session('success') and session('error'), never $errors. A submission
         with no status ticked was rejected by the server, redirected back, and
         showed no message at all — the reported "I saved five times and
         nothing happened". --}}
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible" role="alert">
            <div class="fw-bold mb-1"><i class="ti ti-alert-triangle me-1"></i> Attendance was not saved</div>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $e)
                    <li>{{ $e === 'The attendance field is required.'
                            ? 'No attendance status was ticked. Choose P / A / HD / WFH / PS for at least one salesperson.'
                            : $e }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <ul class="nav nav-pills cc-nav-pills mb-4">
        <li class="nav-item">
            <a class="nav-link active" href="{{ route('cc-attendance.index') }}"><i class="ti ti-calendar-event me-1"></i> Daily View</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="{{ route('cc-attendance.monthly') }}"><i class="ti ti-calendar-stats me-1"></i> Monthly View</a>
        </li>
        @if($canSeeReport ?? $user->hasRole(['admin', 'hr', 'manager']))
        <li class="nav-item">
            <a class="nav-link" href="{{ route('cc-attendance.report') }}"><i class="ti ti-report-search me-1"></i> Submission Report</a>
        </li>
        @endif
        {{-- Admin/HR only: open a back-dated filling window. Same gate the
             controller enforces, so the tab never shows a page that 403s. --}}
        @if($user->hasRole(['admin', 'hr']) || $user->can('cc_attendance.manage'))
        <li class="nav-item">
            <a class="nav-link" href="{{ route('cc-attendance.unlocks') }}"><i class="ti ti-lock-open me-1"></i> Unlock Windows</a>
        </li>
        @endif
    </ul>

    @if(!empty($isLocked) && $isLocked)
        <div class="alert alert-warning d-flex align-items-center" role="alert">
            <i class="bx bx-lock-alt me-2"></i>
            <div>Attendance for <strong>{{ \Carbon\Carbon::parse($date)->format('d M Y') }}</strong> is <strong>locked</strong> —
            the filling window closed on {{ $lockDeadline->format('d M Y, h:i A') }} (each date stays open until 12:00 PM on the 2nd working day after it; Sundays and holidays don't count).
            Only Admin/HR can mark it now.</div>
        </div>
    @elseif(isset($openWindow) && $date >= $openWindow[0] && $date <= $openWindow[1] && !$user->hasRole(['admin','hr']))
        <div class="alert alert-success py-2 d-flex align-items-center" role="alert" style="font-size:.85rem;">
            <i class="ti ti-lock-open me-2"></i>
            <div><strong>July back-fill is open</strong> — you can complete attendance for any date in July 2026, including past dates. (Time restrictions resume next month.)</div>
        </div>
    @elseif(isset($lockDeadline) && !$user->hasRole(['admin','hr']))
        <div class="alert alert-info py-2 d-flex align-items-center" role="alert" style="font-size:.85rem;">
            <i class="bx bx-time-five me-2"></i>
            <div>This date can be filled until <strong>{{ $lockDeadline->format('d M Y, h:i A') }}</strong>, after which it locks (Admin/HR only). Sundays and holidays don't count towards the window.</div>
        </div>
    @endif

    <!-- Date Selection Filter -->
    <div class="card cc-filter-bar mb-4">
        <div class="card-body">
            <form action="{{ route('cc-attendance.index') }}" method="GET" class="d-flex align-items-end gap-3">
                <div>
                    <label class="form-label text-muted fw-bold" for="date" style="font-size: 0.8rem; text-transform:uppercase; letter-spacing: 0.5px;">Select Date</label>
                    <input type="date" id="date" name="date" class="form-control form-control-lg shadow-sm" value="{{ $date }}"
                           @unless($user->hasRole(['admin','hr'])) min="{{ $minDate ?? \Carbon\Carbon::today()->subDays(2)->format('Y-m-d') }}" @endunless
                           max="{{ \Carbon\Carbon::today()->format('Y-m-d') }}">
                </div>
                <div>
                    <button type="submit" class="btn btn-primary btn-lg shadow-sm px-4">Load Register</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Register summary. Counts cover the WHOLE register, not the filtered
         view, so narrowing the list never makes the totals lie. --}}
    <div class="cc-summary mb-3">
      <div class="cc-sum"><span class="l">Salespeople</span><span class="v">{{ $summary['total'] }}</span></div>
      <div class="cc-sum ok"><span class="l">Marked</span><span class="v">{{ $summary['marked'] }}</span></div>
      <div class="cc-sum warn"><span class="l">Pending</span><span class="v">{{ $summary['pending'] }}</span></div>
      <div class="cc-sum"><span class="l">Present</span><span class="v">{{ $summary['P'] }}</span></div>
      <div class="cc-sum"><span class="l">Absent</span><span class="v">{{ $summary['A'] }}</span></div>
      <div class="cc-sum"><span class="l">Half day</span><span class="v">{{ $summary['HD'] }}</span></div>
      <div class="cc-sum"><span class="l">WFH</span><span class="v">{{ $summary['WFH'] }}</span></div>
      <div class="cc-sum"><span class="l">Sun. working</span><span class="v">{{ $summary['PS'] }}</span></div>
      <div class="cc-sum"><span class="l">Holiday working</span><span class="v">{{ $summary['HP'] }}</span></div>
      <div class="cc-sum"><span class="l">On leave</span><span class="v">{{ $summary['L'] }}</span></div>
    </div>

    {{-- Find someone on the register --}}
    <form method="GET" class="card cc-filter-bar mb-3">
      <input type="hidden" name="date" value="{{ $date }}">
      <input type="hidden" name="sort" value="{{ $sort }}">
      <input type="hidden" name="dir" value="{{ $dir }}">
      <div class="card-body py-3">
        <div class="row g-2 align-items-end">
          <div class="col-lg-3 col-md-6">
            <label class="form-label small fw-bold text-muted mb-1">SEARCH</label>
            <input type="text" name="q" value="{{ $fSearch }}" class="form-control form-control-sm"
                   placeholder="Salesperson name or employee ID">
          </div>
          @if($user->hasRole(['admin','hr']))
          <div class="col-lg-2 col-md-6">
            <label class="form-label small fw-bold text-muted mb-1">CC AGENT</label>
            <select name="cc" class="form-select form-select-sm">
              <option value="">All CC agents</option>
              @foreach($ccOptions as $c)
                <option value="{{ $c }}" {{ $fCc === $c ? 'selected' : '' }}>{{ $c }}</option>
              @endforeach
            </select>
          </div>
          @endif
          <div class="col-lg-2 col-md-6">
            <label class="form-label small fw-bold text-muted mb-1">DESIGNATION</label>
            <select name="desig" class="form-select form-select-sm">
              <option value="">All designations</option>
              @foreach($desigOptions as $d)
                <option value="{{ $d }}" {{ $fDesig === $d ? 'selected' : '' }}>{{ $d }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-lg-2 col-md-6">
            <label class="form-label small fw-bold text-muted mb-1">STATUS</label>
            <select name="status" class="form-select form-select-sm">
              <option value="">All</option>
              <option value="pending" {{ $fStatus === 'pending' ? 'selected' : '' }}>Not marked yet</option>
              <option value="marked" {{ $fStatus === 'marked' ? 'selected' : '' }}>Marked</option>
              <option value="P" {{ $fStatus === 'P' ? 'selected' : '' }}>Present</option>
              <option value="A" {{ $fStatus === 'A' ? 'selected' : '' }}>Absent</option>
              <option value="HD" {{ $fStatus === 'HD' ? 'selected' : '' }}>Half day</option>
              <option value="WFH" {{ $fStatus === 'WFH' ? 'selected' : '' }}>Work from home</option>
              <option value="PS" {{ $fStatus === 'PS' ? 'selected' : '' }}>Sunday working</option>
              <option value="HP" {{ $fStatus === 'HP' ? 'selected' : '' }}>Holiday working</option>
              <option value="L" {{ $fStatus === 'L' ? 'selected' : '' }}>On leave</option>
            </select>
          </div>
          <div class="col-lg-3 col-md-6 text-end">
            <button class="btn btn-primary btn-sm px-3" type="submit"><i class="ti ti-search"></i> Search</button>
            <a href="{{ route('cc-attendance.index', ['date' => $date]) }}" class="btn btn-outline-secondary btn-sm px-3">
              <i class="ti ti-x"></i> Clear
            </a>
          </div>
        </div>
      </div>
    </form>

    <!-- Attendance Form -->
    <div class="card cc-card">
        <div class="card-header border-bottom">
            <h5 class="card-title mb-0">Attendance Register for {{ \Carbon\Carbon::parse($date)->format('d M, Y') }}</h5>
        </div>
        
        <div class="card-body mt-4">
            @if($salespeople->isEmpty())
                <div class="alert alert-warning">
                    @if($fSearch !== '' || $fCc !== '' || $fDesig !== '' || $fStatus !== '')
                        No salesperson on this register matches those filters.
                        <a href="{{ route('cc-attendance.index', ['date' => $date]) }}">Clear filters</a>.
                    @else
                        No salespeople are currently tagged to you.
                    @endif
                </div>
            @else
                {{-- id, not the action: route('cc-attendance.index') and
                     route('cc-attendance.store') are the SAME path (/cc-attendance),
                     differing only by verb. querySelector by action matched the GET
                     date-filter form first, so the client-side guard bound to the
                     wrong form and never ran on this one at all. --}}
                <form id="ccAttendanceForm" action="{{ route('cc-attendance.store') }}" method="POST" enctype="multipart/form-data" novalidate>
                    {{-- Stays on screen after a blocked submit. The old alert()
                         vanished the moment it was dismissed, which is why a
                         blocked save looked like the button did nothing. --}}
                    <div class="alert alert-warning d-none mx-3 mt-3" id="ccBlockMsg" role="alert">
                        <div class="fw-bold mb-1"><i class="ti ti-alert-triangle me-1"></i> Nothing was saved</div>
                        <div id="ccBlockBody"></div>
                    </div>
                    @csrf
                    <input type="hidden" name="date" value="{{ $date }}">
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th><a class="cc-sort {{ $sort==='code' ? 'on' : '' }}" href="{{ request()->fullUrlWithQuery(['sort'=>'code','dir'=>$sort==='code'&&$dir==='asc'?'desc':'asc']) }}">Employee ID @include('tenant.cc_attendance._caret', ['col'=>'code'])</a></th>
                                    <th><a class="cc-sort {{ $sort==='name' ? 'on' : '' }}" href="{{ request()->fullUrlWithQuery(['sort'=>'name','dir'=>$sort==='name'&&$dir==='asc'?'desc':'asc']) }}">Salesperson Name @include('tenant.cc_attendance._caret', ['col'=>'name'])</a></th>
                                    <th><a class="cc-sort {{ $sort==='desig' ? 'on' : '' }}" href="{{ request()->fullUrlWithQuery(['sort'=>'desig','dir'=>$sort==='desig'&&$dir==='asc'?'desc':'asc']) }}">Designation @include('tenant.cc_attendance._caret', ['col'=>'desig'])</a></th>
                                    <th><a class="cc-sort {{ $sort==='status' ? 'on' : '' }}" href="{{ request()->fullUrlWithQuery(['sort'=>'status','dir'=>$sort==='status'&&$dir==='asc'?'desc':'asc']) }}">Attendance Status @include('tenant.cc_attendance._caret', ['col'=>'status'])</a></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($salespeople as $emp)
                                    @php
                                        $record = $attendances->get($emp->id);
                                        // Leave over this date. A part-day leave (short / half day /
                                        // WFH) is shown and the rest of the day is marked here; a
                                        // full-day leave makes the row read-only.
                                        $dayLeave  = $dayLeaves->get($emp->id);
                                        $leaveKind = $dayLeave ? \App\Http\Controllers\tenant\CcAttendanceController::leaveKind($dayLeave) : null;
                                        $partDay   = $dayLeave && $leaveKind !== 'full';
                                        $fullLeave = $dayLeave && $leaveKind === 'full';
                                        // The leave module's placeholder row is not a mark yet.
                                        if ($partDay && $record && app(\App\Http\Controllers\tenant\CcAttendanceController::class)->isUnconfirmedLeaveRow($record, $dayLeave)) {
                                            $record = null;
                                        }
                                        $wfhApproved = $leaveKind === 'wfh' && \App\Http\Controllers\tenant\CcAttendanceController::leaveState($dayLeave) === 'approved';
                                        $currentStatus = $record ? $record->status : null;
                                        
                                        // Standardize status
                                        if ($currentStatus) {
                                            $currentStatus = strtolower(str_replace('_', '-', $currentStatus));
                                        }
                                        
                                        // Half day is one of the buttons on this very form, not an
                                        // approved leave. Listing it here locked those rows as "Not
                                        // Editable" even though the server would have accepted the
                                        // change. Kept in step with isLeaveStatus() in the controller.
                                        $isLeave = (in_array($currentStatus, ['leave', 'paid-leave', 'unpaid-leave', 'on-leave']) && !$partDay)
                                            || ($fullLeave && !$record);

                                        // Previous submissions can be corrected while the date is open
                                        // (Admin/HR always). Approved-leave rows stay read-only.
                                        $canEditRow = !$isLeave && empty($isLocked);
                                        // Pre-select the existing choice. 'Present', 'Sunday working'
                                        // and 'Holiday working' all save identically (see
                                        // alignWithDecisionMatrix) — which of the three re-selects
                                        // is derived from the date, same as the summary badges.
                                        $sel = null;
                                        if ($currentStatus) {
                                            if ($currentStatus === 'absent') $sel = 'Absent';
                                            elseif (str_contains($currentStatus, 'home') || str_contains($currentStatus, 'wfh')) $sel = 'Work from home';
                                            elseif (str_contains($currentStatus, 'half')) $sel = 'Half day';
                                            elseif (str_contains($currentStatus, 'present') && $record->check_in_time) {
                                                $workMins = $record->check_out_time ? $record->check_in_time->diffInMinutes($record->check_out_time) : 0;
                                                if ($workMins >= \App\Services\AttendanceDayStatus::OFFDAY_CREDIT_MINUTES && $record->check_in_time->isSunday()) {
                                                    $sel = 'Sunday working';
                                                } elseif ($workMins >= \App\Services\AttendanceDayStatus::OFFDAY_CREDIT_MINUTES && \App\Models\Holiday::appliesTo($emp, $record->check_in_time)) {
                                                    $sel = 'Holiday working';
                                                } else {
                                                    $sel = 'Present';
                                                }
                                            } elseif (str_contains($currentStatus, 'present')) {
                                                $sel = 'Present';
                                            }
                                        }
                                    @endphp
                                    <tr>
                                        <td>{{ $emp->code }}</td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar avatar-sm me-2">
                                                    @if($emp->getProfilePicture())
                                                        <img src="{{ $emp->getProfilePicture() }}" alt="Avatar" class="rounded-circle" onerror="this.onerror=null; this.outerHTML='<span class=\'avatar-initial rounded-circle bg-label-primary\'>{{ $emp->getInitials() }}</span>';">
                                                    @else
                                                        <span class="avatar-initial rounded-circle bg-label-primary">{{ $emp->getInitials() }}</span>
                                                    @endif
                                                </div>
                                                <div class="d-flex flex-column">
                                                    <span class="fw-semibold">{{ $emp->getFullName() }}</span>
                                                    @if($user->hasRole(['admin', 'hr']))
                                                        <small class="text-info fw-bold" style="font-size: 0.7rem;"><i class="ti ti-headset"></i> CC: {{ $emp->mapped_cc_name ?? 'N/A' }}</small>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $emp->designation->name ?? 'N/A' }}</td>
                                        <td>
                                            @if($dayLeave)
                                                <div class="mb-2">
                                                    <span class="badge {{ $partDay ? 'bg-label-info' : 'bg-label-warning' }} px-2 py-1" style="white-space:normal;text-align:left;">
                                                        <i class="ti ti-calendar-event me-1"></i>{{ \App\Http\Controllers\tenant\CcAttendanceController::leaveLabel($dayLeave) }}
                                                    </span>
                                                    @if($partDay && !$record && empty($isLocked))
                                                        <div class="text-muted small mt-1">
                                                            @if($leaveKind === 'short')
                                                                Did they work the rest of the day? <b>P</b> = worked the remaining hours (recorded as 6h+, counts as Present with the short leave), <b>HD</b> = half day, <b>A</b> = absent.
                                                            @elseif($leaveKind === 'half')
                                                                Did they work the other half? <b>P</b> or <b>HD</b> = worked the other half (half day leave + half day worked), <b>A</b> = absent.
                                                            @else
                                                                Did they work from home? <b>WFH</b> or <b>P</b> = worked, <b>A</b> = absent.{{ $wfhApproved ? ' No snapshot needed - the WFH leave is approved.' : '' }}
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif
                                            @if($fullLeave && !$record)
                                                <small class="text-muted fst-italic">On leave - managed by the Leave module</small>
                                            @elseif($currentStatus && !$canEditRow)
                                                <div class="opacity-50 pointer-events-none d-flex flex-column gap-2">
                                                    <div class="d-flex align-items-center gap-2">
                                                        @if($isLeave)
                                                            <span class="badge bg-label-warning px-3 py-2"><i class="ti ti-plane-departure me-1"></i> {{ ucwords(str_replace('-', ' ', $currentStatus)) }} (Approved)</span>
                                                        @else
                                                            @php
                                                              $shortStatus = 'P';
                                                              if ($currentStatus === 'absent') $shortStatus = 'A';
                                                              elseif (str_contains($currentStatus, 'half')) $shortStatus = 'HD';
                                                              elseif (str_contains($currentStatus, 'home') || str_contains($currentStatus, 'wfh')) $shortStatus = 'WFH';
                                                              elseif (str_contains($currentStatus, 'sunday')) $shortStatus = 'PS';
                                                              elseif ($currentStatus === 'paid-leave') $shortStatus = 'P (Comp-Off)';
                                                            @endphp
                                                            <span class="badge bg-label-secondary px-3 py-2 fw-bold"><i class="ti ti-check me-1"></i> {{ $shortStatus }}</span>
                                                        @endif
                                                        <small class="text-muted fst-italic">Not Editable</small>
                                                    </div>
                                                    @if($record->notes)
                                                        <div class="text-muted small"><strong>Report:</strong> {{ $record->notes }}</div>
                                                    @endif
                                                </div>
                                            @elseif(!empty($isLocked))
                                                {{-- Date closed (12:00 PM on the 2nd working day after it) — nothing can be marked here. --}}
                                                <span class="text-muted fst-italic"><i class="ti ti-lock me-1"></i>Locked — contact Admin/HR</span>
                                            @else
                                                <div class="d-flex flex-column gap-2">
                                                    <div class="d-flex gap-2">
                                                        <div class="form-check custom-option custom-option-icon mb-0">
                                                            <input class="form-check-input" type="radio" name="attendance[{{ $emp->id }}]" id="present_{{ $emp->id }}" value="Present" onchange="ccToggleWfh({{ $emp->id }}, this.value)" {{ $sel === 'Present' ? 'checked' : '' }}>
                                                            <label class="form-check-label text-success fw-bold px-3" for="present_{{ $emp->id }}">P</label>
                                                        </div>
                                                        <div class="form-check custom-option custom-option-icon mb-0">
                                                            <input class="form-check-input" type="radio" name="attendance[{{ $emp->id }}]" id="absent_{{ $emp->id }}" value="Absent" onchange="ccToggleWfh({{ $emp->id }}, this.value)" {{ $sel === 'Absent' ? 'checked' : '' }}>
                                                            <label class="form-check-label text-danger fw-bold px-3" for="absent_{{ $emp->id }}">A</label>
                                                        </div>
                                                        @if(!$partDay || in_array($leaveKind, ['short', 'half']))
                                                        <div class="form-check custom-option custom-option-icon mb-0">
                                                            <input class="form-check-input" type="radio" name="attendance[{{ $emp->id }}]" id="hd_{{ $emp->id }}" value="Half day" onchange="ccToggleWfh({{ $emp->id }}, this.value)" {{ $sel === 'Half day' ? 'checked' : '' }}>
                                                            <label class="form-check-label text-warning fw-bold px-3" for="hd_{{ $emp->id }}">HD</label>
                                                        </div>
                                                        @endif
                                                        @if(!$partDay || $leaveKind === 'wfh')
                                                        <div class="form-check custom-option custom-option-icon mb-0">
                                                            <input class="form-check-input" type="radio" name="attendance[{{ $emp->id }}]" id="wfh_{{ $emp->id }}" value="Work from home" onchange="ccToggleWfh({{ $emp->id }}, this.value)" {{ $sel === 'Work from home' ? 'checked' : '' }}>
                                                            <label class="form-check-label text-primary fw-bold px-3" for="wfh_{{ $emp->id }}">WFH</label>
                                                        </div>
                                                        @endif
                                                        {{-- "Sunday working"/"Holiday working" don't mean anything
                                                             on a day that already has a leave claim covering part
                                                             of it -- the server rejects them there too. --}}
                                                        @unless($partDay)
                                                        <div class="form-check custom-option custom-option-icon mb-0">
                                                            <input class="form-check-input" type="radio" name="attendance[{{ $emp->id }}]" id="sunday_{{ $emp->id }}" value="Sunday working" onchange="ccToggleWfh({{ $emp->id }}, this.value)" {{ $sel === 'Sunday working' ? 'checked' : '' }}>
                                                            <label class="form-check-label text-info fw-bold px-3" for="sunday_{{ $emp->id }}">PS</label>
                                                        </div>
                                                        <div class="form-check custom-option custom-option-icon mb-0">
                                                            <input class="form-check-input" type="radio" name="attendance[{{ $emp->id }}]" id="holiday_{{ $emp->id }}" value="Holiday working" onchange="ccToggleWfh({{ $emp->id }}, this.value)" {{ $sel === 'Holiday working' ? 'checked' : '' }}>
                                                            <label class="form-check-label text-info fw-bold px-3" for="holiday_{{ $emp->id }}">HP</label>
                                                        </div>
                                                        @endunless
                                                        {{-- Clear removes a wrongly-marked day so it can be
                                                             re-marked, or left blank altogether. --}}
                                                        @if($record)
                                                        <div class="form-check custom-option custom-option-icon mb-0">
                                                            <input class="form-check-input" type="radio" name="attendance[{{ $emp->id }}]" id="clr_{{ $emp->id }}" value="__clear" onchange="ccToggleWfh({{ $emp->id }}, this.value)">
                                                            <label class="form-check-label text-muted fw-bold px-3" for="clr_{{ $emp->id }}" title="Delete this entry">
                                                                <i class="ti ti-trash"></i> Clear
                                                            </label>
                                                        </div>
                                                        @endif
                                                    </div>
                                                    <div>
                                                        <label class="form-label small fw-semibold mb-1" for="note_{{ $emp->id }}">
                                                            Reporting <span class="text-danger">*</span>
                                                            <span class="text-muted fw-normal">— required for every marked salesperson</span>
                                                        </label>
                                                        <input type="text" id="note_{{ $emp->id }}" name="notes[{{ $emp->id }}]" class="form-control form-control-sm cc-note-input" value="{{ $record->notes ?? '' }}" placeholder="e.g. companies visited">
                                                    </div>
                                                    {{-- Work from home must carry the approval snapshot. --}}
                                                    @if($wfhApproved)
                                                    <div class="cc-wfh-box" id="wfhbox_{{ $emp->id }}" data-approved="1" style="display:none;"></div>
                                                    @else
                                                    <div class="cc-wfh-box" id="wfhbox_{{ $emp->id }}" style="display:{{ $sel === 'Work from home' ? 'block' : 'none' }};">
                                                        <label class="form-label small fw-semibold mb-1 text-primary">
                                                            <i class="ti ti-paperclip"></i> WFH approval snapshot <span class="text-danger">*</span>
                                                        </label>
                                                        <input type="file" name="wfh_proof[{{ $emp->id }}]" accept="image/*"
                                                               class="form-control form-control-sm cc-wfh-input" data-uid="{{ $emp->id }}">
                                                        @if($record && $record->attachment)
                                                            <a href="{{ route('cc-attendance.proof', $record->id) }}" target="_blank"
                                                               class="small d-inline-block mt-1"><i class="ti ti-photo"></i> View uploaded approval</a>
                                                            <div class="text-muted" style="font-size:.7rem;">Leave empty to keep the existing snapshot.</div>
                                                        @else
                                                            <div class="text-muted" style="font-size:.7rem;">Required — screenshot of the approval from the reporting manager.</div>
                                                        @endif
                                                    </div>
                                                    @endif
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-device-floppy me-1"></i> Save Attendance
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>

<script>
// Called inline from every status radio — the approval-snapshot box must appear
// the moment WFH is picked, without depending on listener binding order.
function ccToggleWfh(uid, value) {
    const box = document.getElementById('wfhbox_' + uid);
    if (box && !box.dataset.approved) box.style.display = (value === 'Work from home') ? 'block' : 'none';
}
</script>
<script>
// Reporting/remarks is mandatory: block submit when any marked salesperson
// has an empty reporting box (server enforces this too).
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('ccAttendanceForm');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        const bad = [];      // missing reporting remarks
        const noProof = [];  // WFH with no approval snapshot
        form.querySelectorAll('input[type="radio"]:checked').forEach(function (r) {
            const m = r.name.match(/^attendance\[(\d+)\]$/);
            if (!m) return;
            if (r.value === '__clear') return;   // clearing needs neither
            const note = form.querySelector('input[name="notes[' + m[1] + ']"]');
            if (note && note.value.trim() === '') bad.push(note);
            if (r.value === 'Work from home') {
                const file = form.querySelector('input[name="wfh_proof[' + m[1] + ']"]');
                const box  = document.getElementById('wfhbox_' + m[1]);
                const already = box && (box.querySelector('a') || box.dataset.approved);   // snapshot on file, or WFH leave approved
                if (file && !file.files.length && !already) noProof.push(file);
            }
        });
        // Nothing ticked at all: the browser sends no attendance[] field, the
        // server rejects it, and the redirect used to come back silent. Say so
        // here instead of letting it round-trip.
        const anyTicked = form.querySelectorAll('input[type="radio"]:checked').length > 0;

        if (bad.length || noProof.length || !anyTicked) {
            e.preventDefault();
            bad.forEach(function (n) { n.classList.add('is-invalid'); });
            noProof.forEach(function (n) { n.classList.add('is-invalid'); });

            const parts = [];
            if (!anyTicked) {
                parts.push('No attendance status was ticked. Choose <b>P / A / HD / WFH / PS / HP</b> for at least one salesperson.');
            }
            if (bad.length) {
                parts.push('<b>Reporting</b> is required for every marked salesperson — fill the highlighted box'
                    + (bad.length > 1 ? 'es' : '') + '.');
            }
            if (noProof.length) {
                parts.push('<b>Work From Home</b> needs the approval snapshot — attach it for the highlighted salesperson'
                    + (noProof.length > 1 ? 's' : '') + '.');
            }

            // A banner that STAYS on screen. The old alert() disappeared as soon
            // as it was dismissed, so a blocked save looked like a dead button.
            const box  = document.getElementById('ccBlockMsg');
            const body = document.getElementById('ccBlockBody');
            if (box && body) {
                body.innerHTML = parts.map(p => '<div>• ' + p + '</div>').join('');
                box.classList.remove('d-none');
                box.scrollIntoView({ block: 'center', behavior: 'smooth' });
            }
            const first = bad[0] || noProof[0];
            if (first) { first.focus(); }
        }
    });

    // Picking a status makes that row's Reporting box visibly required, rather
    // than only turning red after a failed save.
    form.addEventListener('change', function (e) {
        const m = e.target.name && e.target.name.match(/^attendance\[(\d+)\]$/);
        if (!m) return;
        const note = form.querySelector('input[name="notes[' + m[1] + ']"]');
        if (!note) return;
        const needsNote = e.target.checked && e.target.value !== '__clear';
        note.required = needsNote;
        note.classList.toggle('border-danger', needsNote && note.value.trim() === '');
    });
    // clear the red border once they type
    form.addEventListener('input', function (e) {
        if (e.target.classList && e.target.classList.contains('cc-note-input') && e.target.value.trim() !== '') {
            e.target.classList.remove('is-invalid', 'border-danger');
            document.getElementById('ccBlockMsg')?.classList.add('d-none');
        }
    });
});
</script>
@endsection
