@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')
@section('title', 'My KPI / KRA')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 fw-bold"><i class="bx bx-target-lock me-1"></i> My KPI / KRA</h5>
    </div>

    @if($assignments->isEmpty())
        <div class="alert alert-info">No KPI/KRA has been assigned to you yet.</div>
    @else
        @php
            $tone = ['completed_on_time' => '#059669', 'completed_late' => '#d97706', 'missed' => '#e11d48', 'pending' => '#94a3b8'];
            $fmt = fn($v) => $v === null ? '—' : rtrim(rtrim(number_format($v,2),'0'),'.');
            $rows = $assignments->sortBy([['kra_group','asc'],['title','asc']])->values();
            $overallPct = $possible > 0 ? round(($earned / $possible) * 100) : 0;

            $monthScoreTotals = [];
            foreach ($months as $m) { $monthScoreTotals[$m['key']] = 0.0; }
        @endphp

        {{-- Summary chips, same pattern as the Sales Report page --}}
        <div class="d-flex flex-wrap gap-2 mb-3">
            <div class="stat-chip"><span class="stat-label">Marks Earned</span><span class="stat-value">{{ $fmt($earned) }} / {{ $fmt($possible) }}</span></div>
            <div class="stat-chip" style="border-left-color:#10b981;"><span class="stat-label">Overall</span><span class="stat-value">{{ $overallPct }}%</span></div>
            <div class="stat-chip" style="border-left-color:#94a3b8;"><span class="stat-label">Pending</span><span class="stat-value">{{ $pendingCount }}</span></div>
            <div class="stat-chip" style="border-left-color:#059669;"><span class="stat-label">Completed</span><span class="stat-value">{{ $completedCount }}</span></div>
        </div>

        {{-- Filter row --}}
        <div class="d-flex flex-wrap gap-2 mb-2">
            <input type="text" id="kpiSearch" class="form-control form-control-sm" style="max-width:220px;" placeholder="Search KPI or KRA...">
            <select id="kraFilter" class="form-select form-select-sm" style="max-width:220px;">
                <option value="">All KRAs</option>
                @foreach($rows->pluck('kra_group')->filter()->unique()->sort() as $kra)
                    <option value="{{ $kra }}">{{ $kra }}</option>
                @endforeach
            </select>
            <select id="statusFilter" class="form-select form-select-sm" style="max-width:180px;">
                <option value="">All statuses</option>
                <option value="completed_on_time">Completed</option>
                <option value="pending">Pending</option>
                <option value="completed_late">Completed late</option>
                <option value="missed">Missed</option>
            </select>
        </div>

        <div class="table-responsive" style="border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,.08);max-height:calc(100vh - 320px);">
            <table class="table table-bordered table-sm align-middle m-0" id="kpiTable" style="white-space:nowrap;font-size:0.85rem;">
                <thead class="position-sticky top-0 text-white" style="z-index:10;background-color:#0f766e;">
                    <tr>
                        <th rowspan="2" class="align-middle">KRA</th>
                        <th rowspan="2" class="align-middle">KPI</th>
                        <th rowspan="2" class="text-end align-middle">Target</th>
                        <th rowspan="2" class="text-end align-middle">Max Marks</th>
                        @foreach($months as $m)
                            <th colspan="2" class="text-center align-middle border-bottom border-white" style="background-color:#0d9488;">{{ $m['label'] }}</th>
                        @endforeach
                        <th rowspan="2" class="text-end align-middle">Total Score</th>
                    </tr>
                    <tr>
                        @foreach($months as $m)
                            <th class="text-end align-middle" style="min-width:70px;background-color:#0d9488;">Achieved</th>
                            <th class="text-end align-middle" style="min-width:60px;background-color:#0d9488;">Score</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                @foreach($rows as $a)
                    @php
                        $rowTotal = 0.0;
                        $currentCheckId = optional($a->currentCheck)->id;
                        $rowStatus = optional($a->currentCheck)->status ?? 'pending';
                    @endphp
                    <tr data-kra="{{ $a->kra_group }}" data-title="{{ strtolower($a->title) }}" data-status="{{ $rowStatus }}">
                        <td class="text-muted">{{ $a->kra_group ?? '—' }}</td>
                        <td>
                            {{ $a->title }}
                            <div class="small text-muted text-capitalize">{{ $a->scoring_type }}@if($a->scoring_type === 'threshold') ≥ {{ $fmt($a->threshold_percent) }}% @endif</div>
                        </td>
                        <td class="text-end">{{ $fmt($a->target_value) }}</td>
                        <td class="text-end">{{ $fmt($a->max_marks) }}</td>
                        @foreach($months as $m)
                            @php
                                $check = $grid[$a->id][$m['key']] ?? null;
                                if ($check && $check->score !== null) { $rowTotal += (float) $check->score; $monthScoreTotals[$m['key']] += (float) $check->score; }
                                $isCurrentCell = $check && $check->id === $currentCheckId;
                            @endphp
                            <td class="text-end">
                                @if($check)
                                    <span style="color:{{ $tone[$check->status] ?? '#78716c' }};">{{ $fmt($check->achieved_value) }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($check && $check->status === 'pending' && $isCurrentCell && $a->scoring_type === 'timeline')
                                    <button class="btn btn-hitech-primary btn-sm complete-kpi" data-id="{{ $a->id }}">
                                        <i class="bx bx-check"></i>
                                    </button>
                                @elseif($check)
                                    <span style="color:{{ $tone[$check->status] ?? '#78716c' }};font-weight:600;">{{ $fmt($check->score) }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        @endforeach
                        <td class="text-end fw-bold">{{ $fmt($rowTotal) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr class="fw-semibold" style="background:#d1fae5;">
                        <td colspan="4">Total</td>
                        @foreach($months as $m)
                            <td colspan="2" class="text-end">{{ $fmt($monthScoreTotals[$m['key']]) }}</td>
                        @endforeach
                        <td class="text-end">{{ $fmt(array_sum($monthScoreTotals)) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
@endsection

@section('page-style')
<style>
  .stat-chip { background:#fff; border:1px solid var(--hitech-border,#dee2e6); border-left:4px solid var(--hitech-primary,#008080);
               border-radius:8px; padding:.5rem 1rem; display:flex; flex-direction:column; min-width:120px; }
  .stat-chip .stat-label { font-size:.7rem; text-transform:uppercase; color:#64748b; font-weight:600; letter-spacing:.03em; }
  .stat-chip .stat-value { font-size:1.1rem; font-weight:700; color:#1e293b; }
</style>
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

// Client-side filter — the whole scorecard is one person's ~dozen rows, no
// need for a server round-trip.
(function () {
    const search = document.getElementById('kpiSearch');
    const kraFilter = document.getElementById('kraFilter');
    const statusFilter = document.getElementById('statusFilter');
    const rows = document.querySelectorAll('#kpiTable tbody tr');

    function apply() {
        const q = search.value.trim().toLowerCase();
        const kra = kraFilter.value;
        const status = statusFilter.value;
        rows.forEach(r => {
            const matchesSearch = !q || r.dataset.title.includes(q) || (r.dataset.kra || '').toLowerCase().includes(q);
            const matchesKra = !kra || r.dataset.kra === kra;
            const matchesStatus = !status || r.dataset.status === status;
            r.style.display = (matchesSearch && matchesKra && matchesStatus) ? '' : 'none';
        });
    }
    [search, kraFilter, statusFilter].forEach(el => el.addEventListener('input', apply));
})();
</script>
@endsection
