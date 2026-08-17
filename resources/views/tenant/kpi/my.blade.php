@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')
@section('title', 'My KPI / KRA')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0 fw-bold"><i class="bx bx-target-lock me-1"></i> My KPI / KRA</h5>
        <div class="text-end">
            <span class="badge bg-label-hitech fs-6">Score: {{ rtrim(rtrim(number_format($earned,2),'0'),'.') }} / {{ rtrim(rtrim(number_format($possible,2),'0'),'.') }}</span>
        </div>
    </div>

    @if($assignments->isEmpty())
        <div class="alert alert-info">No KPI/KRA has been assigned to you yet.</div>
    @else
        @php
            // Colour by outcome, not a badge per cell — a dense grid reads
            // cleaner as coloured numbers than a wall of pills. Hex values are
            // the theme's own status tones (hitech-portal.scss), not guesses.
            $tone = ['completed_on_time' => '#059669', 'completed_late' => '#d97706', 'missed' => '#e11d48', 'pending' => '#94a3b8'];
            $fmt = fn($v) => $v === null ? '—' : rtrim(rtrim(number_format($v,2),'0'),'.');
            $rows = $assignments->sortBy([['kra_group','asc'],['title','asc']]);

            // Column totals across whatever's actually scored this FY.
            $monthTotals = [];
            foreach ($months as $m) { $monthTotals[$m['key']] = 0.0; }
        @endphp

        <div class="card">
            <div class="card-header"><h6 class="mb-0">Fiscal Year {{ $months ? end($months)['label'] : '' }} – {{ $months ? $months[0]['label'] : '' }}</h6></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover mb-0 align-middle" style="white-space:nowrap;">
                    <thead>
                        <tr>
                            <th>KRA</th>
                            <th>KPI</th>
                            <th class="text-end">Max</th>
                            @foreach($months as $m)
                                <th class="text-end">{{ $m['label'] }}</th>
                            @endforeach
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($rows as $a)
                        @php
                            $rowTotal = 0.0;
                            $currentCheckId = optional($a->currentCheck)->id;
                        @endphp
                        <tr>
                            <td class="small text-muted">{{ $a->kra_group ?? '—' }}</td>
                            <td>
                                {{ $a->title }}
                                <div class="small text-muted text-capitalize">{{ $a->scoring_type }}</div>
                            </td>
                            <td class="text-end">{{ $fmt($a->max_marks) }}</td>
                            @foreach($months as $m)
                                @php
                                    $check = $grid[$a->id][$m['key']] ?? null;
                                    if ($check && $check->score !== null) { $rowTotal += (float) $check->score; $monthTotals[$m['key']] += (float) $check->score; }
                                    $isCurrentCell = $check && $check->id === $currentCheckId;
                                @endphp
                                <td class="text-end">
                                    @if($check && $check->status === 'pending' && $isCurrentCell && $a->scoring_type === 'timeline')
                                        <button class="btn btn-hitech-primary btn-sm complete-kpi" data-id="{{ $a->id }}">
                                            <i class="bx bx-check"></i> Mark complete
                                        </button>
                                    @elseif($check)
                                        <span style="color:{{ $tone[$check->status] ?? '#78716c' }};font-weight:600;"
                                              title="Achieved: {{ $fmt($check->achieved_value) }}">
                                            {{ $fmt($check->score) }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="text-end fw-semibold">{{ $fmt($rowTotal) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold" style="background:var(--hitech-bg-soft, #f8fafc);">
                            <td colspan="3">Total</td>
                            @foreach($months as $m)
                                <td class="text-end">{{ $fmt($monthTotals[$m['key']]) }}</td>
                            @endforeach
                            <td class="text-end">{{ $fmt(array_sum($monthTotals)) }}</td>
                        </tr>
                    </tfoot>
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
