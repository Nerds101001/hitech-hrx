@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')

@section('title', '90-Day Goals — Admin View')

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
@endphp
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 mx-4">
    <div>
      <nav class="small text-muted mb-1"><a href="{{ route('tenant.dashboard') }}" class="text-muted">Dashboard</a> <i class="bx bx-chevron-right"></i> <a href="{{ route('performance.ninety-day-goals.index', ['view' => 'overview']) }}" class="text-muted">90-Day Sales Goals</a> <i class="bx bx-chevron-right"></i> Admin View</nav>
      <h3 class="fw-extrabold mb-1 text-dark d-flex align-items-center gap-2">90-Day Sales Goals <span class="badge bg-label-primary rounded-pill fw-bold" style="font-size:.62rem">Admin View</span></h3>
      <p class="text-muted mb-0 small">Monitor all sales programs, participant progress, customer updates and results in one place.</p>
    </div>
    <a href="{{ route('performance.ninety-day-goals.programs.create') }}" class="btn-hitech shadow-sm rounded-pill px-5 d-flex align-items-center gap-2">
      <i class="bx bx-calendar-plus fs-5"></i><span class="fw-bold">Create New Program</span>
    </a>
  </div>

  @include('tenant.performance.ninety-day-goals._tabs')

  <div class="px-4">

  {{-- KPI cards --}}
  <div class="row g-3 mb-6">
    @php
      $kpis = [
        ['bx-calendar-star', 'Active programs', $activePrograms, $programs->count() . ' total'],
        ['bx-group', 'Participants', $cards['people'], 'in scope'],
        ['bx-building-house', 'Customers tracked', $cards['goals'], 'across all programs'],
        ['bx-flag', 'Total target', $fmtL($cards['target']), '₹ lakh'],
        ['bx-trending-up', 'Total achieved', $fmtL($cards['achieved']), 'from Sales Report'],
        ['bx-pie-chart-alt-2', 'Overall achievement', $pctText($cards['pct']), 'achieved ÷ target'],
        ['bx-receipt', 'Orders received', $orderResults['qualifyingOrders'], 'qualifying, this scope'],
      ];
    @endphp
    @foreach($kpis as [$icon, $label, $value, $sub])
      <div class="col-6 col-md-4 col-xl">
        <div class="ndg-kpi">
          <div class="ndg-kpi-l"><i class="bx {{ $icon }}"></i>{{ $label }}</div>
          <div class="ndg-kpi-v">{{ $value }}</div>
          <div class="ndg-kpi-s">{{ $sub }}</div>
        </div>
      </div>
    @endforeach
  </div>

  {{-- Sub-tabs: sections of this one page, switched client-side --}}
  <div class="d-flex gap-2 flex-wrap mb-4 ndg-subtabs" id="ndgAdminTabs">
    @foreach(['overview' => ['Program Overview','bx-grid-alt'], 'people' => ['Salesperson Performance','bx-user-voice'], 'tracking' => ['Customer Tracking','bx-search-alt'], 'weekly' => ['Weekly Submissions','bx-calendar-check'], 'orders' => ['Orders & Results','bx-cart-download'], 'risk' => ['At Risk','bx-error-circle'], 'reports' => ['Reports','bx-download']] as $key => [$lbl, $ic])
      <button type="button" class="ndg-subtab {{ $key === 'overview' ? 'on' : '' }}" data-tab="{{ $key }}"><i class="bx {{ $ic }}"></i>{{ $lbl }}</button>
    @endforeach
  </div>

  {{-- Filters (apply to every tab) --}}
  <div class="hitech-card mb-6">
    <div class="card-body p-4">
      <form method="GET" action="{{ route('performance.ninety-day-goals.admin-view') }}" class="row g-3 align-items-end" id="ndgAdminFilters">
        <div class="col-md-2">
          <label class="form-label fw-bold small text-muted text-uppercase mb-2">Program</label>
          <select name="program_id" class="form-select form-select-hitech">
            <option value="">All programs</option>
            @foreach($programs as $p)<option value="{{ $p->id }}" {{ (int) $filters['program_id'] === $p->id ? 'selected' : '' }}>{{ $p->name }}</option>@endforeach
          </select>
        </div>
        <div class="col-md-2">
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
            @for($w = 0; $w < $weeks; $w++)<option value="{{ $w }}" {{ $filters['week'] === $w ? 'selected' : '' }}>W{{ $w }}</option>@endfor
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
          <a href="{{ route('performance.ninety-day-goals.admin-view') }}" class="btn btn-hitech-cancel rounded-pill px-3">Clear</a>
        </div>
      </form>
    </div>
  </div>

  {{-- ═══════════════════ Program Overview ═══════════════════ --}}
  <div class="ndg-adm-section" data-section="overview">
    <div class="row g-4 mb-6">
      @if($programChartData->isNotEmpty())
      <div class="col-xl-4">
        <div class="hitech-card h-100">
          <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Program-wise performance</div><div class="small text-muted">Target vs. actual, per program</div></div>
          <div class="card-body p-3"><div id="ndgAdmProgramChart"></div></div>
        </div>
      </div>
      @endif
      <div class="col-xl-4">
        <div class="hitech-card h-100">
          <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Customer status (all programs)</div></div>
          <div class="card-body p-3"><div id="ndgAdmStatusDonut"></div></div>
        </div>
      </div>
      <div class="col-xl-4">
        <div class="hitech-card h-100">
          <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Weekly submission compliance</div><div class="small text-muted">Submitted ÷ expected, per Monday</div></div>
          <div class="card-body p-3"><div id="ndgAdmComplianceChart"></div></div>
        </div>
      </div>
    </div>

    <div class="row g-4 mb-6">
      <div class="col-xl-4">
        <div class="hitech-card h-100">
          <div class="card-body p-4 border-bottom d-flex align-items-center justify-content-between">
            <div class="fw-bold text-dark">Recent activity</div>
            <span class="small text-muted">{{ $activity->count() }}</span>
          </div>
          <div class="card-body p-0">
            @forelse($activity as $a)
              <div class="ndg-alert-row">
                <i class="bx bx-radio-circle-marked"></i>
                <div class="flex-grow-1">
                  <div class="small text-dark"><b>{{ $a->who ?: 'Someone' }}</b> {{ $a->what }}</div>
                  <div class="small text-muted">{{ $a->at ? \Illuminate\Support\Carbon::parse($a->at)->diffForHumans() : '' }}</div>
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
            <div class="fw-bold text-dark">Pending this week</div>
            <span class="small text-muted">{{ $salespeople->filter(fn ($u) => $u['weekPending'] > 0)->count() }}</span>
          </div>
          <div class="card-body p-0 table-responsive" style="max-height:340px;overflow-y:auto">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th class="ps-3">Salesperson</th><th class="text-end">Total</th><th class="text-end">Submitted</th><th class="text-end pe-3">Pending</th></tr></thead>
              <tbody>
              @forelse($salespeople->filter(fn ($u) => $u['weekTotal'] > 0)->sortByDesc('weekPending') as $u)
                <tr>
                  <td class="ps-3">{{ $u['name'] }}</td>
                  <td class="text-end">{{ $u['weekTotal'] }}</td>
                  <td class="text-end">{{ $u['weekSubmitted'] }}</td>
                  <td class="text-end pe-3"><span class="{{ $u['weekPending'] ? 'text-danger fw-bold' : '' }}">{{ $u['weekPending'] ?: '·' }}</span></td>
                </tr>
              @empty
                <tr><td colspan="4" class="text-center text-muted p-4">Nobody has an active round right now.</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-xl-4">
        <div class="hitech-card h-100">
          <div class="card-body p-4 border-bottom d-flex align-items-center justify-content-between">
            <div class="fw-bold text-dark">At-risk customers</div>
            <span class="small text-muted">{{ $atRisk->count() }}</span>
          </div>
          <div class="card-body p-0 table-responsive" style="max-height:340px;overflow-y:auto">
            <table class="table table-sm align-middle mb-0">
              <thead><tr><th class="ps-3">Customer</th><th>Salesperson</th><th class="pe-3">Status</th></tr></thead>
              <tbody>
              @forelse($atRisk->take(10) as $r)
                @php [$bg, $fg] = $G::STATUS_COLORS[$r->status] ?? ['#e2e8f0', '#475569']; @endphp
                <tr>
                  <td class="ps-3 fw-semibold">{{ $r->goal->client_name }}</td>
                  <td class="small text-muted">{{ $name($r->user) }}</td>
                  <td class="pe-3">@if($r->status)<span class="ndg-chip" style="background:{{ $bg }};color:{{ $fg }}">{{ $r->status }}</span>@else<span class="ndg-chip" style="background:#e2e8f0;color:#475569">No report</span>@endif</td>
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
  </div>

  {{-- ═══════════════════ Salesperson Performance ═══════════════════ --}}
  <div class="ndg-adm-section d-none" data-section="people">
    <div class="hitech-card mb-6">
      <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Salesperson performance</div><div class="small text-muted">Ranked by share of target sold</div></div>
      <div class="card-body p-0 table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr>
            <th class="ps-4">#</th><th>Salesperson</th><th class="text-end">Customers</th><th class="text-end">Target ₹L</th><th class="text-end">Achieved ₹L</th>
            <th style="min-width:160px">Achievement</th><th class="text-end">Orders</th><th class="text-end">This week</th><th class="text-end pe-4">Pending</th>
          </tr></thead>
          <tbody>
          @foreach($salespeople as $i => $u)
            <tr>
              <td class="ps-4 text-muted">{{ $i + 1 }}</td>
              <td><a href="{{ route('performance.ninety-day-goals.person', $u['id']) }}" class="fw-semibold text-dark">{{ $u['name'] }}</a><div class="small text-muted">{{ $u['department'] }}</div></td>
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
        <span class="small text-muted">Showing {{ $rows->count() }} of {{ $allRowsCount }}</span>
      </div>
      <div class="card-body p-0 ndg-mx-scroll">
        <table class="ndg-mx" style="font-size:.74rem">
          <thead><tr>
            <th class="ndg-mx-l" style="left:0">Program</th><th class="ndg-mx-l" style="left:110px">Salesperson</th><th class="ndg-mx-l" style="left:220px">Customer</th>
            <th>Product</th><th>Goal ₹</th>
            @for($w = 0; $w < $weeks; $w++)<th>W{{ $w }}</th>@endfor
            <th>Actual ₹L</th><th>Achv %</th><th>Status</th>
          </tr></thead>
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
                  [$bg] = $st ? ($G::STATUS_COLORS[$st] ?? ['#94a3b8']) : [null];
                @endphp
                <td class="ndg-mx-c" style="{{ $bg ? 'background:' . $bg : '' }}" title="{{ $st ?: '—' }}"></td>
              @endfor
              <td class="fw-semibold">{{ $fmtL($r->achieved) }}</td>
              <td style="color:{{ $pctColor($r->pct) }}">{{ $pctText($r->pct) }}</td>
              <td>@if($r->status)<span class="ndg-chip" style="background:{{ $G::STATUS_COLORS[$r->status][0] ?? '#e2e8f0' }};color:{{ $G::STATUS_COLORS[$r->status][1] ?? '#475569' }}">{{ $r->status }}</span>@else<span class="ndg-none">·</span>@endif</td>
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
      <div class="card-body p-3"><div id="ndgAdmComplianceChart2"></div></div>
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
                  <td class="pe-4">@if($r->status)<span class="ndg-chip" style="background:{{ $G::STATUS_COLORS[$r->status][0] ?? '#e2e8f0' }};color:{{ $G::STATUS_COLORS[$r->status][1] ?? '#475569' }}">{{ $r->status }}</span>@endif</td>
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
              <td>@if($r->status)<span class="ndg-chip" style="background:{{ $bg }};color:{{ $fg }}">{{ $r->status }}</span>@else<span class="ndg-none">nothing yet</span>@endif</td>
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
        <a href="{{ route('performance.ninety-day-goals.admin-view.export', request()->query()) }}" class="btn-hitech rounded-pill px-5 d-inline-flex align-items-center gap-2">
          <i class="bx bx-download fs-5"></i><span class="fw-bold">Export to Excel (CSV)</span>
        </a>
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
  .ndg-kpi { border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; background: #fff; height: 100%; }
  .ndg-kpi-l { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #64748b; display: flex; align-items: center; gap: 5px; }
  .ndg-kpi-l i { color: #007a7a; font-size: 1rem; }
  .ndg-kpi-v { font-size: 1.5rem; font-weight: 800; color: #0f172a; line-height: 1.25; margin-top: 4px; font-variant-numeric: tabular-nums; }
  .ndg-kpi-s { font-size: .7rem; color: #94a3b8; }
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
</style>
@endsection

@section('page-script')
<script>
(function () {
  var tabs = document.querySelectorAll('#ndgAdminTabs .ndg-subtab');
  var sections = document.querySelectorAll('.ndg-adm-section');
  function show(key) {
    tabs.forEach(function (t) { t.classList.toggle('on', t.dataset.tab === key); });
    sections.forEach(function (s) { s.classList.toggle('d-none', s.dataset.section !== key); });
    try { history.replaceState(null, '', '#' + key); } catch (e) {}
  }
  tabs.forEach(function (t) { t.addEventListener('click', function () { show(t.dataset.tab); }); });
  var initial = (location.hash || '').replace('#', '');
  if (initial && document.querySelector('.ndg-adm-section[data-section="' + initial + '"]')) { show(initial); }

  if (typeof ApexCharts === 'undefined') { return; }
  var teal = '#007a7a', muted = '#64748b';

  var programEl = document.querySelector('#ndgAdmProgramChart');
  if (programEl) {
    var programData = @json($programChartData);
    new ApexCharts(programEl, {
      chart: { type: 'bar', height: 280, toolbar: { show: false }, fontFamily: 'inherit' },
      series: [{ name: 'Target (₹L)', data: programData.map(function (p) { return p.target; }) }, { name: 'Actual (₹L)', data: programData.map(function (p) { return p.achieved; }) }],
      xaxis: { categories: programData.map(function (p) { return p.name; }), labels: { style: { colors: muted, fontSize: '11px' } } },
      yaxis: { labels: { style: { colors: muted } } },
      colors: ['#94c9c9', teal],
      plotOptions: { bar: { columnWidth: '55%', borderRadius: 4 } },
      legend: { position: 'top', horizontalAlign: 'right', fontSize: '12px' },
      dataLabels: { enabled: false },
      grid: { borderColor: '#eef2f5' },
    }).render();
  }

  var donutEl = document.querySelector('#ndgAdmStatusDonut');
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
  ['#ndgAdmComplianceChart', '#ndgAdmComplianceChart2'].forEach(function (sel) {
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
})();
</script>
@endsection
