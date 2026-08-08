@extends('layouts/layoutMaster')

@section('title', __('Team Command Center'))

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
  @vite(['resources/assets/vendor/libs/apex-charts/apex-charts.min.js'])
@endsection

@php
  $hour = (int) now()->format('H');
  $greet = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
  $firstName = auth()->user()->first_name ?? (explode(' ', auth()->user()->name ?? 'Manager')[0]);
  $teamSize = (int) ($activeEmployees ?? $active ?? $totalUser ?? 0);
  $present = (int) ($todayPresentUsers ?? $presentUsersCount ?? 0);
  $onLeave = (int) ($todayOnLeaveCount ?? 0);
  $absent = (int) ($todayAbsentUsers ?? max(0, $teamSize - $present - $onLeave));
  $attPct = $teamSize > 0 ? round($present / $teamSize * 100, 1) : 0;
  $teamApprovals = (int) ($pendingLeaveRequests ?? 0) + (int) ($pendingDocumentRequests ?? 0) + (int) ($pendingLoanRequests ?? 0);
  $deploy = collect($teamDeployment ?? []);
  $todayBdays = collect($todayBirthdays ?? []);
  $todayAnnis = collect($todayAnniversaries ?? []);
@endphp

@section('content')
<div class="adc">

  {{-- Hero --}}
  <div class="adc-hero d-flex flex-wrap align-items-center justify-content-between mb-4">
    <div>
      <h4 class="adc-hero-title mb-1">{{ $greet }}, {{ $firstName }} <span class="adc-wave">👋</span></h4>
      <p class="adc-hero-sub mb-0">Here's how your team is doing today — {{ now()->format('l, d M Y') }}.</p>
    </div>
    <div class="d-flex align-items-center gap-2 mt-2 mt-md-0">
      @if(!empty($actingFor))
        <span class="adc-chip" style="background:#fef3c7;color:#92400e;border:1px solid #f59e0b;"
              title="This vacant position's team, approvals and notifications are routed to you until it is filled.">
          <i class="bx bx-transfer-alt"></i> Acting for: {{ $actingFor }}
        </span>
      @endif
      <span class="adc-chip"><i class="bx bx-been-here"></i> Team Lead</span>
      <span class="adc-live" title="Live — auto-refreshing"><span class="adc-live-dot"></span> Live</span>
      <a href="{{ url('/leaveRequests') }}" class="adc-chip adc-chip-accent"><i class="bx bx-check-shield"></i> Approvals</a>
    </div>
  </div>

  {{-- KPI cards --}}
  <div class="row g-3 mb-2">
    @php
      $kpis = [
        ['label'=>'Team Size','value'=>number_format($teamSize),'icon'=>'bx-group','tone'=>'primary','sub'=>'direct reports','url'=>url('/organizationHierarchy')],
        ['label'=>'Present Today','value'=>$attPct.'%','icon'=>'bx-user-check','tone'=>'success','sub'=>$present.' in','url'=>url('/attendance'),'live'=>'present_pct'],
        ['label'=>'On Leave','value'=>number_format($onLeave),'icon'=>'bx-calendar-event','tone'=>'warning','sub'=>'today','url'=>url('/leaveRequests'),'live'=>'on_leave'],
        ['label'=>'Absent','value'=>number_format($absent),'icon'=>'bx-user-x','tone'=>'danger','sub'=>'not in','url'=>url('/attendance'),'live'=>'absent'],
        ['label'=>'Pending Approvals','value'=>number_format($teamApprovals),'icon'=>'bx-check-shield','tone'=>'info','sub'=>'your queue','url'=>url('/leaveRequests'),'live'=>'approvals'],
        ['label'=>'Target Achievement','value'=>($sm['achievePct'] ?? 0).'%','icon'=>'bx-target-lock','tone'=>'primary','sub'=>'team · this FY','url'=>url('/sales-visits/kingo-bingo')],
      ];
    @endphp
    @foreach($kpis as $k)
      <div class="col-6 col-md-4 col-xxl-2">
        <a href="{{ $k['url'] }}" class="adc-kpi adc-tone-{{ $k['tone'] }} h-100 d-block">
          <div class="adc-kpi-top"><span class="adc-kpi-icon"><i class="bx {{ $k['icon'] }}"></i></span><i class="bx bx-chevron-right adc-kpi-go"></i></div>
          <div class="adc-kpi-value" @if(!empty($k['live'])) data-live="{{ $k['live'] }}" @endif>{{ $k['value'] }}</div>
          <div class="adc-kpi-label">{{ $k['label'] }}</div>
          <div class="adc-kpi-sub">{{ $k['sub'] }}</div>
        </a>
      </div>
    @endforeach
  </div>

  @php
    $fmtL = function($l){ $l=(float)$l; return $l>=100 ? '₹'.number_format($l/100,2).' Cr' : '₹'.number_format($l,1).' L'; };
    $funnel = $sm['funnel'] ?? [];
    $funnelStages = array_keys($funnel);
    $funnelCounts = array_values(array_map(fn($f)=>(int)$f['n'], $funnel));
    $perfTop = array_slice($sm['perf'] ?? [], 0, 7);
    $perfNames = array_values(array_map(fn($p)=>$p['name'], $perfTop));
    $perfPcts = array_values(array_map(fn($p)=>(int)$p['pct'], $perfTop));
    // Three views of the same reps: against forecast, against what is still
    // pending, and the total book. Targets are set for almost nobody, so a
    // target-based bar read 0% for a team that was actually selling.
    $perfSales    = array_values(array_map(fn($p)=>round((float)($p['sold'] ?? 0),1), $perfTop));
    $perfForecast = array_values(array_map(fn($p)=>round((float)($p['forecast'] ?? 0),1), $perfTop));
    $perfPending  = array_values(array_map(fn($p)=>round((float)($p['pending'] ?? 0),1), $perfTop));
    $perfPctPend  = array_values(array_map(fn($p)=>(int)($p['pctPend'] ?? 0), $perfTop));
    $goalLabels = array_values(array_map(fn($g)=>$g['label'], $sm['goals'] ?? []));
    $goalTargets = array_values(array_map(fn($g)=>(float)$g['target'], $sm['goals'] ?? []));
    $goalAchieved = array_values(array_map(fn($g)=>(float)$g['achieved'], $sm['goals'] ?? []));
  @endphp

  {{-- ===== SALES COMMAND ROW ===== --}}
  <div class="row g-4 mt-0">
    <div class="col-xl-5">
      <div class="adc-card h-100">
        <div class="adc-card-head"><div><h6 class="adc-card-title">Sales Pipeline Summary</h6><span class="adc-card-sub">Active deals · your team</span></div><a href="{{ url('/sales-visits/sales-pipeline') }}" class="adc-link">Open</a></div>
        <div class="adc-kpi-value" style="font-size:1.7rem;line-height:1.1">{{ $fmtL($sm['pipelineValueL'] ?? 0) }}</div>
        <div class="adc-kpi-sub mb-2">Total pipeline · {{ $sm['dealsInProgress'] ?? 0 }} active deals · exp. close {{ $fmtL($sm['expectedClosingL'] ?? 0) }}</div>
        <div id="adcFunnel"></div>
      </div>
    </div>
    <div class="col-xl-4">
      <div class="adc-card h-100">
        <div class="adc-card-head"><div><h6 class="adc-card-title">Top Performers</h6><span class="adc-card-sub">This FY · by sales</span></div><a href="{{ url('/sales-visits/sales-pipeline') }}" class="adc-link">All</a></div>
        @if(empty($sm['topPerformers']))
          <div class="adc-empty adc-empty-sm"><i class="bx bx-trophy"></i><span>No sales recorded yet.</span></div>
        @else
          <ul class="adc-appr">
            @foreach($sm['topPerformers'] as $i=>$p)
              <li>
                <span class="sm-rank sm-rank-{{ $i+1 }}">{{ $i+1 }}</span>
                <div class="adc-appr-body"><div class="adc-appr-name">{{ $p['name'] }}</div><div class="adc-appr-meta">{{ $fmtL($p['sold']) }} · target {{ $fmtL($p['target']) }}</div></div>
                <span class="adc-badge adc-tone-{{ $p['pct']>=90?'success':($p['pct']>=60?'warning':'danger') }}">{{ $p['pct'] }}%</span>
              </li>
            @endforeach
          </ul>
        @endif
      </div>
    </div>
    <div class="col-xl-3">
      <div class="adc-card h-100">
        <div class="adc-card-head"><div><h6 class="adc-card-title">Forecast Summary</h6><span class="adc-card-sub">Reps vs forecast</span></div></div>
        <div id="adcTargetDonut"></div>
        <ul class="sm-legend mt-2">
          <li><span class="dot" style="background:#16a34a"></span> On Track <span class="ms-auto fw-bold">{{ $sm['onTrack'] ?? 0 }}</span></li>
          <li><span class="dot" style="background:#d97706"></span> At Risk <span class="ms-auto fw-bold">{{ $sm['atRisk'] ?? 0 }}</span></li>
          <li><span class="dot" style="background:#dc2626"></span> Off Track <span class="ms-auto fw-bold">{{ $sm['offTrack'] ?? 0 }}</span></li>
        </ul>
      </div>
    </div>
  </div>

  <style>
    /* Segmented tabs, using the portal theme's teal rather than a new palette. */
    .adc-perf-tab{border:1px solid var(--hitech-border);background:#fff;color:#64748b;
                  font-size:.72rem;font-weight:700;padding:.25rem .6rem;}
    .adc-perf-tab.active{background:var(--hitech-primary);border-color:var(--hitech-primary);color:#fff;}
    .adc-perf-tab:hover:not(.active){background:var(--hitech-bg-soft);color:var(--hitech-primary);}
  </style>
  {{-- ===== PERFORMANCE + GOALS ===== --}}
  <div class="row g-4 mt-0">
    <div class="col-xl-6">
      <div class="adc-card h-100">
        <div class="adc-card-head">
          <div>
            <h6 class="adc-card-title">Team Performance</h6>
            <span class="adc-card-sub" id="adcPerfSub">Sales vs Forecast · this FY</span>
          </div>
          <div class="btn-group btn-group-sm" role="group" aria-label="Which comparison to show">
            <button type="button" class="btn btn-sm adc-perf-tab active" data-mode="fcst">Sales vs Forecast</button>
            <button type="button" class="btn btn-sm adc-perf-tab" data-mode="pend">Sales vs Pending</button>
            <button type="button" class="btn btn-sm adc-perf-tab" data-mode="book">Total Book</button>
          </div>
        </div>
        {{-- Headline figures for whichever tab is showing. --}}
        <div class="d-flex flex-wrap gap-4 px-1 pb-2" id="adcPerfTotals">
          <div><div class="adc-card-sub">Sales</div><div class="fw-bold" style="font-size:1.05rem;color:#0f172a;" id="adcTotA">{{ $fmtL($sm['achievedL'] ?? 0) }}</div></div>
          <div><div class="adc-card-sub" id="adcTotBLabel">Forecast</div><div class="fw-bold" style="font-size:1.05rem;color:#0f172a;" id="adcTotB">{{ $fmtL($sm['forecastL'] ?? 0) }}</div></div>
          <div><div class="adc-card-sub" id="adcTotCLabel">Achieved</div><div class="fw-bold" style="font-size:1.05rem;color:#0d7377;" id="adcTotC">{{ (int)($sm['achievePct'] ?? 0) }}%</div></div>
        </div>
        <div id="adcPerf"></div>
      </div>
    </div>
    <div class="col-xl-6">
      <div class="adc-card h-100">
        <div class="adc-card-head"><div><h6 class="adc-card-title">Goals vs Achievement</h6><span class="adc-card-sub">Forecast vs achieved (Lakhs / count)</span></div></div>
        <div id="adcGoals"></div>
      </div>
    </div>
  </div>

  {{-- ===== ACTIVITY + SNAPSHOT + ALERTS ===== --}}
  <div class="row g-4 mt-0">
    <div class="col-xl-4">
      <div class="adc-card h-100">
        <div class="adc-card-head"><div><h6 class="adc-card-title">Activity Overview</h6><span class="adc-card-sub">This month</span></div></div>
        <ul class="sm-stat">
          @foreach(($sm['activity'] ?? []) as $a)
            <li><span class="adc-feed-dot adc-tone-info"><i class="bx {{ $a['icon'] }}"></i></span><span class="sm-stat-label">{{ $a['label'] }}</span><b class="ms-auto">{{ $a['value'] }}</b></li>
          @endforeach
        </ul>
      </div>
    </div>
    <div class="col-xl-5">
      <div class="adc-card h-100">
        <div class="adc-card-head"><div><h6 class="adc-card-title">Team Members</h6><span class="adc-card-sub">Status today · sales vs forecast</span></div><a href="{{ url('/organizationHierarchy') }}" class="adc-link">Org</a></div>
        @if(empty($sm['snapshot']))
          <div class="adc-empty adc-empty-sm"><i class="bx bx-group"></i><span>No direct reports.</span></div>
        @else
          <ul class="adc-appr adc-rail-scroll" style="max-height:320px">
            @foreach($sm['snapshot'] as $s)
              <li>
                <img class="adc-appr-av" src="{{ $s['pic'] ?: 'https://ui-avatars.com/api/?name='.urlencode($s['name']).'&background=2563eb&color=fff' }}" alt="">
                <div class="adc-appr-body"><div class="adc-appr-name">{{ $s['name'] }}</div><div class="adc-appr-meta">{{ $s['desig'] }}</div></div>
                <span class="adc-badge adc-tone-{{ $s['status']==='present'?'success':($s['status']==='leave'?'warning':'danger') }}">{{ ucfirst($s['status']) }}</span>
                <span class="adc-badge adc-tone-{{ $s['pct']>=90?'success':($s['pct']>=60?'warning':'danger') }} ms-1">{{ $s['pct'] }}%</span>
              </li>
            @endforeach
          </ul>
        @endif
      </div>
    </div>
    <div class="col-xl-3">
      <div class="adc-card h-100">
        <div class="adc-card-head"><div><h6 class="adc-card-title">Team Alerts</h6></div></div>
        @if(empty($sm['alerts']))
          <div class="adc-empty adc-empty-sm"><i class="bx bx-check-circle text-success"></i><span>No alerts.</span></div>
        @else
          <ul class="adc-feed">
            @foreach($sm['alerts'] as $al)
              <li><span class="adc-feed-dot adc-tone-{{ $al['tone'] }}"><i class="bx {{ $al['icon'] }}"></i></span><div class="adc-feed-body"><div class="adc-feed-title">{{ $al['title'] }}</div><div class="adc-feed-meta">{{ $al['sub'] }}</div></div></li>
            @endforeach
          </ul>
        @endif
      </div>
    </div>
  </div>

  <div class="row g-4 mt-0">
    {{-- MAIN --}}
    <div class="col-xxl-9 col-lg-8">

      <div class="row g-4">
        <div class="col-xl-8">
          <div class="adc-card h-100">
            <div class="adc-card-head"><div><h6 class="adc-card-title">Team Deployment</h6><span class="adc-card-sub">Everyone's status today</span></div><a href="{{ url('/attendance') }}" class="adc-link">Attendance</a></div>
            @if($deploy->isEmpty())
              <div class="adc-empty adc-empty-sm"><i class="bx bx-group"></i><span>No direct reports found.</span></div>
            @else
              <ul class="adc-appr adc-rail-scroll" style="max-height:360px">
                @foreach($deploy as $m)
                  @php
                    $st = $m->deploy_status ?? 'absent';
                    $stTone = $st==='present' ? 'success' : ($st==='leave' ? 'warning' : 'danger');
                    $stLabel = $st==='present' ? 'Present' : ($st==='leave' ? 'On Leave' : 'Absent');
                    $u = $m->user;
                  @endphp
                  <li>
                    <img class="adc-appr-av" src="https://ui-avatars.com/api/?name={{ urlencode($u->name) }}&background=0d9488&color=fff" alt="">
                    <div class="adc-appr-body"><div class="adc-appr-name">{{ $u->name }}</div><div class="adc-appr-meta">{{ optional($u->designation)->name ?? 'Team Member' }}</div></div>
                    <span class="adc-badge adc-tone-{{ $stTone }}">{{ $stLabel }}</span>
                  </li>
                @endforeach
              </ul>
            @endif
          </div>
        </div>
        <div class="col-xl-4">
          <div class="adc-card h-100">
            <div class="adc-card-head"><div><h6 class="adc-card-title">Team Status</h6><span class="adc-card-sub">Present · leave · absent</span></div></div>
            <div id="adcTeamStatus"></div>
          </div>
        </div>
      </div>

      {{-- Team attendance trend + upcoming leaves --}}
      <div class="row g-4 mt-0">
        <div class="col-xl-8">
          <div class="adc-card h-100">
            <div class="adc-card-head"><div><h6 class="adc-card-title">Team Attendance</h6><span class="adc-card-sub">Present % · last 7 days</span></div><a href="{{ url('/attendance') }}" class="adc-link">View</a></div>
            <div id="adcTeamAtt"></div>
          </div>
        </div>
        <div class="col-xl-4">
          <div class="adc-card h-100">
            <div class="adc-card-head"><div><h6 class="adc-card-title">Upcoming Team Leaves</h6><span class="adc-card-sub">Approved · ahead</span></div><a href="{{ url('/leaveRequests') }}" class="adc-link">All</a></div>
            @if(collect($teamUpcomingLeaves ?? [])->isEmpty())
              <div class="adc-empty adc-empty-sm"><i class="bx bx-calendar-check"></i><span>No upcoming team leaves.</span></div>
            @else
              <ul class="adc-appr adc-rail-scroll" style="max-height:260px">
                @foreach($teamUpcomingLeaves as $lr)
                  <li>
                    <img class="adc-appr-av" src="https://ui-avatars.com/api/?name={{ urlencode(optional($lr->user)->name ?? 'U') }}&background=d97706&color=fff" alt="">
                    <div class="adc-appr-body"><div class="adc-appr-name">{{ optional($lr->user)->name ?? 'Member' }}</div><div class="adc-appr-meta">{{ optional($lr->leaveType)->name ?? 'Leave' }}</div></div>
                    <span class="adc-badge adc-tone-warning">{{ \Carbon\Carbon::parse($lr->from_date)->format('d M') }}</span>
                  </li>
                @endforeach
              </ul>
            @endif
          </div>
        </div>
      </div>

      {{-- Manager module tiles --}}
      <div class="adc-card mt-4">
        <div class="adc-card-head"><div><h6 class="adc-card-title">Manager Tools</h6><span class="adc-card-sub">Quick access</span></div></div>
        <div class="row g-3">
          @php
            $tiles = [
              ['name'=>'Team','icon'=>'bx-group','value'=>number_format($teamSize),'sub'=>'Reports','url'=>url('/organizationHierarchy'),'tone'=>'primary'],
              ['name'=>'Approvals','icon'=>'bx-check-shield','value'=>$teamApprovals,'sub'=>'Pending','url'=>url('/leaveRequests'),'tone'=>'warning'],
              ['name'=>'Attendance','icon'=>'bx-time-five','value'=>$attPct.'%','sub'=>'Today','url'=>url('/attendance'),'tone'=>'success'],
              ['name'=>'KRA','icon'=>'bx-task','value'=>'','sub'=>'Manage','url'=>url('/task'),'tone'=>'info'],
              ['name'=>'Training','icon'=>'bx-book','value'=>'','sub'=>'Manage','url'=>url('/training/manage'),'tone'=>'primary'],
              ['name'=>'My Leaves','icon'=>'bx-calendar-event','value'=>'','sub'=>'Apply','url'=>url('/user/leaves'),'tone'=>'warning'],
              ['name'=>'My Payroll','icon'=>'bx-money','value'=>'','sub'=>'View','url'=>url('/user/payroll'),'tone'=>'success'],
              ['name'=>'Policies','icon'=>'bx-book-content','value'=>'','sub'=>'Read','url'=>url('/my-policies'),'tone'=>'info'],
            ];
          @endphp
          @foreach($tiles as $m)
            <div class="col-6 col-md-4 col-xl-3">
              <a href="{{ $m['url'] }}" class="adc-tile adc-tone-{{ $m['tone'] }}">
                <span class="adc-tile-icon"><i class="bx {{ $m['icon'] }}"></i></span>
                <div class="adc-tile-body">
                  <div class="adc-tile-value">{{ $m['value'] !== '' ? $m['value'] : $m['name'] }}</div>
                  <div class="adc-tile-name">{{ $m['value'] !== '' ? $m['name'] : '' }}</div>
                  <div class="adc-tile-sub">{{ $m['sub'] }}</div>
                </div>
              </a>
            </div>
          @endforeach
        </div>
      </div>

      {{-- Team on leave today --}}
      <div class="adc-card mt-4">
        <div class="adc-card-head"><div><h6 class="adc-card-title">Out of Office Today</h6><span class="adc-card-sub">Team members on approved leave</span></div><a href="{{ url('/leaveRequests') }}" class="adc-link">View</a></div>
        @if(collect($teamOutToday ?? [])->isEmpty())
          <div class="adc-empty adc-empty-sm"><i class="bx bx-check-circle text-success"></i><span>Everyone's in today.</span></div>
        @else
          <ul class="adc-feed">
            @foreach(collect($teamOutToday)->take(6) as $lr)
              <li>
                <span class="adc-feed-dot adc-tone-warning"><i class="bx bx-calendar-x"></i></span>
                <div class="adc-feed-body">
                  <div class="adc-feed-title">{{ optional($lr->user)->name ?? 'Member' }} — {{ optional($lr->leaveType)->name ?? 'Leave' }}</div>
                  <div class="adc-feed-meta">{{ optional($lr->from_date)->format('d M') }} → {{ optional($lr->to_date)->format('d M') }}</div>
                </div>
              </li>
            @endforeach
          </ul>
        @endif
      </div>

    </div>

    {{-- RAIL --}}
    <div class="col-xxl-3 col-lg-4">

      {{-- Team pending approvals --}}
      <div class="adc-card mb-4">
        <div class="adc-card-head"><div><h6 class="adc-card-title">Approvals Queue</h6><span class="adc-card-sub"><b data-approval-count>{{ collect($teamPendingLeaveRequests ?? [])->count() }}</b> team leave requests</span></div><a href="{{ url('/leaveRequests') }}" class="adc-link">All</a></div>
        @if(collect($teamPendingLeaveRequests ?? [])->isEmpty())
          <div class="adc-empty adc-empty-sm"><i class="bx bx-check-circle text-success"></i><span>All caught up!</span></div>
        @else
          <ul class="adc-appr adc-rail-scroll">
            @foreach(collect($teamPendingLeaveRequests)->take(10) as $r)
              <li data-approve data-type="Leave" data-id="{{ $r->id }}">
                <img class="adc-appr-av" src="https://ui-avatars.com/api/?name={{ urlencode(optional($r->user)->name ?? 'U') }}&background=2563eb&color=fff" alt="">
                <div class="adc-appr-body"><div class="adc-appr-name">{{ optional($r->user)->name ?? 'N/A' }}</div><div class="adc-appr-meta">{{ optional($r->leaveType)->name ?? 'Leave' }}</div></div>
                <span class="adc-act"><button class="adc-ok" data-act="approve" title="Approve"><i class="bx bx-check"></i></button><button class="adc-no" data-act="reject" title="Reject"><i class="bx bx-x"></i></button></span>
              </li>
            @endforeach
          </ul>
        @endif
      </div>

      {{-- Celebrations --}}
      <div class="adc-card mb-4">
        <div class="adc-card-head"><div><h6 class="adc-card-title"><i class="bx bx-party text-warning"></i> Celebrations</h6></div></div>
        @if($todayBdays->isEmpty() && $todayAnnis->isEmpty())
          <div class="adc-empty adc-empty-sm"><i class="bx bx-calendar-heart"></i><span>Nothing today.</span></div>
        @else
          <ul class="adc-appr">
            @foreach($todayBdays->take(3) as $u)
              <li><img class="adc-appr-av" src="https://ui-avatars.com/api/?name={{ urlencode($u->name) }}&background=ec4899&color=fff" alt=""><div class="adc-appr-body"><div class="adc-appr-name">{{ $u->name }}</div><div class="adc-appr-meta">Birthday 🎂</div></div></li>
            @endforeach
            @foreach($todayAnnis->take(3) as $u)
              <li><img class="adc-appr-av" src="https://ui-avatars.com/api/?name={{ urlencode($u->name) }}&background=0d9488&color=fff" alt=""><div class="adc-appr-body"><div class="adc-appr-name">{{ $u->name }}</div><div class="adc-appr-meta">Anniversary 🎉</div></div></li>
            @endforeach
          </ul>
        @endif
      </div>

      {{-- Quick Actions --}}
      <div class="adc-card">
        <div class="adc-card-head"><div><h6 class="adc-card-title">Quick Actions</h6></div></div>
        <div class="adc-qa">
          <a href="{{ url('/leaveRequests') }}" class="adc-qa-btn"><i class="bx bx-check-shield"></i><span>Approvals</span></a>
          <a href="{{ url('/attendance') }}" class="adc-qa-btn"><i class="bx bx-time-five"></i><span>Attendance</span></a>
          <a href="{{ url('/task') }}" class="adc-qa-btn"><i class="bx bx-task"></i><span>KRA</span></a>
          <a href="{{ url('/user/leaves') }}" class="adc-qa-btn"><i class="bx bx-calendar-event"></i><span>My Leave</span></a>
          <a href="{{ url('/user/payroll') }}" class="adc-qa-btn"><i class="bx bx-money"></i><span>Payslip</span></a>
          <a href="{{ url('/organizationHierarchy') }}" class="adc-qa-btn"><i class="bx bx-sitemap"></i><span>Org Chart</span></a>
        </div>
      </div>

    </div>
  </div>
</div>

@include('tenant.users.dashboard._adc-styles')
@include('tenant.users.dashboard._adc-live')
<style>
  .sm-rank{width:26px;height:26px;flex:0 0 26px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.8rem;color:#fff;background:#94a3b8;margin-right:2px}
  .sm-rank-1{background:linear-gradient(135deg,#f59e0b,#d97706)}.sm-rank-2{background:linear-gradient(135deg,#94a3b8,#64748b)}.sm-rank-3{background:linear-gradient(135deg,#f97316,#ea580c)}
  .sm-legend{list-style:none;margin:0;padding:0;font-size:.82rem}
  .sm-legend li{display:flex;align-items:center;gap:8px;padding:5px 0;color:#475569;border-top:1px solid #f1f5f9}
  .sm-legend .dot{width:10px;height:10px;border-radius:50%;display:inline-block}
  .sm-stat{list-style:none;margin:0;padding:0}
  .sm-stat li{display:flex;align-items:center;gap:10px;padding:9px 0;border-top:1px solid #f1f5f9;font-size:.88rem;color:#334155}
  .sm-stat li:first-child{border-top:0}
  .sm-stat b{font-size:1.05rem;color:#0f172a}
  .sm-stat-label{color:#475569}
</style>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const empty = (el, icon, msg) => { if (el) el.innerHTML = '<div class="adc-empty"><i class="bx '+icon+'"></i><span>'+msg+'</span></div>'; };
  async function getApex() {
    for (let i = 0; i < 200 && !window.ApexCharts; i++) { await new Promise(r => setTimeout(r, 25)); }
    if (window.ApexCharts) return window.ApexCharts;
    const s = [...document.querySelectorAll('script[src]')].find(x => /apex-charts.*\.js/i.test(x.src));
    if (s) { try { const m = await import(s.src); if (m && (m.default || m.ApexCharts)) window.ApexCharts = m.default || m.ApexCharts; } catch (e) {} }
    return window.ApexCharts || null;
  }
  const mk = (AC, sel, opts) => { const el=document.querySelector(sel); if(!el)return; try{ new AC(el,opts).render(); }catch(e){ console.error(sel,e); empty(el,'bx-error-circle','Chart failed'); } };
  getApex().then(AC => {
    // Team status donut
    const el = document.querySelector('#adcTeamStatus');
    const data = [{{ $present }}, {{ $onLeave }}, {{ $absent }}];
    if (el) {
      if (!AC) empty(el,'bx-bar-chart-alt-2','Charts unavailable');
      else if (data.reduce((a,b)=>a+b,0) === 0) empty(el,'bx-doughnut-chart','No team status today');
      else mk(AC,'#adcTeamStatus',{
        series:data, labels:['Present','On Leave','Absent'],
        chart:{type:'donut',height:290,fontFamily:'Public Sans'}, colors:['#16a34a','#d97706','#dc2626'],
        legend:{position:'bottom',fontSize:'12px'}, dataLabels:{enabled:true,formatter:v=>Math.round(v)+'%'},
        plotOptions:{pie:{donut:{size:'68%',labels:{show:true,total:{show:true,label:'Team',fontSize:'13px',color:'#94a3b8',formatter:()=>{{ $teamSize }}}}}}},
        stroke:{width:2,colors:['#fff']}
      });
    }
    // Team attendance 7-day area
    const ael = document.querySelector('#adcTeamAtt');
    const att = {!! json_encode(array_column($teamAtt7 ?? [], 'pct'), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!};
    const attLabels = {!! json_encode(array_column($teamAtt7 ?? [], 'label'), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!};
    if (ael) {
      if (!AC) empty(ael,'bx-bar-chart-alt-2','Charts unavailable');
      else if (!att.length || att.every(v=>v===0)) empty(ael,'bx-line-chart','No team attendance in last 7 days');
      else mk(AC,'#adcTeamAtt',{
        series:[{name:'Present %',data:att}],
        chart:{type:'area',height:290,toolbar:{show:false},fontFamily:'Public Sans'}, colors:['#0d9488'],
        stroke:{curve:'smooth',width:3}, fill:{type:'gradient',gradient:{shadeIntensity:1,opacityFrom:0.35,opacityTo:0.05}},
        dataLabels:{enabled:true,formatter:v=>v+'%',style:{fontSize:'10px',colors:['#0f766e']},background:{enabled:false}},
        markers:{size:4,colors:['#fff'],strokeColors:'#0d9488',strokeWidth:2},
        xaxis:{categories:attLabels,labels:{style:{colors:'#94a3b8'}},axisBorder:{show:false},axisTicks:{show:false}},
        yaxis:{max:100,labels:{style:{colors:'#94a3b8'},formatter:v=>Math.round(v)+'%'}}, grid:{borderColor:'#f1f5f9',strokeDashArray:4}
      });
    }
    // ---- Sales pipeline funnel ----
    (function(){
      const el=document.querySelector('#adcFunnel'); if(!el) return;
      const stages=@json($funnelStages); const counts=@json($funnelCounts);
      if(!AC){ empty(el,'bx-bar-chart-alt-2','Charts unavailable'); return; }
      if(!counts.length || counts.every(v=>v===0)){ empty(el,'bx-filter','No active pipeline'); return; }
      mk(AC,'#adcFunnel',{
        series:[{name:'Deals',data:counts}],
        chart:{type:'bar',height:250,toolbar:{show:false},fontFamily:'Public Sans'},
        plotOptions:{bar:{horizontal:true,borderRadius:6,barHeight:'62%',distributed:true}},
        colors:['#60a5fa','#38bdf8','#22d3ee','#2dd4bf','#0d9488'],
        dataLabels:{enabled:true,style:{colors:['#0f172a'],fontWeight:700}},
        xaxis:{categories:stages,labels:{style:{colors:'#94a3b8'}}},
        yaxis:{labels:{style:{colors:'#475569',fontSize:'12px'}}}, legend:{show:false}, grid:{borderColor:'#f1f5f9'}
      });
    })();
    // ---- Target summary donut ----
    (function(){
      const el=document.querySelector('#adcTargetDonut'); if(!el) return;
      const d=[{{ (int)($sm['onTrack'] ?? 0) }},{{ (int)($sm['atRisk'] ?? 0) }},{{ (int)($sm['offTrack'] ?? 0) }}];
      if(!AC){ empty(el,'bx-doughnut-chart','Charts unavailable'); return; }
      if(d.reduce((a,b)=>a+b,0)===0){ empty(el,'bx-doughnut-chart','No reps'); return; }
      mk(AC,'#adcTargetDonut',{
        series:d, labels:['On Track','At Risk','Off Track'],
        chart:{type:'donut',height:200,fontFamily:'Public Sans'}, colors:['#16a34a','#d97706','#dc2626'],
        legend:{show:false}, dataLabels:{enabled:false},
        plotOptions:{pie:{donut:{size:'70%',labels:{show:true,total:{show:true,label:'Reps',fontSize:'12px',color:'#94a3b8'}}}}},
        stroke:{width:2,colors:['#fff']}
      });
    })();
    // ---- Team performance: three views of the same reps ----
    (function(){
      const el=document.querySelector('#adcPerf'); if(!el) return;
      const names = @json($perfNames);
      const sales = @json($perfSales);
      const fcst  = @json($perfForecast);
      const pend  = @json($perfPending);
      const pctF  = @json($perfPcts);      // sales / forecast
      const pctP  = @json($perfPctPend);   // sales / (sales + pending)
      const totals = {
        sales:   @json(round((float)($sm['achievedL'] ?? 0),1)),
        fcst:    @json(round((float)($sm['forecastL'] ?? 0),1)),
        pend:    @json(round((float)($sm['pendingL']  ?? 0),1)),
        pctF:    @json((int)($sm['achievePct'] ?? 0)),
        pctP:    @json((int)($sm['pendingPct'] ?? 0)),
        book:    @json(round((float)($sm['bookL'] ?? 0),1)),
      };
      if(!AC){ empty(el,'bx-bar-chart','Charts unavailable'); return; }
      if(!names.length){ empty(el,'bx-bar-chart','No performance data'); return; }

      const fmtL = v => v>=100 ? '₹'+(v/100).toFixed(2)+' Cr' : '₹'+Number(v).toFixed(1)+' L';
      let chart=null;

      const MODES = {
        fcst: { sub:'Sales vs Forecast · this FY',
                series:[{name:'Sales',data:sales},{name:'Forecast',data:fcst}],
                colors:['#0d7377','#94a3b8'],
                totals:{ bLabel:'Forecast', b:fmtL(totals.fcst), cLabel:'Achieved', c:totals.pctF+'%' } },
        pend: { sub:'Sales vs Pending · this FY',
                series:[{name:'Sales',data:sales},{name:'Pending',data:pend}],
                colors:['#0d7377','#f5a623'],
                totals:{ bLabel:'Pending', b:fmtL(totals.pend), cLabel:'Realised', c:totals.pctP+'%' } },
        // Third view: the whole book each rep is carrying — what is already
        // sold plus what is still to come. Answers "who is carrying the most".
        book: { sub:'Total book — sales plus pending · this FY',
                series:[{name:'Sales',data:sales},{name:'Pending',data:pend}],
                colors:['#0d7377','#f5a623'], stacked:true,
                totals:{ bLabel:'Pending', b:fmtL(totals.pend), cLabel:'Total book', c:fmtL(totals.book) } },
      };

      function draw(mode){
        const m = MODES[mode];
        document.getElementById('adcPerfSub').textContent = m.sub;
        document.getElementById('adcTotB').textContent      = m.totals.b;
        document.getElementById('adcTotBLabel').textContent = m.totals.bLabel;
        document.getElementById('adcTotC').textContent      = m.totals.c;
        document.getElementById('adcTotCLabel').textContent = m.totals.cLabel;
        // Built directly, not through mk(): mk() does not return the instance,
        // so destroy() could never run and every tab switch would leak a chart.
        if(chart){ try{ chart.destroy(); }catch(e){} chart=null; }
        el.innerHTML='';
        chart = new AC(el,{
          series:m.series,
          chart:{type:'bar',height:280,toolbar:{show:false},fontFamily:'Public Sans',stacked:!!m.stacked},
          plotOptions:{bar:{horizontal:true,borderRadius:4,barHeight:'70%'}},
          colors:m.colors,
          dataLabels:{enabled:false},
          xaxis:{categories:names,labels:{style:{colors:'#94a3b8'},formatter:v=>Number(v).toFixed(0)}},
          yaxis:{labels:{style:{colors:'#475569',fontSize:'12px'}}},
          legend:{show:true,position:'top',horizontalAlign:'right',fontSize:'11px'},
          grid:{borderColor:'#f1f5f9'},
          tooltip:{y:{formatter:v=>fmtL(v)}}
        });
        try{ chart.render(); }
        catch(e){ console.error('#adcPerf',e); empty(el,'bx-error-circle','Chart failed'); }
      }

      document.querySelectorAll('.adc-perf-tab').forEach(function(b){
        b.addEventListener('click', function(){
          document.querySelectorAll('.adc-perf-tab').forEach(x=>x.classList.remove('active'));
          b.classList.add('active');
          draw(b.dataset.mode);
        });
      });
      draw('fcst');
    })();
    // ---- Goals vs achievement ----
    (function(){
      const el=document.querySelector('#adcGoals'); if(!el) return;
      const labels=@json($goalLabels);
      const tgt=@json($goalTargets);
      const ach=@json($goalAchieved);
      if(!AC){ empty(el,'bx-bar-chart','Charts unavailable'); return; }
      if(!labels.length){ empty(el,'bx-bar-chart','No goals'); return; }
      mk(AC,'#adcGoals',{
        series:[{name:'Target',data:tgt},{name:'Achieved',data:ach}],
        chart:{type:'bar',height:280,toolbar:{show:false},fontFamily:'Public Sans'},
        plotOptions:{bar:{borderRadius:5,columnWidth:'58%'}}, colors:['#cbd5e1','#0d9488'],
        dataLabels:{enabled:false}, xaxis:{categories:labels,labels:{style:{colors:'#94a3b8',fontSize:'11px'}}},
        yaxis:{labels:{style:{colors:'#94a3b8'}}}, legend:{position:'top',fontSize:'12px'}, grid:{borderColor:'#f1f5f9'}
      });
    })();
  });
});
</script>
@endsection
