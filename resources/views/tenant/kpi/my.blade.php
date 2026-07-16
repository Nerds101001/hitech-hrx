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

    <div class="row">
        @foreach($assignments as $a)
            @php
                $check = $a->currentCheck;
                $done = in_array($check->status, ['completed_on_time', 'completed_late']);
                $onTime = $check->status === 'completed_on_time';
                $borderClass = $done ? ($onTime ? 'border-success' : 'border-warning') : 'border-secondary';
            @endphp
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 border {{ $borderClass }}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="mb-0">{{ $a->title }}</h6>
                            <span class="badge bg-label-{{ $a->category === 'KRA' ? 'warning' : 'primary' }}">{{ $a->category }}</span>
                        </div>
                        <div class="mb-2">
                            <span class="badge bg-label-info text-capitalize">{{ $a->frequency }}</span>
                            <span class="badge bg-label-secondary text-capitalize">{{ $a->scoring_type }}</span>
                        </div>
                        <p class="small text-muted mb-2">
                            Period: {{ \Carbon\Carbon::parse($check->period_start)->format('d M') }} – {{ \Carbon\Carbon::parse($check->period_end)->format('d M') }}<br>
                            Due by: <strong>{{ \Carbon\Carbon::parse($check->due_date)->format('d M Y') }}</strong>
                        </p>

                        @if($a->scoring_type === 'timeline')
                            @if($done)
                                <div class="alert alert-{{ $onTime ? 'success' : 'warning' }} py-2 mb-2">
                                    <i class="bx bx-check-circle"></i>
                                    Completed {{ $onTime ? 'on time' : 'late' }}
                                    @if($check->score !== null) — {{ rtrim(rtrim(number_format($check->score,2),'0'),'.') }} / {{ rtrim(rtrim(number_format($a->max_marks,2),'0'),'.') }} marks @endif
                                </div>
                            @else
                                <button class="btn btn-primary btn-sm w-100 complete-kpi" data-id="{{ $a->id }}">
                                    <i class="bx bx-check"></i> Mark completed for this period
                                </button>
                                <div class="text-muted small mt-1">Full {{ rtrim(rtrim(number_format($a->max_marks,2),'0'),'.') }} marks if on time, else {{ rtrim(rtrim(number_format($a->late_score,2),'0'),'.') }}.</div>
                            @endif
                        @else
                            <div class="border rounded p-2 bg-light">
                                <div class="d-flex justify-content-between small">
                                    <span>Target</span><strong>{{ rtrim(rtrim(number_format($a->target_value,2),'0'),'.') }}</strong>
                                </div>
                                <div class="d-flex justify-content-between small">
                                    <span>Achieved</span><strong>{{ $check->achieved_value !== null ? rtrim(rtrim(number_format($check->achieved_value,2),'0'),'.') : '—' }}</strong>
                                </div>
                                <div class="d-flex justify-content-between small">
                                    <span>Marks</span><strong>{{ $check->score !== null ? rtrim(rtrim(number_format($check->score,2),'0'),'.') : '—' }} / {{ rtrim(rtrim(number_format($a->max_marks,2),'0'),'.') }}</strong>
                                </div>
                                <div class="text-muted small mt-1">Recorded by HR / Manager.</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- History --}}
    @if($history->isNotEmpty())
    <div class="card mt-2">
        <div class="card-header"><h6 class="mb-0">History</h6></div>
        <div class="card-body table-responsive">
            <table class="table table-sm">
                <thead><tr><th>KPI/KRA</th><th>Period</th><th>Due</th><th>Status</th><th>Achieved</th><th>Score</th></tr></thead>
                <tbody>
                @foreach($history as $h)
                    <tr>
                        <td>{{ optional($h->assignment)->title ?? '—' }}</td>
                        <td class="small">{{ \Carbon\Carbon::parse($h->period_start)->format('d M') }}–{{ \Carbon\Carbon::parse($h->period_end)->format('d M Y') }}</td>
                        <td class="small">{{ \Carbon\Carbon::parse($h->due_date)->format('d M Y') }}</td>
                        <td>
                            @php $badge = ['completed_on_time'=>'success','completed_late'=>'warning','missed'=>'danger','pending'=>'secondary'][$h->status] ?? 'secondary'; @endphp
                            <span class="badge bg-label-{{ $badge }}">{{ ucwords(str_replace('_',' ',$h->status)) }}</span>
                        </td>
                        <td>{{ $h->achieved_value !== null ? rtrim(rtrim(number_format($h->achieved_value,2),'0'),'.') : '—' }}</td>
                        <td>{{ $h->score !== null ? rtrim(rtrim(number_format($h->score,2),'0'),'.') : '—' }}</td>
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
