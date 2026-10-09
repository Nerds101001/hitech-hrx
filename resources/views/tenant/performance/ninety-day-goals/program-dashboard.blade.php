@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')

@section('title', '90-Day Sales Goals — Dashboard')

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

  $complianceChart = $compliance->map(fn ($c) => ['week' => $c['week'], 'pct' => $c['pct']])->values();
  $programChartData = $byProgram->map(fn ($p) => ['name' => $p['name'], 'target' => round($p['target'], 1), 'achieved' => round($p['achieved'], 1)])->values();
  $statusColorMap = collect($G::STATUS_COLORS)->map(fn ($clr) => $clr[0]);
  $statusColorMap['Nothing reported yet'] = '#cbd5e1';

  // The header's date range: the real span from the earliest program's start to the latest
  // program's end, in the current scope — not a fixed/fabricated window.
  $rangeStart = $programs->min('starts_on');
  $rangeEnd = $programs->max('ends_on');
@endphp
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 mx-4">
    <div>
      <nav class="small text-muted mb-1"><a href="{{ route('tenant.dashboard') }}" class="text-muted">Dashboard</a> <i class="bx bx-chevron-right"></i> 90-Day Goals <i class="bx bx-chevron-right"></i> Dashboard</nav>
      <h3 class="fw-extrabold mb-1 text-dark d-flex align-items-center gap-2">90-Day Sales Goals <span class="badge bg-label-success rounded-pill fw-bold" style="font-size:.62rem">{{ $isAdmin ? 'Admin View' : 'My Team' }}</span></h3>
      <p class="text-muted mb-0 small">Monitor all programs, participants, customer updates and results in real time. Drive accountability. Close more business.</p>
    </div>
    @if($canManage)
    <a href="{{ route('performance.ninety-day-goals.programs.create') }}" class="btn-hitech shadow-sm rounded-pill px-5 d-flex align-items-center gap-2">
      <i class="bx bx-calendar-plus fs-5"></i><span class="fw-bold">Create New Program</span>
    </a>
    @endif
  </div>

  <div class="px-4">

  {{-- Sub-tabs + real date range --}}
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div class="d-flex gap-2 flex-wrap ndg-subtabs" id="ndgPdTabs">
      @foreach(['overview' => ['Program Overview','bx-grid-alt'], 'people' => ['Salesperson Performance','bx-user-voice'], 'tracking' => ['Customer Tracking','bx-search-alt'], 'weekly' => ['Weekly Submissions','bx-calendar-check'], 'orders' => ['Orders & Results','bx-cart-download'], 'risk' => ['At Risk','bx-error-circle'], 'reports' => ['Reports','bx-download']] as $key => [$lbl, $ic])
        <button type="button" class="ndg-subtab {{ $key === 'overview' ? 'on' : '' }}" data-tab="{{ $key }}"><i class="bx {{ $ic }}"></i>{{ $lbl }}</button>
      @endforeach
    </div>
    @if($rangeStart && $rangeEnd)
      <div class="ndg-daterange"><i class="bx bx-calendar"></i> {{ $rangeStart->format('d M Y') }} – {{ $rangeEnd->format('d M Y') }}</div>
    @endif
  </div>

  {{-- Select Program: click to scope the whole page to one program, or back to all --}}
  <div class="mb-4">
    <div class="small fw-bold text-muted text-uppercase mb-2">Select Program</div>
    <div class="d-flex flex-wrap gap-2">
      <a href="{{ route('performance.ninety-day-goals.program-dashboard', array_merge(request()->except(['program_id', 'page']))) }}" class="ndg-prog-pill {{ !$filters['program_id'] ? 'on' : '' }}">
        <div class="fw-bold">All Programs</div><div class="small">{{ $programs->count() }}</div>
      </a>
      @foreach($programs->take(6) as $p)
        <a href="{{ route('performance.ninety-day-goals.program-dashboard', array_merge(request()->except(['program_id', 'page']), ['program_id' => $p->id])) }}" class="ndg-prog-pill {{ (int) $filters['program_id'] === $p->id ? 'on' : '' }}">
          <div class="fw-bold">{{ \Illuminate\Support\Str::limit($p->name, 18) }}</div>
          <div class="small">{{ $today->between($p->starts_on, $p->ends_on) ? 'Active' : ($today->lt($p->starts_on) ? 'Upcoming' : 'Ended') }}</div>
        </a>
      @endforeach
      @if($programs->count() > 6)
        <button type="button" class="ndg-prog-pill ndg-prog-more" data-bs-toggle="collapse" data-bs-target="#ndgPdMorePrograms">…</button>
      @endif
    </div>
    @if($programs->count() > 6)
      <div class="collapse mt-2" id="ndgPdMorePrograms">
        <div class="d-flex flex-wrap gap-2">
          @foreach($programs->slice(6) as $p)
            <a href="{{ route('performance.ninety-day-goals.program-dashboard', array_merge(request()->except(['program_id', 'page']), ['program_id' => $p->id])) }}" class="ndg-prog-pill {{ (int) $filters['program_id'] === $p->id ? 'on' : '' }}">
              <div class="fw-bold">{{ \Illuminate\Support\Str::limit($p->name, 18) }}</div>
              <div class="small">{{ $today->between($p->starts_on, $p->ends_on) ? 'Active' : ($today->lt($p->starts_on) ? 'Upcoming' : 'Ended') }}</div>
            </a>
          @endforeach
        </div>
      </div>
    @endif
  </div>

  {{-- KPI cards, with real trend deltas — never a fabricated percentage --}}
  <div class="row g-3 mb-6">
    @php
      $kpis = [
        ['bx-calendar-star', 'Active Programs', $activePrograms, $trends['activePrograms'] > 0 ? '↑' . $trends['activePrograms'] . ' new' : 'No change', 'tracking'],
        ['bx-group', 'Total Participants', $cards['people'], $trends['participants'] > 0 ? '↑' . $trends['participants'] . ' this month' : 'No change', 'people'],
        ['bx-building-house', 'Total Customers', $cards['goals'], $trends['customers'] > 0 ? '↑' . $trends['customers'] . ' new' : 'No change', 'tracking'],
        ['bx-flag', 'Total Target', $fmtL($cards['target']) . ' L', '—', 'tracking'],
        ['bx-trending-up', 'Total Achieved', $fmtL($cards['achieved']) . ' L', '—', 'orders'],
        ['bx-pie-chart-alt-2', 'Overall Achievement', $pctText($cards['pct']), '—', 'orders'],
        ['bx-receipt', 'Orders Received', $orderResults['qualifyingOrders'], $trends['ordersThisMonth'] > 0 ? '↑' . $trends['ordersThisMonth'] . ' this month' : 'No change', 'orders'],
      ];
    @endphp
    @foreach($kpis as [$icon, $label, $value, $trend, $goto])
      <div class="col-6 col-md-4 col-xl">
        <button type="button" class="ndg-kpi ndg-goto w-100 text-start" data-tab="{{ $goto }}">
          <div class="ndg-kpi-l"><i class="bx {{ $icon }}"></i>{{ $label }}</div>
          <div class="ndg-kpi-v">{{ $value }}</div>
          <div class="ndg-kpi-s {{ str_starts_with($trend, '↑') ? 'ndg-up' : '' }}">{{ $trend }}</div>
        </button>
      </div>
    @endforeach
  </div>

  {{-- Filters (apply to every tab) --}}
  <div class="hitech-card mb-6">
    <div class="card-body p-4">
      <form method="GET" action="{{ route('performance.ninety-day-goals.program-dashboard') }}" class="row g-3 align-items-end" id="ndgPdFilters">
        <input type="hidden" name="program_id" value="{{ $filters['program_id'] }}">
        <div class="col-md-3">
          <label class="form-label fw-bold small text-muted text-uppercase mb-2">Salesperson</label>
          <select name="user_id" class="form-select form-select-hitech">
            <option value="">All salespersons</option>
            @foreach($people as $p)<option value="{{ $p->id }}" {{ (int) $filters['user_id'] === $p->id ? 'selected' : '' }}>{{ $p->first_name }} {{ $p->last_name }}</option>@endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-bold small text-muted text-uppercase mb-2">Customer</label>
          <input type="text" name="customer" value="{{ $filters['customer'] }}" class="form-control" placeholder="Search customer…">
        </div>
        <div class="col-md-2">
          <label class="form-label fw-bold small text-muted text-uppercase mb-2">Status</label>
          <select name="status" class="form-select form-select-hitech">
            <option value="">All status</option>
            @foreach($statuses as $s)<option value="{{ $s }}" {{ $filters['status'] === $s ? 'selected' : '' }}>{{ $s }}</option>@endforeach
          </select>
        </div>
        <div class="col-md-1">
          <label class="form-label fw-bold small text-muted text-uppercase mb-2">Week</label>
          <select name="week" class="form-select form-select-hitech">
            <option value="">All</option>
            @for($w = 0; $w < $weeks; $w++)<option value="{{ $w }}" {{ $filters['week'] === $w ? 'selected' : '' }}>W{{ $w + 1 }}</option>@endfor
          </select>
        </div>
        <div class="col-md-1">
          <label class="form-label fw-bold small text-muted text-uppercase mb-2">Product</label>
          <select name="brand" class="form-select form-select-hitech">
            <option value="">All</option>
            @foreach($brands as $b)<option value="{{ $b }}" {{ $filters['brand'] === $b ? 'selected' : '' }}>{{ $b }}</option>@endforeach
          </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
          <button type="submit" class="btn-hitech rounded-pill px-4 flex-grow-1">Apply</button>
          <a href="{{ route('performance.ninety-day-goals.program-dashboard') }}" class="btn btn-hitech-cancel rounded-pill px-3">Clear</a>
        </div>
      </form>
    </div>
  </div>

  {{-- ═══════════════════ Program Overview ═══════════════════ --}}
  <div class="ndg-adm-section" data-section="overview">

    {{-- Row 1: Program Wise Performance | Customer Status | Weekly Submission Compliance.
         Program Wise Performance only means something as a cross-program comparison — scoped to
         one program it's just that program's own target vs. achieved re-shown as a lone bar,
         already on the KPI cards above, so it drops out once a program is selected. --}}
    @php $oneCol = $filters['program_id'] ? 'col-xl-6' : 'col-xl-4'; @endphp
    <div class="row g-4 mb-6">
      @if(!$filters['program_id'] && $programChartData->isNotEmpty())
      <div class="col-xl-4">
        <div class="hitech-card h-100">
          <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Program Wise Performance</div><div class="small text-muted">Target vs. actual, per program</div></div>
          <div class="card-body p-3"><div id="ndgPdProgramChart"></div></div>
        </div>
      </div>
      @endif
      <div class="{{ $oneCol }}">
        <div class="hitech-card h-100">
          <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Customer Status @if($selectedProgram)— {{ $selectedProgram->name }}@else(All Programs)@endif</div></div>
          <div class="card-body p-3"><div id="ndgPdStatusDonut"></div></div>
        </div>
      </div>
      <div class="{{ $oneCol }}">
        <div class="hitech-card h-100">
          <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Weekly Submission Compliance</div><div class="small text-muted">Submitted ÷ expected, per Monday</div></div>
          <div class="card-body p-3"><div id="ndgPdComplianceChart"></div></div>
        </div>
      </div>
    </div>

    @if(!$filters['program_id'])
    {{-- All Programs Overview | Program Progress — every program, with a real View action --}}
    <div class="row g-4 mb-6">
      <div class="col-xl-8">
        <div class="hitech-card h-100">
          <div class="card-body p-4 border-bottom d-flex align-items-center justify-content-between">
            <div class="fw-bold text-dark">All Programs Overview</div>
            <span class="small text-muted">{{ $programSummary->count() }} programs</span>
          </div>
          <div class="card-body p-0 table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead><tr>
                <th class="ps-3">#</th><th>Program Name</th><th>Code</th><th>Start Date</th><th>End Date</th><th>Duration</th>
                <th class="text-end">Participants</th><th class="text-end">Customers</th><th class="text-end">Target (₹ L)</th><th class="text-end">Achieved (₹ L)</th><th class="text-end">Ach. %</th><th>Status</th><th class="pe-3"></th>
              </tr></thead>
              <tbody>
              @forelse($programSummary as $i => $r)
                @php $pActive = $today->between($r->program->starts_on, $r->program->ends_on); @endphp
                <tr>
                  <td class="ps-3 text-muted">{{ $i + 1 }}</td>
                  <td class="fw-semibold text-dark">{{ $r->program->name }}</td>
                  <td class="small text-muted">PRG-{{ $r->program->id }}</td>
                  <td class="small text-muted">{{ $r->program->starts_on->format('d M Y') }}</td>
                  <td class="small text-muted">{{ $r->program->ends_on->format('d M Y') }}</td>
                  <td class="small text-muted">{{ $r->program->starts_on->diffInDays($r->program->ends_on) }} days</td>
                  <td class="text-end">{{ $r->participants }}</td>
                  <td class="text-end">{{ $r->customers }}</td>
                  <td class="text-end">{{ $fmtL($r->target) }} L</td>
                  <td class="text-end">{{ $fmtL($r->achieved) }} L</td>
                  <td class="text-end" style="color:{{ $pctColor($r->pct) }}">{{ $pctText($r->pct) }}</td>
                  <td><span class="badge rounded-pill" style="background:{{ $pActive ? '#d7f3ee' : '#e2e8f0' }};color:{{ $pActive ? '#0b5b48' : '#475569' }}">{{ $pActive ? 'Active' : ($today->lt($r->program->starts_on) ? 'Upcoming' : 'Ended') }}</span></td>
                  <td class="pe-3">@if($isAdmin)<a href="{{ route('performance.ninety-day-goals.program', $r->program->id) }}" class="btn btn-sm btn-hitech-cancel rounded-pill">View</a>@else<span class="text-muted">—</span>@endif</td>
                </tr>
              @empty
                <tr><td colspan="13" class="text-center text-muted p-4">No programs yet.</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-xl-4">
        <div class="hitech-card h-100">
          <div class="card-body p-4 border-bottom d-flex align-items-center justify-content-between">
            <div class="fw-bold text-dark">Program Progress</div>
          </div>
          <div class="card-body p-4" style="max-height:360px;overflow-y:auto">
            @forelse($programSummary as $r)
              <a href="{{ route('performance.ninety-day-goals.program', $r->program->id) }}" class="ndg-bar d-block text-decoration-none">
                <div class="ndg-bar-top"><span class="ndg-bar-name">{{ $r->program->name }}<em>{{ $r->customers }} {{ \Illuminate\Support\Str::plural('customer', $r->customers) }}</em></span><span class="ndg-bar-val" style="color:{{ $pctColor($r->pct) }}">{{ $pctText($r->pct) }}</span></div>
                <div class="ndg-track"><div class="ndg-fill" style="width:{{ $r->pct === null ? 0 : min(100, $r->pct) }}%;background:{{ $pctColor($r->pct) }}"></div></div>
              </a>
            @empty
              <div class="small text-muted">No programs yet.</div>
            @endforelse
          </div>
        </div>
      </div>
    </div>
    @else
    {{-- A specific program is selected: its own customer-level weekly tracking grid, not the
         all-programs list — $rows is already scoped to this one program_id by buildAdminOverviewData(). --}}
    <div class="row g-4 mb-6">
      <div class="col-12">
        <div class="hitech-card h-100">
          <div class="card-body p-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
              <div class="fw-bold text-dark">Customer Tracking &amp; Weekly Updates @if($selectedProgram)— {{ $selectedProgram->name }}@endif</div>
              <div class="small text-muted">{{ $allRowsCount }} {{ \Illuminate\Support\Str::plural('customer', $allRowsCount) }} in this program</div>
              <div class="mt-1">@include('tenant.performance.ninety-day-goals.partials.per-page', ['ppDefault' => 25, 'ppId' => 'Ov'])</div>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-3">
              <div class="d-flex flex-wrap gap-3 small text-muted">
                @foreach($G::STATUS_COLORS as $st => [$bg, $fg])
                  <span class="d-inline-flex align-items-center gap-1"><span style="width:9px;height:9px;border-radius:50%;display:inline-block;background:{{ $fg }}"></span>{{ $st }}</span>
                @endforeach
              </div>
              @if($selectedProgram)
                <a href="{{ route('performance.ninety-day-goals.program', $selectedProgram->id) }}" class="btn btn-sm btn-hitech-cancel rounded-pill px-3">Full Program Details →</a>
              @endif
            </div>
          </div>
          <div class="card-body p-0 ndg-mx-scroll">
            <table class="ndg-mx" style="font-size:.74rem">
              <thead><tr>
                <th class="ndg-mx-l" style="left:0">#</th><th class="ndg-mx-l" style="left:30px">Customer</th><th>Product</th><th>Salesperson</th><th>Target ₹L</th>
                @for($w = 0; $w < $weeks; $w++)<th>W{{ $w + 1 }}</th>@endfor
                <th>Actual ₹L</th><th>Ach.%</th><th>Status</th>
              </tr></thead>
              <tbody>
              @forelse($rows as $i => $r)
                @php $cks = $r->goal->checkins->keyBy('week_no'); @endphp
                <tr>
                  <td class="ndg-mx-l" style="left:0">{{ ($rows->currentPage() - 1) * $rows->perPage() + $i + 1 }}</td>
                  <td class="ndg-mx-l" style="left:30px;font-weight:600">{{ $r->goal->client_name }}</td>
                  <td>{{ $r->goal->product }}</td>
                  <td class="small text-muted">{{ $name($r->user) }}</td>
                  <td>{{ $fmtL($r->goal->monthly_potential) }}</td>
                  @for($w = 0; $w < $weeks; $w++)
                    @php
                      $ck = $cks->get($w);
                      $st = optional($ck)->status;
                      [$bg] = $st ? ($G::STATUS_COLORS[$st] ?? ['#94a3b8']) : [null];
                    @endphp
                    <td class="ndg-mx-c" style="{{ $bg ? 'background:' . $bg : '' }}" title="{{ $st ?: '—' }}"></td>
                  @endfor
                  <td class="fw-semibold">{{ $fmtL($r->achieved) }}</td>
                  <td style="color:{{ $pctColor($r->pct) }}">{{ $pctText($r->pct) }}</td>
                  <td>@if($r->status)<span class="ndg-chip" style="background:{{ $G::STATUS_COLORS[$r->status][0] ?? '#e2e8f0' }};color:{{ $G::STATUS_COLORS[$r->status][1] ?? '#475569' }}">{{ $r->status }}</span>@include('tenant.performance.ninety-day-goals.partials.order-check', ['r' => $r])@else<span class="ndg-none">·</span>@endif</td>
                </tr>
              @empty
                <tr><td colspan="{{ 8 + $weeks }}" class="text-center text-muted p-5">No customers have been added to this program yet.</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
          <div class="card-body pt-3">{{ $rows->links() }}</div>
        </div>
      </div>
    </div>
    @endif

    {{-- Row 2: Top Performing Sales Persons | At Risk --}}
    <div class="row g-4 mb-6">
      <div class="col-xl-6">
        <div class="hitech-card h-100">
          <div class="card-body p-4 border-bottom d-flex align-items-center justify-content-between">
            <div class="fw-bold text-dark">Top Performing Sales Persons</div>
            <button type="button" class="small text-decoration-none border-0 bg-transparent text-primary fw-semibold ndg-goto" data-tab="people">View All →</button>
          </div>
          <div class="card-body p-0 table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th class="ps-3">#</th><th>Salesperson</th><th class="text-end">Customers</th><th class="text-end">Achieved ₹L</th><th class="text-end pe-3">% Ach.</th></tr></thead>
              <tbody>
              @forelse($salespeople->take(5) as $i => $u)
                <tr>
                  <td class="ps-3 text-muted">{{ $i + 1 }}</td>
                  <td class="fw-semibold text-dark">{{ $u['name'] }}</td>
                  <td class="text-end">{{ $u['goals'] }}</td>
                  <td class="text-end">{{ $fmtL($u['achieved']) }}</td>
                  <td class="text-end pe-3" style="color:{{ $pctColor($u['pct']) }}">{{ $pctText($u['pct']) }}</td>
                </tr>
              @empty
                <tr><td colspan="5" class="text-center text-muted p-4">No salespeople in scope yet.</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-xl-6">
        <div class="hitech-card h-100">
          <div class="card-body p-4 border-bottom d-flex align-items-center justify-content-between">
            <div class="fw-bold text-dark">At Risk Customers</div>
            <button type="button" class="small text-decoration-none border-0 bg-transparent text-primary fw-semibold ndg-goto" data-tab="risk">View All →</button>
          </div>
          <div class="card-body p-0 table-responsive" style="max-height:320px;overflow-y:auto">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th class="ps-3">Customer</th><th>Salesperson</th><th class="pe-3">Status</th></tr></thead>
              <tbody>
              @forelse($atRisk->take(5) as $r)
                @php [$bg, $fg] = $G::STATUS_COLORS[$r->status] ?? ['#e2e8f0', '#475569']; @endphp
                <tr>
                  <td class="ps-3 fw-semibold">{{ $r->goal->client_name }}</td>
                  <td class="small text-muted">{{ $name($r->user) }}</td>
                  <td class="pe-3">@if($r->status)<span class="ndg-chip" style="background:{{ $bg }};color:{{ $fg }}">{{ $r->status }}</span>@include('tenant.performance.ninety-day-goals.partials.order-check', ['r' => $r])@else<span class="ndg-chip" style="background:#e2e8f0;color:#475569">No report</span>@endif</td>
                </tr>
              @empty
                <tr><td colspan="3" class="text-center text-muted p-4">Nothing needs attention right now.</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    {{-- Row 3: Recent Orders | Tasks & Alerts | Quick Actions --}}
    <div class="row g-4 mb-6">
      <div class="col-xl-4">
        <div class="hitech-card h-100">
          <div class="card-body p-4 border-bottom d-flex align-items-center justify-content-between">
            <div class="fw-bold text-dark">Recent Orders (From Sales Report)</div>
            <button type="button" class="small text-decoration-none border-0 bg-transparent text-primary fw-semibold ndg-goto" data-tab="orders">View All →</button>
          </div>
          <div class="card-body p-0 table-responsive" style="max-height:320px;overflow-y:auto">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th class="ps-3">Month</th><th>Customer</th><th class="text-end pe-3">Amount ₹L</th></tr></thead>
              <tbody>
              @forelse($recentOrders as $o)
                <tr>
                  <td class="ps-3 small text-muted">{{ $o->month }}</td>
                  <td>
                    <div class="fw-semibold text-dark">{{ $o->row->goal->client_name }}</div>
                    <div class="small text-muted">{{ $o->row->goal->product }} · {{ $name($o->row->user) }}</div>
                  </td>
                  <td class="text-end pe-3 fw-semibold">{{ $fmtL($o->amount) }}</td>
                </tr>
              @empty
                <tr><td colspan="3" class="text-center text-muted p-4">No orders in the Sales Report for this scope yet.</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-xl-4">
        <div class="hitech-card h-100">
          <div class="card-body p-4 border-bottom d-flex align-items-center justify-content-between">
            <div class="fw-bold text-dark">Tasks &amp; Alerts</div>
            <button type="button" class="small text-decoration-none border-0 bg-transparent text-primary fw-semibold ndg-goto" data-tab="risk">View All →</button>
          </div>
          <div class="card-body p-0" style="max-height:320px;overflow-y:auto">
            @forelse($tasksAlerts as $a)
              <div class="ndg-alert-row">
                <i class="bx {{ $a->icon }}" style="{{ $a->urgent ? 'color:#b42318' : '' }}"></i>
                <div class="flex-grow-1">
                  <div class="small text-dark fw-semibold">{{ $a->title }}</div>
                  <div class="small text-muted">{{ $a->sub }}</div>
                </div>
                @if($a->when)<span class="small {{ $a->urgent ? 'text-danger fw-bold' : 'text-muted' }}">{{ $a->when }}</span>@endif
              </div>
            @empty
              <div class="p-4 text-center text-muted small">Nothing needs attention right now.</div>
            @endforelse
          </div>
        </div>
      </div>
      <div class="col-xl-4">
        <div class="hitech-card h-100">
          <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Quick Actions</div></div>
          <div class="card-body p-3 d-flex flex-column gap-2">
    @if($canManage)
            <a href="{{ route('performance.ninety-day-goals.programs.create') }}" class="btn btn-hitech text-start d-flex align-items-center gap-2"><i class="bx bx-plus-circle"></i> Create 90-Day Program</a>
    @endif
    @if($isAdmin)
            <a href="{{ route('performance.ninety-day-goals.programs.index') }}" class="btn btn-hitech-cancel text-start d-flex align-items-center gap-2"><i class="bx bx-list-ul"></i> View All Programs</a>
    @endif
    @if($isAdmin)
            <a href="{{ route('performance.ninety-day-goals.admin-view.export', array_merge(['round' => 'all'], request()->query())) }}" class="btn btn-hitech-cancel text-start d-flex align-items-center gap-2"><i class="bx bx-download"></i> Export Report</a>
    @endif
            <a href="{{ route('sales-visits.pipeline.index') }}" class="btn btn-hitech-cancel text-start d-flex align-items-center gap-2"><i class="bx bx-bar-chart-alt-2"></i> View Sales Report</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ═══════════════════ Salesperson Performance ═══════════════════ --}}
  <div class="ndg-adm-section d-none" data-section="people">
    <div class="hitech-card mb-6">
      <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Salesperson performance</div><div class="small text-muted">Ranked by share of target sold</div></div>
      <div class="card-body p-0 table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr>
            <th class="ps-4">#</th><th>Salesperson</th><th>Customers added</th><th class="text-end">Customers</th><th class="text-end">Target ₹L</th><th class="text-end">Achieved ₹L</th>
            <th style="min-width:160px">Achievement</th><th class="text-end">Orders</th><th class="text-end">This week</th><th class="text-end pe-4">Pending</th>
          </tr></thead>
          <tbody>
          @foreach($salespeople as $i => $u)
            <tr>
              <td class="ps-4 text-muted">{{ $i + 1 }}</td>
              <td><a href="{{ route('performance.ninety-day-goals.person', $u['id']) }}" class="fw-semibold text-dark">{{ $u['name'] }}</a><div class="small text-muted">{{ $u['department'] }}</div></td>
              {{-- Who has actually nominated their target customers. Everyone starts on zero,
                   and the number alone did not say whether that meant "not done yet" or
                   "nothing to show" - which is the first thing to chase after enrolment. --}}
              <td>
                @if(($u['goals'] ?? 0) > 0)
                  <span class="badge bg-label-success"><i class="bx bx-check me-1"></i>Added</span>
                @else
                  <span class="badge bg-label-warning"><i class="bx bx-time-five me-1"></i>Not added</span>
                @endif
              </td>
              <td class="text-end">{{ $u['goals'] }}</td>
              <td class="text-end">{{ $fmtL($u['target']) }}</td>
              <td class="text-end">{{ $fmtL($u['achieved']) }}</td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div class="ndg-track flex-grow-1" style="height:8px"><div class="ndg-fill" style="width:{{ min(100, (float) $u['pct']) }}%;background:{{ $pctColor($u['pct']) }}"></div></div>
                  <b class="small" style="min-width:44px;text-align:right">{{ $pctText($u['pct']) }}</b>
                </div>
              </td>
              <td class="text-end">{{ $u['converted'] }}</td>
              <td class="text-end">{{ $u['weekSubmitted'] }}/{{ $u['weekTotal'] }}</td>
              <td class="text-end pe-4"><span class="{{ $u['weekPending'] ? 'text-danger fw-bold' : '' }}">{{ $u['weekPending'] ?: '·' }}</span></td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- ═══════════════════ Customer Tracking ═══════════════════ --}}
  <div class="ndg-adm-section d-none" data-section="tracking">
    <div class="hitech-card mb-6">
      <div class="card-body p-4 border-bottom d-flex align-items-center justify-content-between">
        <div>
          <div class="fw-bold text-dark">Customer submissions (all programs)</div>
          <div class="small text-muted">Every weekly status and comment, one row per target customer</div>
        </div>
        <div class="d-flex align-items-center gap-3">
          @include('tenant.performance.ninety-day-goals.partials.per-page', ['ppDefault' => 25, 'ppId' => 'Tr'])
          <span class="small text-muted">Showing {{ $rows->count() }} of {{ $allRowsCount }}</span>
        </div>
      </div>
      <div class="card-body p-0 ndg-mx-scroll">
        <table class="ndg-mx ndg-mx-wide" style="font-size:.74rem">
          <thead>
            <tr>
              <th class="ndg-mx-l" style="left:0" rowspan="2">Program</th><th class="ndg-mx-l" style="left:110px" rowspan="2">Salesperson</th><th class="ndg-mx-l" style="left:220px" rowspan="2">Customer</th>
              <th rowspan="2">Product</th><th rowspan="2">Goal ₹L</th>
              @for($w = 0; $w < $weeks; $w++)<th colspan="2" class="text-center">W{{ $w + 1 }}</th>@endfor
              <th rowspan="2">Actual ₹L</th><th rowspan="2">Achv %</th><th rowspan="2">Status</th>
            </tr>
            <tr>
              @for($w = 0; $w < $weeks; $w++)<th>Status</th><th>Comment</th>@endfor
            </tr>
          </thead>
          <tbody>
          @foreach($rows as $r)
            <tr>
              <td class="ndg-mx-l" style="left:0;font-weight:400">{{ optional($r->program)->name ?? '—' }}</td>
              <td class="ndg-mx-l" style="left:110px;font-weight:400">{{ $name($r->user) }}</td>
              <td class="ndg-mx-l" style="left:220px">{{ $r->goal->client_name }}</td>
              <td>{{ $r->goal->product }}</td>
              <td>{{ $fmtL($r->potential) }}</td>
              @for($w = 0; $w < $weeks; $w++)
                @php
                  $ck = $r->goal->checkins->firstWhere('week_no', $w);
                  $st = optional($ck)->status;
                  [$bg, $fg] = $st ? ($G::STATUS_COLORS[$st] ?? ['#94a3b8', '#fff']) : [null, null];
                @endphp
                <td class="text-center">@if($st)<span class="ndg-chip" style="background:{{ $bg }};color:{{ $fg }}">{{ $st }}</span>@else<span class="ndg-none">-</span>@endif</td>
                <td class="ndg-mx-comment">
                  @if($ck && $ck->comment)<div>{{ $ck->comment }}</div>@endif
                  @if($ck)@foreach($ck->notes as $n)<div class="ndg-mx-note"><b>{{ trim(optional($n->author)->first_name . ' ' . optional($n->author)->last_name) }}:</b> {{ $n->body }}</div>@endforeach @endif
                  @if(!$ck || (!$ck->comment && $ck->notes->isEmpty()))<span class="ndg-none">-</span>@endif
                </td>
              @endfor
              <td class="fw-semibold">{{ $fmtL($r->achieved) }}</td>
              <td style="color:{{ $pctColor($r->pct) }}">{{ $pctText($r->pct) }}</td>
              <td>@if($r->status)<span class="ndg-chip" style="background:{{ $G::STATUS_COLORS[$r->status][0] ?? '#e2e8f0' }};color:{{ $G::STATUS_COLORS[$r->status][1] ?? '#475569' }}">{{ $r->status }}</span>@include('tenant.performance.ninety-day-goals.partials.order-check', ['r' => $r])@else<span class="ndg-none">·</span>@endif</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
      <div class="card-body pt-3">{{ $rows->links() }}</div>
    </div>
  </div>

  {{-- ═══════════════════ Weekly Submissions ═══════════════════ --}}
  <div class="ndg-adm-section d-none" data-section="weekly">
    <div class="hitech-card mb-6">
      <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Compliance by week</div></div>
      <div class="card-body p-3"><div id="ndgPdComplianceChart2"></div></div>
      <div class="card-body p-0 table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th class="ps-4">Week</th><th class="text-end">Expected</th><th class="text-end">Submitted</th><th class="text-end">Pending</th><th class="text-end pe-4">Compliance</th></tr></thead>
          <tbody>
          @foreach($compliance as $c)
            <tr>
              <td class="ps-4 fw-semibold">{{ $c['week'] }}</td>
              <td class="text-end">{{ $c['submitted'] + $c['pending'] }}</td>
              <td class="text-end">{{ $c['submitted'] }}</td>
              <td class="text-end"><span class="{{ $c['pending'] ? 'text-danger fw-bold' : '' }}">{{ $c['pending'] ?: '·' }}</span></td>
              <td class="text-end pe-4">{{ $c['pct'] }}%</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- ═══════════════════ Orders & Results ═══════════════════ --}}
  <div class="ndg-adm-section d-none" data-section="orders">
    <div class="row g-4 mb-6">
      <div class="col-xl-4">
        <div class="hitech-card h-100">
          <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Order results from Sales Report</div><div class="small text-muted">Never typed by a salesperson</div></div>
          <div class="card-body p-4">
            @php $orRows = [['Qualifying orders', $orderResults['qualifyingOrders']], ['Total order value', $fmtL($orderResults['totalOrderValue']) . ' L'], ['Average order value', $orderResults['avgOrderValue'] === null ? '–' : $fmtL($orderResults['avgOrderValue']) . ' L'], ['Customers converted', $orderResults['customersConverted']], ['Repeat orders', $orderResults['repeatOrders']]]; @endphp
            <div class="ndg-orlist">
              @foreach($orRows as [$lbl, $val])<div class="ndg-orlist-row"><span>{{ $lbl }}</span><b>{{ $val }}</b></div>@endforeach
            </div>
          </div>
        </div>
      </div>
      <div class="col-xl-8">
        <div class="hitech-card h-100">
          <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Qualifying orders</div><div class="small text-muted">Customers with a real Sales Report sale inside the round</div></div>
          <div class="card-body p-0 table-responsive" style="max-height:420px;overflow-y:auto">
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
                <tr><td colspan="5" class="text-center text-muted p-4">No qualifying orders on this page — clear filters or turn the page.</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    {{-- Order status split and the Trial Order watchlist (both from the retired old Dashboard). --}}
    <div class="row g-4 mb-6">
      <div class="col-xl-4">
        <div class="hitech-card h-100">
          <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Order Status</div><div class="small text-muted">From real sales vs target; Cancelled = marked Dropped</div></div>
          <div class="card-body p-4">
            @php $osTotal = max(1, array_sum($orderStatus)); $osColors = ['Full Order' => '#0b5b48', 'Partial Order' => '#f5a623', 'Cancelled' => '#b42318', 'Pending' => '#94a3b8']; @endphp
            @foreach($orderStatus as $lbl => $n)
              <div class="mb-3">
                <div class="d-flex justify-content-between small mb-1"><span class="fw-semibold text-dark">{{ $lbl }}</span><span class="text-muted">{{ $n }}</span></div>
                <div class="progress" style="height:6px"><div class="progress-bar" style="width:{{ round($n / $osTotal * 100) }}%;background:{{ $osColors[$lbl] ?? '#94a3b8' }}"></div></div>
              </div>
            @endforeach
          </div>
        </div>
      </div>
      <div class="col-xl-8">
        <div class="hitech-card h-100">
          <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">At Trial Order right now</div><div class="small text-muted">{{ $trialing->count() }} customer{{ $trialing->count() === 1 ? '' : 's' }} trialing — the ones to convert to regular orders</div></div>
          <div class="card-body p-0 table-responsive" style="max-height:360px;overflow-y:auto">
            <table class="table align-middle mb-0">
              <thead><tr><th class="ps-4">Customer</th><th>Salesperson</th><th>Brand</th><th class="text-end">Monthly ₹L</th><th class="text-end pe-4">Sold ₹L</th></tr></thead>
              <tbody>
              @forelse($trialing as $r)
                <tr>
                  <td class="ps-4 fw-semibold">{{ $r->goal->client_name }}</td>
                  <td class="small text-muted">{{ $name($r->user) }}</td>
                  <td class="small text-muted">{{ $r->brand }}</td>
                  <td class="text-end">{{ $fmtL($r->potential) }}</td>
                  <td class="text-end pe-4 fw-semibold">{{ $fmtL($r->achieved) }}</td>
                </tr>
              @empty
                <tr><td colspan="5" class="text-center text-muted p-4">Nobody is at Trial Order right now.</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ═══════════════════ At Risk ═══════════════════ --}}
  <div class="ndg-adm-section d-none" data-section="risk">
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
                @if($r->overdueWeeks > 0)
                  <span class="ndg-overdue">{{ $r->overdueWeeks }} {{ \Illuminate\Support\Str::plural('week', $r->overdueWeeks) }}@if($r->oldestOverdue) · since {{ $r->oldestOverdue->format('d M') }}@endif</span>
                @else
                  <span class="ndg-none">up to date</span>
                @endif
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

  {{-- ═══════════════════ Reports ═══════════════════ --}}
  <div class="ndg-adm-section d-none" data-section="reports">
    <div class="hitech-card mb-6">
      <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Export</div><div class="small text-muted">Downloads the complete filtered set above, not just the page on screen.</div></div>
      <div class="card-body p-4">
    @if($isAdmin)
        <a href="{{ route('performance.ninety-day-goals.admin-view.export', array_merge(['round' => 'all'], request()->query())) }}" class="btn-hitech rounded-pill px-5 d-inline-flex align-items-center gap-2">
          <i class="bx bx-download fs-5"></i><span class="fw-bold">Export to Excel (CSV)</span>
        </a>
    @endif
        <div class="small text-muted mt-3">{{ $allRowsCount }} {{ \Illuminate\Support\Str::plural('row', $allRowsCount) }} match the current filters.</div>
      </div>
    </div>
  </div>

  </div>
</div>

<style>
  .ndg-subtab { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 10px; border: 1px solid #d5e3e1; background: #fff; color: #334155; font-weight: 700; font-size: .8rem; cursor: pointer; }
  .ndg-subtab:hover { border-color: #007a7a; color: #007a7a; }
  .ndg-subtab.on { background: #007a7a; border-color: #007a7a; color: #fff; }
  .ndg-daterange { border: 1px solid #d5e3e1; border-radius: 10px; padding: 8px 16px; font-size: .8rem; font-weight: 600; color: #334155; background: #fff; white-space: nowrap; }
  .ndg-prog-pill { display: inline-block; border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px 14px; text-decoration: none; color: #334155; background: #fff; cursor: pointer; min-width: 110px; }
  .ndg-prog-pill:hover { border-color: #007a7a; }
  .ndg-prog-pill .fw-bold { font-size: .82rem; color: #0f172a; }
  .ndg-prog-pill .small { color: #94a3b8; }
  .ndg-prog-pill.on { background: #007a7a; border-color: #007a7a; }
  .ndg-prog-pill.on .fw-bold, .ndg-prog-pill.on .small { color: #fff; }
  .ndg-prog-more { border: 0; background: #f1f5f9; }
  .ndg-daterange i { color: #007a7a; margin-right: 4px; }
  .ndg-kpi { border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; background: #fff; height: 100%; cursor: pointer; transition: border-color .15s; }
  .ndg-kpi:hover { border-color: #007a7a; }
  .ndg-kpi-l { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #64748b; display: flex; align-items: center; gap: 5px; }
  .ndg-kpi-l i { color: #007a7a; font-size: 1rem; }
  .ndg-kpi-v { font-size: 1.5rem; font-weight: 800; color: #0f172a; line-height: 1.25; margin-top: 4px; font-variant-numeric: tabular-nums; }
  .ndg-kpi-s { font-size: .7rem; color: #94a3b8; font-weight: 600; }
  .ndg-kpi-s.ndg-up { color: #0b5b48; }
  .ndg-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-right: 4px; }
  .ndg-track { height: 7px; background: #eef2f5; border-radius: 99px; overflow: hidden; }
  .ndg-fill { height: 100%; background: #007a7a; border-radius: 99px; min-width: 2px; }
  .ndg-chip { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: .72rem; font-weight: 700; }
  .ndg-none { color: #cbd5e1; }
  .ndg-overdue { color: #b42318; font-weight: 700; font-size: .82rem; }
  .ndg-orlist-row { display: flex; align-items: center; justify-content: space-between; padding: 9px 0; border-bottom: 1px dashed #eef2f5; font-size: .86rem; }
  .ndg-orlist-row:last-child { border-bottom: none; }
  .ndg-orlist-row span { color: #64748b; }
  .ndg-orlist-row b { color: #0f172a; font-variant-numeric: tabular-nums; }
  .ndg-alert-row { display: flex; align-items: start; gap: 10px; padding: 10px 16px; border-bottom: 1px solid #f1f5f9; }
  .ndg-alert-row:last-child { border-bottom: none; }
  .ndg-alert-row i { font-size: 1.1rem; color: #007a7a; flex: none; margin-top: 2px; }
  .ndg-mx-scroll { overflow: auto; max-height: 65vh; }
  .ndg-mx { border-collapse: separate; border-spacing: 3px; padding: 0 16px 8px; width: 100%; }
  .ndg-mx thead th { position: sticky; top: 0; background: #fff; font-weight: 700; color: #64748b; font-size: .66rem; text-align: center; padding: 8px 4px 6px; z-index: 2; white-space: nowrap; }
  .ndg-mx-l { text-align: left !important; white-space: nowrap; padding: 4px 10px !important; font-weight: 600; color: #0f172a; position: sticky; background: #fff; z-index: 1; }
  .ndg-mx-c { width: 22px; height: 22px; min-width: 22px; border-radius: 4px; box-shadow: inset 0 0 0 1px #e2e8f0; background: #f1f5f9; }
  .ndg-mx-wide .ndg-mx-comment { min-width: 130px; max-width: 180px; text-align: left !important; white-space: normal; font-size: .72rem; color: #334155; vertical-align: top; padding: 6px 8px !important; }
  .ndg-mx-note { margin-top: 4px; padding-top: 4px; border-top: 1px dashed #e2e8f0; color: #64748b; }
</style>
@endsection

@section('page-script')
<script>
(function () {
  var tabs = document.querySelectorAll('#ndgPdTabs .ndg-subtab');
  var sections = document.querySelectorAll('.ndg-adm-section');
  function show(key) {
    tabs.forEach(function (t) { t.classList.toggle('on', t.dataset.tab === key); });
    sections.forEach(function (s) { s.classList.toggle('d-none', s.dataset.section !== key); });
    try { history.replaceState(null, '', '#' + key); } catch (e) {}
  }
  tabs.forEach(function (t) { t.addEventListener('click', function () { show(t.dataset.tab); }); });
  document.querySelectorAll('.ndg-goto').forEach(function (b) { b.addEventListener('click', function () { show(b.dataset.tab); window.scrollTo({ top: 0, behavior: 'smooth' }); }); });
  var initial = (location.hash || '').replace('#', '');
  if (initial && document.querySelector('.ndg-adm-section[data-section="' + initial + '"]')) { show(initial); }
})();

// ApexCharts loads as an ES module (Vite's build always emits type="module" scripts), which the spec defers
// until after the document has parsed — after the IIFE above would otherwise already have run
// and found `ApexCharts` still undefined. DOMContentLoaded fires only once every deferred/
// module script has finished, so waiting for it is what actually guarantees ApexCharts exists.
document.addEventListener('DOMContentLoaded', function () {
  if (typeof ApexCharts === 'undefined') { return; }
  var teal = '#007a7a', muted = '#64748b';

  var programEl = document.querySelector('#ndgPdProgramChart');
  if (programEl) {
    var programData = @json($programChartData);
    new ApexCharts(programEl, {
      chart: { type: 'bar', height: 280, toolbar: { show: false }, fontFamily: 'inherit' },
      series: [{ name: 'Target (₹L)', data: programData.map(function (p) { return p.target; }) }, { name: 'Achieved (₹L)', data: programData.map(function (p) { return p.achieved; }) }],
      xaxis: { categories: programData.map(function (p) { return p.name; }), labels: { style: { colors: muted, fontSize: '11px' } } },
      yaxis: { labels: { style: { colors: muted } } },
      colors: ['#94c9c9', teal],
      plotOptions: { bar: { columnWidth: '55%', borderRadius: 4 } },
      legend: { position: 'top', horizontalAlign: 'right', fontSize: '12px' },
      dataLabels: { enabled: false },
      grid: { borderColor: '#eef2f5' },
    }).render();
  }

  var donutEl = document.querySelector('#ndgPdStatusDonut');
  if (donutEl) {
    var statuses = @json($statusCounts);
    var labels = Object.keys(statuses).filter(function (k) { return statuses[k] > 0; });
    var colorMap = @json($statusColorMap);
    new ApexCharts(donutEl, {
      chart: { type: 'donut', height: 280, fontFamily: 'inherit' },
      series: labels.map(function (l) { return statuses[l]; }),
      labels: labels,
      colors: labels.map(function (l) { return colorMap[l] || muted; }),
      legend: { position: 'bottom', fontSize: '11px' },
      dataLabels: { enabled: false },
      plotOptions: { pie: { donut: { size: '68%', labels: { show: true, total: { show: true, label: 'Customers', color: '#0f172a' } } } } },
    }).render();
  }

  var complianceData = @json($complianceChart);
  ['#ndgPdComplianceChart', '#ndgPdComplianceChart2'].forEach(function (sel) {
    var el = document.querySelector(sel);
    if (!el) { return; }
    new ApexCharts(el, {
      chart: { type: 'bar', height: 280, toolbar: { show: false }, fontFamily: 'inherit' },
      series: [{ name: 'Compliance %', data: complianceData.map(function (c) { return c.pct; }) }],
      xaxis: { categories: complianceData.map(function (c) { return c.week; }), labels: { style: { colors: muted, fontSize: '10px' } } },
      yaxis: { max: 100, labels: { style: { colors: muted }, formatter: function (v) { return v + '%'; } } },
      colors: [teal],
      plotOptions: { bar: { columnWidth: '55%', borderRadius: 4 } },
      dataLabels: { enabled: false },
      grid: { borderColor: '#eef2f5' },
    }).render();
  });
});
</script>
@endsection
