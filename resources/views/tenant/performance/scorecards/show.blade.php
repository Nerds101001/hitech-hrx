@php $configData = Helper::appClasses(); @endphp
@extends('layouts/layoutMaster')

@section('title', 'Scorecard - ' . $employee->first_name . ' ' . $employee->last_name)

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6 mx-4">
        <div>
            <a href="{{ route('performance.scorecards.index') }}" class="text-muted small d-inline-flex align-items-center gap-1 mb-2">
                <i class="bx bx-chevron-left"></i> Back to Scorecards
            </a>
            <h3 class="fw-extrabold mb-1 text-dark">{{ $employee->first_name }} {{ $employee->last_name }}</h3>
            <p class="text-muted mb-0 small">
                {{ $employee->department?->name ?? 'No department' }} &middot; Same KPIs/KRAs, reporting, and scoring as their own My Scorecard.
            </p>
        </div>
    </div>

    @if($assignments->isEmpty())
        <div class="px-4"><div class="alert alert-info">No KPI/KRA has been assigned to this employee yet.</div></div>
    @else
        @php
            $tone = ['completed_on_time' => '#059669', 'completed_late' => '#d97706', 'missed' => '#e11d48', 'pending' => '#94a3b8'];
            $fmt = fn($v) => $v === null ? '—' : rtrim(rtrim(number_format($v,2),'0'),'.');
            $rows = $assignments->sortBy([['kra_group','asc'],['title','asc']])->values();
            $overallPct = $possible > 0 ? round(($earned / $possible) * 100) : 0;

            $monthScoreTotals = [];
            foreach ($months as $m) { $monthScoreTotals[$m['key']] = 0.0; }
        @endphp

        {{-- Stat cards, identical to My Scorecard's. --}}
        <div class="row g-6 mb-6 mx-0 px-4">
            <div class="col-xl-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay:.05s">
                <div class="hitech-stat-card dashboard-variant card-teal uniform-card">
                    <div class="stat-card-header mb-2">
                        <div class="stat-icon-wrap icon-teal" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-medal"></i></div>
                    </div>
                    <div><h3 class="stat-value mb-0">{{ $fmt($earned) }}</h3><span class="stat-label">Marks earned</span></div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay:.1s">
                <div class="hitech-stat-card dashboard-variant card-blue uniform-card">
                    <div class="stat-card-header mb-2">
                        <div class="stat-icon-wrap icon-blue" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-target-lock"></i></div>
                    </div>
                    <div><h3 class="stat-value mb-0">{{ $overallPct }}%</h3><span class="stat-label">Of {{ $fmt($possible) }} possible</span></div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay:.15s">
                <div class="hitech-stat-card dashboard-variant card-amber uniform-card">
                    <div class="stat-card-header mb-2">
                        <div class="stat-icon-wrap icon-amber" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-time-five"></i></div>
                    </div>
                    <div><h3 class="stat-value mb-0">{{ $pendingCount }}</h3><span class="stat-label">Pending</span></div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay:.2s">
                <div class="hitech-stat-card dashboard-variant card-red uniform-card">
                    <div class="stat-card-header mb-2">
                        <div class="stat-icon-wrap icon-red" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-check-circle"></i></div>
                    </div>
                    <div><h3 class="stat-value mb-0">{{ $completedCount }}</h3><span class="stat-label">Completed</span></div>
                </div>
            </div>
        </div>

        {{-- Filter panel - same shell as My Scorecard's, plus a Month filter
             (toggles which month column-pairs are visible - client-side,
             matching how search/KRA/status already work here). --}}
        <div class="px-4">
        <div class="hitech-card mb-6 overflow-hidden">
          <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-center gap-3">
              <div class="search-wrapper-hitech" style="width: 320px;">
                <i class="bx bx-search text-muted ms-3 fs-5"></i>
                <input type="text" id="kpiSearch" class="form-control" placeholder="Search KPI or KRA...">
                <button class="btn-search shadow-sm" type="button"><i class="bx bx-search fs-5"></i><span>Search</span></button>
              </div>
              <div class="compact-select" style="min-width: 160px;">
                <select id="kraFilter" class="form-select filter-item-hitech">
                    <option value="">KRA: All</option>
                    @foreach($rows->pluck('kra_group')->filter()->unique()->sort() as $kra)
                        <option value="{{ $kra }}">{{ $kra }}</option>
                    @endforeach
                </select>
              </div>
              <div class="compact-select" style="min-width: 160px;">
                <select id="statusFilter" class="form-select filter-item-hitech">
                    <option value="">Status: All</option>
                    <option value="completed_on_time">Completed</option>
                    <option value="pending">Pending</option>
                    <option value="completed_late">Completed late</option>
                    <option value="missed">Missed</option>
                </select>
              </div>
              <div class="compact-select" style="min-width: 160px;">
                <select id="monthFilter" class="form-select filter-item-hitech">
                    <option value="">Month: All</option>
                    @foreach($months as $m)
                        <option value="{{ $m['key'] }}">{{ $m['label'] }}</option>
                    @endforeach
                </select>
              </div>
            </div>
          </div>
        </div>
        </div>

        <div class="px-4">
        <div class="hitech-card mb-6">
        <div class="table-responsive" style="max-height:calc(100vh - 320px);overflow-y:auto;">
            <table class="table table-bordered align-middle mb-0" id="kpiTable" style="white-space:nowrap;font-size:0.9rem;">
                <thead class="position-sticky top-0" style="z-index:10;">
                    <tr class="kpi-head-main">
                        <th rowspan="2" class="align-middle">KRA</th>
                        <th rowspan="2" class="align-middle">KPI</th>
                        <th rowspan="2" class="text-end align-middle">Target</th>
                        <th rowspan="2" class="text-end align-middle">Max Marks</th>
                        @foreach($months as $m)
                            <th colspan="2" class="text-center align-middle kpi-head-sub border-bottom border-white" data-month="{{ $m['key'] }}">{{ $m['label'] }}</th>
                        @endforeach
                        <th rowspan="2" class="text-end align-middle">Total Score</th>
                    </tr>
                    <tr class="kpi-head-sub">
                        @foreach($months as $m)
                            <th class="text-end align-middle" style="min-width:70px;" data-month="{{ $m['key'] }}">Achieved</th>
                            <th class="text-end align-middle" style="min-width:60px;" data-month="{{ $m['key'] }}">Score</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                @foreach($rows as $a)
                    @php
                        $rowTotal = 0.0;
                        $rowStatus = optional($a->currentCheck)->status ?? 'pending';
                    @endphp
                    <tr data-kra="{{ $a->kra_group }}" data-title="{{ strtolower($a->title) }}" data-status="{{ $rowStatus }}">
                        <td class="text-muted">{{ $a->kra_group ?? '—' }}</td>
                        <td>
                            {{ $a->title }}
                            <div class="small text-muted text-capitalize">{{ $a->scoring_type }}@if($a->scoring_type === 'threshold') ≥ {{ $fmt($a->threshold_percent) }}% @endif</div>
                        </td>
                        <td class="text-end">
                            {{ $fmt($a->target_value) }}@if(str_starts_with((string) $a->data_source, 'auto:') && (float) $a->target_value == 100.0)<span class="text-muted">%</span>@endif
                            <div class="small text-muted" style="font-size:.68rem;white-space:nowrap">{{ $targetNote[$a->id] ?? '' }}</div>
                        </td>
                        <td class="text-end">{{ $fmt($a->max_marks) }}</td>
                        @foreach($months as $m)
                            @php
                                $check = $grid[$a->id][$m['key']] ?? null;
                                if ($check && $check->score !== null) { $rowTotal += (float) $check->score; $monthScoreTotals[$m['key']] += (float) $check->score; }
                            @endphp
                            <td class="text-end" data-month="{{ $m['key'] }}">
                                @if($check)
                                    <span style="color:{{ $tone[$check->status] ?? '#78716c' }};">{{ $fmt($check->achieved_value) }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end" data-month="{{ $m['key'] }}">
                                @if($check)
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
                            <td colspan="2" class="text-end" data-month="{{ $m['key'] }}">{{ $fmt($monthScoreTotals[$m['key']]) }}</td>
                        @endforeach
                        <td class="text-end">{{ $fmt(array_sum($monthScoreTotals)) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        </div>
        </div>
    @endif
</div>

<style>
  #kpiTable thead tr.kpi-head-main th {
    background-color: #0f766e !important;
    color: #fff !important;
    border-color: #0f766e !important;
  }
  #kpiTable thead th.kpi-head-sub,
  #kpiTable thead tr.kpi-head-sub th {
    background-color: #0d9488 !important;
    color: #fff !important;
    border-color: #0d9488 !important;
  }
  #kpiTable th, #kpiTable td { padding: 0.65rem 0.85rem; }
</style>

<script>
(function () {
    const search = document.getElementById('kpiSearch');
    const kraFilter = document.getElementById('kraFilter');
    const statusFilter = document.getElementById('statusFilter');
    const monthFilter = document.getElementById('monthFilter');
    const rows = document.querySelectorAll('#kpiTable tbody tr');
    const monthCells = document.querySelectorAll('#kpiTable [data-month]');

    function applyRows() {
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

    function applyMonth() {
        const month = monthFilter.value;
        monthCells.forEach(c => {
            c.style.display = (!month || c.dataset.month === month) ? '' : 'none';
        });
    }

    [search, kraFilter, statusFilter].forEach(el => el.addEventListener('input', applyRows));
    monthFilter.addEventListener('input', applyMonth);
})();
</script>
@endsection
