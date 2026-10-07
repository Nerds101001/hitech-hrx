@extends('layouts/layoutMaster')

@section('title', __('Admin Command Center'))

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/select2/select2.scss',
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/libs/apex-charts/apex-charts.scss'
  ])
  @vite(['resources/assets/vendor/scss/pages/hitech-portal.scss'])
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap">
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/apex-charts/apex-charts.min.js',
  ])
@endsection

@php
  $hour = (int) now()->format('H');
  $greet = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
  $firstName = auth()->user()->first_name ?? (explode(' ', auth()->user()->name ?? 'Admin')[0]);
  $toneHex = ['primary' => '#0d9488', 'info' => '#2563eb', 'success' => '#16a34a', 'warning' => '#d97706', 'danger' => '#dc2626'];
  // Rupee formatters for the CEO Command Center. $money takes raw rupees;
  // $fmtL takes a value already expressed in LAKHS (sales figures are stored
  // that way throughout the pipeline/target tables).
  $money = function ($n) {
    $n = (float) $n;
    if ($n >= 10000000) return '₹' . number_format($n / 10000000, 2) . ' Cr';
    if ($n >= 100000)   return '₹' . number_format($n / 100000, 2) . ' L';
    return '₹' . number_format($n);
  };
  $fmtL = function ($v) {
    $v = (float) $v;
    if ($v >= 100) return '₹' . number_format($v / 100, 2) . ' Cr';
    if ($v >= 1)   return '₹' . number_format($v, 2) . ' L';
    return '₹' . number_format($v * 100000);
  };
@endphp

@section('content')
<div class="adc">

  {{-- ===== GREETING HEADER ===== --}}
  <div class="adc-hero d-flex flex-wrap align-items-center justify-content-between mb-4">
    <div>
      <h4 class="adc-hero-title mb-1">{{ $greet }}, {{ $firstName }} <span class="adc-wave">👋</span></h4>
      <p class="adc-hero-sub mb-0">Here's what's happening across your organization today — {{ now()->format('l, d M Y') }}.</p>
    </div>
    <div class="d-flex align-items-center gap-2 mt-2 mt-md-0">
      <span class="adc-live" title="Live — auto-refreshing"><span class="adc-live-dot"></span> Live</span>
      <span class="adc-chip"><i class="bx bx-calendar"></i> {{ now()->format('M Y') }}</span>
      <a href="{{ url('/reports') }}" class="adc-chip adc-chip-accent"><i class="bx bx-bar-chart-alt-2"></i> Analytics</a>
    </div>
  </div>

  {{-- ===== KPI CARDS ===== --}}
  <div class="row g-3 mb-2">
    @php
      $kpis = [
        ['label' => 'Total Employees', 'value' => number_format($activeEmployees), 'icon' => 'bx-group', 'tone' => 'primary', 'trend' => $trends['totalStaff'] ?? null, 'sub' => 'active headcount', 'url' => url('/employees'), 'live' => 'active'],
        ['label' => 'Attendance', 'value' => $att7Avg . '%', 'icon' => 'bx-user-check', 'tone' => 'info', 'trend' => null, 'sub' => 'avg · last 7 days', 'url' => url('/attendance'), 'spark' => true],
        ['label' => 'Monthly Payroll', 'value' => '₹' . $monthlyPayrollShort, 'icon' => 'bx-money', 'tone' => 'success', 'trend' => null, 'sub' => $monthlyPayrollLabel . ' net', 'url' => url('/payroll')],
        ['label' => 'Sales Pipeline', 'value' => '₹' . $pipelineValueShort, 'icon' => 'bx-doughnut-chart', 'tone' => 'primary', 'trend' => null, 'sub' => 'open potential', 'url' => url('/sales-visits/sales-pipeline')],
        ['label' => 'Open Positions', 'value' => number_format($activeJobsCount), 'icon' => 'bx-briefcase', 'tone' => 'warning', 'trend' => $trends['openings'] ?? null, 'sub' => 'actively hiring', 'url' => url('/job')],
        ['label' => 'Attrition (YTD)', 'value' => $attritionRate . '%', 'icon' => 'bx-user-x', 'tone' => 'danger', 'trend' => null, 'sub' => $relievedThisYear . ' relieved', 'url' => url('/employees')],
      ];
    @endphp
    @foreach($kpis as $k)
      {{-- col-lg-2 (not col-xxl-2): the xxl breakpoint is 1400px, so a
           1291-1399px-wide laptop window fell one tier short of the
           6-per-row layout and wrapped these into two stacked rows of
           three instead - doubling this section's height for no visual
           reason, and no amount of padding/font-size trimming inside a
           card can undo a whole extra row. lg (992px) is the same
           threshold the short-viewport compacting below already uses. --}}
      <div class="col-6 col-md-4 col-lg-2">
        <a href="{{ $k['url'] }}" class="adc-kpi adc-tone-{{ $k['tone'] }} h-100 d-block">
          <div class="adc-kpi-top">
            <span class="adc-kpi-icon"><i class="bx {{ $k['icon'] }}"></i></span>
            @if($k['trend'])
              <span class="adc-kpi-trend {{ $k['trend']['isUp'] ? 'up' : 'down' }}">
                <i class="bx {{ $k['trend']['isUp'] ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt' }}"></i>{{ $k['trend']['value'] }}
              </span>
            @endif
            <i class="bx bx-chevron-right adc-kpi-go"></i>
          </div>
          <div class="adc-kpi-value" @if(!empty($k['live'])) data-live="{{ $k['live'] }}" @endif>{{ $k['value'] }}</div>
          <div class="adc-kpi-label">{{ $k['label'] }}</div>
          <div class="adc-kpi-sub">{{ $k['sub'] }}</div>
          @if(!empty($k['spark']))<div id="adcAtt7" class="adc-kpi-spark"></div>@endif
        </a>
      </div>
    @endforeach
  </div>

  {{-- ===== MAIN / RAIL SPLIT ===== --}}
  <div class="row g-4 mt-0">

    {{-- ========== MAIN COLUMN ========== --}}
    <div class="col-xxl-9 col-lg-8">

      {{-- Row A: My Tasks + Sales Target vs Achievement, side by side so
           neither leaves the other half of the row empty. --}}
      <div class="row g-4">
        <div class="col-xl-6">
          @include('tenant.users.dashboard._adc-task-widget')
        </div>
        <div class="col-xl-6">
          <div class="adc-card h-100">
            <div class="adc-card-head">
              <div><h6 class="adc-card-title">Sales Target vs Achievement</h6><span class="adc-card-sub">{{ now()->format('F Y') }} · company-wide</span></div>
              <div class="adc-seg" id="adcTgtSeg">
                <button type="button" data-t="Bingo" class="active">Bingo</button>
                <button type="button" data-t="Kingo">Kingo</button>
                <button type="button" data-t="Super Kingo">Super K</button>
              </div>
            </div>
            <div class="row align-items-center g-2">
              <div class="col-md-5"><div id="adcGauge"></div></div>
              <div class="col-md-7">
                <div class="adc-tgt-list">
                  <div class="adc-tgt-row"><span class="adc-mini-label">Target</span><b id="adcTgtVal">—</b></div>
                  <div class="adc-tgt-row"><span class="adc-mini-label">Achieved</span><b id="adcAchVal" class="text-success">—</b></div>
                  <div class="adc-tgt-row"><span class="adc-mini-label">Gap</span><b id="adcGapVal">—</b></div>
                </div>
              </div>
            </div>
            <div class="adc-staff">
              <div class="adc-staff-head d-flex justify-content-between align-items-center">
                <span>Top Performing Sales Staff</span>
                @if($topPerformers->isNotEmpty())<small class="text-muted fw-normal">{{ $topPerformers->count() }} reps · scroll</small>@endif
              </div>
              @if($topPerformers->isEmpty())
                <div class="adc-empty adc-empty-sm"><i class="bx bx-bar-chart-alt-2"></i><span>No sales targets set for this month.</span></div>
              @else
                @php
                  $inr = function ($n) { $n = (int) round($n); $neg = $n < 0 ? '-' : ''; $s = (string) abs($n); if (strlen($s) <= 3) return $neg . $s; $l3 = substr($s, -3); $rest = substr($s, 0, -3); $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest); return $neg . $rest . ',' . $l3; };
                @endphp
                <div class="adc-staff-scroll">
                  <table class="table adc-table adc-staff-table mb-0">
                    <tbody>
                      @foreach($topPerformers as $p)
                        @php $pct = $p->pct ?? 0; $barTone = $pct >= 100 ? 'success' : ($pct >= 70 ? 'primary' : 'danger'); @endphp
                        <tr>
                          <td class="fw-medium"><img class="adc-staff-av" src="https://ui-avatars.com/api/?name={{ urlencode($p->name) }}&background=0d9488&color=fff&bold=true&size=48" alt="">{{ $p->name }}</td>
                          <td class="text-end text-muted">₹{{ $inr($p->target * 100000) }}</td>
                          <td class="text-end fw-semibold" style="width:60px">{{ $p->pct !== null ? $pct . '%' : '—' }}</td>
                          <td style="width:90px"><div class="adc-bar"><span class="adc-tone-{{ $barTone }}" style="width: {{ min(100, $pct) }}%"></span></div></td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              @endif
            </div>
          </div>
        </div>
      </div>

      {{-- Sales Pipeline Overview + HRX Modules — side by side in two
           columns instead of stacked full-width. --}}
      <div class="row g-4 mt-1">
        <div class="col-xl-6">
          <div class="adc-card h-100">
            <div class="adc-card-head"><div><h6 class="adc-card-title">Sales Pipeline Overview</h6><span class="adc-card-sub">Business potential by stage · hover for detail</span></div><a href="{{ url('/sales-visits/sales-pipeline') }}" class="adc-link">View</a></div>
            <div id="adcFunnel"></div>
          </div>
        </div>
        <div class="col-xl-6">
          <div class="adc-card h-100">
            <div class="adc-card-head"><div><h6 class="adc-card-title">HRX Modules</h6><span class="adc-card-sub">Live snapshot across the platform</span></div></div>
            <div class="row g-3">
              @foreach($moduleTiles as $m)
                <div class="col-6 col-xl-4">
                  <a href="{{ $m['url'] }}" class="adc-tile adc-tone-{{ $m['tone'] }}">
                    <span class="adc-tile-icon"><i class="bx {{ $m['icon'] }}"></i></span>
                    <div class="adc-tile-body">
                      <div class="adc-tile-value">{{ $m['value'] }}</div>
                      <div class="adc-tile-name">{{ $m['name'] }}</div>
                      <div class="adc-tile-sub">{{ $m['sub'] }}</div>
                    </div>
                  </a>
                </div>
              @endforeach
            </div>
          </div>
        </div>
      </div>

      {{-- ================= CEO COMMAND CENTER ================= --}}
      {{-- The money view: what is owed, what payroll costs, who is
           carrying the number and who is behind. --}}
      @php
        $ceoOut   = $ceoCashOut ?? ['payroll'=>0,'claims'=>0,'expenses'=>0,'loans'=>0];
        $ceoTotal = $ceoCashOutTotal ?? 0;
        $ceoCards = [
          ['label'=>'Payroll to pay','value'=>$ceoOut['payroll'],'icon'=>'bx-money','tone'=>'primary','url'=>url('/payroll')],
          ['label'=>'Travel claims','value'=>$ceoOut['claims'],'icon'=>'bx-car','tone'=>'warning','url'=>url('/travel-claims/admin/dashboard')],
          ['label'=>'Expense claims','value'=>$ceoOut['expenses'],'icon'=>'bx-receipt','tone'=>'info','url'=>url('/expenseRequests')],
          ['label'=>'Loan requests','value'=>$ceoOut['loans'],'icon'=>'bx-wallet','tone'=>'success','url'=>url('/loan')],
        ];
      @endphp
      <div class="adc-card mt-4">
        <div class="adc-card-head">
          <div>
            <h6 class="adc-card-title">💼 CEO Command Center — Cash Out</h6>
            <span class="adc-card-sub">Committed but not yet paid · total {{ $money($ceoTotal) }}</span>
          </div>
          <a href="{{ url('/payroll') }}" class="adc-link">Payroll</a>
        </div>
        <div class="row g-3">
          @foreach($ceoCards as $c)
            <div class="col-6 col-xl-3">
              <a href="{{ $c['url'] }}" class="adc-tile adc-tone-{{ $c['tone'] }} d-block h-100">
                <span class="adc-tile-icon"><i class="bx {{ $c['icon'] }}"></i></span>
                <div class="adc-tile-body">
                  <div class="adc-tile-value">{{ $money($c['value']) }}</div>
                  <div class="adc-tile-name">{{ $c['label'] }}</div>
                  <div class="adc-tile-sub">awaiting payment</div>
                </div>
              </a>
            </div>
          @endforeach
        </div>
      </div>

      <div class="row g-4 mt-1">
        <div class="col-xl-8">
          <div class="adc-card h-100">
            <div class="adc-card-head">
              <div><h6 class="adc-card-title">Payroll Cost Trend</h6><span class="adc-card-sub">Net salary paid · last 12 months · current run {{ $money($ceoMonthlySalaryCost ?? 0) }}/mo</span></div>
              <a href="{{ url('/payroll') }}" class="adc-link">View</a>
            </div>
            <div id="ceoPayrollTrend"></div>
          </div>
        </div>
        <div class="col-xl-4">
          <div class="adc-card h-100">
            <div class="adc-card-head"><div><h6 class="adc-card-title">Cost by Department</h6><span class="adc-card-sub">Monthly salary · top 8</span></div></div>
            @if(collect($ceoDeptCost ?? [])->isEmpty())
              <div class="adc-empty adc-empty-sm"><i class="bx bx-building"></i><span>No salary data recorded.</span></div>
            @else
              <ul class="adc-appr adc-rail-scroll" style="max-height:300px">
                @foreach($ceoDeptCost as $d)
                  <li>
                    <div class="adc-appr-body">
                      <div class="adc-appr-name">{{ $d->dept_name }}</div>
                      <div class="adc-appr-meta">{{ $d->headcount }} {{ \Illuminate\Support\Str::plural('person', $d->headcount) }}</div>
                    </div>
                    <span class="adc-badge adc-tone-info">{{ $money($d->cost) }}</span>
                  </li>
                @endforeach
              </ul>
            @endif
          </div>
        </div>
      </div>

      @if(collect($ceoTopReps ?? [])->isNotEmpty())
      <div class="row g-4 mt-1">
        <div class="col-xl-6">
          <div class="adc-card h-100">
            <div class="adc-card-head"><div><h6 class="adc-card-title">🏆 Top Performers</h6><span class="adc-card-sub">vs Bingo target · {{ now()->format('M Y') }}</span></div><a href="{{ url('/sales-visits/kingo-bingo') }}" class="adc-link">Scorecard</a></div>
            <ul class="adc-appr">
              @foreach($ceoTopReps as $r)
                <li>
                  <img class="adc-appr-av" src="https://ui-avatars.com/api/?name={{ urlencode($r['name']) }}&background=10b981&color=fff&bold=true" alt="">
                  <div class="adc-appr-body">
                    <div class="adc-appr-name">{{ $r['name'] }}</div>
                    <div class="adc-appr-meta">{{ $fmtL($r['achieved']) }} of {{ $fmtL($r['target']) }}</div>
                  </div>
                  <span class="adc-badge adc-tone-{{ $r['pct'] >= 100 ? 'success' : ($r['pct'] >= 75 ? 'warning' : 'danger') }}">{{ round($r['pct']) }}%</span>
                </li>
              @endforeach
            </ul>
          </div>
        </div>
        <div class="col-xl-6">
          <div class="adc-card h-100">
            <div class="adc-card-head"><div><h6 class="adc-card-title">⚠️ Needs Attention</h6><span class="adc-card-sub">Furthest behind target · {{ now()->format('M Y') }}</span></div><a href="{{ url('/sales-visits/kingo-bingo') }}" class="adc-link">Scorecard</a></div>
            <ul class="adc-appr">
              @foreach($ceoBottomReps as $r)
                <li>
                  <img class="adc-appr-av" src="https://ui-avatars.com/api/?name={{ urlencode($r['name']) }}&background=ef4444&color=fff&bold=true" alt="">
                  <div class="adc-appr-body">
                    <div class="adc-appr-name">{{ $r['name'] }}</div>
                    <div class="adc-appr-meta">{{ $fmtL($r['achieved']) }} of {{ $fmtL($r['target']) }}</div>
                  </div>
                  <span class="adc-badge adc-tone-{{ $r['pct'] >= 100 ? 'success' : ($r['pct'] >= 75 ? 'warning' : 'danger') }}">{{ round($r['pct']) }}%</span>
                </li>
              @endforeach
            </ul>
          </div>
        </div>
      </div>
      @endif
      {{-- =============== /CEO COMMAND CENTER =============== --}}

      {{-- Row C: Workforce Overview + KPI donut --}}
      <div class="row g-4 mt-4">
        <div class="col-xl-8">
          <div class="adc-card h-100">
            <div class="adc-card-head">
              <div><h6 class="adc-card-title">Workforce Overview</h6><span class="adc-card-sub">New joins vs exits · last 12 months</span></div>
              <a href="{{ url('/employees') }}" class="adc-link">View</a>
            </div>
            <div id="adcWorkforce"></div>
          </div>
        </div>
        <div class="col-xl-4">
          <div class="adc-card h-100">
            <div class="adc-card-head"><div><h6 class="adc-card-title">{{ $kpiSummary['source'] === 'kpi' ? 'KPI Summary' : "Today's Workforce" }}</h6><span class="adc-card-sub">{{ $kpiSummary['source'] === 'kpi' ? 'KRA compliance' : 'Present · leave · absent' }}</span></div><a href="{{ url('/attendance') }}" class="adc-link">View</a></div>
            <div id="adcKpi"></div>
          </div>
        </div>
      </div>

      {{-- Row D: Dept Headcount + Leave trend --}}
      <div class="row g-4 mt-4">
        <div class="col-xl-7">
          <div class="adc-card h-100">
            <div class="adc-card-head"><div><h6 class="adc-card-title">Dept Headcount</h6><span class="adc-card-sub">Active by department</span></div><a href="{{ url('/organizationHierarchy') }}" class="adc-link">View</a></div>
            <div id="adcDept"></div>
          </div>
        </div>
        <div class="col-xl-5">
          <div class="adc-card h-100">
            <div class="adc-card-head"><div><h6 class="adc-card-title">Leave Trend</h6><span class="adc-card-sub">Approved · {{ now()->year }}</span></div><a href="{{ url('/leaveRequests') }}" class="adc-link">View</a></div>
            <div id="adcLeave"></div>
          </div>
        </div>
      </div>

      {{-- Row E: Assets overview + Recent activity --}}
      <div class="row g-4 mt-4">
        <div class="col-xl-5">
          <div class="adc-card h-100">
            <div class="adc-card-head"><div><h6 class="adc-card-title">Assets Overview</h6><span class="adc-card-sub">₹{{ $assetValueShort }} total value</span></div><a href="{{ url('/asset-management') }}" class="adc-link">Manage</a></div>
            <div class="adc-asset-grid">
              <div class="adc-asset-cell"><div class="adc-asset-num">{{ number_format($assetTotal) }}</div><div class="adc-asset-lbl"><i class="bx bx-archive text-primary"></i> Total</div></div>
              <div class="adc-asset-cell"><div class="adc-asset-num">{{ number_format($assetAssigned) }}</div><div class="adc-asset-lbl"><i class="bx bx-user-check text-success"></i> Assigned</div></div>
              <div class="adc-asset-cell"><div class="adc-asset-num">{{ number_format($assetAvailable) }}</div><div class="adc-asset-lbl"><i class="bx bx-box text-info"></i> Available</div></div>
              <div class="adc-asset-cell"><div class="adc-asset-num">{{ number_format($assetMaintenance) }}</div><div class="adc-asset-lbl"><i class="bx bx-wrench text-warning"></i> Maintenance</div></div>
            </div>
          </div>
        </div>
        <div class="col-xl-7">
          <div class="adc-card h-100">
            <div class="adc-card-head"><div><h6 class="adc-card-title">Recent Activity</h6><span class="adc-card-sub">Latest across the org</span></div></div>
            @if($recentFeed->isEmpty())
              <div class="adc-empty"><i class="bx bx-time"></i><span>No recent activity.</span></div>
            @else
              <ul class="adc-feed">
                @foreach($recentFeed->take(6) as $f)
                  <li>
                    <a href="{{ $f['url'] ?? '#' }}" class="adc-rowlink">
                      <span class="adc-feed-dot adc-tone-{{ $f['tone'] }}"><i class="bx {{ $f['icon'] }}"></i></span>
                      <div class="adc-feed-body">
                        <div class="adc-feed-title">{{ $f['title'] }}</div>
                        <div class="adc-feed-meta"><span class="adc-badge adc-tone-{{ $f['tone'] }}">{{ $f['type'] }}</span> · {{ $f['time'] }}</div>
                      </div>
                    </a>
                  </li>
                @endforeach
              </ul>
            @endif
          </div>
        </div>
      </div>

      {{-- Celebrations — birthdays and work anniversaries, with the Send-a-Wish
           action. Shared partial so Admin and HR cannot drift apart. --}}
      <div class="row g-3 mt-0">
        @include('tenant.users.dashboard._adc-celebrations')
      </div>

    </div>

    {{-- ========== RIGHT RAIL ========== --}}
    <div class="col-xxl-3 col-lg-4">

      {{-- AI Insights --}}
      <div class="adc-card adc-ai mb-4">
        <div class="adc-card-head"><div><h6 class="adc-card-title"><i class="bx bx-bulb text-warning"></i> AI Insights</h6><span class="adc-card-sub">Signals from your live data</span></div></div>
        <ul class="adc-insights">
          @foreach($aiInsights as $ins)
            <li>
              <a href="{{ $ins['url'] ?? '#' }}" class="adc-rowlink">
                <span class="adc-ins-icon adc-tone-{{ $ins['tone'] }}"><i class="bx {{ $ins['icon'] }}"></i></span>
                <div><div class="adc-ins-title">{{ $ins['title'] }}</div><div class="adc-ins-sub">{{ $ins['sub'] }}</div></div>
              </a>
            </li>
          @endforeach
        </ul>
      </div>

      @include('tenant.users.dashboard._adc-announcements')

      {{-- Today's Events --}}
      <div class="adc-card mb-4">
        <div class="adc-card-head"><div><h6 class="adc-card-title">Today's Events</h6></div></div>
        <ul class="adc-events">
          @foreach($eventsToday as $ev)
            <li>
              <a href="{{ $ev['url'] ?? '#' }}" class="adc-rowlink">
                <span class="adc-ev-icon adc-tone-{{ $ev['tone'] }}"><i class="bx {{ $ev['icon'] }}"></i></span>
                <span class="adc-ev-label">{{ $ev['label'] }}</span>
                <span class="adc-ev-count">{{ $ev['count'] }}</span>
              </a>
            </li>
          @endforeach
        </ul>
      </div>

      {{-- Upcoming Holidays --}}
      <div class="adc-card mb-4">
        <div class="adc-card-head"><div><h6 class="adc-card-title">Upcoming Calendar</h6></div><a href="{{ url('/holidays') }}" class="adc-link">All</a></div>
        @if($upcomingHolidays->isEmpty())
          <div class="adc-empty adc-empty-sm"><span>No upcoming holidays.</span></div>
        @else
          <ul class="adc-cal">
            @foreach($upcomingHolidays as $h)
              <li>
                <a href="{{ url('/holidays') }}" class="adc-rowlink">
                  <span class="adc-cal-date"><b>{{ \Carbon\Carbon::parse($h->date)->format('d') }}</b><small>{{ \Carbon\Carbon::parse($h->date)->format('M') }}</small></span>
                  <div><div class="adc-cal-name">{{ $h->name ?? $h->title ?? 'Holiday' }}</div><div class="adc-cal-day">{{ \Carbon\Carbon::parse($h->date)->format('l') }}</div></div>
                </a>
              </li>
            @endforeach
          </ul>
        @endif
      </div>

      {{-- Pending Approvals --}}
      <div class="adc-card mb-4">
        <div class="adc-card-head"><div><h6 class="adc-card-title">Pending Approvals</h6><span class="adc-card-sub"><b data-approval-count>{{ $activeApprovalsTotal }}</b> awaiting action</span></div><a href="{{ $pendingApprovalsUrl ?? url('/leaveRequests') }}" class="adc-link">All</a></div>
        @if($pendingApprovals->isEmpty())
          <div class="adc-empty adc-empty-sm"><i class="bx bx-check-circle text-success"></i><span>All caught up!</span></div>
        @else
          <ul class="adc-appr">
            @foreach($pendingApprovals->take(6) as $a)
              @php $tmap = ['Leave'=>'info','Expense'=>'warning','Document'=>'primary','Loan'=>'danger']; $t = $tmap[$a['type']] ?? 'primary'; @endphp
              <li data-approve data-type="{{ $a['type'] }}" data-id="{{ $a['id'] ?? '' }}">
                <img src="{{ $a['avatar'] }}" alt="" class="adc-appr-av" onerror="this.src='https://ui-avatars.com/api/?name={{ urlencode($a['user'] ?? 'U') }}&background=0d9488&color=fff'">
                <div class="adc-appr-body">
                  <div class="adc-appr-name">{{ $a['user'] ?? 'N/A' }} <span class="adc-badge adc-tone-{{ $t }}" style="font-size:.6rem">{{ $a['type'] }}</span></div>
                  <div class="adc-appr-meta">{{ $a['details'] ?? $a['type'] }}</div>
                </div>
                <span class="adc-act"><button class="adc-ok" data-act="approve" title="Approve"><i class="bx bx-check"></i></button><button class="adc-no" data-act="reject" title="Reject"><i class="bx bx-x"></i></button></span>
              </li>
            @endforeach
          </ul>
        @endif
      </div>

      {{-- Probation Ending --}}
      <div class="adc-card mb-4">
        @php
          $probOver = (int) ($probationOverdueCount ?? 0);
          $probSoon = (int) ($probationDueSoonCount ?? 0);
          $probSubtitle = $probOver > 0
            ? $probOver . ' overdue' . ($probSoon > 0 ? ' · ' . $probSoon . ' due in 30d' : '')
            : ($probSoon > 0 ? $probSoon . ' due in next 30 days' : 'Next 30 days');
        @endphp
        <div class="adc-card-head"><div><h6 class="adc-card-title">Probation Review</h6><span class="adc-card-sub">{{ $probSubtitle }}</span></div><a href="{{ url('/probation/dashboard') }}" class="adc-link">All</a></div>
        @if($upcomingProbationEnds->isEmpty())
          <div class="adc-empty adc-empty-sm"><i class="bx bx-check-circle text-success"></i><span>None upcoming.</span></div>
        @else
          <ul class="adc-appr adc-rail-scroll">
            @foreach($upcomingProbationEnds as $u)
              @php $pd = \Carbon\Carbon::parse($u->probation_end_date); @endphp
              <li>
                <a href="{{ url('/probation/evaluate/' . $u->id) }}" class="adc-rowlink" title="Review probation">
                  <img class="adc-appr-av" src="https://ui-avatars.com/api/?name={{ urlencode($u->name) }}&background=d97706&color=fff&bold=true" alt="">
                  <div class="adc-appr-body">
                    <div class="adc-appr-name">{{ $u->name }}</div>
                    <div class="adc-appr-meta">Ends {{ $pd->format('d M Y') }}</div>
                  </div>
                  <span class="adc-badge adc-tone-{{ $pd->isPast() ? 'danger' : 'warning' }}">{{ $pd->diffForHumans(null, true) }}</span>
                </a>
              </li>
            @endforeach
          </ul>
        @endif
      </div>

      @include('tenant.users.dashboard._adc-important-contacts')

      @include('tenant.users.dashboard._adc-telephony-stats')

      {{-- Quick Actions --}}
      <div class="adc-card">
        <div class="adc-card-head"><div><h6 class="adc-card-title">Quick Actions</h6></div></div>
        <div class="adc-qa">
          <a href="{{ url('/employees') }}" class="adc-qa-btn"><i class="bx bx-user-plus"></i><span>Employees</span></a>
          <a href="{{ url('/payroll') }}" class="adc-qa-btn"><i class="bx bx-money"></i><span>Run Payroll</span></a>
          <a href="{{ url('/leaveRequests') }}" class="adc-qa-btn"><i class="bx bx-check-shield"></i><span>Approvals</span></a>
          <a href="{{ url('/job') }}" class="adc-qa-btn"><i class="bx bx-briefcase"></i><span>Post Job</span></a>
          <a href="{{ url('/attendance') }}" class="adc-qa-btn"><i class="bx bx-time-five"></i><span>Attendance</span></a>
          <a href="{{ url('/announcements') }}" class="adc-qa-btn"><i class="bx bx-news"></i><span>Publish News</span></a>
          <a href="{{ url('/hr-policies') }}" class="adc-qa-btn"><i class="bx bx-book-content"></i><span>HR Policies</span></a>
        </div>
      </div>

    </div>
  </div>
</div>

@include('tenant.users.dashboard._adc-styles')
@include('tenant.users.dashboard._adc-live')

@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const money = v => '₹' + (v >= 1e7 ? (v/1e7).toFixed(2)+' Cr' : v >= 1e5 ? (v/1e5).toFixed(2)+' L' : v >= 1e3 ? (v/1e3).toFixed(1)+'K' : Math.round(v));
  // Sales values are stored in LAKHS -> format raw as Cr / L (never K).
  const moneyLakh = v => { v = Number(v) || 0; return '₹' + (v >= 100 ? (v/100).toFixed(2)+' Cr' : v >= 1 ? v.toFixed(2)+' L' : Math.round(v*100000).toLocaleString('en-IN')); };
  const axisColor = '#94a3b8', gridColor = '#f1f5f9';
  const toolbar = { show: true, tools: { download: true, selection: true, zoom: true, zoomin: true, zoomout: true, pan: true, reset: true } };
  const empty = (el, icon, msg) => { if (el) el.innerHTML = '<div class="adc-empty"><i class="bx '+icon+'"></i><span>'+msg+'</span></div>'; };

  // ApexCharts ships as an ES module in the Vite build, so the global may not be
  // set synchronously. Wait briefly for the Vite module to evaluate, then fall
  // back to dynamically importing the apex chunk found in the DOM — bulletproof.
  async function getApex() {
    // The Vite module sets window.ApexCharts shortly after load; poll for it.
    for (let i = 0; i < 200 && !window.ApexCharts; i++) { await new Promise(r => setTimeout(r, 25)); }
    if (window.ApexCharts) return window.ApexCharts;
    // Fallback: import the apex chunk directly (silent — only reached if the
    // global never appeared).
    const s = [...document.querySelectorAll('script[src]')].find(x => /apex-charts.*\.js/i.test(x.src));
    if (s) { try { const m = await import(s.src); if (m && (m.default || m.ApexCharts)) window.ApexCharts = m.default || m.ApexCharts; } catch (e) {} }
    return window.ApexCharts || null;
  }

  function build(AC) {
    // Every chart on this page hardcodes its own pixel height, which no CSS
    // media query can touch (ApexCharts sets it as an inline style at
    // render time). On a short window (~1291x671-class laptops) those
    // heights alone - 230/280/300/320px each - were the actual reason the
    // page stayed oversized no matter how much the surrounding CSS
    // (.adc-kpi, .adc-card padding, etc.) was compacted. Scaled here once,
    // centrally, rather than hand-tuning each chart's own height value.
    const mk = (sel, opts) => {
      const el = document.querySelector(sel);
      if (!el) return null;
      if (opts.chart && typeof opts.chart.height === 'number' && window.innerHeight < 750) {
        opts.chart.height = Math.round(opts.chart.height * 0.65);
      }
      try { const c = new AC(el, opts); c.render(); return c; }
      catch (e) { console.error('[adc] '+sel, e); empty(el, 'bx-error-circle', 'Chart failed to load'); return null; }
    };

    // 1. Workforce Overview (area, zoomable)
    const wfH = {!! json_encode($hiringTrend['hires'] ?? [], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!};
    const wfE = {!! json_encode($hiringTrend['attrition'] ?? [], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!};
    if (wfH.some(v=>v>0) || wfE.some(v=>v>0)) {
      mk('#adcWorkforce', {
        series: [{ name:'New Joins', data:wfH }, { name:'Exits', data:wfE }],
        chart: { type:'area', height:300, toolbar, zoom:{enabled:true}, fontFamily:'Public Sans' },
        colors: ['#0d9488','#dc2626'], stroke:{curve:'smooth',width:3},
        fill:{type:'gradient',gradient:{shadeIntensity:1,opacityFrom:0.28,opacityTo:0.03}},
        dataLabels:{enabled:false}, legend:{position:'top',horizontalAlign:'right'},
        xaxis:{categories:{!! json_encode($hiringTrend['labels'] ?? [], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!},labels:{style:{colors:axisColor}},axisBorder:{show:false},axisTicks:{show:false}},
        yaxis:{labels:{style:{colors:axisColor}}}, grid:{borderColor:gridColor,strokeDashArray:4}
      });
    } else empty(document.querySelector('#adcWorkforce'), 'bx-line-chart', 'No hiring activity in the last 12 months');

    // 1b. CEO Command Center — payroll cost trend (12 months)
    const ceoPay = {!! json_encode($ceoPayrollTrend ?? ['labels'=>[],'data'=>[]], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!};
    if (document.querySelector('#ceoPayrollTrend')) {
      if ((ceoPay.data || []).some(v => v > 0)) {
        mk('#ceoPayrollTrend', {
          series: [{ name:'Net Salary Paid', data: ceoPay.data }],
          chart: { type:'area', height:300, toolbar, fontFamily:'Public Sans' },
          colors: ['#0d9488'], stroke:{curve:'smooth',width:3},
          fill:{type:'gradient',gradient:{shadeIntensity:1,opacityFrom:0.3,opacityTo:0.03}},
          dataLabels:{enabled:false},
          xaxis:{categories: ceoPay.labels, labels:{style:{colors:axisColor}}, axisBorder:{show:false}, axisTicks:{show:false}},
          yaxis:{labels:{style:{colors:axisColor}, formatter:v => '₹'+Math.round(v).toLocaleString('en-IN')}},
          tooltip:{y:{formatter:v => '₹'+Math.round(v).toLocaleString('en-IN')}},
          grid:{borderColor:gridColor,strokeDashArray:4}
        });
      } else {
        empty(document.querySelector('#ceoPayrollTrend'), 'bx-money', 'No payroll paid in the last 12 months');
      }
    }

    // Attendance sparkline (last 7 days)
    const att7 = {!! json_encode(array_column($att7, 'pct'), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!};
    if (document.querySelector('#adcAtt7') && att7.some(v=>v>0)) {
      mk('#adcAtt7', { series:[{name:'Present %',data:att7}], chart:{type:'area',height:34,sparkline:{enabled:true}}, colors:['#2563eb'], stroke:{curve:'smooth',width:2}, fill:{type:'gradient',gradient:{opacityFrom:0.4,opacityTo:0.05}}, tooltip:{y:{formatter:v=>v+'%'}} });
    }

    // 2. Sales Target gauge with Bingo / Kingo / Super Kingo toggle
    const sTargets = {!! json_encode($salesTargets, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!};
    const sPct = {!! json_encode($salesTargetPct, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!};
    const sAch = {{ (float) $salesAchieved }};
    const gEl = document.querySelector('#adcGauge');
    const anyTarget = Object.values(sTargets).some(v=>v>0);
    const setFigs = t => {
      const tv=document.querySelector('#adcTgtVal'), gv=document.querySelector('#adcGapVal'), av=document.querySelector('#adcAchVal');
      if(tv) tv.textContent = moneyLakh(sTargets[t]||0);
      if(av) av.textContent = moneyLakh(sAch);
      if(gv) gv.textContent = moneyLakh(Math.max(0,(sTargets[t]||0)-sAch));
    };
    if (gEl && (anyTarget || sAch>0)) {
      const gauge = mk('#adcGauge', {
        series:[Math.min(150, sPct['Bingo']||0)],
        chart:{type:'radialBar',height:230,offsetY:-10,fontFamily:'Public Sans'}, colors:['#16a34a'],
        plotOptions:{radialBar:{
          startAngle:-90, endAngle:90,
          hollow:{size:'62%'},
          track:{background:'#eef1f5',strokeWidth:'100%',margin:4},
          dataLabels:{
            name:{offsetY:6,color:'#94a3b8',fontSize:'12px'},
            value:{offsetY:-34,fontSize:'30px',fontWeight:800,color:'#1e293b',formatter:v=>Math.round(v)+'%'}
          }
        }},
        fill:{type:'gradient',gradient:{shade:'light',type:'horizontal',shadeIntensity:0.4,gradientToColors:['#5eead4'],stops:[0,100]}},
        stroke:{lineCap:'round'}, labels:['Overall Achievement']
      });
      setFigs('Bingo');
      document.querySelectorAll('#adcTgtSeg button').forEach(b => b.addEventListener('click', function(){
        document.querySelectorAll('#adcTgtSeg button').forEach(x=>x.classList.remove('active'));
        this.classList.add('active');
        const t=this.dataset.t;
        if(gauge) gauge.updateSeries([Math.min(150, sPct[t]||0)]);
        setFigs(t);
      }));
    } else if (gEl) {
      empty(gEl, 'bx-target-lock', 'No sales targets set this month');
      const seg=document.querySelector('#adcTgtSeg'); if(seg) seg.style.display='none';
      setFigs('Bingo');
    }

    // 3. Department donut
    const deptData = {!! json_encode($departmentData, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!};
    if (deptData && deptData.length) {
      mk('#adcDept', {
        series: deptData.map(d=>Number(d.count)), labels: deptData.map(d=>d.name),
        chart:{type:'donut',height:280,fontFamily:'Public Sans'},
        colors:['#0d9488','#2563eb','#f59e0b','#8b5cf6','#ec4899','#14b8a6','#ef4444','#64748b','#22c55e','#0ea5e9'],
        legend:{position:'bottom',fontSize:'12px'}, dataLabels:{enabled:false},
        plotOptions:{pie:{donut:{size:'68%',labels:{show:true,total:{show:true,label:'Total',fontSize:'13px',color:'#94a3b8',formatter:w=>w.globals.seriesTotals.reduce((a,b)=>a+b,0)}}}}},
        stroke:{width:2,colors:['#fff']}
      });
    } else empty(document.querySelector('#adcDept'), 'bx-pie-chart-alt-2', 'No department data');

    // 4. KPI / Workforce donut
    const kpiVals = {!! json_encode($kpiSummary['values'] ?? [], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!};
    if (kpiVals.reduce((a,b)=>a+b,0) > 0) {
      mk('#adcKpi', {
        series: kpiVals, labels: {!! json_encode($kpiSummary['labels'] ?? [], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!},
        chart:{type:'donut',height:280,fontFamily:'Public Sans'}, colors:['#16a34a','#d97706','#dc2626'],
        legend:{position:'bottom',fontSize:'12px'}, dataLabels:{enabled:true,formatter:(v)=>Math.round(v)+'%'},
        plotOptions:{pie:{donut:{size:'68%'}}}, stroke:{width:2,colors:['#fff']}
      });
    } else empty(document.querySelector('#adcKpi'), 'bx-doughnut-chart', 'No data yet');

    // 5. Leave trend (line, zoomable)
    const leaveSeries = {!! json_encode($leaveTrend['series'] ?? [], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!};
    if (leaveSeries.length) {
      mk('#adcLeave', {
        series: leaveSeries, chart:{type:'line',height:280,toolbar,zoom:{enabled:true},fontFamily:'Public Sans'},
        colors:['#0d9488','#2563eb','#f59e0b','#8b5cf6','#ec4899'], stroke:{curve:'smooth',width:2.5},
        dataLabels:{enabled:false}, legend:{position:'bottom',fontSize:'11px'},
        xaxis:{categories:{!! json_encode($leaveTrend['labels'] ?? [], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!},labels:{style:{colors:axisColor}},axisBorder:{show:false},axisTicks:{show:false}},
        yaxis:{labels:{style:{colors:axisColor}}}, grid:{borderColor:gridColor,strokeDashArray:4}
      });
    } else empty(document.querySelector('#adcLeave'), 'bx-calendar-event', 'No approved leave this year');

    // 6. Sales Pipeline Overview (interactive gradient area chart)
    const funnel = {!! json_encode($salesFunnel->take(7)->map(fn($r) => ['stage'=>$r->stage,'value'=>(float)$r->value,'deals'=>(int)$r->deals])->values(), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!};
    if (funnel && funnel.length) {
      mk('#adcFunnel', {
        series:[{ name:'Pipeline', data: funnel.map(f=>+(f.value/100).toFixed(2)) }],
        chart:{type:'area',height:320,fontFamily:'Public Sans',
          toolbar:{show:true,tools:{download:true,zoom:true,zoomin:true,zoomout:true,pan:true,reset:true}},
          zoom:{enabled:true}, animations:{enabled:true,easing:'easeinout',speed:800}},
        colors:['#0d9488'],
        stroke:{curve:'smooth',width:3},
        fill:{type:'gradient',gradient:{shadeIntensity:1,opacityFrom:0.5,opacityTo:0.05,stops:[0,100]}},
        markers:{size:5,colors:['#fff'],strokeColors:'#0d9488',strokeWidth:3,hover:{size:8}},
        dataLabels:{enabled:true,formatter:v=>'₹'+v+'Cr',offsetY:-7,style:{fontSize:'10px',colors:['#0f766e'],fontWeight:700},background:{enabled:false}},
        xaxis:{categories:funnel.map(f=>f.stage),tickPlacement:'on',
          labels:{rotate:-32,rotateAlways:true,hideOverlappingLabels:false,trim:true,style:{colors:axisColor,fontSize:'10px'}},
          axisBorder:{show:false},axisTicks:{show:false}},
        yaxis:{labels:{style:{colors:axisColor},formatter:v=>'₹'+Math.round(v)+'Cr'}},
        grid:{borderColor:gridColor,strokeDashArray:4,padding:{bottom:4}},
        tooltip:{custom:({dataPointIndex})=>{ const f=funnel[dataPointIndex]; return '<div class="px-2 py-1"><b>'+f.stage+'</b><br>'+moneyLakh(f.value)+' &middot; '+f.deals+' deals</div>'; }}
      });
    } else empty(document.querySelector('#adcFunnel'), 'bx-doughnut-chart', 'No open pipeline deals');
  }

  getApex().then(AC => {
    if (AC) build(AC);
    else ['#adcWorkforce','#adcGauge','#adcDept','#adcKpi','#adcLeave','#adcFunnel'].forEach(s => empty(document.querySelector(s), 'bx-bar-chart-alt-2', 'Charts unavailable'));
  });
});
</script>
@endsection
