@extends('layouts.layoutMaster')

@section('title', 'Monthly Salesperson Attendance')

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
  
  .cc-select {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-color: #fff;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23a1aab2' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 4px center;
    background-size: 10px;
    border: 1px solid #d9dee3;
    border-radius: 6px;
    padding: 2px 14px 2px 4px;
    text-align: center;
    font-size: 0.75rem;
    font-weight: 600;
    color: #5a5f71;
    width: 48px;
    height: 30px;
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(0,0,0,0.03);
  }
  .cc-select:focus {
    border-color: #0d7377;
    outline: 0;
    box-shadow: 0 0 0 0.2rem rgba(13, 115, 119, 0.25);
  }
  .cc-opt-p { color: #007bff; font-weight: 600; }
  .cc-opt-a { color: #dc3545; font-weight: 600; }
  .cc-opt-hd { color: #ff9800; font-weight: 600; }
  .cc-opt-wfh { color: #17a2b8; font-weight: 600; }
  .cc-opt-ps { color: #28a745; font-weight: 600; }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    
    <!-- Premium Hero Header -->
    <div class="cc-hero">
        <div class="position-relative" style="z-index: 2;">
            <h2 class="cc-hero-title"><i class="ti ti-calendar-stats me-2"></i> Monthly Salesperson Attendance</h2>
            <p class="cc-hero-subtitle">Review, track, and modify attendance in bulk across the month.</p>
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
            <a class="nav-link" href="{{ route('cc-attendance.index') }}"><i class="ti ti-calendar-event me-1"></i> Daily View</a>
        </li>
        <li class="nav-item">
            <a class="nav-link active" href="{{ route('cc-attendance.monthly') }}"><i class="ti ti-calendar-stats me-1"></i> Monthly View</a>
        </li>
    </ul>

    <!-- Month Selection Filter -->
    <div class="card cc-filter-bar mb-4">
        <div class="card-body">
            <form action="{{ route('cc-attendance.monthly') }}" method="GET" class="d-flex align-items-end gap-3">
                <div>
                    <label class="form-label text-muted fw-bold" style="font-size: 0.8rem; text-transform:uppercase; letter-spacing: 0.5px;">Month</label>
                    <select name="month" class="form-select form-select-lg shadow-sm">
                        @for($m=1; $m<=12; $m++)
                            <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                                {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                            </option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label class="form-label text-muted fw-bold" style="font-size: 0.8rem; text-transform:uppercase; letter-spacing: 0.5px;">Year</label>
                    <select name="year" class="form-select form-select-lg shadow-sm">
                        @for($y = date('Y') - 1; $y <= date('Y'); $y++)
                            <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary btn-lg shadow-sm px-4">Load Matrix</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Monthly Attendance Matrix Form -->
    <div class="card cc-card">
        <div class="card-header border-bottom">
            <h5 class="card-title mb-0">Monthly Register for {{ date('F', mktime(0, 0, 0, $month, 1)) }} {{ $year }}</h5>
        </div>
        
        <div class="card-body mt-4">
            @if($salespeople->isEmpty())
                <div class="alert alert-warning">
                    No salespeople are currently tagged to you.
                </div>
            @else
                <form action="{{ route('cc-attendance.monthly.store') }}" method="POST">
                    @csrf
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm align-middle text-center" style="font-size: 0.8rem; min-width: 1500px;">
                            <thead>
                                <tr>
                                    <th class="text-start position-sticky start-0 bg-white shadow-sm" style="min-width: 200px; z-index:2;">Salesperson Name</th>
                                    @for($day = 1; $day <= $daysInMonth; $day++)
                                        @php
                                            $dateObj = \Carbon\Carbon::create($year, $month, $day);
                                            $isSunday = $dateObj->isSunday();
                                            $isToday = $dateObj->isToday();
                                            
                                            $bgClass = '';
                                            if ($isSunday) $bgClass = 'bg-label-secondary';
                                            if ($isToday) $bgClass = 'bg-label-primary';
                                        @endphp
                                        <th class="{{ $bgClass }}" style="min-width: 80px;">
                                            <div>{{ $day }}</div>
                                            <div class="text-muted" style="font-size: 0.7rem;">{{ $dateObj->format('D') }}</div>
                                        </th>
                                    @endfor
                                </tr>
                            </thead>
                            
                            @php
                                $sundaySpans = [];
                                $salespeopleList = $salespeople->values();
                                for ($d = 1; $d <= $daysInMonth; $d++) {
                                    $dObj = \Carbon\Carbon::create($year, $month, $d);
                                    if (!$dObj->isSunday()) continue;
                                    $dStr = $dObj->format('Y-m-d');
                                    
                                    $i = 0;
                                    while ($i < $salespeopleList->count()) {
                                        $empItem = $salespeopleList[$i];
                                        $attList = $attendances[$empItem->id] ?? [];
                                        $record = $attList[$dStr] ?? null;
                                        
                                        if ($record && $record->status) {
                                            $sundaySpans[$d][$empItem->id] = ['rowspan' => 1, 'render' => true];
                                            $i++;
                                        } else {
                                            $count = 1;
                                            $j = $i + 1;
                                            while ($j < $salespeopleList->count()) {
                                                $nextEmp = $salespeopleList[$j];
                                                $nextAttList = $attendances[$nextEmp->id] ?? [];
                                                $nextRecord = $nextAttList[$dStr] ?? null;
                                                if ($nextRecord && $nextRecord->status) {
                                                    break;
                                                }
                                                $count++;
                                                $j++;
                                            }
                                            
                                            $sundaySpans[$d][$empItem->id] = ['rowspan' => $count, 'render' => true];
                                            for ($k = $i + 1; $k < $j; $k++) {
                                                $sundaySpans[$d][$salespeopleList[$k]->id] = ['rowspan' => 0, 'render' => false];
                                            }
                                            
                                            $i = $j;
                                        }
                                    }
                                }
                            @endphp
                            
                            <tbody>
                                @foreach($salespeople as $emp)
                                    <tr>
                                        <td class="text-start position-sticky start-0 bg-white shadow-sm fw-semibold" style="z-index: 1;">
                                            {{ $emp->getFullName() }} <br>
                                            <small class="text-muted fw-normal">{{ $emp->code }}</small>
                                            @if($user->hasRole(['admin', 'hr']))
                                                <br><small class="text-info fw-bold" style="font-size: 0.7rem;"><i class="ti ti-headset"></i> CC: {{ $emp->mapped_cc_name ?? 'N/A' }}</small>
                                            @endif
                                        </td>
                                        
                                        @for($day = 1; $day <= $daysInMonth; $day++)
                                            @php
                                                $dateObj = \Carbon\Carbon::create($year, $month, $day);
                                                $dateStr = $dateObj->format('Y-m-d');
                                                $isSunday = $dateObj->isSunday();
                                                
                                                // 3-day window logic
                                                $minDate = \Carbon\Carbon::today()->subDays(3);
                                                $maxDate = \Carbon\Carbon::today();
                                                
                                                $isWithinWindow = $dateObj->between($minDate, $maxDate);
                                                
                                                // Existing Record
                                                $attList = $attendances[$emp->id] ?? [];
                                                $record = $attList[$dateStr] ?? null;
                                                $status = $record ? strtolower($record->status) : null;
                                            @endphp
                                            @php
                                                $skipCell = false;
                                                $renderMerged = false;
                                                if ($isSunday) {
                                                    $spanData = $sundaySpans[$day][$emp->id] ?? ['rowspan' => 1, 'render' => true];
                                                    if (!$spanData['render']) {
                                                        $skipCell = true;
                                                    } elseif ($spanData['rowspan'] > 1) {
                                                        $renderMerged = true;
                                                    }
                                                }
                                            @endphp
                                                
                                            @if($skipCell)
                                                @continue
                                            @endif
                                            
                                            @if($renderMerged)
                                                <td class="bg-light align-middle p-0" rowspan="{{ $spanData['rowspan'] }}">
                                                    <div style="display: flex; flex-direction: column; justify-content: space-evenly; height: 100%; min-height: {{ $spanData['rowspan'] * 45 }}px; color: #a1aab2; font-weight: bold; letter-spacing: 2px; opacity: 0.5;">
                                                        @php $repeats = max(1, floor($spanData['rowspan'] / 3)); @endphp
                                                        @for($r=0; $r<$repeats; $r++)
                                                            <div style="writing-mode: vertical-rl; transform: rotate(180deg); margin: auto;">SUNDAY</div>
                                                        @endfor
                                                    </div>
                                                </td>
                                                @continue
                                            @endif

                                            <td class="{{ $isSunday ? 'bg-light' : '' }}">
                                                @if($status)
                                                    {{-- Marked --}}
                                                    @php
                                                        $displayStatus = 'P';
                                                        if ($status === 'absent') $displayStatus = 'A';
                                                        elseif (str_contains($status, 'half')) $displayStatus = 'HD';
                                                        elseif (str_contains($status, 'home') || str_contains($status, 'wfh')) $displayStatus = 'WFH';
                                                        elseif (str_contains($status, 'sunday')) $displayStatus = 'PS';
                                                        elseif ($status === 'paid-leave') $displayStatus = 'P(C)';
                                                        elseif (str_contains($status, 'leave')) $displayStatus = 'LV';
                                                    @endphp
                                                    <div class="opacity-75 fw-bold {{ $status == 'absent' ? 'text-danger' : 'text-success' }}" title="{{ ucwords($record->status) }}{{ $record->notes ? "\nReporting: " . $record->notes : '' }}">
                                                        {{ $displayStatus }}
                                                        @if($record->notes)
                                                            <i class="ti ti-notes" style="font-size: 0.7rem; position: absolute; margin-left: 2px;"></i>
                                                        @endif
                                                    </div>
                                                @else
                                                    {{-- Not Marked --}}
                                                    @if($isSunday && !$isWithinWindow)
                                                        {{-- Sunday but not in window, just show Sun --}}
                                                        <span class="text-muted fst-italic" style="font-size: 0.75rem;">Sun</span>
                                                    @elseif(!$isWithinWindow)
                                                        {{-- Outside Window --}}
                                                        <span class="text-muted" style="font-size: 0.75rem;">-</span>
                                                    @else
                                                        {{-- Editable Window --}}
                                                        <div class="d-flex flex-column align-items-center justify-content-center">
                                                            <select name="attendance[{{ $emp->id }}][{{ $dateStr }}]" class="cc-select">
                                                                <option value="">--</option>
                                                                <option value="Present" class="cc-opt-p">P</option>
                                                                <option value="Absent" class="cc-opt-a">A</option>
                                                                <option value="Half day" class="cc-opt-hd">HD</option>
                                                                <option value="Work from home" class="cc-opt-wfh">WFH</option>
                                                                <option value="Sunday working" class="cc-opt-ps">PS</option>
                                                            </select>
                                                            <input type="hidden" name="notes[{{ $emp->id }}][{{ $dateStr }}]" id="note_{{ $emp->id }}_{{ $dateStr }}">
                                                            <a href="javascript:void(0)" onclick="let n = prompt('Enter Reporting Notes:'); if(n) { document.getElementById('note_{{ $emp->id }}_{{ $dateStr }}').value = n; this.style.color = '#25D366'; this.innerHTML = '<i class=\'ti ti-check\'></i> Note Added'; }" class="text-muted mt-1" style="font-size: 0.65rem; text-decoration: none;" title="Add Reporting Note">
                                                                <i class="ti ti-notes"></i> Add Note
                                                            </a>
                                                        </div>
                                                    @endif
                                                @endif
                                            </td>
                                        @endfor
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
