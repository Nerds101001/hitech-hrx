@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')

@section('title', '90-Day Goals — ' . $program->name)

@section('vendor-style')
  @vite(['resources/assets/vendor/libs/apex-charts/apex-charts.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/apex-charts/apexcharts.js'])
@endsection

@section('content')
@php
  $G = \App\Services\NinetyDayGoalService::class;
  $fmtL = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.') ?: '0';
  $pctText = fn ($v) => $v === null ? '–' : rtrim(rtrim(number_format($v, 1), '0'), '.') . '%';
  $pctColor = fn ($v) => $v === null ? '#cbd5e1' : ($v >= 66 ? '#007a7a' : ($v >= 33 ? '#f5a623' : '#ef4444'));
  $name = fn ($u) => trim(optional($u)->first_name . ' ' . optional($u)->last_name);
  $isActive = $today->between($program->starts_on, $program->ends_on);
  $target = (float) $cards['target'];
  $achieved = (float) $cards['achieved'];
  $pct = $cards['pct'];
  $statusColorMap = collect($G::STATUS_COLORS)->map(fn ($clr) => $clr[0]);
  $statusChartData = $statusCounts;

  // Server-side pagination for the customer table — 10 per page, same as the reference.
  $perPage = 10;
  $page = max(1, request()->integer('page', 1));
  $paged = new \Illuminate\Pagination\LengthAwarePaginator(
      $rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page,
      ['path' => request()->url(), 'query' => request()->except('page')]
  );
@endphp
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4 mx-4">
    <div>
      <nav class="small text-muted mb-1"><a href="{{ route('tenant.dashboard') }}" class="text-muted">Dashboard</a> <i class="bx bx-chevron-right"></i> <a href="{{ route('performance.ninety-day-goals.program-dashboard') }}" class="text-muted">90-Day Goals</a> <i class="bx bx-chevron-right"></i> Program Details</nav>
      <h3 class="fw-extrabold mb-1 text-dark d-flex align-items-center gap-2">
        {{ $program->name }}
        <span class="badge rounded-pill fw-bold" style="font-size:.62rem;background:{{ $isActive ? '#d7f3ee' : '#e2e8f0' }};color:{{ $isActive ? '#0b5b48' : '#475569' }}">{{ $isActive ? 'Active' : ($today->lt($program->starts_on) ? 'Upcoming' : 'Ended') }}</span>
      </h3>
      <p class="text-muted mb-0 small">{{ $program->starts_on->format('d M Y') }} – {{ $program->ends_on->format('d M Y') }} ({{ $program->starts_on->diffInDays($program->ends_on) }} days) · Real-time progress for every participant in this program.</p>
    </div>
    <a href="{{ route('performance.ninety-day-goals.program-dashboard') }}" class="btn btn-hitech-cancel rounded-pill px-4 d-flex align-items-center gap-2"><i class="bx bx-arrow-back"></i> Back to Dashboard</a>
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
      <div class="card-body p-4 border-bottom d-flex align-items-center justify-content-between">
        <div class="fw-bold text-dark">Weekly Submission Progress (Team)</div>
        <div class="small text-muted">Current week: W{{ $currentWeek }} · {{ $weekDue->format('d M Y') }}</div>
      </div>
      <div class="card-body p-3 d-flex gap-2 ndg-week-strip" id="ndgPrWeekStrip">
        @foreach($weekStrip as $w)
          <a href="?{{ http_build_query(array_merge(request()->except('page'), ['w' => $w['week']])) }}" class="ndg-week-pill {{ $w['week'] === $selectedWeek ? 'on' : '' }} {{ $w['week'] === $currentWeek ? 'is-current' : '' }}">
            <div class="fw-bold">W{{ $w['week'] }}</div>
            <div class="small">{{ $w['done'] }}/{{ $w['total'] }}{{ $w['total'] ? ' (' . $w['pct'] . '%)' : '' }}</div>
          </a>
        @endforeach
      </div>
    </div>

    {{-- Charts --}}
    <div class="row g-4 mb-6">
      <div class="col-xl-4">
        <div class="hitech-card h-100">
          <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Week W{{ $selectedWeek }} Summary</div><div class="small text-muted">{{ $weekDue->format('d M Y') }}</div></div>
          <div class="card-body p-3 text-center"><div id="ndgPrWeekDonut"></div></div>
        </div>
      </div>
      <div class="col-xl-4">
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
      <div class="col-xl-4">
        <div class="hitech-card h-100">
          <div class="card-body p-4 border-bottom d-flex align-items-center justify-content-between">
            <div class="fw-bold text-dark">Top Performing Salespersons</div>
            <button type="button" class="small text-decoration-none border-0 bg-transparent text-primary fw-semibold ndg-pr-goto" data-tab="participants">View All →</button>
          </div>
          <div class="card-body p-0 table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th class="ps-3">#</th><th>Salesperson</th><th class="text-end">Customers</th><th class="text-end pe-3">Ach. ₹</th></tr></thead>
              <tbody>
              @forelse($salespeople->take(5) as $i => $u)
                <tr><td class="ps-3 text-muted">{{ $i + 1 }}</td><td class="fw-semibold">{{ $u['name'] }}</td><td class="text-end">{{ $u['goals'] }}</td><td class="text-end pe-3">{{ number_format($u['achieved'] * 100000 / 100, 0) }}</td></tr>
              @empty
                <tr><td colspan="4" class="text-center text-muted p-4">No participants yet.</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="hitech-card mb-6">
      <div class="card-body p-4 border-bottom d-flex align-items-center justify-content-between">
        <div class="fw-bold text-dark">Recent Activity</div>
        <button type="button" class="small text-decoration-none border-0 bg-transparent text-primary fw-semibold ndg-pr-goto" data-tab="activity">View All →</button>
      </div>
      <div class="card-body p-0">
        @forelse($activity->take(6) as $a)
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

  {{-- ═══════════════════ Participants ═══════════════════ --}}
  <div class="ndg-pr-section d-none" data-section="participants">
    <div class="hitech-card mb-6">
      <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Participants</div></div>
      <div class="card-body p-0 table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th class="ps-4">#</th><th>Salesperson</th><th class="text-end">Customers</th><th class="text-end">Target ₹L</th><th class="text-end">Achieved ₹L</th><th style="min-width:160px">Achievement</th><th class="text-end pe-4">Orders</th></tr></thead>
          <tbody>
          @foreach($salespeople as $i => $u)
            <tr>
              <td class="ps-4 text-muted">{{ $i + 1 }}</td>
              <td><a href="{{ route('performance.ninety-day-goals.person', $u['id']) }}" class="fw-semibold text-dark">{{ $u['name'] }}</a><div class="small text-muted">{{ $u['department'] }}</div></td>
              <td class="text-end">{{ $u['goals'] }}</td>
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

    {{-- Filters --}}
    <div class="hitech-card mb-4">
      <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-end">
          <input type="hidden" name="w" value="{{ $selectedWeek }}">
          <div class="col-md-3"><input type="text" name="customer" value="{{ $filters['customer'] }}" class="form-control" placeholder="Search customer, product…"></div>
          <div class="col-md-2">
            <select name="status" class="form-select form-select-hitech" onchange="this.form.submit()">
              <option value="">All Status</option>
              @foreach($statuses as $s)<option value="{{ $s }}" {{ $filters['status'] === $s ? 'selected' : '' }}>{{ $s }}</option>@endforeach
            </select>
          </div>
          <div class="col-md-2">
            <select name="brand" class="form-select form-select-hitech" onchange="this.form.submit()">
              <option value="">All Products</option>
              @foreach($brands as $b)<option value="{{ $b }}" {{ $filters['brand'] === $b ? 'selected' : '' }}>{{ $b }}</option>@endforeach
            </select>
          </div>
          <div class="col-md-2">
            <select name="user_id" class="form-select form-select-hitech" onchange="this.form.submit()">
              <option value="">All Salespersons</option>
              @foreach($people as $p)<option value="{{ $p->id }}" {{ (int) $filters['user_id'] === $p->id ? 'selected' : '' }}>{{ $p->first_name }} {{ $p->last_name }}</option>@endforeach
            </select>
          </div>
          <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn-hitech rounded-pill px-4 flex-grow-1">Apply</button>
            <a href="{{ route('performance.ninety-day-goals.program', $program->id) }}" class="btn btn-hitech-cancel rounded-pill px-3">Clear</a>
            <a href="{{ route('performance.ninety-day-goals.admin-view.export', array_merge($filters, ['program_id' => $program->id])) }}" class="btn btn-hitech-cancel rounded-pill px-3"><i class="bx bx-download"></i></a>
          </div>
        </form>
      </div>
    </div>

    <div class="hitech-card mb-6">
      <div class="card-body p-4 border-bottom d-flex align-items-center justify-content-between">
        <div class="fw-bold text-dark">Customer Tracking &amp; Weekly Updates ({{ $rows->count() }} Customers)</div>
      </div>
      <div class="card-body p-0 ndg-mx-scroll">
        <table class="ndg-mx" style="font-size:.74rem">
          <thead><tr>
            <th class="ndg-mx-l" style="left:0">#</th><th class="ndg-mx-l" style="left:30px">Customer</th><th>Product</th><th>Salesperson</th><th>Target ₹</th><th>Expected</th>
            @for($w = 0; $w < $weeks; $w++)<th>W{{ $w }}</th>@endfor
            <th>Actual ₹</th><th>Ach.%</th><th>Status</th><th>Last Comment</th>
          </tr></thead>
          <tbody>
          @foreach($paged as $i => $r)
            @php
              $cks = $r->goal->checkins->keyBy('week_no');
              $lastComment = $r->goal->checkins->whereNotNull('comment')->sortByDesc('week_no')->first();
            @endphp
            <tr>
              <td class="ndg-mx-l" style="left:0">{{ ($paged->currentPage() - 1) * $paged->perPage() + $i + 1 }}</td>
              <td class="ndg-mx-l" style="left:30px;font-weight:600">{{ $r->goal->client_name }}</td>
              <td>{{ $r->goal->product }}</td>
              <td class="small text-muted">{{ $name($r->user) }}</td>
              <td>{{ number_format($r->goal->monthly_potential * 100000 / 100, 0) }}</td>
              <td class="small text-muted">{{ $r->goal->expected_order_date ? $r->goal->expected_order_date->format('d M Y') : '—' }}</td>
              @for($w = 0; $w < $weeks; $w++)
                @php
                  $ck = $cks->get($w);
                  $st = optional($ck)->status;
                  [$bg] = $st ? ($G::STATUS_COLORS[$st] ?? ['#94a3b8']) : [null];
                @endphp
                <td class="ndg-mx-c" style="{{ $bg ? 'background:' . $bg : '' }}" title="{{ $st ?: '—' }}"></td>
              @endfor
              <td class="fw-semibold">{{ number_format($r->achieved * 100000 / 100, 0) }}</td>
              <td style="color:{{ $pctColor($r->pct) }}">{{ $pctText($r->pct) }}</td>
              <td>@if($r->status)<span class="ndg-chip" style="background:{{ $G::STATUS_COLORS[$r->status][0] ?? '#e2e8f0' }};color:{{ $G::STATUS_COLORS[$r->status][1] ?? '#475569' }}">{{ $r->status }}</span>@else<span class="ndg-none">·</span>@endif</td>
              <td class="small text-muted" style="max-width:160px;white-space:normal">{{ $lastComment ? $lastComment->comment : '—' }}</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
      <div class="card-body pt-3">{{ $paged->links() }}</div>
    </div>
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
              <td class="ps-4 fw-semibold">W{{ $w['week'] }}</td>
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
              <td class="pe-4">@if($r->status)<span class="ndg-chip" style="background:{{ $G::STATUS_COLORS[$r->status][0] ?? '#e2e8f0' }};color:{{ $G::STATUS_COLORS[$r->status][1] ?? '#475569' }}">{{ $r->status }}</span>@endif</td>
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
              <td>@if($r->status)<span class="ndg-chip" style="background:{{ $bg }};color:{{ $fg }}">{{ $r->status }}</span>@else<span class="ndg-none">nothing yet</span>@endif</td>
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
