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
    </ul>

    <!-- Date Selection Filter -->
    <div class="card cc-filter-bar mb-4">
        <div class="card-body">
            <form action="{{ route('cc-attendance.index') }}" method="GET" class="d-flex align-items-end gap-3">
                <div>
                    <label class="form-label text-muted fw-bold" for="date" style="font-size: 0.8rem; text-transform:uppercase; letter-spacing: 0.5px;">Select Date</label>
                    <input type="date" id="date" name="date" class="form-control form-control-lg shadow-sm" value="{{ $date }}" min="{{ \Carbon\Carbon::today()->subDays(3)->format('Y-m-d') }}" max="{{ \Carbon\Carbon::today()->format('Y-m-d') }}">
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
                <form action="{{ route('cc-attendance.store') }}" method="POST">
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
                                            @if($currentStatus)
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
                                            @else
                                                <div class="d-flex flex-column gap-2">
                                                    <div class="d-flex gap-2">
                                                        <div class="form-check custom-option custom-option-icon mb-0">
                                                            <input class="form-check-input" type="radio" name="attendance[{{ $emp->id }}]" id="present_{{ $emp->id }}" value="Present">
                                                            <label class="form-check-label text-success fw-bold px-3" for="present_{{ $emp->id }}">P</label>
                                                        </div>
                                                        <div class="form-check custom-option custom-option-icon mb-0">
                                                            <input class="form-check-input" type="radio" name="attendance[{{ $emp->id }}]" id="absent_{{ $emp->id }}" value="Absent">
                                                            <label class="form-check-label text-danger fw-bold px-3" for="absent_{{ $emp->id }}">A</label>
                                                        </div>
                                                        <div class="form-check custom-option custom-option-icon mb-0">
                                                            <input class="form-check-input" type="radio" name="attendance[{{ $emp->id }}]" id="hd_{{ $emp->id }}" value="Half day">
                                                            <label class="form-check-label text-warning fw-bold px-3" for="hd_{{ $emp->id }}">HD</label>
                                                        </div>
                                                        <div class="form-check custom-option custom-option-icon mb-0">
                                                            <input class="form-check-input" type="radio" name="attendance[{{ $emp->id }}]" id="wfh_{{ $emp->id }}" value="Work from home">
                                                            <label class="form-check-label text-primary fw-bold px-3" for="wfh_{{ $emp->id }}">WFH</label>
                                                        </div>
                                                        <div class="form-check custom-option custom-option-icon mb-0">
                                                            <input class="form-check-input" type="radio" name="attendance[{{ $emp->id }}]" id="sunday_{{ $emp->id }}" value="Sunday working">
                                                            <label class="form-check-label text-info fw-bold px-3" for="sunday_{{ $emp->id }}">PS</label>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <input type="text" name="notes[{{ $emp->id }}]" class="form-control form-control-sm" placeholder="Reporting (e.g. companies visited)">
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
@endsection
