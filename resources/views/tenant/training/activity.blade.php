@extends('layouts.layoutMaster')

@section('title', 'Training Activity Dashboard')

@section('page-style')
<style>
    .ta-page {
        --ta-primary: #0b5f68;
        --ta-primary-dark: #07464d;
        --ta-gradient: linear-gradient(120deg, #07464d 0%, #0b6b73 55%, #11a3ad 100%);
        --ta-border: #e3ebee;
        --ta-ink: #1e293b;
        --ta-muted: #64748b;
        --ta-soft: #f4f8f9;
        --ta-green: #16a34a;
        --ta-orange: #f59e0b;
        --ta-blue: #3b82f6;
        --ta-red: #ef4444;
        --ta-purple: #8b5cf6;
    }

    /* ── Hero ───────────────────────────────────────── */
    .ta-hero {
        background: var(--ta-gradient); border-radius: 18px; padding: 26px 30px; margin-bottom: 18px;
        color: #fff; position: relative; overflow: hidden; box-shadow: 0 12px 32px rgba(7, 70, 77, .22);
    }
    .ta-hero::before { content: ''; position: absolute; right: -70px; top: -90px; width: 280px; height: 280px; border-radius: 50%; background: rgba(255,255,255,.07); }
    .ta-hero::after  { content: ''; position: absolute; right: 180px; bottom: -80px; width: 170px; height: 170px; border-radius: 50%; background: rgba(255,255,255,.05); }
    .ta-hero h1 { font-size: 1.75rem; font-weight: 800; margin: 0 0 4px; color: #fff; letter-spacing: -.01em; }
    .ta-hero p  { margin: 0; color: rgba(255,255,255,.82); font-size: .9rem; }
    .ta-hero-btn {
        position: relative; z-index: 1; display: inline-flex; align-items: center; gap: 8px;
        border: 1px solid rgba(255,255,255,.45); background: rgba(255,255,255,.12); color: #fff;
        border-radius: 12px; padding: 10px 18px; font-weight: 600; font-size: .85rem; text-decoration: none; white-space: nowrap;
    }
    .ta-hero-btn:hover { background: rgba(255,255,255,.22); color: #fff; }

    /* ── Cards / panels ─────────────────────────────── */
    .ta-panel { background: #fff; border: 1px solid var(--ta-border); border-radius: 14px; box-shadow: 0 2px 10px rgba(15, 23, 42, .04); height: 100%; display: flex; flex-direction: column; }
    .ta-panel-head { display: flex; justify-content: space-between; align-items: center; padding: 16px 18px 6px; }
    .ta-panel-title { font-size: 1rem; font-weight: 800; color: var(--ta-ink); margin: 0; }
    .ta-panel-link { font-size: .76rem; font-weight: 600; color: var(--ta-primary); text-decoration: none; white-space: nowrap; }
    .ta-panel-link:hover { color: var(--ta-primary-dark); }
    .ta-panel-body { padding: 8px 18px 16px; flex: 1; }
    /* a body that fills the card and scrolls inside it, instead of setting the row height */
    .ta-fill { position: relative; min-height: 210px; padding: 0; }
    .ta-fill > .ta-scroll { position: absolute; inset: 0; overflow-y: auto; overflow-x: auto; padding: 0 18px 12px; }
    /* Tables inside .ta-panel-body with no .ta-fill wrapper (e.g. Modules
       Requiring Attention) have no scroll box of their own at all -
       without this, their nowrap <th>s push the panel/page wider than the
       viewport on phone instead of just scrolling within the card. */
    .ta-panel-body > .ta-table-scroll { overflow-x: auto; }
    .ta-center { display: flex; align-items: center; }
    .ta-center > * { flex: 1; }

    /* ── Filter bar ─────────────────────────────────── */
    .ta-filter { padding: 14px 18px; margin-bottom: 18px; }
    .ta-filter label { display: block; font-size: .76rem; font-weight: 600; color: var(--ta-ink); margin-bottom: 5px; }
    .ta-iconsel { position: relative; }
    .ta-iconsel > i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--ta-muted); font-size: 1rem; pointer-events: none; z-index: 1; }
    .ta-page .ta-iconsel .form-select, .ta-page .ta-iconsel .form-control { padding-left: 2.5rem !important; }
    .ta-page .ta-apply { height: 46px; border-radius: 12px; font-weight: 700; background: var(--ta-primary); border-color: var(--ta-primary); padding: 0 34px; }
    .ta-page .ta-apply:hover { background: var(--ta-primary-dark); border-color: var(--ta-primary-dark); }
    .ta-reset { display: inline-flex; align-items: center; gap: 6px; height: 46px; font-weight: 600; font-size: .86rem; color: var(--ta-ink); text-decoration: none; white-space: nowrap; }
    .ta-reset:hover { color: var(--ta-primary); }
    .ta-summary { font-size: .76rem; color: var(--ta-muted); margin-top: 8px; }

    /* ── KPI cards ──────────────────────────────────── */
    .ta-kpi { display: flex; flex-direction: row; gap: 12px; align-items: flex-start; padding: 16px; }
    .ta-kpi-icon { width: 46px; height: 46px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; }
    .ta-kpi-value { font-size: 1.45rem; font-weight: 800; color: var(--ta-ink); line-height: 1.1; }
    .ta-kpi-label { font-size: .8rem; color: var(--ta-ink); font-weight: 500; }
    .ta-kpi-sub { font-size: .7rem; color: var(--ta-muted); margin-top: 2px; }
    .ta-trend { display: inline-block; margin-top: 5px; font-size: .68rem; font-weight: 700; color: var(--ta-green); background: #f0fdf4; border-radius: 6px; padding: 1px 6px; }
    .ta-trend.down { color: var(--ta-red); background: #fef2f2; }
    .ti-bg-teal { background: #e0f2f1; color: #0b6b73; } .ti-bg-green { background: #dcfce7; color: #16a34a; }
    .ti-bg-orange { background: #fef3c7; color: #d97706; } .ti-bg-red { background: #fee2e2; color: #dc2626; }
    .ti-bg-rose { background: #ffe4e6; color: #e11d48; } .ti-bg-purple { background: #ede9fe; color: #7c3aed; }
    .ti-bg-blue { background: #dbeafe; color: #2563eb; }

    /* ── Funnel ─────────────────────────────────────── */
    .ta-funnel { display: flex; flex-direction: column; justify-content: space-around; gap: 8px; height: 100%; min-height: 220px; }
    .ta-funnel-row { display: flex; align-items: center; gap: 14px; }
    .ta-funnel-bar { height: 46px; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 800; font-size: 1.05rem; }
    .ta-funnel-cap { font-size: .72rem; color: var(--ta-muted); line-height: 1.2; border-bottom: 1px dashed var(--ta-border); padding-bottom: 6px; flex: 1; }
    .ta-funnel-cap b { display: block; font-size: .95rem; color: var(--ta-ink); }

    /* ── Donut ──────────────────────────────────────── */
    .ta-donut { width: 150px; height: 150px; border-radius: 50%; position: relative; flex-shrink: 0; }
    .ta-donut::after { content: ''; position: absolute; inset: 22px; background: #fff; border-radius: 50%; }
    .ta-donut-center { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 1; }
    .ta-donut-center b { font-size: 1.6rem; font-weight: 800; color: var(--ta-ink); line-height: 1; }
    .ta-donut-center span { font-size: .8rem; color: var(--ta-muted); }
    .ta-legend { list-style: none; padding: 0; margin: 0; font-size: .8rem; flex: 1; }
    .ta-legend li { display: flex; align-items: center; gap: 8px; padding: 6px 0; }
    .ta-legend .dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
    .ta-legend .n { margin-left: auto; font-weight: 700; color: var(--ta-ink); }
    .ta-legend .p { color: var(--ta-muted); width: 52px; text-align: right; }

    /* ── Tables ─────────────────────────────────────── */
    .ta-table { width: 100%; border-collapse: collapse; font-size: .8rem; }
    .ta-table th { font-size: .72rem; font-weight: 700; color: var(--ta-ink); background: #f5f8f9; padding: 9px 10px; text-align: left; white-space: nowrap; border-bottom: 1px solid var(--ta-border); }
    .ta-table td { padding: 9px 10px; border-bottom: 1px solid #f0f4f5; color: var(--ta-ink); vertical-align: middle; }
    .ta-table tr:last-child td { border-bottom: none; }
    .ta-table tbody tr:hover td { background: #f8fcfc; }
    .ta-mini-bar { height: 7px; background: #e8eef0; border-radius: 6px; overflow: hidden; }
    .ta-mini-bar > span { display: block; height: 100%; border-radius: 6px; }
    .ta-level { font-size: .7rem; font-weight: 700; padding: 3px 12px; border-radius: 6px; display: inline-block; }
    .lv-High { background: #fee2e2; color: #b91c1c; } .lv-Medium { background: #fef3c7; color: #b45309; } .lv-Normal { background: #dcfce7; color: #15803d; }

    /* ── Attention + activity ───────────────────────── */
    .ta-attn { display: flex; align-items: center; gap: 12px; padding: 11px 12px; border: 1px solid var(--ta-border); border-radius: 12px; text-decoration: none; margin-bottom: 8px; transition: all .15s; }
    .ta-attn:hover { border-color: var(--ta-primary); background: var(--ta-soft); }
    .ta-attn-icon { width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
    .ta-attn b { font-size: 1.25rem; font-weight: 800; color: var(--ta-ink); margin-right: 4px; }
    .ta-attn .t { font-weight: 700; color: var(--ta-ink); font-size: .84rem; }
    .ta-attn .s { font-size: .72rem; color: var(--ta-muted); }
    .ta-attn .chev { margin-left: auto; color: var(--ta-muted); }
    .ta-act { display: flex; align-items: flex-start; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f0f4f5; font-size: .78rem; color: var(--ta-ink); }
    .ta-act:last-child { border-bottom: none; }
    .ta-act .when { margin-left: auto; font-size: .7rem; color: var(--ta-muted); white-space: nowrap; padding-left: 8px; }
    .ta-av { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: .72rem; font-weight: 800; flex-shrink: 0; }
    .av-0 { background: #dcfce7; color: #15803d; } .av-1 { background: #fee2e2; color: #b91c1c; } .av-2 { background: #dbeafe; color: #1d4ed8; }
    .av-3 { background: #fef3c7; color: #b45309; } .av-4 { background: #ede9fe; color: #6d28d9; } .av-5 { background: #ccfbf1; color: #0f766e; } .av-6 { background: #fce7f3; color: #be185d; }

    /* ── Progress table ─────────────────────────────── */
    .ta-toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; padding: 6px 18px 12px; }
    .ta-toolbar .grow { flex: 1 1 220px; }
    .ta-toolbar .sel { flex: 0 1 170px; }
    .ta-toolbar label { display: block; font-size: .72rem; color: var(--ta-muted); margin-bottom: 4px; }
    .ta-page .ta-export { height: 46px; border-radius: 12px; border: 1px solid var(--ta-border); background: #fff; color: var(--ta-ink); font-weight: 600; font-size: .84rem; display: inline-flex; align-items: center; gap: 8px; padding: 0 16px; }
    .ta-page .ta-export:hover { border-color: var(--ta-primary); color: var(--ta-primary); }
    .ta-page .ta-kebab { height: 46px; width: 46px; border-radius: 12px; border: 1px solid var(--ta-border); background: #fff; }
    .ta-table th a.ta-sort { color: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: 3px; }
    .ta-table th a.ta-sort:hover, .ta-table th a.ta-sort.active { color: var(--ta-primary); }
    .ta-sort-arrow { font-size: .65rem; opacity: .4; } .ta-sort.active .ta-sort-arrow { opacity: 1; }
    .ta-score { font-size: .76rem; font-weight: 700; padding: 3px 10px; border-radius: 6px; display: inline-block; }
    .sc-high { background: #dcfce7; color: #15803d; } .sc-ok { background: #ecfccb; color: #4d7c0f; } .sc-mid { background: #fef3c7; color: #b45309; } .sc-low { background: #fee2e2; color: #b91c1c; }
    .ta-pill { font-size: .72rem; font-weight: 700; padding: 4px 12px; border-radius: 6px; white-space: nowrap; display: inline-block; }
    .pl-completed { background: #dcfce7; color: #15803d; } .pl-in_progress { background: #fef3c7; color: #b45309; }
    .pl-not_started { background: #f1f5f9; color: #64748b; } .pl-locked { background: #fee2e2; color: #b91c1c; }
    .ta-journey { background: var(--ta-primary); color: #fff; font-size: .74rem; font-weight: 700; border-radius: 6px; padding: 6px 14px; text-decoration: none; white-space: nowrap; display: inline-block; }
    .ta-journey:hover { background: var(--ta-primary-dark); color: #fff; }
    .ta-results { color: var(--ta-primary); font-size: 1rem; margin-left: 6px; text-decoration: none; }
    .ta-foot { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; padding: 12px 18px 16px; font-size: .78rem; color: var(--ta-muted); }
    .ta-pages { display: flex; gap: 6px; align-items: center; }
    .ta-pages button { min-width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--ta-border); background: #fff; color: var(--ta-ink); font-size: .78rem; font-weight: 600; }
    .ta-pages button.active { background: var(--ta-primary); border-color: var(--ta-primary); color: #fff; }
    .ta-pages button:disabled { opacity: .45; }
    .ta-page .ta-perpage { width: 110px; }

    /* on its own line (below xl) the department card has no neighbour to match: grow with the rows */
    @media (max-width: 1199.98px) {
        .ta-fill { min-height: 0; }
        .ta-fill > .ta-scroll { position: static; max-height: 360px; }
    }
    @media (max-width: 767.98px) {
        .ta-hero { padding: 20px; } .ta-hero h1 { font-size: 1.35rem; }
        .ta-toolbar .sel { flex: 1 1 45%; }
    }
</style>
@endsection

@section('content')
@php
    $pctOf = fn ($x) => $totalEnrolled > 0 ? round($x / $totalEnrolled * 100, 1) : 0;
    $initials = fn ($u) => strtoupper(substr($u->first_name ?? '?', 0, 1) . substr($u->last_name ?? '', 0, 1));
    $stateLabel = ['completed' => 'Completed', 'in_progress' => 'In Progress', 'not_started' => 'Not Started'];
@endphp
<div class="container-xxl flex-grow-1 container-p-y ta-page">

    {{-- ── Hero ─────────────────────────────────────────────────── --}}
    <div class="ta-hero d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div style="position:relative;z-index:1;">
            <h1>Training Activity Dashboard</h1>
            <p>Track employee training progress, completion, assessment scores and pending actions.</p>
        </div>
        <div class="d-flex flex-wrap gap-2" style="position:relative;z-index:1;">
            <a href="{{ route('training.activity', ['view' => 'log']) }}" class="ta-hero-btn"><i class="bx bx-history"></i> Activity Log</a>
            @if($canManage ?? false)
                <a href="{{ route('training.manage.index') }}" class="ta-hero-btn"><i class="bx bx-book-open"></i> Manage Programs</a>
            @endif
        </div>
    </div>

    {{-- ── Filters: they narrow the whole page ──────────────────── --}}
    <form method="GET" action="{{ route('training.activity') }}" id="taFilters" class="ta-panel ta-filter">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-lg">
                <label for="taProgram">Training Program</label>
                <div class="ta-iconsel"><i class="bx bx-book-reader"></i>
                    <select id="taProgram" name="program" class="form-select" data-mirror="program">
                        <option value="">All Training Programs</option>
                        <option value="compulsory" @selected($filters['program'] === 'compulsory')>Compulsory onboarding (Phase 1 &amp; 2)</option>
                        <option value="optional" @selected($filters['program'] === 'optional')>Optional learning (CRM, Learn @ Hi Tech)</option>
                    </select>
                </div>
            </div>
            <div class="col-6 col-lg">
                <label for="taPhase">Phase</label>
                <div class="ta-iconsel"><i class="bx bx-layer"></i>
                    <select id="taPhase" name="phase" class="form-select" data-mirror="phase">
                        <option value="">All Phases</option>
                        <option value="none" @selected($filters['phase'] === 'none')>Not started</option>
                        @foreach($phases as $ph)
                            <option value="{{ $ph->id }}" @selected($filters['phase'] === (string) $ph->id)>Phase {{ $ph->order }}: {{ $ph->title }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-6 col-lg">
                <label for="taDept">Department</label>
                <div class="ta-iconsel"><i class="bx bx-buildings"></i>
                    <select id="taDept" name="department" class="form-select" data-mirror="department">
                        <option value="">All Departments</option>
                        @foreach($departments as $d)
                            <option value="{{ $d->id }}" @selected($filters['department'] === (string) $d->id)>{{ $d->name }}</option>
                        @endforeach
                        <option value="none" @selected($filters['department'] === 'none')>No department</option>
                    </select>
                </div>
            </div>
            <div class="col-6 col-lg">
                <label for="taStatus">Status</label>
                <div class="ta-iconsel"><i class="bx bx-flag"></i>
                    <select id="taStatus" name="status" class="form-select" data-mirror="status">
                        <option value="">All Status</option>
                        <option value="completed" @selected($filters['status'] === 'completed')>Completed</option>
                        <option value="in_progress" @selected($filters['status'] === 'in_progress')>In Progress</option>
                        <option value="not_started" @selected($filters['status'] === 'not_started')>Not Started</option>
                        <option value="held" @selected($filters['status'] === 'held')>Account Locked</option>
                        <option value="failed" @selected($filters['status'] === 'failed')>Has a failed module</option>
                        <option value="low_score" @selected($filters['status'] === 'low_score')>Low score (below 80%)</option>
                    </select>
                </div>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary ta-apply">Apply</button>
            </div>
            <div class="col-auto">
                <a href="{{ route('training.activity') }}" class="ta-reset"><i class="bx bx-refresh"></i> Reset Filters</a>
            </div>
        </div>
        <input type="hidden" name="sort" value="{{ $sort }}">
        <input type="hidden" name="dir" value="{{ $dir }}">
        <div class="ta-summary">
            Showing <strong>{{ $totalEnrolled }}</strong> of {{ $totalAll }} enrolled
            @if($filtered) &middot; filtered @endif
        </div>
    </form>

    {{-- ── KPI cards ────────────────────────────────────────────── --}}
    <div class="row g-3 mb-3">
        @php
            $kpis = [
                ['icon' => 'bx bx-group', 'bg' => 'ti-bg-teal', 'value' => $cards['enrolled'], 'label' => 'Total Enrolled', 'sub' => null,
                 'trend' => $trends['started'] > 0 ? '+' . $trends['started'] . ' this month' : null],
                ['icon' => 'bx bx-check-circle', 'bg' => 'ti-bg-green', 'value' => $cards['completed'], 'label' => 'Completed', 'sub' => $cards['completed_pct'] . '% of enrolled',
                 'trend' => $trends['completed'] > 0 ? '+' . $trends['completed'] . ' this month' : null],
                ['icon' => 'bx bx-play-circle', 'bg' => 'ti-bg-orange', 'value' => $cards['in_progress'], 'label' => 'In Progress', 'sub' => $cards['in_progress_pct'] . '% of enrolled', 'trend' => null],
                ['icon' => 'bx bx-time-five', 'bg' => 'ti-bg-rose', 'value' => $cards['not_started'], 'label' => 'Not Started', 'sub' => $cards['not_started_pct'] . '% of enrolled', 'trend' => null],
                ['icon' => 'bx bx-lock-alt', 'bg' => 'ti-bg-red', 'value' => $cards['locked'], 'label' => 'Account Locked', 'sub' => $cards['locked_pct'] . '% of enrolled', 'trend' => null],
                ['icon' => 'bx bx-bar-chart-alt-2', 'bg' => 'ti-bg-purple', 'value' => $cards['avg_score'] . '%', 'label' => 'Average Assessment Score', 'sub' => null,
                 'trend' => $trends['score'] !== null && $trends['score'] != 0 ? ($trends['score'] > 0 ? '+' : '') . $trends['score'] . ' pts vs last month' : null,
                 'down' => ($trends['score'] ?? 0) < 0],
            ];
        @endphp
        @foreach($kpis as $k)
            <div class="col-6 col-md-4 col-xl-2">
                <div class="ta-panel ta-kpi">
                    <div class="ta-kpi-icon {{ $k['bg'] }}"><i class="{{ $k['icon'] }}"></i></div>
                    <div style="min-width:0;">
                        <div class="ta-kpi-value">{{ $k['value'] }}</div>
                        <div class="ta-kpi-label">{{ $k['label'] }}</div>
                        @if($k['sub'])<div class="ta-kpi-sub">{{ $k['sub'] }}</div>@endif
                        @if($k['trend'])
                            <span class="ta-trend {{ ($k['down'] ?? false) ? 'down' : '' }}">{{ ($k['down'] ?? false) ? '↓' : '↑' }} {{ $k['trend'] }}</span>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ── Funnel · Status · Departments ────────────────────────── --}}
    <div class="row g-3 mb-3">
        <div class="col-lg-6 col-xl-4">
            <div class="ta-panel">
                <div class="ta-panel-head">
                    <h6 class="ta-panel-title">Training Completion Funnel</h6>
                    <a href="#progress" class="ta-panel-link">View Details →</a>
                </div>
                <div class="ta-panel-body">
                    <div class="ta-funnel">
                        @php $fColors = ['#0e9aa5', '#3b82f6', '#22c55e', '#f5b50b']; @endphp
                        @foreach($funnel as $i => $f)
                            @php $w = 100 - $i * 12; @endphp
                            <div class="ta-funnel-row">
                                <div style="width:58%;display:flex;justify-content:center;">
                                    <div class="ta-funnel-bar" style="width:{{ $w }}%;background:{{ $fColors[$i] }};clip-path:polygon(0 0,100% 0,{{ 100 - 5 }}% 100%,5% 100%);">{{ $f['count'] }}</div>
                                </div>
                                <div class="ta-funnel-cap"><b>{{ $f['count'] }}</b>{{ $f['label'] }} ({{ $f['pct'] }}%)</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6 col-xl-3">
            <div class="ta-panel">
                <div class="ta-panel-head">
                    <h6 class="ta-panel-title">Training Status</h6>
                    <a href="#progress" class="ta-panel-link">View Details →</a>
                </div>
                <div class="ta-panel-body ta-center">
                    @php
                        $c1 = $pctOf($cards['completed']); $c2 = $c1 + $pctOf($cards['in_progress']);
                        $donut = $totalEnrolled > 0
                            ? "conic-gradient(#22c55e 0 {$c1}%, #f59e0b {$c1}% {$c2}%, #3b82f6 {$c2}% 100%)"
                            : 'conic-gradient(#e2e8f0 0 100%)';
                    @endphp
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div class="ta-donut" style="background:{{ $donut }};">
                            <div class="ta-donut-center"><b>{{ $totalEnrolled }}</b><span>Enrolled</span></div>
                        </div>
                        <ul class="ta-legend">
                            <li><span class="dot" style="background:#22c55e"></span>Completed <span class="n">{{ $cards['completed'] }}</span><span class="p">({{ $cards['completed_pct'] }}%)</span></li>
                            <li><span class="dot" style="background:#f59e0b"></span>In Progress <span class="n">{{ $cards['in_progress'] }}</span><span class="p">({{ $cards['in_progress_pct'] }}%)</span></li>
                            <li><span class="dot" style="background:#3b82f6"></span>Not Started <span class="n">{{ $cards['not_started'] }}</span><span class="p">({{ $cards['not_started_pct'] }}%)</span></li>
                            <li title="Also counted in In Progress / Not Started"><span class="dot" style="background:#ef4444"></span>Account Locked <span class="n">{{ $cards['locked'] }}</span><span class="p">({{ $cards['locked_pct'] }}%)</span></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="ta-panel">
                <div class="ta-panel-head">
                    <h6 class="ta-panel-title">Department Wise Performance</h6>
                    <a href="{{ request()->fullUrlWithQuery(['sort' => 'department', 'dir' => 'asc']) }}#progress" class="ta-panel-link">View Details →</a>
                </div>
                <div class="ta-panel-body ta-fill"><div class="ta-scroll">
                    <table class="ta-table">
                        <thead><tr><th>Department</th><th>Enrolled</th><th>Completed</th><th>In Progress</th><th style="width:26%"></th><th>Avg. Score</th></tr></thead>
                        <tbody>
                            @forelse($deptStats as $d)
                                @php $bar = $d['progress'] >= 80 ? '#16a34a' : ($d['progress'] > 0 ? '#f59e0b' : '#cbd5e1'); @endphp
                                <tr>
                                    <td>
                                        @if($d['id'])
                                            <a href="{{ request()->fullUrlWithQuery(['department' => $d['id']]) }}" style="color:inherit;text-decoration:none;">{{ $d['name'] }}</a>
                                        @else
                                            {{ $d['name'] }}
                                        @endif
                                    </td>
                                    <td>{{ $d['enrolled'] }}</td>
                                    <td>{{ $d['completed'] }}</td>
                                    <td>{{ $d['in_progress'] }}</td>
                                    <td><div class="ta-mini-bar" title="{{ $d['progress'] }}% average progress"><span style="width:{{ $d['progress'] }}%;background:{{ $bar }}"></span></div></td>
                                    <td style="font-weight:700;color:{{ $d['avg_score'] === null ? '#94a3b8' : ($d['avg_score'] >= 90 ? '#15803d' : ($d['avg_score'] >= 80 ? '#b45309' : '#b91c1c')) }}">{{ $d['avg_score'] === null ? '—' : $d['avg_score'] . '%' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-4">No one to show.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div></div>
            </div>
        </div>
    </div>

    {{-- ── Modules · Attention · Recent ─────────────────────────── --}}
    <div class="row g-3 mb-3">
        <div class="col-xl-4">
            <div class="ta-panel">
                <div class="ta-panel-head">
                    <h6 class="ta-panel-title">Modules Requiring Attention</h6>
                    @if($canManage ?? false)<a href="{{ route('training.manage.index') }}" class="ta-panel-link">View All →</a>@endif
                </div>
                <div class="ta-panel-body">
                    <div class="ta-table-scroll">
                    <table class="ta-table">
                        <thead><tr><th>#</th><th>Module Name</th><th>Failed</th><th>Avg. Score</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse($moduleStats as $i => $m)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td title="{{ $m->phase->title ?? '' }}">{{ $m->title }}</td>
                                    <td style="color:{{ $m->fail_count > 0 ? '#dc2626' : '#94a3b8' }};font-weight:700;">{{ $m->fail_count }}</td>
                                    <td>{{ $m->avg_score !== null ? round($m->avg_score) . '%' : '—' }}</td>
                                    <td><span class="ta-level lv-{{ $m->level }}">{{ $m->level }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">No assessment attempts yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6 col-xl-4">
            <div class="ta-panel">
                <div class="ta-panel-head">
                    <h6 class="ta-panel-title">Employees Needing Attention</h6>
                    <a href="#progress" class="ta-panel-link">View All →</a>
                </div>
                <div class="ta-panel-body">
                    @php $tones = ['red' => 'ti-bg-red', 'orange' => 'ti-bg-orange', 'blue' => 'ti-bg-blue', 'purple' => 'ti-bg-purple']; @endphp
                    @foreach($attention as $a)
                        <a href="{{ request()->fullUrlWithQuery(['status' => $a['key']]) }}#progress" class="ta-attn">
                            <span class="ta-attn-icon {{ $tones[$a['tone']] }}"><i class="{{ $a['icon'] }}"></i></span>
                            <span><b>{{ $a['count'] }}</b><span class="t">{{ $a['title'] }}</span><div class="s">{{ $a['sub'] }}</div></span>
                            <i class="bx bx-chevron-right chev"></i>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-lg-6 col-xl-4">
            <div class="ta-panel">
                <div class="ta-panel-head">
                    <h6 class="ta-panel-title">Recent Training Activity</h6>
                    <a href="{{ route('training.activity', ['view' => 'log', 'tab' => 'log']) }}" class="ta-panel-link">View All →</a>
                </div>
                <div class="ta-panel-body">
                    @forelse($recent as $ev)
                        @php
                            $who = $ev->user; $mod = $ev->module;
                            $modName = ($mod->title ?? 'a module') . ($mod && $mod->phase ? ' – Phase ' . $mod->phase->order : '');
                        @endphp
                        <div class="ta-act">
                            <span class="ta-av av-{{ ($who->id ?? 0) % 7 }}">{{ $who ? $initials($who) : '?' }}</span>
                            <div>
                                <b>{{ $who ? trim($who->first_name . ' ' . $who->last_name) : 'Someone' }}</b>
                                @if($ev->status === 'completed')
                                    completed {{ $modName }}@if($ev->assessment_score !== null) and scored <b style="color:#16a34a">{{ round($ev->assessment_score) }}%</b>@endif
                                @elseif($ev->status === 'failed')
                                    failed {{ $modName }}@if($ev->assessment_score !== null) (Score: <b style="color:#dc2626">{{ round($ev->assessment_score) }}%</b>)@endif
                                @else
                                    started {{ $modName }}
                                @endif
                            </div>
                            <span class="when">{{ $ev->updated_at?->diffForHumans() }}</span>
                        </div>
                    @empty
                        <p class="text-muted text-center py-4 mb-0" style="font-size:.84rem;">No training activity yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- ── Employee Training Progress ───────────────────────────── --}}
    <div class="ta-panel" id="progress">
        <div class="ta-panel-head">
            <h6 class="ta-panel-title">Employee Training Progress ({{ $userStats->count() }})</h6>
        </div>
        <div class="ta-toolbar">
            <div class="grow">
                <div class="ta-iconsel"><i class="bx bx-search"></i>
                    <input type="search" name="q" form="taFilters" value="{{ $filters['q'] }}" class="form-control" placeholder="Search employee, email or code...">
                </div>
            </div>
            <div class="sel"><label>Department</label>
                <select class="form-select ta-mirror" data-target="taDept" aria-label="Department">
                    <option value="">All Departments</option>
                    @foreach($departments as $d)<option value="{{ $d->id }}" @selected($filters['department'] === (string) $d->id)>{{ $d->name }}</option>@endforeach
                    <option value="none" @selected($filters['department'] === 'none')>No department</option>
                </select>
            </div>
            <div class="sel"><label>Training Program</label>
                <select class="form-select ta-mirror" data-target="taProgram" aria-label="Training Program">
                    <option value="">All Programs</option>
                    <option value="compulsory" @selected($filters['program'] === 'compulsory')>Compulsory onboarding</option>
                    <option value="optional" @selected($filters['program'] === 'optional')>Optional learning</option>
                </select>
            </div>
            <div class="sel"><label>Phase</label>
                <select class="form-select ta-mirror" data-target="taPhase" aria-label="Phase">
                    <option value="">All Phases</option>
                    <option value="none" @selected($filters['phase'] === 'none')>Not started</option>
                    @foreach($phases as $ph)<option value="{{ $ph->id }}" @selected($filters['phase'] === (string) $ph->id)>Phase {{ $ph->order }}: {{ $ph->title }}</option>@endforeach
                </select>
            </div>
            <div class="sel"><label>Completion Status</label>
                <select class="form-select ta-mirror" data-target="taStatus" aria-label="Completion Status">
                    <option value="">All Status</option>
                    <option value="completed" @selected($filters['status'] === 'completed')>Completed</option>
                    <option value="in_progress" @selected($filters['status'] === 'in_progress')>In Progress</option>
                    <option value="not_started" @selected($filters['status'] === 'not_started')>Not Started</option>
                    <option value="held" @selected($filters['status'] === 'held')>Account Locked</option>
                </select>
            </div>
            <button type="button" class="ta-export" id="taExport" @disabled($userStats->isEmpty())><i class="bx bx-download"></i> Export Excel</button>
            <div class="dropdown">
                <button type="button" class="ta-kebab" data-bs-toggle="dropdown" aria-label="More"><i class="bx bx-dots-vertical-rounded"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('training.activity') }}"><i class="bx bx-refresh me-2"></i>Reset filters</a></li>
                    @if($canManage ?? false)<li><a class="dropdown-item" href="{{ route('training.manage.index') }}"><i class="bx bx-book-open me-2"></i>Manage programs</a></li>@endif
                </ul>
            </div>
        </div>

        <div style="overflow-x:auto;">
            <table class="ta-table" id="empTable">
                <thead>
                    @php
                        $th = function (string $key, string $label) use ($sort, $dir) {
                            $active = $sort === $key;
                            $next = $active && $dir === 'desc' ? 'asc' : 'desc';
                            $arrow = $active ? ($dir === 'asc' ? '&#9650;' : '&#9660;') : '&#8693;';
                            return '<a class="ta-sort' . ($active ? ' active' : '') . '" href="' . e(request()->fullUrlWithQuery(['sort' => $key, 'dir' => $next])) . '#progress">'
                                . e($label) . ' <span class="ta-sort-arrow">' . $arrow . '</span></a>';
                        };
                    @endphp
                    <tr>
                        <th>#</th>
                        <th>{!! $th('name', 'Employee') !!}</th>
                        <th>{!! $th('department', 'Department') !!}</th>
                        <th>Current Phase</th>
                        <th style="min-width:150px;">{!! $th('pct', 'Progress') !!}</th>
                        <th>{!! $th('avg_score', 'Assessment Score') !!}</th>
                        <th>{!! $th('failed', 'Failed Attempts') !!}</th>
                        <th>{!! $th('last_activity', 'Last Activity') !!}</th>
                        <th>{!! $th('state', 'Status') !!}</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($userStats as $i => $row)
                        @php
                            $u = $row['user'];
                            $bar = $row['pct'] >= 100 ? '#16a34a' : ($row['pct'] > 0 ? '#f59e0b' : '#cbd5e1');
                            $sc = $row['avg_score'];
                            $scClass = $sc >= 90 ? 'sc-high' : ($sc >= 80 ? 'sc-ok' : ($sc >= 60 ? 'sc-mid' : 'sc-low'));
                            $csv = [
                                'name' => trim($u->first_name . ' ' . $u->last_name), 'code' => $u->code, 'email' => $u->email,
                                'department' => optional($u->department)->name,
                                'phase' => $row['current_phase'] ? 'Phase ' . $row['current_phase']->order . ': ' . $row['current_phase']->title : 'Not started',
                                'module' => $row['current_module'],
                                'done' => $row['completed'] . '/' . $row['total'], 'pct' => $row['pct'] . '%',
                                'avg' => $sc > 0 ? $sc . '%' : '', 'failed' => $row['failed_count'],
                                'last' => $row['last_activity'] ? $row['last_activity']->format('d M Y H:i') : '',
                                'status' => $stateLabel[$row['state']], 'locked' => $row['held'] ? 'Yes' : 'No',
                            ];
                        @endphp
                        <tr data-row="{{ json_encode($csv) }}">
                            <td class="text-muted">{{ $i + 1 }}</td>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <span class="ta-av av-{{ $u->id % 7 }}">{{ $initials($u) }}</span>
                                    <div style="min-width:0;">
                                        <div style="font-weight:700;">{{ $u->first_name }} {{ $u->last_name }}</div>
                                        <div style="font-size:.7rem;color:var(--ta-muted);">{{ $u->code ? $u->code . ' · ' : '' }}{{ $u->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ optional($u->department)->name ?? '—' }}</td>
                            <td>
                                @if($row['current_phase'])
                                    <div style="font-weight:700;">Phase {{ $row['current_phase']->order }}</div>
                                    <div style="font-size:.7rem;color:var(--ta-muted);">{{ $row['current_module'] ?? $row['current_phase']->title }}</div>
                                @else
                                    <span style="color:#94a3b8;">Not started</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="ta-mini-bar" style="flex:1;"><span style="width:{{ $row['pct'] }}%;background:{{ $bar }}"></span></div>
                                    <span style="font-weight:700;color:{{ $row['pct'] >= 100 ? '#16a34a' : ($row['pct'] > 0 ? '#d97706' : '#94a3b8') }};min-width:36px;">{{ $row['pct'] }}%</span>
                                </div>
                            </td>
                            <td>@if($sc > 0)<span class="ta-score {{ $scClass }}">{{ $sc }}%</span>@else<span style="color:#94a3b8;">-</span>@endif</td>
                            <td style="color:{{ $row['failed_count'] > 0 ? '#dc2626' : 'inherit' }};font-weight:{{ $row['failed_count'] > 0 ? 700 : 400 }};">{{ $row['failed_count'] }}</td>
                            <td style="color:var(--ta-muted);">{{ $row['last_activity'] ? $row['last_activity']->diffForHumans() : '-' }}</td>
                            <td>
                                @if($row['held'])
                                    <span class="ta-pill pl-locked" title="Account locked until the compulsory training is passed">Locked</span>
                                @else
                                    <span class="ta-pill pl-{{ $row['state'] }}">{{ $stateLabel[$row['state']] }}</span>
                                @endif
                            </td>
                            <td style="white-space:nowrap;">
                                <a href="{{ route('training.portal') }}?user_id={{ $u->id }}" class="ta-journey">View Journey</a>
                                <a href="{{ route('training.report-card', $u->id) }}" class="ta-results" title="Results (report card)"><i class="bx bx-certification"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" style="text-align:center;padding:40px;color:var(--ta-muted);">
                                @if($filtered)
                                    No one matches these filters. <a href="{{ route('training.activity') }}" style="color:var(--ta-primary);font-weight:600;">Reset Filters</a>
                                @else
                                    No employees have started training yet.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="ta-foot">
            <span id="taShowing">Showing {{ $userStats->isEmpty() ? 0 : 1 }} to {{ min(10, $userStats->count()) }} of {{ $userStats->count() }} employees</span>
            <div class="d-flex align-items-center gap-2">
                <div class="ta-pages" id="taPages"></div>
                <select class="form-select ta-perpage" id="taPerPage" aria-label="Rows per page">
                    <option value="10">10 / page</option>
                    <option value="25">25 / page</option>
                    <option value="50">50 / page</option>
                    <option value="100">100 / page</option>
                    <option value="0">All</option>
                </select>
            </div>
        </div>
    </div>

</div>
@endsection

@section('page-script')
<script>
    // Plain JS on purpose: jQuery/bootstrap load deferred.
    (function () {
        var form = document.getElementById('taFilters');

        // Filters apply as soon as a dropdown changes; the table's dropdowns
        // mirror the ones at the top.
        document.querySelectorAll('#taFilters select').forEach(function (el) {
            el.addEventListener('change', function () { form.submit(); });
        });
        document.querySelectorAll('.ta-mirror').forEach(function (el) {
            el.addEventListener('change', function () {
                var target = document.getElementById(el.dataset.target);
                if (target) { target.value = el.value; form.submit(); }
            });
        });

        // Pagination of the rows shown (everything is already filtered and sorted).
        var rows = Array.prototype.slice.call(document.querySelectorAll('#empTable tbody tr[data-row]'));
        var perSel = document.getElementById('taPerPage');
        var pagesEl = document.getElementById('taPages');
        var showing = document.getElementById('taShowing');
        var page = 1;

        function render() {
            var per = parseInt(perSel.value, 10) || rows.length || 1;
            var pages = Math.max(1, Math.ceil(rows.length / per));
            if (page > pages) page = pages;
            var from = (page - 1) * per;
            rows.forEach(function (tr, i) { tr.style.display = (i >= from && i < from + per) ? '' : 'none'; });
            showing.textContent = rows.length
                ? 'Showing ' + (from + 1) + ' to ' + Math.min(from + per, rows.length) + ' of ' + rows.length + ' employees'
                : 'Showing 0 of 0 employees';

            pagesEl.innerHTML = '';
            if (pages <= 1) return;
            var btn = function (label, target, opts) {
                var b = document.createElement('button');
                b.type = 'button'; b.innerHTML = label;
                if (opts && opts.active) b.className = 'active';
                if (opts && opts.disabled) b.disabled = true;
                if (target) b.addEventListener('click', function () { page = target; render(); });
                pagesEl.appendChild(b);
            };
            btn('&lsaquo;', page - 1, { disabled: page === 1 });
            var shown = {};
            [1, 2, page - 1, page, page + 1, pages - 1, pages].forEach(function (p) { if (p >= 1 && p <= pages) shown[p] = true; });
            var last = 0;
            Object.keys(shown).map(Number).sort(function (a, b) { return a - b; }).forEach(function (p) {
                if (p - last > 1) btn('&hellip;', null, { disabled: true });
                btn(String(p), p, { active: p === page });
                last = p;
            });
            btn('&rsaquo;', page + 1, { disabled: page === pages });
        }
        if (perSel) { perSel.addEventListener('change', function () { page = 1; render(); }); render(); }

        // Export every row shown (filters and sort applied, all pages) as a CSV Excel opens.
        var exp = document.getElementById('taExport');
        if (exp) exp.addEventListener('click', function () {
            var head = ['Employee', 'Employee code', 'Email', 'Department', 'Current phase', 'Current module', 'Modules done', 'Progress', 'Avg score', 'Failed attempts', 'Last activity', 'Status', 'Account locked'];
            var keys = ['name', 'code', 'email', 'department', 'phase', 'module', 'done', 'pct', 'avg', 'failed', 'last', 'status', 'locked'];
            var cell = function (v) { v = (v === null || v === undefined) ? '' : String(v); if (/^[=+\-@]/.test(v)) v = "'" + v; return '"' + v.replace(/"/g, '""') + '"'; };
            var lines = [head.map(cell).join(',')];
            rows.forEach(function (tr) {
                var r = JSON.parse(tr.dataset.row);
                lines.push(keys.map(function (k) { return cell(r[k]); }).join(','));
            });
            var blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'training-results-' + new Date().toISOString().slice(0, 10) + '.csv';
            document.body.appendChild(a); a.click(); a.remove();
        });
    })();
</script>
@endsection
