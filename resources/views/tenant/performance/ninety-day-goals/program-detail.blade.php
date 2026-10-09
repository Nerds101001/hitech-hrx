@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')

@section('title', '90-Day Goals — ' . $program->name)

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/apex-charts/apex-charts.scss',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
  ])
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.js',
    'resources/assets/vendor/libs/apex-charts/apexcharts.js',
  ])
@endsection

@section('content')
@php
  $G = \App\Services\NinetyDayGoalService::class;
  $fmtL = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.') ?: '0';
  $pctText = fn ($v) => $v === null ? '–' : rtrim(rtrim(number_format($v, 1), '0'), '.') . '%';
  $pctColor = fn ($v) => $v === null ? '#cbd5e1' : ($v >= 66 ? '#007a7a' : ($v >= 33 ? '#f5a623' : '#ef4444'));
  $name = fn ($u) => trim(optional($u)->first_name . ' ' . optional($u)->last_name);
  $min = (int) config('ninety_day.min_customers', 10);
  $amber = (int) config('ninety_day.customers_amber_from', 7);
  $isActive = $today->between($program->starts_on, $program->ends_on);
  $target = (float) $cards['target'];
  $achieved = (float) $cards['achieved'];
  $pct = $cards['pct'];
  $statusColorMap = collect($G::STATUS_COLORS)->map(fn ($clr) => $clr[0]);
  $statusChartData = $statusCounts;

  // Server-side pagination for the customer table: 25 per page unless "Show" picks more.
  $perPage = \App\Services\NinetyDayGoalService::perPage(request('per_page'), 25, $rows->count());
  $page = max(1, request()->integer('page', 1));
  $paged = new \Illuminate\Pagination\LengthAwarePaginator(
      $rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page,
      ['path' => request()->url(), 'query' => request()->except('page')]
  );
@endphp
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4 mx-4">
    <div>
      <nav class="small text-muted mb-1"><a href="{{ route('tenant.dashboard') }}" class="text-muted">Dashboard</a> <i class="bx bx-chevron-right"></i> <a href="{{ route('performance.ninety-day-goals.programs.index') }}" class="text-muted">Programs</a> <i class="bx bx-chevron-right"></i> {{ $program->name }}</nav>
      <h3 class="fw-extrabold mb-1 text-dark d-flex align-items-center gap-2">
        {{ $program->name }}
        <span class="badge rounded-pill fw-bold" style="font-size:.62rem;background:{{ $isActive ? '#d7f3ee' : '#e2e8f0' }};color:{{ $isActive ? '#0b5b48' : '#475569' }}">{{ $isActive ? 'Active' : ($today->lt($program->starts_on) ? 'Upcoming' : 'Ended') }}</span>
      </h3>
      <p class="text-muted mb-0 small">{{ $program->starts_on->format('d M Y') }} – {{ $program->ends_on->format('d M Y') }} ({{ $program->starts_on->diffInDays($program->ends_on) }} days) · Real-time progress for every participant in this program.</p>
    </div>
    <div class="d-flex gap-2">
      @if($canManage ?? false)
        <button type="button" class="btn-hitech rounded-pill px-4 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#ndgManageModal"><i class="bx bx-cog"></i> Manage program</button>
      @endif
      <a href="{{ route('performance.ninety-day-goals.programs.index') }}" class="btn btn-hitech-cancel rounded-pill px-4 d-flex align-items-center gap-2"><i class="bx bx-arrow-back"></i> All Programs</a>
    </div>
  </div>

  <div class="px-4">

  {{-- Program info strip --}}
  <div class="hitech-card mb-6">
    <div class="card-body p-4 d-flex flex-wrap align-items-center gap-4">
      <div><div class="small text-muted text-uppercase fw-bold" style="font-size:.65rem">Program Code</div><div class="fw-bold text-dark">PRG-{{ $program->id }}</div></div>
      <div><div class="small text-muted text-uppercase fw-bold" style="font-size:.65rem">Created By</div><div class="fw-bold text-dark">{{ optional($program->creator)->id ? $name($program->creator) : '—' }}</div></div>
      <div><div class="small text-muted text-uppercase fw-bold" style="font-size:.65rem">Participants</div><div class="fw-bold text-dark">{{ $participants }}</div></div>
      <div><div class="small text-muted text-uppercase fw-bold" style="font-size:.65rem">Customers</div><div class="fw-bold text-dark">{{ $cards['goals'] }}</div></div>
      <div><div class="small text-muted text-uppercase fw-bold" style="font-size:.65rem">Target</div><div class="fw-bold text-dark">₹{{ $fmtL($target) }} L</div></div>
      <div><div class="small text-muted text-uppercase fw-bold" style="font-size:.65rem">Achieved</div><div class="fw-bold text-dark">₹{{ $fmtL($achieved) }} L <span style="color:{{ $pctColor($pct) }}">({{ $pctText($pct) }})</span></div></div>
      <div class="flex-grow-1" style="min-width:160px">
        <div class="progress" style="height:8px"><div class="progress-bar" style="width:{{ $pct === null ? 0 : min(100, $pct) }}%;background:{{ $pctColor($pct) }}"></div></div>
      </div>
    </div>
  </div>

  {{-- Sub-tabs --}}
  <div class="d-flex gap-2 flex-wrap mb-6 ndg-subtabs" id="ndgPrTabs">
    @foreach(['overview' => ['Overview','bx-grid-alt'], 'participants' => ['Participants ('.$participants.')','bx-group'], 'customers' => ['Customers ('.$cards['goals'].')','bx-search-alt'], 'weekly' => ['Weekly Tracking','bx-calendar-check'], 'orders' => ['Orders & Results','bx-cart-download'], 'risk' => ['At Risk ('.$atRisk->count().')','bx-error-circle'], 'activity' => ['Activity & Comments','bx-history'], 'reports' => ['Reports','bx-download']] as $key => [$lbl, $ic])
      <button type="button" class="ndg-subtab {{ $key === 'overview' ? 'on' : '' }}" data-tab="{{ $key }}"><i class="bx {{ $ic }}"></i>{{ $lbl }}</button>
    @endforeach
  </div>

  {{-- ═══════════════════ Overview ═══════════════════ --}}
  <div class="ndg-pr-section" data-section="overview">

    @if($cards['goals'] === 0)
      <div class="hitech-card mb-6" style="border-color:#f5a623">
        <div class="card-body p-4 d-flex align-items-center gap-3">
          <i class="bx bx-info-circle fs-3" style="color:#f5a623"></i>
          <div>
            <div class="fw-bold text-dark">No customers added yet</div>
            <div class="small text-muted">{{ $participants }} {{ \Illuminate\Support\Str::plural('person', $participants) }} {{ $participants === 1 ? 'is' : 'are' }} enrolled in this program, but none of them have added a target customer yet — every number below is genuinely zero, not an error.</div>
          </div>
        </div>
      </div>
    @endif

    {{-- KPI cards --}}
    <div class="row g-3 mb-6">
      @php
        $kpis = [
          ['bx-building-house', $cards['goals'], 'Total Customers', '#e0f2fe', '#0369a1'],
          ['bx-check-circle', ($statusCounts['On Track'] ?? 0), 'On Track', '#dcfce7', '#166534'],
          ['bx-error-alt', $atRisk->count(), 'At Risk', '#fee2e2', '#b42318'],
          ['bx-trending-down', ($statusCounts['Off Track'] ?? 0), 'Off Track', '#ffedd5', '#c2540c'],
          ['bx-flask', ($statusCounts['Trial Order'] ?? 0), 'First Trial Order', '#ede9fe', '#5b21b6'],
          ['bx-cart-download', ($statusCounts['Regular Order'] ?? 0), 'Regular Order', '#ccfbf1', '#0f766e'],
          ['bx-shield-x', ($statusCounts['Dropped'] ?? 0), 'Dropped', '#fee2e2', '#991b1b'],
          ['bx-target-lock', $fmtL($achieved) . ' L', 'Achieved (' . $pctText($pct) . ')', '#dbeafe', '#1d4ed8'],
        ];
      @endphp
      @foreach($kpis as [$icon, $val, $lbl, $bg, $fg])
        <div class="col-6 col-md-3">
          <div class="ndg-kpi2">
            <div class="ndg-kpi2-icon" style="background:{{ $bg }};color:{{ $fg }}"><i class="bx {{ $icon }}"></i></div>
            <div><div class="ndg-kpi2-v">{{ $val }}</div><div class="ndg-kpi2-l">{{ $lbl }}</div></div>
          </div>
        </div>
      @endforeach
    </div>

    {{-- Weekly Submission Progress strip --}}
    <div class="hitech-card mb-6">
      <div class="card-body p-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="fw-bold text-dark">Weekly Submission Progress (Team)</div>
        <div class="d-flex align-items-center gap-3">
          <div class="small text-muted">Current week: W{{ $currentWeek + 1 }} · {{ $program->starts_on->copy()->addDays(7 * $currentWeek)->format('d M Y') }}</div>
          {{-- A future week has no check-ins yet, so remind for the selected week only up to the current one. --}}
          @if($canManage ?? false)
          <button type="button" id="ndgPrRemindBtn" class="btn-hitech rounded-pill px-3 py-2 d-flex align-items-center gap-2" style="font-size:.78rem" data-week="{{ min($selectedWeek, $currentWeek) }}">
            <i class="bx bx-bell"></i> Send Reminder
          </button>
          @endif
        </div>
      </div>
      <div class="card-body p-3 d-flex gap-2 ndg-week-strip" id="ndgPrWeekStrip">
        @foreach($weekStrip as $w)
          <a href="?{{ http_build_query(array_merge(request()->except('page'), ['w' => $w['week']])) }}" class="ndg-week-pill {{ $w['week'] === $selectedWeek ? 'on' : '' }} {{ $w['week'] === $currentWeek ? 'is-current' : '' }}">
            <div class="fw-bold">W{{ $w['week'] + 1 }}</div>
            <div class="small">{{ $w['done'] }}/{{ $w['total'] }}{{ $w['total'] ? ' (' . $w['pct'] . '%)' : '' }}</div>
          </a>
        @endforeach
      </div>
    </div>

    {{-- The same customer tracking grid the Customers tab shows, surfaced right on Overview. --}}
    @include('tenant.performance.ninety-day-goals.partials.customer-tracking-table')

    {{-- Charts --}}
    <div class="row g-4 mb-6">
      <div class="col-xl-3 col-md-6">
        <div class="hitech-card h-100">
          <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Week W{{ $selectedWeek + 1 }} Summary</div><div class="small text-muted">{{ $weekDue->format('d M Y') }}</div></div>
          <div class="card-body p-3 text-center"><div id="ndgPrWeekDonut"></div></div>
        </div>
      </div>
      <div class="col-xl-3 col-md-6">
        <div class="hitech-card h-100">
          <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Status Distribution ({{ $cards['goals'] }} Customers)</div></div>
          <div class="card-body p-4">
            @php $maxSt = max(1, max($statusCounts) ?: 1); @endphp
            @foreach($statusCounts as $st => $n)
              @if($n > 0)
                @php [$bg] = $G::STATUS_COLORS[$st] ?? ['#94a3b8']; @endphp
                <div class="ndg-bar">
                  <div class="ndg-bar-top"><span class="ndg-bar-name">{{ $st }}</span><span class="ndg-bar-val">{{ $n }}<small>{{ $cards['goals'] ? round($n / $cards['goals'] * 100) : 0 }}%</small></span></div>
                  <div class="ndg-track"><div class="ndg-fill" style="width:{{ round($n / $maxSt * 100) }}%;background:{{ $bg }}"></div></div>
                </div>
              @endif
            @endforeach
          </div>
        </div>
      </div>
      <div class="col-xl-3 col-md-6">
        <div class="hitech-card h-100">
          <div class="card-body p-4 border-bottom d-flex align-items-center justify-content-between">
            <div class="fw-bold text-dark">Top Performing Salespersons</div>
            <button type="button" class="small text-decoration-none border-0 bg-transparent text-primary fw-semibold ndg-pr-goto" data-tab="participants">View All →</button>
          </div>
          <div class="card-body p-0 table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th class="ps-3">#</th><th>Salesperson</th><th class="text-end">Customers</th><th class="text-end pe-3">Ach. ₹L</th></tr></thead>
              <tbody>
              @forelse($salespeople->take(5) as $i => $u)
                <tr><td class="ps-3 text-muted">{{ $i + 1 }}</td><td class="fw-semibold">{{ $u['name'] }}</td><td class="text-end">{{ $u['goals'] }}</td><td class="text-end pe-3">{{ $fmtL($u['achieved']) }}</td></tr>
              @empty
                <tr><td colspan="4" class="text-center text-muted p-4">No participants yet.</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-xl-3 col-md-6">
        <div class="hitech-card h-100">
          <div class="card-body p-4 border-bottom d-flex align-items-center justify-content-between">
            <div class="fw-bold text-dark">Recent Activity</div>
            <button type="button" class="small text-decoration-none border-0 bg-transparent text-primary fw-semibold ndg-pr-goto" data-tab="activity">View All →</button>
          </div>
          <div class="card-body p-0" style="max-height:320px;overflow-y:auto">
            @forelse($activity->take(8) as $a)
              <div class="ndg-alert-row">
                <i class="bx {{ $a->kind === 'note' ? 'bx-message-square-detail' : 'bx-radio-circle-marked' }}"></i>
                <div class="flex-grow-1">
                  <div class="small text-dark"><b>{{ trim(optional($a->by)->first_name . ' ' . optional($a->by)->last_name) ?: 'Someone' }}</b> {{ $a->kind === 'note' ? 'commented on' : 'updated' }} {{ $a->goal->client_name }}</div>
                  <div class="small text-muted">{{ optional($a->at)->diffForHumans() }}</div>
                </div>
              </div>
            @empty
              <div class="p-4 text-center text-muted small">No activity yet.</div>
            @endforelse
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ═══════════════════ Participants ═══════════════════ --}}
  <div class="ndg-pr-section d-none" data-section="participants">
    <div class="hitech-card mb-6">
      <div class="card-body p-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="fw-bold text-dark">Participants</div>
        {{-- Customer-count light: green at the minimum, amber close, red behind. --}}
        <div class="d-flex flex-wrap gap-3 small text-muted">
          <span class="d-inline-flex align-items-center gap-1"><span class="ndg-dot" style="background:#16a34a"></span>{{ $min }}+ on target</span>
          <span class="d-inline-flex align-items-center gap-1"><span class="ndg-dot" style="background:#d97706"></span>{{ $amber }}–{{ $min - 1 }} almost there</span>
          <span class="d-inline-flex align-items-center gap-1"><span class="ndg-dot" style="background:#dc2626"></span>under {{ $amber }} behind</span>
        </div>
      </div>
      <div class="card-body p-0 table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th class="ps-4">#</th><th>Salesperson</th><th class="text-center">Customers</th><th class="text-center" style="min-width:230px">Current Status</th><th class="text-end">Target ₹L</th><th class="text-end">Achieved ₹L</th><th style="min-width:160px">Achievement</th><th class="text-end pe-4">Orders</th></tr></thead>
          <tbody>
          @foreach($salespeople as $i => $u)
            @php
              $meta = ($participantMeta ?? collect())[$u['id']] ?? null;
              $rag = $meta['rag'] ?? $G::customerRag((int) $u['goals']);
            @endphp
            <tr>
              <td class="ps-4 text-muted">{{ $i + 1 }}</td>
              <td><a href="{{ route('performance.ninety-day-goals.person', $u['id']) }}" class="fw-semibold text-dark">{{ $u['name'] }}</a><div class="small text-muted">{{ $u['department'] }}</div></td>
              <td class="text-center">
                <span class="ndg-rag" style="background:{{ $rag['bg'] }};color:{{ $rag['fg'] }}" title="{{ $rag['label'] }} — {{ $u['goals'] }} of {{ $min }}">
                  <span class="ndg-dot" style="background:{{ $rag['dot'] }}"></span>{{ $u['goals'] }}/{{ $min }}
                </span>
              </td>
              @php
                $st = ($participantStatus ?? [])[$u['id']] ?? ['label' => '—', 'tone' => 'muted'];
                $tone = [
                  'green' => ['#dcfce7', '#166534', '#16a34a'],
                  'amber' => ['#fef3c7', '#92400e', '#d97706'],
                  'red'   => ['#fee2e2', '#991b1b', '#dc2626'],
                  'muted' => ['#f1f5f9', '#475569', '#94a3b8'],
                ][$st['tone']] ?? ['#f1f5f9', '#475569', '#94a3b8'];
              @endphp
              <td class="text-center small">
                <span class="ndg-rag" style="background:{{ $tone[0] }};color:{{ $tone[1] }}">
                  <span class="ndg-dot" style="background:{{ $tone[2] }}"></span>{{ $st['label'] }}
                </span>
                @if($meta && $meta['reminder_sent_at'])
                  <div class="text-muted" style="font-size:.62rem"><i class="bx bx-envelope"></i> reminder sent {{ \Illuminate\Support\Carbon::parse($meta['reminder_sent_at'])->format('d M, h:i A') }}</div>
                @elseif($meta && !$meta['meets_min'] && $meta['open'])
                  <div class="text-muted" style="font-size:.62rem">window till {{ $meta['closes_at']->format('d M, h:i A') }}</div>
                @elseif($meta && !$meta['meets_min'] && !$meta['open'])
                  <div class="text-muted" style="font-size:.62rem">closed {{ $meta['closes_at']->format('d M, h:i A') }}</div>
                @endif
              </td>
              <td class="text-end">{{ $fmtL($u['target']) }}</td>
              <td class="text-end">{{ $fmtL($u['achieved']) }}</td>
              <td><div class="d-flex align-items-center gap-2"><div class="ndg-track flex-grow-1" style="height:8px"><div class="ndg-fill" style="width:{{ min(100, (float) $u['pct']) }}%;background:{{ $pctColor($u['pct']) }}"></div></div><b class="small" style="min-width:44px;text-align:right">{{ $pctText($u['pct']) }}</b></div></td>
              <td class="text-end pe-4">{{ $u['converted'] }}</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- ═══════════════════ Customers ═══════════════════ --}}
  <div class="ndg-pr-section d-none" data-section="customers">
    @include('tenant.performance.ninety-day-goals.partials.customer-tracking-table')
  </div>

  {{-- ═══════════════════ Weekly Tracking ═══════════════════ --}}
  <div class="ndg-pr-section d-none" data-section="weekly">
    <div class="hitech-card mb-6">
      <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Compliance by week</div></div>
      <div class="card-body p-0 table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th class="ps-4">Week</th><th class="text-end">Expected</th><th class="text-end">Submitted</th><th class="text-end">Pending</th><th class="text-end pe-4">Compliance</th></tr></thead>
          <tbody>
          @foreach($weekStrip as $w)
            <tr>
              <td class="ps-4 fw-semibold">W{{ $w['week'] + 1 }}</td>
              <td class="text-end">{{ $w['total'] }}</td>
              <td class="text-end">{{ $w['done'] }}</td>
              <td class="text-end"><span class="{{ $w['total'] - $w['done'] ? 'text-danger fw-bold' : '' }}">{{ ($w['total'] - $w['done']) ?: '·' }}</span></td>
              <td class="text-end pe-4">{{ $w['pct'] }}%</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- ═══════════════════ Orders & Results ═══════════════════ --}}
  <div class="ndg-pr-section d-none" data-section="orders">
    {{-- Marked as an order by the salesperson, but the Sales Report has no sale for the customer in
         this program's months yet. These do not count as converted until the sale is entered there. --}}
    @php $awaiting = $rows->filter(fn ($r) => in_array($r->status, $G::ORDER_STATUSES, true) && $r->achieved <= 0)->values(); @endphp
    <div class="hitech-card mb-6" id="ndgAwaitingOrders" @if($awaiting->isNotEmpty()) style="border-color:#f59e0b" @endif>
      <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Orders awaiting Sales Report ({{ $awaiting->count() }})</div><div class="small text-muted">Marked as an order, but no sale for the customer is on the Sales Report yet - they count once it is</div></div>
      <div class="card-body p-0 table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th class="ps-4">Customer</th><th>Salesperson</th><th>Product</th><th class="pe-4">Status</th></tr></thead>
          <tbody>
          @forelse($awaiting as $r)
            <tr>
              <td class="ps-4 fw-semibold text-dark">{{ $r->goal->client_name }}</td>
              <td>{{ trim(optional($r->user)->first_name . ' ' . optional($r->user)->last_name) }}</td>
              <td class="small text-muted">{{ $r->goal->product }}</td>
              <td class="pe-4"><span class="ndg-chip" style="background:{{ $G::STATUS_COLORS[$r->status][0] ?? '#e2e8f0' }};color:{{ $G::STATUS_COLORS[$r->status][1] ?? '#475569' }}">{{ $r->status }}</span>@include('tenant.performance.ninety-day-goals.partials.order-check', ['r' => $r])</td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-center text-muted p-4">None - every order marked so far is on the Sales Report.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
    <div class="hitech-card mb-6">
      <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Qualifying orders</div><div class="small text-muted">Real Sales Report sales only, never typed in</div></div>
      <div class="card-body p-0 table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th class="ps-4">Customer</th><th>Salesperson</th><th>Product</th><th class="text-end">Order value ₹L</th><th class="pe-4">Status</th></tr></thead>
          <tbody>
          @forelse($rows->filter(fn ($r) => $r->achieved > 0)->sortByDesc('achieved') as $r)
            <tr>
              <td class="ps-4 fw-semibold">{{ $r->goal->client_name }}</td>
              <td class="small text-muted">{{ $name($r->user) }}</td>
              <td class="small text-muted">{{ $r->goal->product }}</td>
              <td class="text-end fw-semibold">{{ $fmtL($r->achieved) }}@if($r->repeat)<span class="badge bg-label-info rounded-pill ms-1" style="font-size:.6rem">repeat</span>@endif</td>
              <td class="pe-4">@if($r->status)<span class="ndg-chip" style="background:{{ $G::STATUS_COLORS[$r->status][0] ?? '#e2e8f0' }};color:{{ $G::STATUS_COLORS[$r->status][1] ?? '#475569' }}">{{ $r->status }}</span>@include('tenant.performance.ninety-day-goals.partials.order-check', ['r' => $r])@endif</td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted p-4">No qualifying orders yet.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- ═══════════════════ At Risk ═══════════════════ --}}
  <div class="ndg-pr-section d-none" data-section="risk">
    <div class="hitech-card mb-6">
      <div class="card-body p-4 border-bottom">
        <div class="fw-bold text-dark">{{ $atRisk->count() }} customers need a look</div>
        <div class="small text-muted">Off track, silent, dropped, or a status that never came — most urgent first.</div>
      </div>
      <div class="card-body p-0 table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th class="ps-4">Salesperson</th><th>Customer</th><th>Brand</th><th class="text-end">Monthly ₹L</th><th>Latest status</th><th class="pe-4">Not sent</th></tr></thead>
          <tbody>
          @forelse($atRisk as $r)
            @php [$bg, $fg] = $G::STATUS_COLORS[$r->status] ?? ['#e2e8f0', '#475569']; @endphp
            <tr>
              <td class="ps-4">{{ $name($r->user) }}</td>
              <td class="fw-semibold">{{ $r->goal->client_name }}</td>
              <td>{{ $r->brand }}</td>
              <td class="text-end">{{ $fmtL($r->potential) }}</td>
              <td>@if($r->status)<span class="ndg-chip" style="background:{{ $bg }};color:{{ $fg }}">{{ $r->status }}</span>@include('tenant.performance.ninety-day-goals.partials.order-check', ['r' => $r])@else<span class="ndg-none">nothing yet</span>@endif</td>
              <td class="pe-4">
                @if($r->overdueWeeks > 0)<span class="ndg-overdue">{{ $r->overdueWeeks }} {{ \Illuminate\Support\Str::plural('week', $r->overdueWeeks) }}</span>@else<span class="ndg-none">up to date</span>@endif
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted p-4">Nothing needs attention right now.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- ═══════════════════ Activity & Comments ═══════════════════ --}}
  <div class="ndg-pr-section d-none" data-section="activity">
    <div class="hitech-card mb-6">
      <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">{{ $activity->count() }} events</div></div>
      <div class="card-body p-0">
        @forelse($activity as $a)
          <div class="ndg-alert-row">
            <i class="bx {{ $a->kind === 'note' ? 'bx-message-square-detail' : 'bx-radio-circle-marked' }}"></i>
            <div class="flex-grow-1">
              <div class="small text-dark"><b>{{ trim(optional($a->by)->first_name . ' ' . optional($a->by)->last_name) ?: 'Someone' }}</b> {{ $a->kind === 'note' ? 'commented on' : 'updated' }} {{ $a->goal->client_name }} @if($a->status)· <span class="ndg-chip" style="background:{{ $G::STATUS_COLORS[$a->status][0] ?? '#e2e8f0' }};color:{{ $G::STATUS_COLORS[$a->status][1] ?? '#475569' }}">{{ $a->status }}</span>@endif</div>
              @if($a->text)<div class="small text-muted">{{ $a->text }}</div>@endif
              <div class="small text-muted">{{ optional($a->at)->diffForHumans() }}</div>
            </div>
          </div>
        @empty
          <div class="p-4 text-center text-muted small">No activity yet.</div>
        @endforelse
      </div>
    </div>
  </div>

  {{-- ═══════════════════ Reports ═══════════════════ --}}
  <div class="ndg-pr-section d-none" data-section="reports">
    <div class="hitech-card mb-6">
      <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Export</div><div class="small text-muted">Downloads this program's complete filtered set, not just the page on screen.</div></div>
      <div class="card-body p-4">
        <a href="{{ route('performance.ninety-day-goals.admin-view.export', array_merge($filters, ['program_id' => $program->id])) }}" class="btn-hitech rounded-pill px-5 d-inline-flex align-items-center gap-2">
          <i class="bx bx-download fs-5"></i><span class="fw-bold">Export to Excel (CSV)</span>
        </a>
        <div class="small text-muted mt-3">{{ $rows->count() }} {{ \Illuminate\Support\Str::plural('row', $rows->count()) }} match the current filters.</div>
      </div>
    </div>
  </div>

  </div>
</div>

@if($canManage ?? false)
{{-- Manage program: change name and dates, add or remove people, reopen the window to add customers, delete (admin only). --}}
<div class="modal fade" id="ndgManageModal" tabindex="-1" aria-hidden="true"
     data-url="{{ route('performance.ninety-day-goals.program.update', $program->id) }}" data-name="{{ $program->name }}">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Manage program · {{ $program->name }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">

        <h6 class="fw-bold text-dark mb-2">Program details</h6>
        <div class="row g-2 mb-1">
          <div class="col-md-6"><label class="form-label small mb-1" for="ndgMgName">Name</label><input type="text" id="ndgMgName" class="form-control" maxlength="150" value="{{ $program->name }}"></div>
          <div class="col-md-3"><label class="form-label small mb-1" for="ndgMgStart">Start</label><input type="date" id="ndgMgStart" class="form-control" value="{{ $program->starts_on->toDateString() }}" {{ ($startLocked ?? false) ? 'disabled' : '' }}></div>
          <div class="col-md-3"><label class="form-label small mb-1" for="ndgMgEnd">End</label><input type="date" id="ndgMgEnd" class="form-control" value="{{ $program->ends_on->toDateString() }}"></div>
        </div>
        <div class="small text-muted mb-2">
          Weeks run Monday to Monday, so a start date is moved back to its Monday.
          @if($startLocked ?? false) The start date is locked: weekly statuses have already been submitted against the current weeks. @endif
        </div>
        <button type="button" id="ndgMgSaveDetails" class="btn-hitech rounded-pill px-4 py-2 mb-4" style="font-size:.8rem">Save details</button>

        <h6 class="fw-bold text-dark mb-2">Participants ({{ ($manageRounds ?? collect())->count() }})</h6>
        <div class="table-responsive mb-2" style="max-height:260px">
          <table class="table table-sm align-middle mb-0" style="font-size:.8rem">
            <thead><tr><th>Name</th><th class="text-end">Customers</th><th>Adding customers</th><th></th></tr></thead>
            <tbody>
              @forelse(($manageRounds ?? collect()) as $r)
                <tr>
                  <td>{{ $name($r->user) }} <span class="text-muted">{{ $r->user->code }}</span></td>
                  <td class="text-end">{{ $r->customers }}</td>
                  <td>
                    @if($r->window['open'])
                      <span class="text-success">open until {{ $r->window['closes_at']->format('d M, h:i A') }}</span>
                    @else
                      <span class="text-muted">closed {{ $r->window['closes_at']->format('d M, h:i A') }}</span>
                    @endif
                  </td>
                  <td class="text-end"><button type="button" class="btn btn-sm btn-hitech-cancel py-0 ndg-mg-remove" data-user="{{ $r->user->id }}" data-name="{{ $name($r->user) }}" data-customers="{{ $r->customers }}">Remove</button></td>
                </tr>
              @empty
                <tr><td colspan="4" class="text-muted">Nobody is in this program.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        <label class="form-label small mb-1" for="ndgMgAddSelect">Add people <span class="text-muted">(hold Ctrl to pick several)</span></label>
        <div class="d-flex gap-2 align-items-start mb-4">
          <select id="ndgMgAddSelect" class="form-select" multiple size="5" style="font-size:.8rem">
            @foreach(($addablePeople ?? collect()) as $p)<option value="{{ $p->id }}">{{ $p->first_name }} {{ $p->last_name }} ({{ $p->code }})</option>@endforeach
          </select>
          <button type="button" id="ndgMgAdd" class="btn-hitech rounded-pill px-4 py-2 text-nowrap" style="font-size:.8rem">Add</button>
        </div>

        @php
          // Default the picker to the window that is ACTUALLY open now (the latest current
          // close across the program's rounds) so the admin sees the real end and does not
          // accidentally shorten it. Only fall back to now+48h when no window is open.
          $openWindows = ($manageRounds ?? collect())->filter(fn ($r) => data_get($r->window, 'open'))
            ->map(fn ($r) => data_get($r->window, 'closes_at'))->filter();
          $windowDefault = $openWindows->isNotEmpty()
            ? $openWindows->sortByDesc(fn ($c) => $c->getTimestamp())->first()
            : now()->addHours(48);
        @endphp
        <h6 class="fw-bold text-dark mb-2">Window to add customers</h6>
        <div class="small text-muted mb-2">Normally open for 48 hours after someone is enrolled. Reopen it until a time you choose.@if($openWindows->isNotEmpty()) <span class="text-dark">Currently open until {{ $windowDefault->format('d M Y, h:i A') }}.</span>@endif</div>
        <div class="row g-2 align-items-end mb-2">
          <div class="col-md-5"><label class="form-label small mb-1" for="ndgMgUntil">Open until</label><input type="datetime-local" id="ndgMgUntil" class="form-control" value="{{ $windowDefault->format('Y-m-d\TH:i') }}"></div>
          <div class="col-md-7 small">
            <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="ndgMgWho" id="ndgMgWhoAll" value="all" checked><label class="form-check-label" for="ndgMgWhoAll">Everyone in the program</label></div>
            <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="ndgMgWho" id="ndgMgWhoSome" value="some"><label class="form-check-label" for="ndgMgWhoSome">Chosen people</label></div>
          </div>
        </div>
        <select id="ndgMgWindowSelect" class="form-select d-none mb-2" multiple size="5" style="font-size:.8rem">
          @foreach(($manageRounds ?? collect()) as $r)<option value="{{ $r->user->id }}">{{ $name($r->user) }}</option>@endforeach
        </select>
        <button type="button" id="ndgMgOpenWindow" class="btn-hitech rounded-pill px-4 py-2 mb-4" style="font-size:.8rem">Open window</button>

        @if($canDeleteProgram ?? false)
          <div class="border border-danger rounded-3 p-3">
            <h6 class="fw-bold text-danger mb-1">Delete program</h6>
            <div class="small text-muted mb-2">
              Removes the program with its {{ $footprint['participants'] ?? 0 }} participant(s), {{ $footprint['customers'] ?? 0 }} customer(s),
              {{ $footprint['statuses'] ?? 0 }} submitted weekly status(es), every task it created and {{ $footprint['notifications'] ?? 0 }} notification(s).
              Type the program name to confirm.
            </div>
            <div class="d-flex gap-2">
              <input type="text" id="ndgMgConfirmName" class="form-control form-control-sm" placeholder="{{ $program->name }}" autocomplete="off">
              <button type="button" id="ndgMgDelete" class="btn btn-sm btn-danger rounded-pill px-3 text-nowrap">Delete program</button>
            </div>
          </div>
        @endif
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-hitech-cancel rounded-pill px-4" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
@endif

<style>
  .ndg-subtab { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 10px; border: 1px solid #d5e3e1; background: #fff; color: #334155; font-weight: 700; font-size: .8rem; cursor: pointer; }
  .ndg-subtab:hover { border-color: #007a7a; color: #007a7a; }
  .ndg-subtab.on { background: #007a7a; border-color: #007a7a; color: #fff; }
  .ndg-kpi2 { border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 14px; background: #fff; height: 100%; display: flex; align-items: center; gap: 10px; }
  .ndg-kpi2-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex: none; }
  .ndg-kpi2-v { font-size: 1.2rem; font-weight: 800; color: #0f172a; line-height: 1.2; font-variant-numeric: tabular-nums; }
  .ndg-kpi2-l { font-size: .68rem; color: #94a3b8; font-weight: 600; }
  .ndg-week-strip { overflow-x: auto; }
  .ndg-week-pill { flex: none; min-width: 74px; border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px 12px; text-align: center; text-decoration: none; color: #334155; }
  .ndg-week-pill:hover { border-color: #007a7a; }
  .ndg-week-pill.on { background: #007a7a; border-color: #007a7a; color: #fff; }
  .ndg-week-pill.is-current:not(.on) { border-color: #f5a623; box-shadow: inset 0 0 0 1px #f5a623; }
  .ndg-chip { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: .72rem; font-weight: 700; }
  .ndg-none { color: #cbd5e1; }
  .ndg-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; flex: none; }
  .ndg-rag { display: inline-flex; align-items: center; gap: 5px; padding: 2px 10px; border-radius: 999px; font-size: .72rem; font-weight: 700; white-space: nowrap; }
  .ndg-overdue { color: #b42318; font-weight: 700; font-size: .82rem; }
  .ndg-bar { margin-bottom: 12px; }
  .ndg-bar-top { display: flex; justify-content: space-between; gap: 10px; margin-bottom: 4px; font-size: .84rem; }
  .ndg-bar-name { font-weight: 600; color: #0f172a; }
  .ndg-bar-val { font-weight: 800; white-space: nowrap; }
  .ndg-bar-val small { font-weight: 400; color: #94a3b8; margin-left: 4px; }
  .ndg-track { height: 7px; background: #eef2f5; border-radius: 99px; overflow: hidden; }
  .ndg-fill { height: 100%; background: #007a7a; border-radius: 99px; min-width: 2px; }
  .ndg-alert-row { display: flex; align-items: start; gap: 10px; padding: 10px 16px; border-bottom: 1px solid #f1f5f9; }
  .ndg-alert-row:last-child { border-bottom: none; }
  .ndg-alert-row i { font-size: 1.1rem; color: #007a7a; flex: none; margin-top: 2px; }
  .ndg-mx-scroll { overflow: auto; }
  .ndg-mx { border-collapse: separate; border-spacing: 3px; padding: 0 16px 8px; width: 100%; font-size: .74rem; }
  .ndg-mx thead th { position: sticky; top: 0; background: #fff; font-weight: 700; color: #64748b; font-size: .66rem; text-align: center; padding: 8px 4px 6px; z-index: 2; white-space: nowrap; }
  .ndg-mx-l { text-align: left !important; white-space: nowrap; padding: 4px 10px !important; font-weight: 600; color: #0f172a; position: sticky; background: #fff; z-index: 1; }
  .ndg-mx-c { width: 22px; height: 22px; min-width: 22px; border-radius: 4px; box-shadow: inset 0 0 0 1px #e2e8f0; background: #f1f5f9; }
</style>
@endsection

@section('page-script')
<script>
(function () {
  var tabs = document.querySelectorAll('#ndgPrTabs .ndg-subtab');
  var sections = document.querySelectorAll('.ndg-pr-section');
  function show(key) {
    tabs.forEach(function (t) { t.classList.toggle('on', t.dataset.tab === key); });
    sections.forEach(function (s) { s.classList.toggle('d-none', s.dataset.section !== key); });
  }
  tabs.forEach(function (t) { t.addEventListener('click', function () { show(t.dataset.tab); }); });
  document.querySelectorAll('.ndg-pr-goto').forEach(function (b) { b.addEventListener('click', function () { show(b.dataset.tab); window.scrollTo({ top: 0, behavior: 'smooth' }); }); });

  // Scroll the week strip so the selected/current week is in view.
  var strip = document.getElementById('ndgPrWeekStrip');
  var onWeek = strip ? strip.querySelector('.ndg-week-pill.on') : null;
  if (onWeek) { onWeek.scrollIntoView({ inline: 'center', block: 'nearest' }); }

  // Send Reminder: nudges only whoever hasn't submitted the currently-selected week yet.
  var remindBtn = document.getElementById('ndgPrRemindBtn');
  if (remindBtn) {
    remindBtn.addEventListener('click', function () {
      var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
      remindBtn.disabled = true;
      fetch('{{ route('performance.ninety-day-goals.program.remind', $program->id) }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ week: remindBtn.dataset.week }),
      }).then(function (r) { return r.json(); })
        .then(function (j) {
          remindBtn.disabled = false;
          var msg = j.message || 'Could not send the reminder.';
          if (j.success) {
            if (typeof window.showSuccessSwal === 'function') { window.showSuccessSwal(msg); } else { alert(msg); }
          } else if (typeof window.showErrorSwal === 'function') { window.showErrorSwal(msg); } else { alert(msg); }
        })
        .catch(function () { remindBtn.disabled = false; alert('Request failed. Try again.'); });
    });
  }
})();

// Manage program: details, participants, customer window, delete.
(function () {
  var modal = document.getElementById('ndgManageModal');
  if (!modal) { return; }
  var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  var tell = function (ok, msg) {
    if (ok && typeof window.showSuccessSwal === 'function') { window.showSuccessSwal(msg); }
    else if (!ok && typeof window.showErrorSwal === 'function') { window.showErrorSwal(msg); }
    else { alert(msg); }
  };
  var send = function (method, url, body, btn, reloadOnOk) {
    if (btn) { btn.disabled = true; }
    return fetch(url, { method: method, headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: body ? JSON.stringify(body) : null })
      .then(function (r) { return r.json().catch(function () { return { success: false, message: 'Unexpected response (' + r.status + ').' }; }); })
      .then(function (j) {
        if (btn) { btn.disabled = false; }
        var msg = j.message || (j.errors ? Object.values(j.errors).flat().join(' ') : 'Could not save.');
        tell(!!j.success, msg);
        if (j.success && j.redirect) { setTimeout(function () { window.location.href = j.redirect; }, 1200); }
        else if (j.success && reloadOnOk) { setTimeout(function () { window.location.reload(); }, 1200); }
        return j;
      })
      .catch(function () { if (btn) { btn.disabled = false; } tell(false, 'Request failed. Try again.'); });
  };
  var url = modal.dataset.url;

  // "Edit" on the Programs list lands here with ?manage=1: open the panel straight away.
  if (new URLSearchParams(window.location.search).get('manage') === '1') {
    window.addEventListener('load', function () {
      if (window.bootstrap && window.bootstrap.Modal) { window.bootstrap.Modal.getOrCreateInstance(modal).show(); }
    });
  }

  document.getElementById('ndgMgSaveDetails').addEventListener('click', function () {
    send('PUT', url, { name: document.getElementById('ndgMgName').value, starts_on: document.getElementById('ndgMgStart').value, ends_on: document.getElementById('ndgMgEnd').value }, this, true);
  });

  document.getElementById('ndgMgAdd').addEventListener('click', function () {
    var ids = Array.prototype.map.call(document.getElementById('ndgMgAddSelect').selectedOptions, function (o) { return parseInt(o.value, 10); });
    if (!ids.length) { tell(false, 'Choose at least one person to add.'); return; }
    send('POST', url + '/participants', { user_ids: ids }, this, true);
  });

  modal.querySelectorAll('.ndg-mg-remove').forEach(function (b) {
    b.addEventListener('click', function () {
      var who = b.dataset.name, n = parseInt(b.dataset.customers, 10);
      if (!confirm('Remove ' + who + ' from this program?' + (n ? '\n\nTheir ' + n + ' customer(s) and weekly statuses in this program are removed too.' : '') + '\n\nThe tasks and notifications this program gave them are removed.')) { return; }
      send('DELETE', url + '/participants/' + b.dataset.user, null, b, true);
    });
  });

  document.getElementById('ndgMgOpenWindow').addEventListener('click', function () {
    var until = document.getElementById('ndgMgUntil').value;
    if (!until) { tell(false, 'Pick until when adding customers should stay open.'); return; }
    var who = document.querySelector('input[name="ndgMgWho"]:checked').value;
    var ids = who === 'some' ? Array.prototype.map.call(document.getElementById('ndgMgWindowSelect').selectedOptions, function (o) { return parseInt(o.value, 10); }) : null;
    if (who === 'some' && !ids.length) { tell(false, 'Choose who the window is for.'); return; }
    send('POST', url + '/window', { until: until, user_ids: ids }, this, true);
  });
  document.querySelectorAll('input[name="ndgMgWho"]').forEach(function (r) {
    r.addEventListener('change', function () { document.getElementById('ndgMgWindowSelect').classList.toggle('d-none', r.value !== 'some' || !r.checked); });
  });

  var del = document.getElementById('ndgMgDelete');
  if (del) {
    del.addEventListener('click', function () {
      var typed = document.getElementById('ndgMgConfirmName').value;
      if (typed.trim() !== modal.dataset.name.trim()) { tell(false, 'Type the program name exactly to confirm.'); return; }
      if (!confirm('Delete "' + modal.dataset.name + '" and everything in it? This cannot be undone from the screen.')) { return; }
      send('DELETE', url, { confirm_name: typed }, del, false);
    });
  }
})();

document.addEventListener('DOMContentLoaded', function () {
  if (typeof ApexCharts === 'undefined') { return; }
  var teal = '#007a7a', muted = '#64748b';

  var weekDonutEl = document.querySelector('#ndgPrWeekDonut');
  if (weekDonutEl) {
    var ws = @json($weekSummary);
    new ApexCharts(weekDonutEl, {
      chart: { type: 'donut', height: 240, fontFamily: 'inherit' },
      series: [ws.submitted, ws.pending, ws.overdue, ws.notStarted],
      labels: ['Submitted', 'Pending', 'Overdue', 'Not Started'],
      colors: [teal, '#f5a623', '#ef4444', '#cbd5e1'],
      legend: { position: 'bottom', fontSize: '11px' },
      dataLabels: { enabled: false },
      plotOptions: { pie: { donut: { size: '68%' } } },
    }).render();
  }
});
</script>
@endsection
