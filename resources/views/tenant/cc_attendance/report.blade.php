@extends('layouts.layoutMaster')
@section('title', 'Attendance Submission Report')

@section('content')
<style>
  .cc-hero { background: linear-gradient(120deg, #0d7377 0%, #14a085 100%); border-radius: 16px; padding: 1.6rem 2rem; margin-bottom: 1.5rem; color: #fff; position: relative; overflow: hidden; }
  .cc-hero-title { color: #fff; font-weight: 800; margin-bottom: .2rem; }
  .cc-hero-subtitle { color: rgba(255,255,255,.85); margin: 0; }
  .cc-nav-pills .nav-link { font-weight: 600; padding: 0.6rem 1.2rem; border-radius: 10px; color: #5a5f71; }
  .cc-nav-pills .nav-link.active { background: #0d7377; color: #fff; box-shadow: 0 4px 12px rgba(13,115,119,.3); }
  .rep-table th { background: #0d7377; color: #fff; font-size: .78rem; white-space: nowrap; }
  .rep-table td { font-size: .8rem; vertical-align: middle; }
  .sum-chip { border: 1px solid #e2e8f0; border-left: 4px solid #0d7377; border-radius: 8px; padding: 6px 14px; background: #fff; font-size: .8rem; }
</style>

<div class="container-fluid px-4 py-4">
    <div class="cc-hero">
        <h2 class="cc-hero-title"><i class="ti ti-report-search me-2"></i> Attendance Submission Report</h2>
        <p class="cc-hero-subtitle">Who marked what, for whom, at exactly what time — with reporting remarks.</p>
    </div>

    <ul class="nav nav-pills cc-nav-pills mb-4">
        <li class="nav-item"><a class="nav-link" href="{{ route('cc-attendance.index') }}"><i class="ti ti-calendar-event me-1"></i> Daily View</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('cc-attendance.monthly') }}"><i class="ti ti-calendar-stats me-1"></i> Monthly View</a></li>
        <li class="nav-item"><a class="nav-link active" href="{{ route('cc-attendance.report') }}"><i class="ti ti-report-search me-1"></i> Submission Report</a></li>
    </ul>

    {{-- Filters --}}
    <div class="card mb-3">
        <div class="card-body py-3">
            <form method="GET" class="d-flex flex-wrap align-items-end gap-2">
                <div>
                    <label class="form-label small fw-bold mb-1">From</label>
                    <input type="date" name="from" class="form-control form-control-sm" value="{{ $from }}">
                </div>
                <div>
                    <label class="form-label small fw-bold mb-1">To</label>
                    <input type="date" name="to" class="form-control form-control-sm" value="{{ $to }}">
                </div>
                <div>
                    <label class="form-label small fw-bold mb-1">Salesperson</label>
                    <select name="sp" class="form-select form-select-sm" style="min-width:170px;">
                        <option value="">All Salespersons</option>
                        @foreach($salesList as $u)
                            <option value="{{ $u->id }}" {{ (string)request('sp') === (string)$u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label small fw-bold mb-1">Marked by</label>
                    <select name="cc" class="form-select form-select-sm" style="min-width:170px;">
                        <option value="">Anyone</option>
                        @foreach($markers as $u)
                            <option value="{{ $u->id }}" {{ (string)request('cc') === (string)$u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label small fw-bold mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm" style="min-width:130px;">
                        <option value="">All</option>
                        @foreach($summary->keys() as $st)
                            <option value="{{ $st }}" {{ request('status') === $st ? 'selected' : '' }}>{{ ucwords(str_replace('_',' ',$st)) }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-primary btn-sm px-3"><i class="ti ti-search me-1"></i>Apply</button>
                <a href="{{ route('cc-attendance.report') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                <a href="{{ route('cc-attendance.report.export', request()->query()) }}" class="btn btn-success btn-sm px-3">
                    <i class="ti ti-file-spreadsheet me-1"></i>Export Excel
                </a>
                <span class="ms-auto text-muted small">Showing {{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }} of {{ $rows->total() }} entries</span>
            </form>
        </div>
    </div>

    {{-- Summary chips --}}
    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach($summary as $st => $c)
            <div class="sum-chip"><strong>{{ ucwords(str_replace('_',' ',$st ?: '(none)')) }}</strong>: {{ $c }}</div>
        @endforeach
    </div>

    {{-- Table --}}
    <div class="card">
        <div class="table-responsive">
            <table class="table table-bordered table-sm rep-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Salesperson</th>
                        <th>Status</th>
                        <th>Reporting / Remarks</th>
                        <th>Marked By</th>
                        <th>Submitted At</th>
                        <th>Last Updated</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                        @php
                            $st = strtolower($r->status ?? '');
                            $badge = str_contains($st,'present') || $st==='p' ? 'bg-label-success'
                                   : (str_contains($st,'absent') ? 'bg-label-danger'
                                   : (str_contains($st,'half') ? 'bg-label-warning'
                                   : (str_contains($st,'home') || str_contains($st,'wfh') ? 'bg-label-info' : 'bg-label-secondary')));
                            $wasUpdated = $r->updated_at && $r->created_at && !$r->updated_at->equalTo($r->created_at);
                        @endphp
                        <tr>
                            <td class="text-nowrap">{{ optional($r->check_in_time)->format('d M Y') }} <span class="text-muted">{{ optional($r->check_in_time)->format('D') }}</span></td>
                            <td class="fw-semibold">{{ $r->user->name ?? ('#'.$r->user_id) }} <span class="text-muted">{{ $r->user->code ? '('.$r->user->code.')' : '' }}</span></td>
                            <td><span class="badge {{ $badge }}">{{ ucwords(str_replace('_',' ',$r->status ?? '-')) }}</span>@if($r->leaveRequest)
                                <div class="small text-info mt-1" style="white-space:normal;"><i class="ti ti-calendar-event"></i> {{ \App\Http\Controllers\tenant\CcAttendanceController::leaveLabel($r->leaveRequest) }}{{ ($r->check_in_time && $r->check_in_time->format('H:i') === '00:00' && !$r->check_out_time) ? ' - set by the leave, not marked by CCare' : '' }}</div>
                            @endif</td>
                            <td style="max-width:260px;white-space:normal;">{{ $r->notes ?: '—' }}</td>
                            <td>{{ $r->createdBy->name ?? ($r->updatedBy->name ?? 'System/Biometric') }}</td>
                            <td class="text-nowrap font-monospace" style="font-size:.75rem;">{{ optional($r->created_at)->format('d M Y, h:i:s A') }}</td>
                            <td class="text-nowrap" style="font-size:.75rem;">
                                @if($wasUpdated)
                                    {{ $r->updated_at->format('d M Y, h:i:s A') }}
                                    @if($r->updatedBy)<br><span class="text-muted">by {{ $r->updatedBy->name }}</span>@endif
                                @else — @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No attendance entries in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body py-2">{{ $rows->onEachSide(1)->links() }}</div>
    </div>
</div>
@endsection
