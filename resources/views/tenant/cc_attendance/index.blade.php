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

    <ul class="nav nav-pills cc-nav-pills mb-4">
        <li class="nav-item">
            <a class="nav-link active" href="{{ route('cc-attendance.index') }}"><i class="ti ti-calendar-event me-1"></i> Daily View</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="{{ route('cc-attendance.monthly') }}"><i class="ti ti-calendar-stats me-1"></i> Monthly View</a>
        </li>
        @if($user->hasRole(['admin', 'hr', 'manager']))
        <li class="nav-item">
            <a class="nav-link" href="{{ route('cc-attendance.report') }}"><i class="ti ti-report-search me-1"></i> Submission Report</a>
        </li>
        @endif
    </ul>

    @if(!empty($isLocked) && $isLocked)
        <div class="alert alert-warning d-flex align-items-center" role="alert">
            <i class="ti ti-lock me-2"></i>
            <div>Attendance for <strong>{{ \Carbon\Carbon::parse($date)->format('d M Y') }}</strong> is <strong>locked</strong> —
            the filling window closed on {{ $lockDeadline->format('d M Y, h:i A') }} (each date stays open until 2 days later, 12:00 PM).
            Only Admin/HR can mark it now.</div>
        </div>
    @elseif(isset($openWindow) && $date >= $openWindow[0] && $date <= $openWindow[1] && !$user->hasRole(['admin','hr']))
        <div class="alert alert-success py-2 d-flex align-items-center" role="alert" style="font-size:.85rem;">
            <i class="ti ti-lock-open me-2"></i>
            <div><strong>July back-fill is open</strong> — you can complete attendance for any date in July 2026, including past dates. (Time restrictions resume next month.)</div>
        </div>
    @elseif(isset($lockDeadline) && !$user->hasRole(['admin','hr']))
        <div class="alert alert-info py-2 d-flex align-items-center" role="alert" style="font-size:.85rem;">
            <i class="ti ti-clock me-2"></i>
            <div>This date can be filled until <strong>{{ $lockDeadline->format('d M Y, h:i A') }}</strong>, after which it locks (Admin/HR only).</div>
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

    <!-- Attendance Form -->
    <div class="card cc-card">
        <div class="card-header border-bottom">
            <h5 class="card-title mb-0">Attendance Register for {{ \Carbon\Carbon::parse($date)->format('d M, Y') }}</h5>
        </div>
        
        <div class="card-body mt-4">
            @if($salespeople->isEmpty())
                <div class="alert alert-warning">
                    No salespeople are currently tagged to you.
                </div>
            @else
                <form action="{{ route('cc-attendance.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="date" value="{{ $date }}">
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Employee ID</th>
                                    <th>Salesperson Name</th>
                                    <th>Designation</th>
                                    <th>Attendance Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($salespeople as $emp)
                                    @php
                                        $record = $attendances->get($emp->id);
                                        $currentStatus = $record ? $record->status : null;
                                        
                                        // Standardize status
                                        if ($currentStatus) {
                                            $currentStatus = strtolower(str_replace('_', '-', $currentStatus));
                                        }
                                        
                                        $isLeave = in_array($currentStatus, ['leave', 'paid-leave', 'unpaid-leave', 'on-leave', 'half-day']);

                                        // Previous submissions can be corrected while the date is open
                                        // (Admin/HR always). Approved-leave rows stay read-only.
                                        $canEditRow = !$isLeave && empty($isLocked);
                                        // Pre-select the existing choice.
                                        $sel = null;
                                        if ($currentStatus) {
                                            if ($currentStatus === 'absent') $sel = 'Absent';
                                            elseif (str_contains($currentStatus, 'home') || str_contains($currentStatus, 'wfh')) $sel = 'Work from home';
                                            elseif (str_contains($currentStatus, 'sunday')) $sel = 'Sunday working';
                                            elseif (str_contains($currentStatus, 'half')) $sel = 'Half day';
                                            elseif (str_contains($currentStatus, 'present')) $sel = 'Present';
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
                                            @if($currentStatus && !$canEditRow)
                                                <div class="opacity-50 pointer-events-none d-flex flex-column gap-2">
                                                    <div class="d-flex align-items-center gap-2">
                                                        @if($isLeave && $currentStatus !== 'half-day')
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
                                                {{-- Date closed (D+2 12:00 PM) — nothing can be marked here. --}}
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
                                                        <div class="form-check custom-option custom-option-icon mb-0">
                                                            <input class="form-check-input" type="radio" name="attendance[{{ $emp->id }}]" id="hd_{{ $emp->id }}" value="Half day" onchange="ccToggleWfh({{ $emp->id }}, this.value)" {{ $sel === 'Half day' ? 'checked' : '' }}>
                                                            <label class="form-check-label text-warning fw-bold px-3" for="hd_{{ $emp->id }}">HD</label>
                                                        </div>
                                                        <div class="form-check custom-option custom-option-icon mb-0">
                                                            <input class="form-check-input" type="radio" name="attendance[{{ $emp->id }}]" id="wfh_{{ $emp->id }}" value="Work from home" onchange="ccToggleWfh({{ $emp->id }}, this.value)" {{ $sel === 'Work from home' ? 'checked' : '' }}>
                                                            <label class="form-check-label text-primary fw-bold px-3" for="wfh_{{ $emp->id }}">WFH</label>
                                                        </div>
                                                        <div class="form-check custom-option custom-option-icon mb-0">
                                                            <input class="form-check-input" type="radio" name="attendance[{{ $emp->id }}]" id="sunday_{{ $emp->id }}" value="Sunday working" onchange="ccToggleWfh({{ $emp->id }}, this.value)" {{ $sel === 'Sunday working' ? 'checked' : '' }}>
                                                            <label class="form-check-label text-info fw-bold px-3" for="sunday_{{ $emp->id }}">PS</label>
                                                        </div>
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
                                                        <input type="text" name="notes[{{ $emp->id }}]" class="form-control form-control-sm cc-note-input" value="{{ $record->notes ?? '' }}" placeholder="Reporting — required (e.g. companies visited)">
                                                    </div>
                                                    {{-- Work from home must carry the approval snapshot. --}}
                                                    <div class="cc-wfh-box" id="wfhbox_{{ $emp->id }}" style="display:{{ $sel === 'Work from home' ? 'block' : 'none' }};">
                                                        <label class="form-label small fw-semibold mb-1 text-primary">
                                                            <i class="ti ti-paperclip"></i> WFH approval snapshot <span class="text-danger">*</span>
                                                        </label>
                                                        <input type="file" name="wfh_proof[{{ $emp->id }}]" accept="image/*"
                                                               class="form-control form-control-sm cc-wfh-input" data-uid="{{ $emp->id }}">
                                                        @if($record && $record->attachment)
                                                            <a href="{{ \Illuminate\Support\Facades\Storage::url($record->attachment) }}" target="_blank"
                                                               class="small d-inline-block mt-1"><i class="ti ti-photo"></i> View uploaded approval</a>
                                                            <div class="text-muted" style="font-size:.7rem;">Leave empty to keep the existing snapshot.</div>
                                                        @else
                                                            <div class="text-muted" style="font-size:.7rem;">Required — screenshot of the approval from the reporting manager.</div>
                                                        @endif
                                                    </div>
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
    if (box) box.style.display = (value === 'Work from home') ? 'block' : 'none';
}
</script>
<script>
// Reporting/remarks is mandatory: block submit when any marked salesperson
// has an empty reporting box (server enforces this too).
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form[action="{{ route('cc-attendance.store') }}"]');
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
                const already = box && box.querySelector('a');   // snapshot already on file
                if (file && !file.files.length && !already) noProof.push(file);
            }
        });
        if (bad.length || noProof.length) {
            e.preventDefault();
            bad.forEach(function (n) { n.classList.add('is-invalid'); });
            noProof.forEach(function (n) { n.classList.add('is-invalid'); });
            const first = bad[0] || noProof[0];
            first.scrollIntoView({ block: 'center', behavior: 'smooth' });
            first.focus();
            alert(bad.length
                ? 'Reporting/remarks is mandatory for every marked salesperson. Please fill the highlighted reporting box(es).'
                : 'Work From Home needs the approval snapshot. Please attach it for the highlighted salesperson(s).');
        }
    });
    // clear the red border once they type
    form.addEventListener('input', function (e) {
        if (e.target.classList && e.target.classList.contains('cc-note-input') && e.target.value.trim() !== '') {
            e.target.classList.remove('is-invalid');
        }
    });
});
</script>
@endsection
