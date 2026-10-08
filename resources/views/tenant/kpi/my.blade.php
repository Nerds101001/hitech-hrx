@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')
@section('title', 'My KPI / KRA')

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6 mx-4">
        <div>
            <h3 class="fw-extrabold mb-1 text-dark">My Scorecard</h3>
            <p class="text-muted mb-0 small">
                Your KPIs and KRAs, what you have reported, and what has been scored.
            </p>
        </div>
        <div class="d-flex gap-3 flex-wrap align-items-center">
            {{-- One month's scorecard as a PDF: the download is this page with ?pdf=YYYY-MM. --}}
            <form method="GET" action="{{ url('/kpi/my') }}" class="d-flex gap-2 align-items-center">
                <select name="pdf" class="form-select form-select-hitech" style="min-width:160px" aria-label="Month">
                    @foreach($pdfMonths as $pm)
                        <option value="{{ $pm['key'] }}">{{ $pm['label'] }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-hitech shadow-sm rounded-pill px-4 d-flex align-items-center gap-2">
                    <i class="bx bx-download fs-5"></i><span class="fw-bold">PDF</span>
                </button>
            </form>
            <a href="{{ url('/user/dashboard') }}" class="btn btn-hitech-cancel shadow-sm rounded-pill px-4 d-flex align-items-center gap-2">
                <i class="bx bx-home-alt fs-5"></i><span class="fw-bold">Dashboard</span>
            </a>
        </div>
    </div>

    @if($assignments->isEmpty())
        <div class="alert alert-info">No KPI/KRA has been assigned to you yet.</div>
    @else
        @php
            $tone = ['completed_on_time' => '#059669', 'completed_late' => '#d97706', 'missed' => '#e11d48', 'pending' => '#94a3b8'];
            $fmt = fn($v) => $v === null ? '—' : rtrim(rtrim(number_format($v,2),'0'),'.');
            // Red / amber / green by the share of a KPI's marks earned that month —
            // green >= 70%, amber 40-69%, red below 40% (incl. a zero). Info/penalty
            // KPIs (no max) and not-yet-scored cells stay neutral grey.
            $rag = function ($score, $max) {
                if ($score === null || (float) $max <= 0) return '#94a3b8';
                $p = (float) $score / (float) $max * 100;
                return $p >= 70 ? '#16a34a' : ($p >= 40 ? '#d97706' : '#dc2626');
            };
            $rows = $assignments->sortBy([['kra_group','asc'],['title','asc']])->values();
            $overallPct = $possible > 0 ? round(($earned / $possible) * 100) : 0;

            $monthScoreTotals = [];
            foreach ($months as $m) { $monthScoreTotals[$m['key']] = 0.0; }
        @endphp

        {{-- Stat cards, same four-across row the Employee Directory uses. --}}
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

            {{-- Two figures people were reading off the grid by eye. Both are
                 computed exactly the way their KPIs are, so a card and a scored
                 row can never disagree. --}}
            <div class="col-xl-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay:.25s">
                <div class="hitech-stat-card dashboard-variant card-teal uniform-card">
                    <div class="stat-card-header mb-2">
                        <div class="stat-icon-wrap icon-teal" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-line-chart"></i></div>
                    </div>
                    <div>
                        <h3 class="stat-value mb-0">₹{{ number_format((float) ($totalPipeline ?? 0), 2) }}<span style="font-size:.6em;font-weight:600;"> L</span></h3>
                        <span class="stat-label">Total pipeline</span>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay:.3s">
                <div class="hitech-stat-card dashboard-variant card-blue uniform-card">
                    <div class="stat-card-header mb-2">
                        <div class="stat-icon-wrap icon-blue" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-calendar-check"></i></div>
                    </div>
                    <div>
                        <h3 class="stat-value mb-0">
                            {{ $attendancePct === null ? '—' : number_format((float) $attendancePct, 1) . '%' }}
                        </h3>
                        <span class="stat-label">Attendance, {{ now()->format('M Y') }}</span>
                    </div>
                </div>
            </div>

            {{-- Shown, not scored: e.g. Total Leads Allocated (Sales / New Biz),
                 entered monthly in Bulk KPI Entry. --}}
            @foreach(($infoCards ?? []) as $infoLabel => $infoValue)
            <div class="col-xl-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay:.35s">
                <div class="hitech-stat-card dashboard-variant card-teal uniform-card">
                    <div class="stat-card-header mb-2">
                        <div class="stat-icon-wrap icon-teal" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-target-lock"></i></div>
                    </div>
                    <div>
                        <h3 class="stat-value mb-0">{{ $infoValue === null ? '—' : rtrim(rtrim(number_format((float) $infoValue, 2), '0'), '.') }}</h3>
                        <span class="stat-label">{{ $infoLabel }}, {{ now()->format('M Y') }}</span>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- This week's own reporting. Scoring stays monthly; these weekly
             figures add up into the month, which is why a weekly KPI does not
             award its marks four times over. --}}
        @if($manualAssignments->isNotEmpty())
          @php
            $stateTone = [
              'submitted' => ['#d97706', 'Waiting for HR'],
              'approved'  => ['#059669', 'Approved'],
              'rejected'  => ['#e11d48', 'Sent back'],
            ];
          @endphp
          <div class="px-4">
          <div class="hitech-card mb-6">
            <div class="card-body p-4">
              <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div>
                  <h6 class="fw-bold mb-0">What did you do?</h6>
                  <small class="text-muted">
                    Report each one over its own period. Number and customers are both required.
                  </small>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                  @if($sentBack->isNotEmpty())
                    <span class="badge badge-hitech" style="background:#fee2e2;color:#b91c1c;">
                      {{ $sentBack->count() }} sent back — please correct
                    </span>
                  @endif
                  <label for="wkGlobalPeriod" class="small text-muted fw-semibold mb-0">Reporting for</label>
                  <select id="wkGlobalPeriod" class="form-select form-select-sm filter-item-hitech" style="width:auto;min-width:170px;">
                    <option value="this" selected>{{ \Carbon\Carbon::today()->format('F Y') }} (current)</option>
                    <option value="last">{{ \Carbon\Carbon::today()->startOfMonth()->subMonthNoOverflow()->format('F Y') }}</option>
                  </select>
                </div>
              </div>

              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th style="min-width:200px">KPI</th>
                      <th style="min-width:150px">Period</th>
                      <th class="text-center">Approval</th>
                      <th style="width:150px">Your number</th>
                      <th style="min-width:200px">Customers</th>
                      <th class="text-end" style="width:150px">Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($manualAssignments as $a)
                      @php
                        $entry = $weekEntries[$a->id] ?? null;
                        $lastEntry = $lastMonthEntries[$a->id] ?? null;
                        $isMonthly = $a->frequency === 'monthly';
                        $needsAppr = $a->data_source === 'approval';
                        [$tc, $tl] = $entry ? ($stateTone[$entry->status] ?? ['#78716c', ucfirst($entry->status)]) : ['#94a3b8', 'Not sent'];
                        $thisPeriodDate = \Carbon\Carbon::today()->startOfMonth()->toDateString();
                        $lastPeriodDate = $lastMonthKey ?? \Carbon\Carbon::today()->startOfMonth()->subMonthNoOverflow()->toDateString();
                        $thisPeriodJson = json_encode([
                            'date' => $thisPeriodDate,
                            'value' => $entry ? (float) $entry->value : null,
                            'note' => $entry->note ?? null,
                            'status' => $entry->status ?? null,
                            'review_note' => $entry->review_note ?? null,
                            'label' => \Carbon\Carbon::today()->format('F Y') . ' (current)',
                        ]);
                        $lastPeriodJson = json_encode([
                            'date' => $lastPeriodDate,
                            'value' => $lastEntry ? (float) $lastEntry->value : null,
                            'note' => $lastEntry->note ?? null,
                            'status' => $lastEntry->status ?? null,
                            'review_note' => $lastEntry->review_note ?? null,
                            'label' => \Carbon\Carbon::parse($lastPeriodDate)->format('F Y'),
                        ]);
                      @endphp
                      <tr class="wk-row" data-id="{{ $a->id }}" data-monthly="{{ $isMonthly ? '1' : '0' }}"
                          data-period="{{ $isMonthly ? $thisPeriodDate : '' }}"
                          data-this="{{ $thisPeriodJson }}"
                          data-last="{{ $lastPeriodJson }}">
                        <td>
                          <div class="fw-medium">{{ $a->title }}</div>
                          <small class="text-muted">
                            {{ rtrim(rtrim(number_format((float) $a->max_marks, 2, '.', ''), '0'), '.') }} marks
                            @if($a->kra_group) · {{ $a->kra_group }} @endif
                          </small>
                        </td>
                        <td class="text-nowrap">
                          @if($isMonthly)
                            <span class="small wk-period-label">{{ \Carbon\Carbon::today()->format('F Y') }} (current)</span>
                          @else
                            <span class="small">{{ $entryPeriod[$a->id] ?? '' }}</span>
                            <small class="text-muted d-block text-capitalize" style="font-size:.68rem">{{ $a->frequency }}</small>
                          @endif
                        </td>
                        <td class="text-center">
                          @if($needsAppr)
                            <span class="badge badge-hitech badge-muted">HR signs off</span>
                          @else
                            <span class="badge badge-hitech badge-brand-soft">Counts at once</span>
                          @endif
                        </td>
                        <td>
                          <input type="number" step="0.01" min="0" class="form-control form-control-sm wk-val" required
                                 value="{{ $entry ? rtrim(rtrim(number_format((float) $entry->value, 2, '.', ''), '0'), '.') : '' }}"
                                 placeholder="0">
                        </td>
                        <td>
                          <input type="text" maxlength="255" class="form-control form-control-sm wk-note" required
                                 value="{{ $entry->note ?? '' }}" placeholder="Customer names, comma separated">
                        </td>
                        <td class="text-end text-nowrap">
                          <span class="wk-state fw-semibold small" style="color:{{ $tc }}">{{ $tl }}</span>
                          <button class="btn btn-hitech-primary btn-sm wk-send ms-2" title="Send">
                            <i class="bx bx-send"></i>
                          </button>
                          @if($entry && $entry->status === 'rejected' && $entry->review_note)
                            <small class="d-block text-danger mt-1 wk-review-note">HR: {{ $entry->review_note }}</small>
                          @endif
                        </td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>

              <div class="d-flex justify-content-end mt-3">
                <button type="button" class="btn btn-hitech-primary" id="wkSubmitAll">
                  <i class="bx bx-send me-1"></i>Submit all
                </button>
              </div>
            </div>
          </div>
          </div>
        @endif

        {{-- Filter panel — same inline-row shell as Leave Approvals' filter
             row (search-wrapper-hitech + compact-select + filter-item-hitech),
             not the boxed labels-above-inputs layout this used to have. --}}
        <div class="px-4">
        <div class="hitech-card mb-6 overflow-hidden">
          <div class="card-body p-4">
            <div class="d-flex flex-wrap align-items-center gap-3">
              <div class="search-wrapper-hitech" style="width: 360px;">
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
                            <th colspan="2" class="text-center align-middle kpi-head-sub border-bottom border-white">{{ $m['label'] }}</th>
                        @endforeach
                        <th rowspan="2" class="text-end align-middle">Total Score</th>
                    </tr>
                    <tr class="kpi-head-sub">
                        @foreach($months as $m)
                            <th class="text-end align-middle" style="min-width:70px;">Achieved</th>
                            <th class="text-end align-middle" style="min-width:60px;">Score</th>
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
                            <div class="small text-muted text-capitalize">{{ $a->scoring_type }}@if($a->scoring_type === 'threshold') ≥ {{ $fmt($a->threshold_percent) }}% @endif@if(($a->trend ?? 'increase') === 'decrease') · lower is better @endif</div>
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
                                $isCurrentCell = $check && $check->id === $currentCheckId;
                            @endphp
                            <td class="text-end">
                                @if($check)
                                    <span style="color:{{ $rag($check->score, $a->max_marks) }};">{{ $fmt($check->achieved_value) }}</span>
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
                                    <span style="color:{{ $rag($check->score, $a->max_marks) }};font-weight:600;">{{ $fmt($check->score) }}</span>
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
        </div>
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

  /* hitech-portal.scss's own .table thead th rule (line ~4657) sets
     background/color with !important, which beats a plain inline style
     regardless of specificity — an id-scoped rule at the same !important
     tier is the only thing that reliably wins. */
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
@endsection

@section('page-script')
<script>
const CSRF = '{{ csrf_token() }}';

// ── Global period picker — one control for every monthly row at once
// (was per-row, which meant picking the month five separate times for one
// reporting cycle). Switching it swaps in whatever was already fetched for
// that month (data-this / data-last), no round trip, and sets data-period
// on each row so a send/submit-all knows which month to target. Non-monthly
// rows (data-monthly="0") are untouched -- they always report their own
// current window.
function wkApplyPeriod(row, which) {
    const picked = JSON.parse(row.dataset[which]);
    row.dataset.period = picked.date;

    row.querySelector('.wk-val').value = picked.value === null ? '' : picked.value;
    row.querySelector('.wk-note').value = picked.note || '';

    const periodLabel = row.querySelector('.wk-period-label');
    if (periodLabel) periodLabel.textContent = picked.label;

    const state = row.querySelector('.wk-state');
    const tone = { submitted: ['#d97706', 'Waiting for HR'], approved: ['#059669', 'Approved'], rejected: ['#e11d48', 'Sent back'] };
    const [color, label] = picked.status ? (tone[picked.status] || ['#78716c', picked.status]) : ['#94a3b8', 'Not sent'];
    state.textContent = label;
    state.style.color = color;

    let noteEl = row.querySelector('.wk-review-note');
    if (picked.status === 'rejected' && picked.review_note) {
        if (!noteEl) {
            noteEl = document.createElement('small');
            noteEl.className = 'd-block text-danger mt-1 wk-review-note';
            state.closest('td').appendChild(noteEl);
        }
        noteEl.textContent = 'HR: ' + picked.review_note;
    } else if (noteEl) {
        noteEl.remove();
    }
}

const wkGlobalPeriod = document.getElementById('wkGlobalPeriod');
if (wkGlobalPeriod) {
    wkGlobalPeriod.addEventListener('change', function () {
        document.querySelectorAll('.wk-row[data-monthly="1"]').forEach(function (row) {
            wkApplyPeriod(row, wkGlobalPeriod.value);
        });
    });
}

/** Validate one row has both a number and customer names. Returns an error string, or null if fine. */
function wkRowError(row) {
    const val = row.querySelector('.wk-val').value;
    const note = row.querySelector('.wk-note').value.trim();
    if (val === '' || isNaN(parseFloat(val))) return 'Enter a number.';
    if (!note) return 'Enter the customer names.';
    return null;
}

async function wkSubmitRow(row) {
    const btn = row.querySelector('.wk-send');
    const state = row.querySelector('.wk-state');
    const before = state.textContent;
    btn.disabled = true;
    state.textContent = 'Sending...';
    try {
        const body = {
            value: parseFloat(row.querySelector('.wk-val').value),
            note: row.querySelector('.wk-note').value.trim(),
        };
        if (row.dataset.monthly === '1' && row.dataset.period) {
            body.week = row.dataset.period;
        }
        const res = await fetch('/kpi/' + row.dataset.id + '/entry', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            credentials: 'same-origin',
            body: JSON.stringify(body)
        });
        const data = await res.json().catch(() => ({}));
        if (res.ok && data.status === 'success') {
            const approved = data.entry_status === 'approved';
            state.textContent = approved ? 'Approved' : 'Waiting for HR';
            state.style.color = approved ? '#059669' : '#d97706';
            btn.disabled = false;
            return true;
        }
        state.textContent = before;
        btn.disabled = false;
        alert((row.querySelector('td .fw-medium')?.textContent || 'That row') + ': ' + (data.message || 'Could not send that.'));
        return false;
    } catch (err) {
        state.textContent = before;
        btn.disabled = false;
        alert('Could not send that: ' + err.message);
        return false;
    }
}

document.querySelectorAll('.wk-send').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const row = btn.closest('.wk-row');
        const err = wkRowError(row);
        if (err) { alert(err); return; }
        wkSubmitRow(row);
    });
});

const wkSubmitAllBtn = document.getElementById('wkSubmitAll');
if (wkSubmitAllBtn) {
    wkSubmitAllBtn.addEventListener('click', async function () {
        const rows = Array.from(document.querySelectorAll('.wk-row'));
        const errors = [];
        rows.forEach(function (row) {
            const err = wkRowError(row);
            if (err) errors.push((row.querySelector('td .fw-medium')?.textContent || 'A row') + ': ' + err);
        });
        if (errors.length) { alert('Fix these before submitting all:\n\n' + errors.join('\n')); return; }

        wkSubmitAllBtn.disabled = true;
        const original = wkSubmitAllBtn.innerHTML;
        wkSubmitAllBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin me-1"></i>Submitting...';
        let okCount = 0;
        for (const row of rows) {
            if (await wkSubmitRow(row)) okCount++;
        }
        wkSubmitAllBtn.disabled = false;
        wkSubmitAllBtn.innerHTML = original;
        alert(okCount + ' of ' + rows.length + ' submitted.');
    });
}

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
