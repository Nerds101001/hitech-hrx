@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')

@section('title', 'My 90-Day Sales Goals — Dashboard')

@section('vendor-style')
  @vite(['resources/assets/vendor/libs/apex-charts/apex-charts.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/apex-charts/apexcharts.js'])
@endsection

@section('content')
@php
  $G = \App\Services\NinetyDayGoalService::class;
  $name = fn ($u) => trim(optional($u)->first_name . ' ' . optional($u)->last_name);
  $pctText = fn ($v) => $v === null ? '–' : rtrim(rtrim(number_format($v, 1), '0'), '.') . '%';
  $pctColor = fn ($v) => $v === null ? '#94a3b8' : ($v >= 66 ? '#007a7a' : ($v >= 33 ? '#f5a623' : '#ef4444'));
  // Amounts are stored in lakh; shown as ₹ … L like every other 90-Day Goals page.
  $fmtL = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.') ?: '0';
  // Every round always has a real label, whether or not an admin program is behind it.
  $roundLabel = fn ($r) => optional($r->program)->name ?: ('My 90-Day Goals · Round ' . $r->round_no);
  $selectedLabel = $selectedRound ? $roundLabel($selectedRound) : null;
  // A simple, purely presentational next-step hint from the current status — not a stored field.
  $nextAction = fn ($status) => match ($status) {
    'Regular Order' => 'Order received',
    'Trial Order'   => 'Follow up on trial',
    'Off Track'     => 'Follow up call',
    'On Track'      => 'Share update',
    'Dropped'       => 'Closed — no action',
    'Transferred'   => 'Reassigned',
    default         => 'Submit this week\'s status',
  };
@endphp
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 mx-4">
    <div>
      <nav class="small text-muted mb-1"><a href="{{ route('tenant.dashboard') }}" class="text-muted">Dashboard</a> <i class="bx bx-chevron-right"></i> 90-Day Goals <i class="bx bx-chevron-right"></i> My Dashboard</nav>
      <h3 class="fw-extrabold mb-1 text-dark">My 90-Day Sales Goals</h3>
      <p class="text-muted mb-0 small">Track your progress across all assigned programs. Select a program to view detailed information.</p>
    </div>
  </div>

  <div class="px-4">

  {{-- Any active round still short of the minimum, with the deadline to fix it. Replaces the
       older "no customers yet" banner: stopping at four is just as much a round that will not
       be counted as stopping at zero, and neither said when the chance to fix it runs out. --}}
  @foreach($windowAlerts as $w)
    @php $r = $w['round']; @endphp
    {{-- Red, and while the window is open the whole alert opens the round with the Add Customer popup. --}}
    @if($w['open'])<a href="{{ route('performance.ninety-day-goals.round', $r->id) }}?add=1" class="d-block text-decoration-none ndg-dash-alert" title="Click to add customers">@endif
    <div class="hitech-card mb-4" style="border-color:#e11d48;background:#fff1f2">
      <div class="card-body p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
          <i class="bx {{ $w['open'] ? 'bx-error-circle' : 'bx-lock-alt' }} fs-2" style="color:#e11d48"></i>
          <div>
            @if($w['open'])
              <div class="fw-bold text-dark">
                "{{ $roundLabel($r) }}" — add {{ $w['short_by'] }} more target customer{{ $w['short_by'] === 1 ? '' : 's' }} to reach the minimum of {{ $w['min'] }}
              </div>
              <div class="small text-muted">
                {{ $w['count'] }} of {{ $w['min'] }} added ·
                closes {{ $w['closes_at']->format('d M Y, H:i') }}
                ({{ $w['hours_left'] }} hour{{ $w['hours_left'] === 1 ? '' : 's' }} left) ·
                a round below {{ $w['min'] }} is not counted
              </div>
            @else
              <div class="fw-bold text-dark">
                "{{ $roundLabel($r) }}" — the window to add customers has closed
              </div>
              <div class="small text-muted">
                {{ $w['count'] }} of {{ $w['min'] }} added · closed {{ $w['closes_at']->format('d M Y, H:i') }} ·
                this round will not be counted. Ask admin or HR if something still needs to go in.
              </div>
            @endif
          </div>
        </div>
        @if($w['open'])
          <span class="btn btn-hitech-danger rounded-pill px-4 d-flex align-items-center gap-2 flex-shrink-0"><i class="bx bx-plus"></i> Add customers now</span>
        @endif
      </div>
    </div>
    @if($w['open'])</a>@endif
  @endforeach

  {{-- Select Program: a compact dropdown for small screens, real cards for the rest --}}
  <div class="d-flex flex-wrap align-items-stretch gap-3 mb-6">
    <div style="min-width:220px">
      <select class="form-select form-select-hitech h-100" onchange="if(this.value){location.href=this.value}" style="font-weight:700">
        <option value="{{ route('performance.ninety-day-goals.my-dashboard') }}" {{ !$selectedRoundId ? 'selected' : '' }}>All Programs ({{ $myRounds->count() }})</option>
        @foreach($myRounds as $r)
          <option value="{{ route('performance.ninety-day-goals.my-dashboard', ['round_id' => $r->id]) }}" {{ $selectedRoundId === $r->id ? 'selected' : '' }}>
            {{ $roundLabel($r) }} ({{ ucfirst($r->status) }})
          </option>
        @endforeach
      </select>
    </div>
    <div class="d-flex flex-wrap gap-2 flex-grow-1">
      <a href="{{ route('performance.ninety-day-goals.my-dashboard') }}" class="ndg-prog-card {{ !$selectedRoundId ? 'on' : '' }}">
        <i class="bx bx-group"></i>
        <div><div class="fw-bold">All Programs</div><div class="small">Overview</div></div>
      </a>
      @foreach($myRounds as $r)
        @php $rActive = $r->status === 'active'; @endphp
        <a href="{{ route('performance.ninety-day-goals.my-dashboard', ['round_id' => $r->id]) }}" class="ndg-prog-card {{ $selectedRoundId === $r->id ? 'on' : '' }}">
          <i class="bx {{ $rActive ? 'bx-broadcast' : 'bx-check-shield' }}"></i>
          <div>
            <div class="fw-bold">{{ \Illuminate\Support\Str::limit($roundLabel($r), 20) }}</div>
            <div class="small d-flex align-items-center gap-2">
              <span class="ndg-status-dot" style="background:{{ $rActive ? '#0b5b48' : '#94a3b8' }}"></span>
              {{ ucfirst($r->status) }} · {{ $r->starts_on->format('d M') }} – {{ $r->ends_on->format('d M Y') }}
            </div>
          </div>
        </a>
      @endforeach
    </div>
  </div>

  {{-- KPI cards --}}
  <div class="row g-3 mb-6">
    <div class="col-6 col-lg-3">
      <div class="ndg-kpi3">
        <div class="ndg-kpi3-icon" style="background:#e0f2fe;color:#0369a1"><i class="bx bx-building-house"></i></div>
        <div>
          <div class="small text-muted fw-semibold">My Customers @if($selectedLabel)— {{ \Illuminate\Support\Str::limit($selectedLabel, 14) }}@else (All Programs)@endif</div>
          <div class="ndg-kpi3-v">{{ $cards['goals'] }}</div>
          @if($trends['customers'] > 0)<div class="small" style="color:#0b5b48">↑{{ $trends['customers'] }} this month</div>@endif
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="ndg-kpi3">
        <div class="ndg-kpi3-icon" style="background:#ede9fe;color:#5b21b6"><i class="bx bx-bar-chart-alt-2"></i></div>
        <div>
          <div class="small text-muted fw-semibold">My Target @if($selectedLabel)— {{ \Illuminate\Support\Str::limit($selectedLabel, 14) }}@else (All Programs)@endif</div>
          <div class="ndg-kpi3-v">₹{{ $fmtL($cards['target']) }} L</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="ndg-kpi3">
        <div class="ndg-kpi3-icon" style="background:#dcfce7;color:#166534"><i class="bx bx-rupee"></i></div>
        <div class="flex-grow-1">
          <div class="small text-muted fw-semibold">My Achieved @if($selectedLabel)— {{ \Illuminate\Support\Str::limit($selectedLabel, 14) }}@else (All Programs)@endif</div>
          <div class="ndg-kpi3-v">₹{{ $fmtL($cards['achieved']) }} L</div>
          <div class="d-flex align-items-center gap-2 mt-1">
            <div class="ndg-track flex-grow-1" style="height:6px"><div class="ndg-fill" style="width:{{ $cards['pct'] === null ? 0 : min(100, $cards['pct']) }}%;background:{{ $pctColor($cards['pct']) }}"></div></div>
            <b class="small" style="color:{{ $pctColor($cards['pct']) }}">{{ $pctText($cards['pct']) }}</b>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="ndg-kpi3">
        <div class="ndg-kpi3-icon" style="background:#fff7ed;color:#c2540c"><i class="bx bx-receipt"></i></div>
        <div>
          <div class="small text-muted fw-semibold">Orders Received</div>
          <div class="ndg-kpi3-v">{{ $cards['converted'] }}</div>
          @if($trends['ordersThisMonth'] > 0)<div class="small" style="color:#0b5b48">↑{{ $trends['ordersThisMonth'] }} this month</div>@endif
        </div>
      </div>
    </div>
  </div>

  {{-- Charts --}}
  <div class="row g-4 mb-6">
    <div class="col-xl-4">
      <div class="hitech-card h-100">
        <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">My Progress @if($selectedLabel)— {{ $selectedLabel }}@else (All Programs)@endif</div><div class="small text-muted">@if($focusRound){{ $focusRound->starts_on->format('d M') }} – {{ $focusRound->ends_on->format('d M Y') }}@else No round yet @endif</div></div>
        <div class="card-body p-3"><div id="myProgressChart"></div></div>
      </div>
    </div>
    <div class="col-xl-4">
      <div class="hitech-card h-100">
        <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">My Customer Status @if($selectedLabel)— {{ \Illuminate\Support\Str::limit($selectedLabel, 16) }}@else (All Programs)@endif</div></div>
        <div class="card-body p-3"><div id="myStatusDonut"></div></div>
      </div>
    </div>
    <div class="col-xl-4">
      <div class="hitech-card h-100">
        <div class="card-body p-4 border-bottom d-flex align-items-center justify-content-between">
          <div class="fw-bold text-dark">My Weekly Submission</div>
          @if($focusRound)<a href="{{ route('performance.ninety-day-goals.round', $focusRound->id) }}" class="small text-decoration-none text-primary fw-semibold">View All →</a>@endif
        </div>
        <div class="card-body p-3"><div id="myWeeklyBar"></div></div>
      </div>
    </div>
  </div>

  {{-- My Customers table --}}
  <div class="hitech-card mb-6">
    <div class="card-body p-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-3">
      <div>
        <div class="fw-bold text-dark">My Customers @if($selectedLabel)— {{ $selectedLabel }}@else (All Programs)@endif</div>
        <div class="small text-muted">{{ $allRowsCount }} {{ \Illuminate\Support\Str::plural('customer', $allRowsCount) }}</div>
      </div>
      <a href="{{ $focusRound ? route('performance.ninety-day-goals.round', $focusRound->id) : route('performance.ninety-day-goals.my-program') }}" class="btn-hitech rounded-pill px-4 d-flex align-items-center gap-2"><i class="bx bx-plus"></i> Add Customer</a>
    </div>
    <div class="card-body p-0 ndg-mx-scroll">
      <table class="ndg-mx" style="font-size:.72rem">
        <thead><tr>
          <th class="ndg-mx-l" style="left:0">#</th><th class="ndg-mx-l" style="left:30px">Customer</th><th>Product</th>
          @if(!$selectedRoundId)<th>Program</th>@endif
          <th>Target ₹L</th><th>Expected</th>
          @for($w = 0; $w < $G::WEEKS; $w++)<th>W{{ $w + 1 }}</th>@endfor
          <th>Actual ₹L</th><th>Ach.%</th><th>Status</th><th>Next Action</th><th></th>
        </tr></thead>
        <tbody>
        @forelse($rows as $i => $r)
          @php $cks = $r->goal->checkins->keyBy('week_no'); @endphp
          <tr>
            <td class="ndg-mx-l" style="left:0">{{ ($rows->currentPage() - 1) * $rows->perPage() + $i + 1 }}</td>
            <td class="ndg-mx-l" style="left:30px;font-weight:600">{{ $r->goal->client_name }}</td>
            <td>{{ $r->goal->product }}</td>
            @if(!$selectedRoundId)<td class="small text-muted">{{ optional($r->program)->name ?? 'No program' }}</td>@endif
            <td>{{ $fmtL($r->goal->monthly_potential) }}</td>
            <td class="small text-muted">{{ $r->goal->expected_order_date ? $r->goal->expected_order_date->format('d M Y') : '—' }}</td>
            @for($w = 0; $w < $G::WEEKS; $w++)
              @php
                $ck = $cks->get($w);
                $st = optional($ck)->status;
                [$bg, $fg] = $st ? ($G::STATUS_COLORS[$st] ?? ['#94a3b8', '#fff']) : [null, null];
              @endphp
              <td class="text-center">@if($st)<span class="ndg-chip" style="background:{{ $bg }};color:{{ $fg }}">{{ $st }}</span>@else<span class="ndg-none">-</span>@endif</td>
            @endfor
            <td class="fw-semibold">{{ $fmtL($r->achieved) }}</td>
            <td style="color:{{ $pctColor($r->pct) }}">{{ $pctText($r->pct) }}</td>
            <td>@if($r->status)<span class="ndg-chip" style="background:{{ $G::STATUS_COLORS[$r->status][0] ?? '#e2e8f0' }};color:{{ $G::STATUS_COLORS[$r->status][1] ?? '#475569' }}">{{ $r->status }}</span>@include('tenant.performance.ninety-day-goals.partials.order-check', ['r' => $r])@else<span class="ndg-none">·</span>@endif</td>
            <td class="small text-muted">{{ $nextAction($r->status) }}</td>
            <td><a href="{{ route('performance.ninety-day-goals.round', $r->goal->round_id) }}" class="text-muted" title="View in My Programs"><i class="bx bx-dots-vertical-rounded"></i></a></td>
          </tr>
        @empty
          <tr><td colspan="{{ $selectedRoundId ? 10 + $G::WEEKS : 11 + $G::WEEKS }}" class="text-center text-muted p-5">
            No target customers yet — <a href="{{ route('performance.ninety-day-goals.my-program') }}">add your first one</a>.
          </td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-body pt-3">{{ $rows->links() }}</div>
  </div>

  {{-- My Recent Activities | My Pending Tasks | My Team Performance --}}
  <div class="row g-4 mb-6">
    <div class="col-xl-4">
      <div class="hitech-card h-100">
        <div class="card-body p-4 border-bottom d-flex align-items-center justify-content-between">
          <div class="fw-bold text-dark">My Recent Activities</div>
          @if($focusRound)<a href="{{ route('performance.ninety-day-goals.round', $focusRound->id) }}" class="small text-decoration-none text-primary fw-semibold">View All →</a>@endif
        </div>
        <div class="card-body p-0" style="max-height:320px;overflow-y:auto">
          @forelse($activity as $a)
            <div class="ndg-alert-row">
              <i class="bx {{ $a->kind === 'note' ? 'bx-message-square-detail' : 'bx-radio-circle-marked' }}"></i>
              <div class="flex-grow-1">
                <div class="small text-dark">You {{ $a->kind === 'note' ? 'commented on' : 'marked' }} <b>{{ $a->goal->client_name }}</b>@if($a->status) as <span class="ndg-chip" style="background:{{ $G::STATUS_COLORS[$a->status][0] ?? '#e2e8f0' }};color:{{ $G::STATUS_COLORS[$a->status][1] ?? '#475569' }}">{{ $a->status }}</span>@endif</div>
                <div class="small text-muted">{{ optional($a->at)->diffForHumans() }}</div>
              </div>
            </div>
          @empty
            <div class="p-4 text-center text-muted small">No activity yet.</div>
          @endforelse
        </div>
      </div>
    </div>
    <div class="col-xl-4">
      <div class="hitech-card h-100">
        <div class="card-body p-4 border-bottom d-flex align-items-center justify-content-between">
          <div class="fw-bold text-dark">My Pending Tasks</div>
          <a href="{{ route('my-tasks.index') }}" class="small text-decoration-none text-primary fw-semibold">View All →</a>
        </div>
        <div class="card-body p-0 table-responsive" style="max-height:320px;overflow-y:auto">
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th class="ps-3">Task</th><th>Due Date</th><th class="pe-3">Status</th></tr></thead>
            <tbody>
            @forelse($pendingTasks as $t)
              <tr>
                <td class="ps-3 small">{{ \Illuminate\Support\Str::after($t->title, '90-Day Goals: ') }}</td>
                <td class="small {{ $t->due_date && \Carbon\Carbon::parse($t->due_date)->lt($today) ? 'text-danger fw-bold' : 'text-muted' }}">{{ $t->due_date ? \Carbon\Carbon::parse($t->due_date)->format('d M Y') : '—' }}</td>
                <td class="pe-3"><span class="badge rounded-pill bg-label-warning" style="font-size:.62rem">{{ ucfirst($t->status) }}</span></td>
              </tr>
            @empty
              <tr><td colspan="3" class="text-center text-muted p-4">Nothing pending — you're all caught up.</td></tr>
            @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-xl-4">
      <div class="hitech-card h-100">
        <div class="card-body p-4 border-bottom d-flex align-items-center gap-2">
          <div class="fw-bold text-dark">My Team Performance (Summary Only)</div>
          <i class="bx bx-info-circle text-muted" title="Customer details are restricted. Contact Sales Head for more information."></i>
        </div>
        <div class="card-body p-0 table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th class="ps-3">#</th><th>Salesperson</th><th class="text-end">Target ₹L</th><th class="text-end">Achieved ₹L</th><th class="text-end pe-3">Ach. %</th></tr></thead>
            <tbody>
            @forelse($team as $i => $u)
              <tr>
                <td class="ps-3 text-muted">{{ $i + 1 }}</td>
                <td class="fw-semibold">{{ $u['name'] }}</td>
                <td class="text-end">{{ number_format($u['target'], 1) }}</td>
                <td class="text-end">{{ number_format($u['achieved'], 1) }}</td>
                <td class="text-end pe-3" style="color:{{ $pctColor($u['pct']) }}">{{ $pctText($u['pct']) }}</td>
              </tr>
            @empty
              <tr><td colspan="5" class="text-center text-muted p-4">No teammates in scope yet.</td></tr>
            @endforelse
            </tbody>
          </table>
        </div>
        <div class="card-body pt-2"><div class="small text-muted">Note: Customer details are restricted. Contact Sales Head for more information.</div></div>
      </div>
    </div>
  </div>

  </div>
</div>

<style>
  .ndg-prog-card { display: flex; align-items: center; gap: 10px; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 16px; text-decoration: none; color: #334155; background: #fff; min-width: 170px; }
  .ndg-prog-card:hover { border-color: #007a7a; }
  .ndg-prog-card i { font-size: 1.3rem; color: #007a7a; flex: none; }
  .ndg-prog-card .fw-bold { font-size: .84rem; color: #0f172a; }
  .ndg-prog-card .small { color: #94a3b8; }
  .ndg-prog-card.on { background: #007a7a; border-color: #007a7a; }
  .ndg-prog-card.on i, .ndg-prog-card.on .fw-bold, .ndg-prog-card.on .small { color: #fff; }
  .ndg-status-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
  .ndg-kpi3 { border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; background: #fff; height: 100%; display: flex; align-items: flex-start; gap: 12px; }
  .ndg-kpi3-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex: none; }
  .ndg-kpi3-v { font-size: 1.3rem; font-weight: 800; color: #0f172a; line-height: 1.25; font-variant-numeric: tabular-nums; }
  .ndg-track { height: 7px; background: #eef2f5; border-radius: 99px; overflow: hidden; }
  .ndg-fill { height: 100%; background: #007a7a; border-radius: 99px; min-width: 2px; }
  .ndg-chip { display: inline-block; padding: 2px 7px; border-radius: 6px; font-size: .66rem; font-weight: 700; white-space: nowrap; }
  .ndg-none { color: #cbd5e1; }
  .ndg-alert-row { display: flex; align-items: start; gap: 10px; padding: 10px 16px; border-bottom: 1px solid #f1f5f9; }
  .ndg-alert-row:last-child { border-bottom: none; }
  .ndg-alert-row i { font-size: 1.1rem; color: #007a7a; flex: none; margin-top: 2px; }
  .ndg-mx-scroll { overflow: auto; max-height: 65vh; }
  .ndg-mx { border-collapse: separate; border-spacing: 3px; padding: 0 16px 8px; width: 100%; }
  .ndg-mx thead th { position: sticky; top: 0; background: #fff; font-weight: 700; color: #64748b; font-size: .64rem; text-align: center; padding: 8px 4px 6px; z-index: 2; white-space: nowrap; }
  .ndg-mx-l { text-align: left !important; white-space: nowrap; padding: 4px 10px !important; font-weight: 600; color: #0f172a; position: sticky; background: #fff; z-index: 1; }
</style>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof ApexCharts === 'undefined') { return; }
  var teal = '#007a7a', muted = '#64748b';

  var progressEl = document.querySelector('#myProgressChart');
  if (progressEl) {
    var wp = @json($weeklyProgress);
    new ApexCharts(progressEl, {
      chart: { type: 'area', height: 260, toolbar: { show: false }, fontFamily: 'inherit' },
      series: [{ name: 'Target (Cumulative)', data: wp.target }, { name: 'Achieved (Cumulative)', data: wp.achieved }],
      xaxis: { categories: wp.labels, labels: { style: { colors: muted, fontSize: '9px' }, rotate: -45 } },
      yaxis: { labels: { style: { colors: muted }, formatter: function (v) { return '₹' + (Math.round(v * 100) / 100) + ' L'; } } },
      colors: ['#94c9c9', teal],
      stroke: { curve: 'smooth', width: 2 },
      fill: { type: 'gradient', gradient: { opacityFrom: 0.25, opacityTo: 0.02 } },
      dataLabels: { enabled: false },
      legend: { position: 'top', horizontalAlign: 'right', fontSize: '11px' },
      grid: { borderColor: '#eef2f5' },
    }).render();
  }

  var donutEl = document.querySelector('#myStatusDonut');
  if (donutEl) {
    var statuses = @json($statusCounts);
    var colorMap = @json(collect($G::STATUS_COLORS)->map(fn ($c) => $c[0]));
    colorMap['Nothing reported yet'] = '#cbd5e1';
    var labels = Object.keys(statuses).filter(function (k) { return statuses[k] > 0; });
    new ApexCharts(donutEl, {
      chart: { type: 'donut', height: 260, fontFamily: 'inherit' },
      series: labels.map(function (l) { return statuses[l]; }),
      labels: labels,
      colors: labels.map(function (l) { return colorMap[l] || muted; }),
      legend: { position: 'bottom', fontSize: '11px' },
      dataLabels: { enabled: false },
      plotOptions: { pie: { donut: { size: '68%', labels: { show: true, total: { show: true, label: 'Customers', color: '#0f172a' } } } } },
    }).render();
  }

  var barEl = document.querySelector('#myWeeklyBar');
  if (barEl) {
    var ws = @json($weekStrip);
    new ApexCharts(barEl, {
      chart: { type: 'bar', height: 260, stacked: true, toolbar: { show: false }, fontFamily: 'inherit' },
      series: [{ name: 'Submitted', data: ws.map(function (w) { return w.done; }) }, { name: 'Pending', data: ws.map(function (w) { return w.pending; }) }],
      xaxis: { categories: ws.map(function (w) { return 'W' + w.week; }), labels: { style: { colors: muted, fontSize: '11px' } } },
      yaxis: { labels: { style: { colors: muted } } },
      colors: [teal, '#e2e8f0'],
      plotOptions: { bar: { columnWidth: '55%', borderRadius: 4 } },
      dataLabels: { enabled: false },
      legend: { position: 'top', horizontalAlign: 'right', fontSize: '11px' },
      grid: { borderColor: '#eef2f5' },
    }).render();
  }
});
</script>
@endsection
