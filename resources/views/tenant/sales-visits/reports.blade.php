@extends('layouts.layoutMaster')

@section('title', 'Visit Reports & Analytics')

@section('page-style')
<style>
    /* ── Hero ──────────────────────────────────────────────── */
    .rpt-hero {
        background: linear-gradient(135deg, #0d9488 0%, #0f766e 50%, #134e4a 100%);
        border-radius: 1rem;
        padding: 2.5rem 2rem;
        color: #fff;
        margin-bottom: 2rem;
        box-shadow: 0 8px 32px rgba(13,148,136,.35);
        position: relative;
        overflow: hidden;
    }
    .rpt-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    }
    .rpt-hero h1 { font-size: 1.85rem; font-weight: 700; margin-bottom: .3rem; }
    .rpt-hero p  { opacity: .85; font-size: .95rem; margin-bottom: 0; }

    /* ── Date filter ───────────────────────────────────────── */
    .date-filter-form {
        background: rgba(255,255,255,.12);
        border: 1px solid rgba(255,255,255,.25);
        border-radius: .75rem;
        padding: .75rem 1.25rem;
        display: flex;
        flex-wrap: wrap;
        gap: .75rem;
        align-items: flex-end;
    }
    .date-filter-form label { color: rgba(255,255,255,.8); font-size: .78rem; font-weight: 600; margin-bottom: .2rem; }
    .date-filter-form input[type=date] {
        background: rgba(255,255,255,.15);
        border: 1px solid rgba(255,255,255,.3);
        color: #fff;
        border-radius: .5rem;
        padding: .35rem .75rem;
        font-size: .9rem;
        width: 160px;
    }
    .date-filter-form input[type=date]::-webkit-calendar-picker-indicator { filter: invert(1); }
    .date-filter-form .btn-filter {
        background: #fff;
        color: #0d9488;
        border: none;
        border-radius: .5rem;
        padding: .38rem 1.1rem;
        font-weight: 700;
        font-size: .88rem;
        transition: transform .15s;
    }
    .date-filter-form .btn-filter:hover { transform: translateY(-1px); color: #0f766e; }

    /* ── KPI Cards ─────────────────────────────────────────── */
    .kpi-card {
        border-radius: 1rem;
        padding: 1.4rem 1.5rem;
        box-shadow: 0 4px 20px rgba(0,0,0,.08);
        color: #fff;
        position: relative;
        overflow: hidden;
        transition: transform .2s, box-shadow .2s;
    }
    .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(0,0,0,.14); }
    .kpi-card .kpi-icon {
        position: absolute;
        right: 1.25rem;
        top: 1rem;
        font-size: 2.5rem;
        opacity: .2;
    }
    .kpi-card .kpi-value {
        font-size: 2rem;
        font-weight: 800;
        line-height: 1;
        margin-bottom: .2rem;
    }
    .kpi-card .kpi-label {
        font-size: .78rem;
        font-weight: 600;
        letter-spacing: .06em;
        text-transform: uppercase;
        opacity: .85;
    }
    .kpi-teal    { background: linear-gradient(135deg,#0d9488,#0f766e); }
    .kpi-green   { background: linear-gradient(135deg,#10b981,#059669); }
    .kpi-amber   { background: linear-gradient(135deg,#f59e0b,#d97706); }
    .kpi-red     { background: linear-gradient(135deg,#ef4444,#dc2626); }
    .kpi-blue    { background: linear-gradient(135deg,#3b82f6,#2563eb); }
    .kpi-purple  { background: linear-gradient(135deg,#8b5cf6,#7c3aed); }

    /* ── Chart cards ───────────────────────────────────────── */
    .chart-card {
        background: #fff;
        border-radius: 1rem;
        box-shadow: 0 4px 20px rgba(0,0,0,.07);
        padding: 1.5rem;
        height: 100%;
    }
    .chart-card h6 {
        font-weight: 700;
        color: #0d9488;
        margin-bottom: 1.25rem;
        font-size: .95rem;
    }

    /* ── Tables ────────────────────────────────────────────── */
    .analytics-table-card {
        background: #fff;
        border-radius: 1rem;
        box-shadow: 0 4px 20px rgba(0,0,0,.07);
        overflow: hidden;
    }
    .analytics-table-card .table-title {
        padding: 1.25rem 1.5rem .75rem;
        font-weight: 700;
        color: #0d9488;
        font-size: .95rem;
        border-bottom: 1px solid #f3f4f6;
    }
    .analytics-table-card .table thead th {
        background: #f8fafc;
        font-size: .75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #6b7280;
        border-bottom: 1px solid #e5e7eb;
        padding: .75rem 1.25rem;
    }
    .analytics-table-card .table tbody td {
        padding: .75rem 1.25rem;
        vertical-align: middle;
        font-size: .9rem;
        color: #374151;
        border-bottom: 1px solid #f3f4f6;
    }
    .analytics-table-card .table tbody tr:hover td { background: #f0fdfa; }
    .badge-pill-teal {
        background: #ccfbf1;
        color: #0f766e;
        border-radius: 2rem;
        padding: .2rem .7rem;
        font-size: .75rem;
        font-weight: 700;
    }
    .completion-bar {
        height: 6px;
        background: #e5e7eb;
        border-radius: 3px;
        overflow: hidden;
        width: 80px;
        display: inline-block;
        vertical-align: middle;
        margin-left: .5rem;
    }
    .completion-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #0d9488, #10b981);
        border-radius: 3px;
    }
</style>
@endsection

@section('content')
<div class="animate__animated animate__fadeIn">

    {{-- ── Hero Header ───────────────────────────────────────────── --}}
    <div class="rpt-hero">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-4">
            <div>
                <h1><i class="ti ti-chart-bar me-2"></i>Sales Visit Analytics</h1>
                <p>Comprehensive insights into field visit performance and outcomes</p>
            </div>
            <form method="GET" action="{{ route('sales-visits.reports') }}" class="date-filter-form">
                <div>
                    <label for="date_from">From</label>
                    <input type="date" id="date_from" name="date_from"
                           value="{{ $dateFrom ? $dateFrom->format('Y-m-d') : '' }}">
                </div>
                <div>
                    <label for="date_to">To</label>
                    <input type="date" id="date_to" name="date_to"
                           value="{{ $dateTo ? $dateTo->format('Y-m-d') : '' }}">
                </div>
                <button type="submit" class="btn-filter">
                    <i class="ti ti-filter me-1"></i> Filter
                </button>
                @if(request()->hasAny(['date_from','date_to']))
                    <a href="{{ route('sales-visits.reports') }}"
                       class="btn-filter text-decoration-none" style="background:rgba(255,255,255,.2);color:#fff;">
                        <i class="ti ti-x me-1"></i> Clear
                    </a>
                @endif
            </form>
        </div>
    </div>

    {{-- ── KPI Row ─────────────────────────────────────────────── --}}
    <div class="row g-4 mb-4">
        <div class="col-6 col-md-4 col-xl-2">
            <div class="kpi-card kpi-teal">
                <div class="kpi-icon"><i class="ti ti-briefcase"></i></div>
                <div class="kpi-value">{{ number_format($kpis['total']) }}</div>
                <div class="kpi-label">Total Visits</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="kpi-card kpi-green">
                <div class="kpi-icon"><i class="ti ti-circle-check"></i></div>
                <div class="kpi-value">{{ number_format($kpis['completed']) }}</div>
                <div class="kpi-label">Completed</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="kpi-card kpi-amber">
                <div class="kpi-icon"><i class="ti ti-clock"></i></div>
                <div class="kpi-value">{{ number_format($kpis['pending']) }}</div>
                <div class="kpi-label">Pending</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="kpi-card kpi-red">
                <div class="kpi-icon"><i class="ti ti-ban"></i></div>
                <div class="kpi-value">{{ number_format($kpis['cancelled']) }}</div>
                <div class="kpi-label">Cancelled</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="kpi-card kpi-blue">
                <div class="kpi-icon"><i class="ti ti-flask"></i></div>
                <div class="kpi-value">{{ number_format($kpis['trials']) }}</div>
                <div class="kpi-label">Product Trials</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="kpi-card kpi-purple">
                <div class="kpi-icon"><i class="ti ti-percentage"></i></div>
                <div class="kpi-value">{{ number_format($kpis['completion_rate'], 1) }}%</div>
                <div class="kpi-label">Completion Rate</div>
            </div>
        </div>
    </div>

    {{-- ── Charts Row ──────────────────────────────────────────── --}}
    <div class="row g-4 mb-4">

        {{-- Doughnut: Visit Types --}}
        <div class="col-lg-5">
            <div class="chart-card">
                <h6><i class="ti ti-chart-donut me-2"></i>Visit Types Breakdown</h6>
                <div style="max-height:280px;display:flex;justify-content:center;">
                    <canvas id="visitTypeChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Bar: Daily Visits --}}
        <div class="col-lg-7">
            <div class="chart-card">
                <h6><i class="ti ti-chart-bar me-2"></i>Daily Visit Volume</h6>
                <canvas id="dailyVisitsChart" style="max-height:280px;"></canvas>
            </div>
        </div>

    </div>

    {{-- ── Per-Salesperson Table ───────────────────────────────── --}}
    <div class="analytics-table-card mb-4">
        <div class="table-title">
            <i class="ti ti-user-check me-2"></i>Per-Salesperson Performance
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Salesperson</th>
                        <th class="text-center">Total</th>
                        <th class="text-center">Completed</th>
                        <th class="text-center">Pending</th>
                        <th class="text-center">Cancelled</th>
                        <th class="text-center">Trials</th>
                        <th class="text-center">Rate</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bySalesperson as $row)
                    @php
                        $rate = $row->total > 0 ? round(($row->completed / $row->total) * 100) : 0;
                    @endphp
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span style="width:32px;height:32px;border-radius:50%;background:#ccfbf1;color:#0f766e;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:.8rem;flex-shrink:0;">
                                    {{ strtoupper(substr($row->salesperson->name ?? 'S', 0, 1)) }}
                                </span>
                                <span class="fw-semibold">{{ $row->salesperson->name ?? '—' }}</span>
                            </div>
                        </td>
                        <td class="text-center fw-bold">{{ $row->total }}</td>
                        <td class="text-center">
                            <span class="badge-pill-teal">{{ $row->completed }}</span>
                        </td>
                        <td class="text-center">
                            <span style="background:#fef3c7;color:#92400e;border-radius:2rem;padding:.2rem .7rem;font-size:.75rem;font-weight:700;">{{ $row->pending }}</span>
                        </td>
                        <td class="text-center">
                            <span style="background:#fee2e2;color:#991b1b;border-radius:2rem;padding:.2rem .7rem;font-size:.75rem;font-weight:700;">{{ $row->cancelled }}</span>
                        </td>
                        <td class="text-center">
                            <span style="background:#dbeafe;color:#1e40af;border-radius:2rem;padding:.2rem .7rem;font-size:.75rem;font-weight:700;">{{ $row->trials }}</span>
                        </td>
                        <td class="text-center">
                            <span class="fw-bold" style="color:{{ $rate >= 70 ? '#059669' : ($rate >= 40 ? '#d97706' : '#dc2626') }};">
                                {{ $rate }}%
                            </span>
                            <span class="completion-bar">
                                <span class="completion-bar-fill" style="width:{{ $rate }}%;"></span>
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="ti ti-inbox" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>
                            No salesperson data for the selected period.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Per-CC-Agent Table ──────────────────────────────────── --}}
    <div class="analytics-table-card mb-4">
        <div class="table-title">
            <i class="ti ti-headset me-2"></i>Per-CC-Agent Summary
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>CC Agent</th>
                        <th class="text-center">Total Booked</th>
                        <th class="text-center">Completed</th>
                        <th class="text-center">Booking Rate</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($byCcAgent as $row)
                    @php
                        $ccRate = $row->total > 0 ? round(($row->completed / $row->total) * 100) : 0;
                    @endphp
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span style="width:32px;height:32px;border-radius:50%;background:#e0f2fe;color:#0369a1;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:.8rem;flex-shrink:0;">
                                    {{ strtoupper(substr($row->ccAgent->name ?? 'C', 0, 1)) }}
                                </span>
                                <span class="fw-semibold">{{ $row->ccAgent->name ?? '—' }}</span>
                            </div>
                        </td>
                        <td class="text-center fw-bold">{{ $row->total }}</td>
                        <td class="text-center">
                            <span class="badge-pill-teal">{{ $row->completed }}</span>
                        </td>
                        <td class="text-center">
                            <span class="fw-bold" style="color:{{ $ccRate >= 70 ? '#059669' : ($ccRate >= 40 ? '#d97706' : '#dc2626') }};">
                                {{ $ccRate }}%
                            </span>
                            <span class="completion-bar">
                                <span class="completion-bar-fill" style="width:{{ $ccRate }}%;"></span>
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">
                            <i class="ti ti-inbox" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>
                            No CC agent data for the selected period.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Client Feedback & Reviews Table ──────────────────────── --}}
    <div class="analytics-table-card mb-4">
        <div class="table-title d-flex justify-content-between align-items-center">
            <div>
                <i class="ti ti-star me-2"></i>Client Feedback &amp; Ratings
            </div>
            @if($ratingCount > 0)
                <span class="badge bg-label-success px-3 py-2 fs-6">
                    Average: <strong>{{ $avgRating }} / 5</strong> ({{ $ratingCount }} reviews)
                </span>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table mb-0 table-hover align-middle">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Salesperson</th>
                        <th class="text-center">Rating</th>
                        <th>Client Feedback Comment</th>
                        <th class="text-center">Completed At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reviews as $review)
                    <tr>
                        <td>
                            <span class="fw-semibold">{{ $review->client->name ?? '—' }}</span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span style="width:32px;height:32px;border-radius:50%;background:#e2fcf7;color:#0d9488;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:.8rem;flex-shrink:0;">
                                    {{ strtoupper(substr($review->salesperson->name ?? 'S', 0, 1)) }}
                                </span>
                                <span class="fw-semibold">{{ $review->salesperson->name ?? '—' }}</span>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="fs-4" title="Rating: {{ $review->rating }}/5">
                                @switch($review->rating)
                                    @case(1) 😠 @break
                                    @case(2) 🙁 @break
                                    @case(3) 😐 @break
                                    @case(4) 🙂 @break
                                    @case(5) 😀 @break
                                    @default —
                                @endswitch
                            </span>
                            <div class="text-muted" style="font-size:0.75rem; font-weight:600;">{{ $review->rating }} / 5</div>
                        </td>
                        <td>
                            @if($review->rating_comment)
                                <span class="fst-italic" style="color: #4a5568;">"{{ $review->rating_comment }}"</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-center text-muted" style="font-size: 0.85rem;">
                            {{ $review->completed_at ? $review->completed_at->format('d M Y') : '' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            <i class="ti ti-star-off" style="font-size:2rem;display:block;margin-bottom:.5rem;color:#cbd5e1;"></i>
                            No client feedback ratings received yet for the selected period.
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
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// ── Visit Type Doughnut Chart ──────────────────────────────────────
(function () {
    const typeLabels = @json(array_keys($byType->toArray()));
    const typeData   = @json(array_values($byType->toArray()));

    const prettyLabels = typeLabels.map(l =>
        l.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
    );

    const ctx = document.getElementById('visitTypeChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: prettyLabels,
            datasets: [{
                data: typeData,
                backgroundColor: ['#0d9488','#10b981','#3b82f6','#8b5cf6'],
                borderColor: '#fff',
                borderWidth: 3,
                hoverOffset: 8,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '65%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { padding: 16, font: { size: 12 }, boxWidth: 12, borderRadius: 4 }
                },
                tooltip: { bodyFont: { size: 13 } }
            }
        }
    });
})();

// ── Daily Visits Bar Chart ─────────────────────────────────────────
(function () {
    const dailyData   = @json($dailyVisits);
    const labels      = dailyData.map(d => d.date);
    const totals      = dailyData.map(d => d.total);

    const ctx = document.getElementById('dailyVisitsChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Visits',
                data: totals,
                backgroundColor: 'rgba(13,148,136,.75)',
                borderRadius: 6,
                borderSkipped: false,
                hoverBackgroundColor: '#0d9488',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { bodyFont: { size: 13 } }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { maxRotation: 45, font: { size: 11 } }
                },
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 },
                    grid: { color: 'rgba(0,0,0,.05)' }
                }
            }
        }
    });
})();
</script>
@endsection
