@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')
@section('title', 'My KPI / KRA')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0 fw-bold"><i class="bx bx-target-lock me-1"></i> My KPI / KRA</h5>
        <div class="text-end">
            <span class="badge bg-label-primary fs-6">Score: {{ rtrim(rtrim(number_format($earned,2),'0'),'.') }} / {{ rtrim(rtrim(number_format($possible,2),'0'),'.') }}</span>
        </div>
    </div>

    @if($assignments->isEmpty())
        <div class="alert alert-info">No KPI/KRA has been assigned to you yet.</div>
    @endif

    @php
        $statusBadge = ['completed_on_time'=>'success','completed_late'=>'warning','missed'=>'danger','pending'=>'secondary'];
        $fmt = fn($v) => $v === null ? '—' : rtrim(rtrim(number_format($v,2),'0'),'.');
    @endphp

    {{-- Current period, one row per assigned KPI/KRA --}}
    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0">This Period</h6></div>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>KPI / KRA</th>
                        <th>Period</th>
                        <th>Due</th>
                        <th>Status</th>
                        <th class="text-end">Target</th>
                        <th class="text-end">Achieved</th>
                        <th class="text-end">Score</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($assignments as $a)
                    @php
                        $check = $a->currentCheck;
                        $done = in_array($check->status, ['completed_on_time', 'completed_late']);
                        $onTime = $check->status === 'completed_on_time';
                        $periodLabel = \App\Services\KpiPeriodService::periodLabel($a->frequency, $check->period_start, $check->period_end);
                    @endphp
                    <tr>
                        <td><span class="badge bg-label-{{ $a->category === 'KRA' ? 'warning' : 'primary' }}">{{ $a->category }}</span></td>
                        <td>
                            {{ $a->title }}
                            <div class="small text-muted text-capitalize">{{ $a->frequency }} · {{ $a->scoring_type }}</div>
                        </td>
                        <td class="small">{{ $periodLabel }}</td>
                        <td class="small">{{ \Carbon\Carbon::parse($check->due_date)->format('d M Y') }}</td>
                        <td>
                            <span class="badge bg-label-{{ $statusBadge[$check->status] ?? 'secondary' }}">{{ ucwords(str_replace('_',' ',$check->status)) }}</span>
                        </td>
                        @if($a->scoring_type === 'timeline')
                            <td class="text-end text-muted">—</td>
                            <td class="text-end text-muted">—</td>
                            <td class="text-end">{{ $fmt($check->score) }} / {{ $fmt($a->max_marks) }}</td>
                            <td class="text-end">
                                @if(!$done)
                                    <button class="btn btn-primary btn-sm complete-kpi" data-id="{{ $a->id }}">
                                        <i class="bx bx-check"></i> Mark complete
                                    </button>
                                @endif
                            </td>
                        @else
                            <td class="text-end">{{ $fmt($a->target_value) }}</td>
                            <td class="text-end">{{ $fmt($check->achieved_value) }}</td>
                            <td class="text-end">{{ $fmt($check->score) }} / {{ $fmt($a->max_marks) }}</td>
                            <td class="text-end text-muted small">HR / Manager</td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">No KPI/KRA has been assigned to you yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- History --}}
    @if($history->isNotEmpty())
    <div class="card mt-2">
        <div class="card-header"><h6 class="mb-0">History</h6></div>
        <div class="card-body table-responsive p-0">
            <table class="table table-sm mb-0">
                <thead><tr><th>Type</th><th>KPI/KRA</th><th>Period</th><th>Due</th><th>Status</th><th class="text-end">Achieved</th><th class="text-end">Score</th></tr></thead>
                <tbody>
                @foreach($history as $h)
                    @php $hAssignment = $h->assignment; @endphp
                    <tr>
                        <td><span class="badge bg-label-{{ optional($hAssignment)->category === 'KRA' ? 'warning' : 'primary' }}">{{ optional($hAssignment)->category ?? '—' }}</span></td>
                        <td>{{ optional($hAssignment)->title ?? '—' }}</td>
                        <td class="small">{{ $hAssignment ? \App\Services\KpiPeriodService::periodLabel($hAssignment->frequency, $h->period_start, $h->period_end) : \Carbon\Carbon::parse($h->period_start)->format('d M Y') }}</td>
                        <td class="small">{{ \Carbon\Carbon::parse($h->due_date)->format('d M Y') }}</td>
                        <td>
                            <span class="badge bg-label-{{ $statusBadge[$h->status] ?? 'secondary' }}">{{ ucwords(str_replace('_',' ',$h->status)) }}</span>
                        </td>
                        <td class="text-end">{{ $fmt($h->achieved_value) }}</td>
                        <td class="text-end">{{ $fmt($h->score) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection

@section('page-script')
<script>
const CSRF = '{{ csrf_token() }}';
document.querySelectorAll('.complete-kpi').forEach(b => b.addEventListener('click', function() {
    const id = this.dataset.id;
    this.disabled = true;
    fetch(`/kpi/${id}/complete`, { method: 'POST', headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'}, body: JSON.stringify({_token: CSRF}) })
      .then(r => r.json()).then(res => {
        if (res.status === 'success') { location.reload(); }
        else { alert(res.message || 'Error'); this.disabled = false; }
      }).catch(() => { alert('Request failed.'); this.disabled = false; });
}));
</script>
@endsection
