@extends('layouts.layoutMaster')

@section('title', 'Training Activity Dashboard')

@section('page-style')
<style>
    :root {
        --ta-primary: #005f6b;
        --ta-secondary: #00acc1;
        --ta-gradient: linear-gradient(135deg, #00474e 0%, #007a87 55%, #00acc1 100%);
        --ta-border: #d0e8ea;
        --ta-ink: #1e293b;
        --ta-muted: #64748b;
        --ta-soft: #f0f9fa;
    }

    /* ── Header ─────────────────────────────────────── */
    .ta-header {
        background: var(--ta-gradient);
        border-radius: 20px; padding: 28px 36px;
        color: white; margin-bottom: 28px;
        box-shadow: 0 12px 36px rgba(0,96,100,0.22);
        position: relative; overflow: hidden;
    }
    .ta-header::before {
        content:''; position:absolute; top:-60px; right:-60px;
        width:260px; height:260px;
        background:rgba(255,255,255,0.07); border-radius:50%;
    }
    .ta-header::after {
        content:''; position:absolute; bottom:-50px; right:200px;
        width:160px; height:160px;
        background:rgba(255,255,255,0.05); border-radius:50%;
    }
    .ta-header-inner { position:relative; z-index:1; }

    /* ── Stat Cards ─────────────────────────────────── */
    .stat-card {
        background: white; border-radius: 16px;
        border: 1px solid var(--ta-border);
        padding: 20px 22px;
        box-shadow: 0 4px 16px rgba(0,96,100,0.06);
        display: flex; align-items: center; gap: 16px;
        transition: box-shadow 0.18s, transform 0.18s;
    }
    .stat-card:hover { box-shadow: 0 8px 24px rgba(0,96,100,0.12); transform: translateY(-2px); }
    .stat-icon {
        width: 52px; height: 52px; border-radius: 14px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.4rem;
    }
    .stat-value { font-size: 1.7rem; font-weight: 800; color: var(--ta-ink); line-height: 1; }
    .stat-label { font-size: 0.78rem; color: var(--ta-muted); font-weight: 500; margin-top: 3px; }

    /* ── Section Cards ───────────────────────────────── */
    .ta-card {
        background: white; border-radius: 16px;
        border: 1px solid var(--ta-border);
        box-shadow: 0 4px 16px rgba(0,96,100,0.05);
        overflow: hidden; margin-bottom: 24px;
    }
    .ta-card-header {
        padding: 16px 22px;
        border-bottom: 1px solid #f0f8f8;
        background: linear-gradient(to right, #f3fbfb, white);
        display: flex; align-items: center; justify-content: space-between; gap: 12px;
    }
    .ta-card-title { font-size: 0.95rem; font-weight: 700; color: var(--ta-ink); margin: 0; }
    .ta-card-body { padding: 20px 22px; }

    /* ── Phase Funnel ────────────────────────────────── */
    .phase-funnel-row {
        display: flex; align-items: center; gap: 14px;
        padding: 10px 0;
        border-bottom: 1px solid #f4fafa;
    }
    .phase-funnel-row:last-child { border-bottom: none; }
    .phase-funnel-badge {
        width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: 0.9rem; color: white;
    }
    .phase-bar-wrap { flex: 1; }
    .phase-bar-label {
        display: flex; justify-content: space-between;
        font-size: 0.78rem; font-weight: 600; color: var(--ta-ink); margin-bottom: 5px;
    }
    .phase-bar-track { height: 10px; background: #e8f4f5; border-radius: 10px; overflow: hidden; }
    .phase-bar-fill { height: 100%; border-radius: 10px; transition: width 0.8s ease; }
    .phase-count-pill {
        font-size: 0.72rem; font-weight: 700;
        padding: 3px 10px; border-radius: 20px;
        background: #e8f4f5; color: var(--ta-primary);
        flex-shrink: 0; white-space: nowrap;
    }

    /* ── Employee Table ──────────────────────────────── */
    .emp-table { width: 100%; border-collapse: collapse; }
    .emp-table th {
        font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.5px; color: var(--ta-muted);
        padding: 10px 14px; border-bottom: 1px solid #f0f4f4;
        background: #f8fbfb; text-align: left; white-space: nowrap;
    }
    .emp-table td {
        padding: 11px 14px; border-bottom: 1px solid #f4f8f8;
        font-size: 0.83rem; color: var(--ta-ink); vertical-align: middle;
    }
    .emp-table tr:last-child td { border-bottom: none; }
    .emp-table tr:hover td { background: #f8fdfd; }

    .emp-avatar {
        width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.8rem; font-weight: 700; color: white;
    }
    .mini-bar-track { height: 6px; background: #e8f4f5; border-radius: 6px; width: 80px; overflow: hidden; display: inline-block; }
    .mini-bar-fill { height: 100%; border-radius: 6px; }

    .status-pill {
        padding: 3px 10px; border-radius: 20px;
        font-size: 0.68rem; font-weight: 700; white-space: nowrap;
    }
    .sp-completed { background: #dcfce7; color: #166534; }
    .sp-progress  { background: #fef3c7; color: #92400e; }
    .sp-not_started { background: #f1f5f9; color: #64748b; }

    .score-badge {
        font-size: 0.78rem; font-weight: 700;
        padding: 3px 9px; border-radius: 8px;
    }
    .score-high { background: #dcfce7; color: #166534; }
    .score-mid  { background: #fef3c7; color: #92400e; }
    .score-low  { background: #fee2e2; color: #991b1b; }
    .score-none { background: #f1f5f9; color: #94a3b8; }

    /* ── Difficult Modules ───────────────────────────── */
    .mod-diff-row {
        display: flex; align-items: center; gap: 12px;
        padding: 10px 0; border-bottom: 1px solid #f4fafa;
    }
    .mod-diff-row:last-child { border-bottom: none; }
    .mod-diff-icon {
        width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem;
    }
    .fail-rate-badge {
        font-size: 0.72rem; font-weight: 700; padding: 3px 9px;
        border-radius: 20px; white-space: nowrap;
    }
    .fr-high { background: #fee2e2; color: #991b1b; }
    .fr-mid  { background: #fef3c7; color: #92400e; }
    .fr-low  { background: #dcfce7; color: #166534; }

    /* ── Search ──────────────────────────────────────── */
    #empSearch {
        border: 1.5px solid var(--ta-border); border-radius: 20px;
        padding: 6px 14px; font-size: 0.82rem; outline: none;
        width: 220px; transition: border-color 0.15s;
    }
    #empSearch:focus { border-color: var(--ta-secondary); }

    /* ── Phase colors ────────────────────────────────── */
    .pc-1{background:linear-gradient(135deg,#005f6b,#0097a7);}
    .pc-2{background:linear-gradient(135deg,#1565c0,#42a5f5);}
    .pc-3{background:linear-gradient(135deg,#6a1b9a,#ab47bc);}
    .pc-4{background:linear-gradient(135deg,#e65100,#ff7043);}
    .pc-5{background:linear-gradient(135deg,#2e7d32,#66bb6a);}
    .pf-1{background:linear-gradient(90deg,#0097a7,#4dd0e1);}
    .pf-2{background:linear-gradient(90deg,#1e88e5,#90caf9);}
    .pf-3{background:linear-gradient(90deg,#ab47bc,#e040fb);}
    .pf-4{background:linear-gradient(90deg,#ff7043,#ffab91);}
    .pf-5{background:linear-gradient(90deg,#66bb6a,#a5d6a7);}

    @media(max-width:767.98px) {
        .ta-header { padding: 22px 18px; }
        .ta-card-body { padding: 14px; }
        #empSearch { width: 100%; }
    }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

    {{-- ── Header ─────────────────────────────────────── --}}
    <div class="ta-header animate__animated animate__fadeIn">
        <div class="ta-header-inner d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h2 class="fw-bold text-white mb-1" style="font-size:1.4rem;">Training Activity Dashboard</h2>
                <p class="mb-0" style="color:rgba(255,255,255,0.78);font-size:0.86rem;">
                    Monitor employee progress, assessment scores, and phase completion across your team.
                </p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('training.manage.index') }}"
                    style="border:none;background:rgba(255,255,255,0.18);color:white;font-size:0.78rem;font-weight:600;border-radius:999px;padding:8px 18px;text-decoration:none;border:1px solid rgba(255,255,255,0.3);">
                    Manage Content
                </a>
            </div>
        </div>
    </div>

    {{-- ── Stat Cards ──────────────────────────────────── --}}
    <div class="row g-4 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#e0f2f1;">
                    <span style="font-size:1.5rem;">&#128101;</span>
                </div>
                <div>
                    <div class="stat-value">{{ $totalEnrolled }}</div>
                    <div class="stat-label">Employees Enrolled</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#dcfce7;">
                    <span style="font-size:1.5rem;">&#9989;</span>
                </div>
                <div>
                    <div class="stat-value" style="color:#16a34a;">{{ $totalCompleted }}</div>
                    <div class="stat-label">Fully Completed</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#fef3c7;">
                    <span style="font-size:1.5rem;">&#9654;</span>
                </div>
                <div>
                    <div class="stat-value" style="color:#d97706;">{{ $inProgress }}</div>
                    <div class="stat-label">In Progress</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#ede9fe;">
                    <span style="font-size:1.5rem;">&#127941;</span>
                </div>
                <div>
                    <div class="stat-value" style="color:#7c3aed;">{{ $avgScore }}%</div>
                    <div class="stat-label">Avg Assessment Score</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">

        {{-- ── Phase Completion Funnel ─────────────────── --}}
        <div class="col-lg-5">
            <div class="ta-card h-100">
                <div class="ta-card-header">
                    <h6 class="ta-card-title">Phase Completion Funnel</h6>
                    <span style="font-size:0.75rem;color:var(--ta-muted);">{{ $totalEnrolled }} employees</span>
                </div>
                <div class="ta-card-body">
                    @forelse($phaseStats as $ps)
                    @php $ci = (($ps['phase']->order - 1) % 5) + 1; @endphp
                    <div class="phase-funnel-row">
                        <div class="phase-funnel-badge pc-{{ $ci }}">{{ $ps['phase']->order }}</div>
                        <div class="phase-bar-wrap">
                            <div class="phase-bar-label">
                                <span>{{ $ps['phase']->title }}</span>
                            </div>
                            <div class="phase-bar-track">
                                <div class="phase-bar-fill pf-{{ $ci }}" style="width:{{ $ps['pct'] }}%;"></div>
                            </div>
                        </div>
                        <div class="phase-count-pill">{{ $ps['completed_count'] }}/{{ $ps['total'] }}</div>
                    </div>
                    @empty
                    <p class="text-muted text-center py-4 mb-0" style="font-size:0.84rem;">No phases created yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ── Hardest Modules ─────────────────────────── --}}
        <div class="col-lg-7">
            <div class="ta-card h-100">
                <div class="ta-card-header">
                    <h6 class="ta-card-title">Modules with Most Failures</h6>
                    <span style="font-size:0.75rem;color:var(--ta-muted);">Top {{ $moduleStats->count() }} by fail count</span>
                </div>
                <div class="ta-card-body">
                    @forelse($moduleStats as $ms)
                    @php
                        $failRate = $ms->total_attempts > 0 ? round($ms->fail_count / $ms->total_attempts * 100) : 0;
                        $frClass = $failRate >= 50 ? 'fr-high' : ($failRate >= 25 ? 'fr-mid' : 'fr-low');
                    @endphp
                    <div class="mod-diff-row">
                        <div class="mod-diff-icon
                            @if($ms->content_type==='video') " style="background:#fff8e1;">
                            <i class="ti ti-player-play" style="color:#f59e0b;"></i>
                        @elseif($ms->content_type==='policy') " style="background:#e3f2fd;">
                            <i class="ti ti-file-text" style="color:#1e88e5;"></i>
                        @else " style="background:#e8f5e9;">
                            <i class="ti ti-book" style="color:#43a047;"></i>
                        @endif
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:0.83rem;font-weight:600;color:var(--ta-ink);" class="text-truncate">{{ $ms->title }}</div>
                            <div style="font-size:0.72rem;color:var(--ta-muted);">{{ $ms->phase->title ?? '—' }} &middot; {{ $ms->total_attempts }} attempts</div>
                        </div>
                        <div style="display:flex;gap:6px;align-items:center;flex-shrink:0;">
                            <span style="font-size:0.72rem;color:#16a34a;font-weight:600;">{{ $ms->pass_count }} passed</span>
                            <span class="fail-rate-badge {{ $frClass }}">{{ $ms->fail_count }} failed</span>
                        </div>
                    </div>
                    @empty
                    <p class="text-muted text-center py-4 mb-0" style="font-size:0.84rem;">No assessment attempts recorded yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- ── Employee Activity Table ─────────────────────── --}}
    <div class="ta-card">
        <div class="ta-card-header">
            <h6 class="ta-card-title">Employee Progress Tracker</h6>
            <input type="text" id="empSearch" placeholder="Search employee..." oninput="filterTable()">
        </div>
        <div style="overflow-x:auto;">
            <table class="emp-table" id="empTable">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Current Phase</th>
                        <th>Modules Done</th>
                        <th>Overall Progress</th>
                        <th>Avg Score</th>
                        <th>Failed Attempts</th>
                        <th>Last Activity</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($userStats as $row)
                    @php
                        $u = $row['user'];
                        $initials = strtoupper(substr($u->first_name ?? '?', 0, 1) . substr($u->last_name ?? '', 0, 1));
                        $avatarColors = ['#005f6b','#1565c0','#6a1b9a','#e65100','#2e7d32','#0097a7','#c62828'];
                        $avatarColor = $avatarColors[$u->id % count($avatarColors)];
                        $scoreClass = $row['avg_score'] >= 80 ? 'score-high' : ($row['avg_score'] >= 50 ? 'score-mid' : ($row['avg_score'] > 0 ? 'score-low' : 'score-none'));
                        $barColor = $row['pct'] >= 80 ? '#22c55e' : ($row['pct'] >= 40 ? '#f59e0b' : '#94a3b8');
                    @endphp
                    <tr data-name="{{ strtolower($u->first_name . ' ' . $u->last_name) }}">
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div class="emp-avatar" style="background:{{ $avatarColor }};">{{ $initials }}</div>
                                <div>
                                    <div style="font-weight:600;font-size:0.84rem;">{{ $u->first_name }} {{ $u->last_name }}</div>
                                    <div style="font-size:0.72rem;color:var(--ta-muted);">{{ $u->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($row['current_phase'])
                                @php $ci = (($row['current_phase']->order - 1) % 5) + 1; @endphp
                                <span style="font-size:0.76rem;font-weight:700;padding:3px 10px;border-radius:20px;color:white;" class="pc-{{ $ci }}">
                                    Phase {{ $row['current_phase']->order }}
                                </span>
                                <div style="font-size:0.71rem;color:var(--ta-muted);margin-top:2px;">{{ $row['current_phase']->title }}</div>
                            @else
                                <span style="font-size:0.76rem;color:#94a3b8;">Not started</span>
                            @endif
                        </td>
                        <td>
                            <span style="font-weight:700;color:var(--ta-ink);">{{ $row['completed'] }}</span>
                            <span style="color:var(--ta-muted);font-size:0.78rem;">/ {{ $row['total'] }}</span>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div class="mini-bar-track">
                                    <div class="mini-bar-fill" style="width:{{ $row['pct'] }}%;background:{{ $barColor }};"></div>
                                </div>
                                <span style="font-size:0.78rem;font-weight:700;color:{{ $barColor }};">{{ $row['pct'] }}%</span>
                            </div>
                        </td>
                        <td>
                            <span class="score-badge {{ $scoreClass }}">
                                {{ $row['avg_score'] > 0 ? $row['avg_score'].'%' : '—' }}
                            </span>
                        </td>
                        <td>
                            @if($row['failed_count'] > 0)
                                <span style="color:#ef4444;font-weight:700;">{{ $row['failed_count'] }}</span>
                            @else
                                <span style="color:#94a3b8;">0</span>
                            @endif
                        </td>
                        <td style="color:var(--ta-muted);font-size:0.78rem;">
                            {{ $row['last_activity'] ? $row['last_activity']->diffForHumans() : '—' }}
                        </td>
                        <td>
                            @if($row['status'] === 'completed')
                                <span class="status-pill sp-completed">Completed</span>
                            @elseif($row['completed'] > 0)
                                <span class="status-pill sp-progress">In Progress</span>
                            @else
                                <span class="status-pill sp-not_started">Not Started</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('training.portal') }}?user_id={{ $u->id }}"
                                style="border:none;background:#005f6b;color:white;font-size:0.72rem;font-weight:600;border-radius:999px;padding:5px 12px;text-decoration:none;white-space:nowrap;">
                                View Journey
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" style="text-align:center;padding:40px;color:var(--ta-muted);font-size:0.86rem;">
                            No employees have started training yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@section('page-script')
<script>
    function filterTable() {
        const q = document.getElementById('empSearch').value.toLowerCase();
        document.querySelectorAll('#empTable tbody tr').forEach(row => {
            const name = row.dataset.name || '';
            row.style.display = name.includes(q) ? '' : 'none';
        });
    }
</script>
@endsection
