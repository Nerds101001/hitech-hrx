@extends('layouts/layoutMaster')

@section('title', __('HR Command Center'))

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
  $firstName = auth()->user()->first_name ?? (explode(' ', auth()->user()->name ?? 'HR')[0]);
  $present = (int) ($presentUsersCount ?? 0);
  $activeCnt = (int) ($active ?? $totalUser ?? 0);
  $onLeave = (int) ($onLeaveUsersCount ?? 0);
  $absent = (int) ($absentCount ?? max(0, $activeCnt - $present - $onLeave));
  $attPct = $activeCnt > 0 ? round($present / $activeCnt * 100, 1) : 0;
  $approvalsTotal = (int)($pendingLeaveRequests ?? 0) + (int)($pendingExpenseRequests ?? 0) + (int)($pendingDocumentRequests ?? 0) + (int)($pendingLoanRequests ?? 0);
  $todayBdays = collect($upcomingBirthdays ?? [])->filter(fn($u) => $u->is_today ?? false);
  $todayAnnis = collect($upcomingAnniversaries ?? [])->filter(fn($u) => $u->is_today ?? false);
@endphp

@section('content')
<div class="adc">

  {{-- Hero --}}
  <div class="adc-hero d-flex flex-wrap align-items-center justify-content-between mb-4">
    <div>
      <h4 class="adc-hero-title mb-1">{{ $greet }}, {{ $firstName }} <span class="adc-wave">👋</span></h4>
      <p class="adc-hero-sub mb-0">Your people operations at a glance — {{ now()->format('l, d M Y') }}.</p>
    </div>
    <div class="d-flex align-items-center gap-2 mt-2 mt-md-0">
      <span class="adc-chip"><i class="bx bx-id-card"></i> HR Console</span>
      <span class="adc-live" title="Live — auto-refreshing"><span class="adc-live-dot"></span> Live</span>
      <a href="{{ url('/employees') }}" class="adc-chip adc-chip-accent"><i class="bx bx-group"></i> Employees</a>
    </div>
  </div>

  {{-- KPI cards --}}
  <div class="row g-3 mb-2">
    @php
      $kpis = [
        ['label'=>'Total Employees','value'=>number_format($activeCnt),'icon'=>'bx-group','tone'=>'primary','trend'=>$trends['totalStaff']??null,'sub'=>'active headcount','url'=>url('/employees')],
        ['label'=>'Present Today','value'=>$attPct.'%','icon'=>'bx-user-check','tone'=>'success','trend'=>$trends['present']??null,'sub'=>$present.' checked in','url'=>url('/attendance'),'live'=>'present_pct'],
        ['label'=>'On Leave','value'=>number_format($onLeave),'icon'=>'bx-calendar-event','tone'=>'warning','trend'=>null,'sub'=>'today','url'=>url('/leaveRequests'),'live'=>'on_leave'],
        ['label'=>'Absent','value'=>number_format($absent),'icon'=>'bx-user-x','tone'=>'danger','trend'=>null,'sub'=>'not checked in','url'=>url('/attendance'),'live'=>'absent'],
        ['label'=>'Open Positions','value'=>number_format($activeJobsCount ?? 0),'icon'=>'bx-briefcase','tone'=>'info','trend'=>$trends['openings']??null,'sub'=>($newApplicantsToday ?? 0).' new today','url'=>url('/job')],
        ['label'=>'Approvals','value'=>number_format($approvalsTotal),'icon'=>'bx-check-shield','tone'=>'primary','trend'=>null,'sub'=>'awaiting action','url'=>url('/leaveRequests'),'live'=>'approvals'],
      ];
    @endphp
    @foreach($kpis as $k)
      <div class="col-6 col-md-4 col-xxl-2">
        <a href="{{ $k['url'] }}" class="adc-kpi adc-tone-{{ $k['tone'] }} h-100 d-block">
          <div class="adc-kpi-top">
            <span class="adc-kpi-icon"><i class="bx {{ $k['icon'] }}"></i></span>
            @if($k['trend'])<span class="adc-kpi-trend {{ $k['trend']['isUp']?'up':'down' }}"><i class="bx {{ $k['trend']['isUp']?'bx-up-arrow-alt':'bx-down-arrow-alt' }}"></i>{{ $k['trend']['value'] }}</span>@endif
            <i class="bx bx-chevron-right adc-kpi-go"></i>
          </div>
          <div class="adc-kpi-value" @if(!empty($k['live'])) data-live="{{ $k['live'] }}" @endif>{{ $k['value'] }}</div>
          <div class="adc-kpi-label">{{ $k['label'] }}</div>
          <div class="adc-kpi-sub">{{ $k['sub'] }}</div>
        </a>
      </div>
    @endforeach
  </div>

  <div class="row g-4 mt-0">
    {{-- MAIN --}}
    <div class="col-xxl-9 col-lg-8">

      {{-- My Tasks + Today's Workforce, paired so neither half of the row
           sits empty. --}}
      <div class="row g-4">
        <div class="col-xl-6">
          @include('tenant.users.dashboard._adc-task-widget')
        </div>
        <div class="col-xl-6">
          <div class="adc-card h-100">
            <div class="adc-card-head"><div><h6 class="adc-card-title">Today's Workforce</h6><span class="adc-card-sub">Present · leave · absent</span></div><a href="{{ url('/attendance') }}" class="adc-link">View</a></div>
            <div id="adcWfStatus"></div>
          </div>
        </div>
      </div>

      <div class="row g-4 mt-1">
        <div class="col-12">
          <div class="adc-card">
            <div class="adc-card-head"><div><h6 class="adc-card-title">Workforce Overview</h6><span class="adc-card-sub">New joins vs exits · last 12 months</span></div><a href="{{ url('/employees') }}" class="adc-link">View</a></div>
            <div id="adcWorkforce"></div>
          </div>
        </div>
      </div>

      {{-- HR module tiles --}}
      <div class="adc-card mt-4">
        <div class="adc-card-head"><div><h6 class="adc-card-title">HR Modules</h6><span class="adc-card-sub">Quick access</span></div></div>
        <div class="row g-3">
          @php
            $tiles = [
              ['name'=>'Employees','icon'=>'bx-group','value'=>number_format($activeCnt),'sub'=>'Directory','url'=>url('/employees'),'tone'=>'primary'],
              ['name'=>'Recruitment','icon'=>'bx-user-plus','value'=>$activeJobsCount ?? 0,'sub'=>'Open Roles','url'=>url('/job'),'tone'=>'info'],
              ['name'=>'Attendance','icon'=>'bx-time-five','value'=>$attPct.'%','sub'=>'Today','url'=>url('/attendance'),'tone'=>'success'],
              ['name'=>'Leaves','icon'=>'bx-calendar-event','value'=>$pendingLeaveRequests ?? 0,'sub'=>'Pending','url'=>url('/leaveRequests'),'tone'=>'warning'],
              ['name'=>'Payroll','icon'=>'bx-money','value'=>'','sub'=>'Manage','url'=>url('/payroll'),'tone'=>'success'],
              ['name'=>'Probation','icon'=>'bx-check-double','value'=>($upcomingProbationEnds->count() ?? 0),'sub'=>'Ending','url'=>url('/probation/evaluations'),'tone'=>'warning'],
              ['name'=>'Disciplinary','icon'=>'bx-shield-x','value'=>'','sub'=>'Actions','url'=>url('/disciplinary-actions/warnings'),'tone'=>'danger'],
              ['name'=>'Policies','icon'=>'bx-book-content','value'=>'','sub'=>'Manage','url'=>url('/hr-policies'),'tone'=>'primary'],
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

      <div class="row g-4 mt-0">
        <div class="col-xl-5">
          <div class="adc-card h-100">
            <div class="adc-card-head"><div><h6 class="adc-card-title">Dept Headcount</h6><span class="adc-card-sub">Active by department</span></div><a href="{{ url('/organizationHierarchy') }}" class="adc-link">View</a></div>
            <div id="adcDept"></div>
          </div>
        </div>
        <div class="col-xl-7">
          <div class="adc-card h-100">
            <div class="adc-card-head"><div><h6 class="adc-card-title">Recruitment Pipeline</h6><span class="adc-card-sub">Active openings</span></div><a href="{{ url('/job') }}" class="adc-link">All Jobs</a></div>
            @if(collect($activeJobs ?? [])->isEmpty())
              <div class="adc-empty adc-empty-sm"><i class="bx bx-briefcase"></i><span>No active job openings.</span></div>
            @else
              <div class="adc-staff-scroll">
                <table class="table adc-table mb-0">
                  <tbody>
                    @foreach($activeJobs as $job)
                      <tr>
                        <td class="fw-medium">{{ $job->title }}</td>
                        <td class="text-end"><span class="adc-badge adc-tone-info">{{ $job->applications_count ?? 0 }} applicants</span></td>
                        <td class="text-end text-muted" style="width:90px">{{ optional($job->created_at)->diffForHumans() }}</td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            @endif
          </div>
        </div>
      </div>

      <div class="row g-4 mt-0">
        <div class="col-xl-7">
          <div class="adc-card h-100">
            <div class="adc-card-head"><div><h6 class="adc-card-title">Recent Applicants</h6><span class="adc-card-sub">Latest recruitment activity</span></div><a href="{{ url('/job-application') }}" class="adc-link">View</a></div>
            @if(collect($recentActivities ?? [])->isEmpty())
              <div class="adc-empty adc-empty-sm"><i class="bx bx-user-voice"></i><span>No recent applicants.</span></div>
            @else
              <ul class="adc-feed">
                @foreach(collect($recentActivities)->take(6) as $ra)
                  <li>
                    <span class="adc-feed-dot adc-tone-info"><i class="bx bx-user-plus"></i></span>
                    <div class="adc-feed-body">
                      <div class="adc-feed-title">{{ $ra->name ?? $ra->first_name ?? 'Applicant' }} — {{ optional($ra->jobs)->title ?? 'Application' }}</div>
                      <div class="adc-feed-meta">{{ optional($ra->created_at)->diffForHumans() }}</div>
                    </div>
                  </li>
                @endforeach
              </ul>
            @endif
          </div>
        </div>
        <div class="col-xl-5">
          <div class="adc-card h-100">
            <div class="adc-card-head"><div><h6 class="adc-card-title">Announcements</h6></div><a href="{{ url('/announcements') }}" class="adc-link">All</a></div>
            @if(collect($announcements ?? [])->isEmpty())
              <div class="adc-empty adc-empty-sm"><i class="bx bx-megaphone"></i><span>No announcements.</span></div>
            @else
              <ul class="adc-feed">
                @foreach(collect($announcements)->take(5) as $an)
                  <li>
                    <span class="adc-feed-dot adc-tone-warning"><i class="bx bx-broadcast"></i></span>
                    <div class="adc-feed-body">
                      <div class="adc-feed-title">{{ $an->title ?? 'Notice' }}</div>
                      <div class="adc-feed-meta">{{ optional($an->start_date ?? $an->created_at)->diffForHumans() }}</div>
                    </div>
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

    {{-- RAIL --}}
    <div class="col-xxl-3 col-lg-4">

      @include('tenant.users.dashboard._adc-announcements')


      {{-- Celebrations --}}
      <div class="adc-card mb-4">
        <div class="adc-card-head"><div><h6 class="adc-card-title"><i class="bx bx-party text-warning"></i> Celebrations</h6><span class="adc-card-sub">Today across the org</span></div></div>
        @if($todayBdays->isEmpty() && $todayAnnis->isEmpty())
          <div class="adc-empty adc-empty-sm"><i class="bx bx-calendar-heart"></i><span>Nothing today.</span></div>
        @else
          <ul class="adc-appr">
            @foreach($todayBdays->take(4) as $u)
              <li>
                <img class="adc-appr-av" src="https://ui-avatars.com/api/?name={{ urlencode($u->name) }}&background=ec4899&color=fff" alt="">
                <div class="adc-appr-body"><div class="adc-appr-name">{{ $u->name }}</div><div class="adc-appr-meta">Birthday 🎂</div></div>
                <span class="adc-badge adc-tone-danger">Wish</span>
              </li>
            @endforeach
            @foreach($todayAnnis->take(3) as $u)
              <li>
                <img class="adc-appr-av" src="https://ui-avatars.com/api/?name={{ urlencode($u->name) }}&background=0d9488&color=fff" alt="">
                <div class="adc-appr-body"><div class="adc-appr-name">{{ $u->name }}</div><div class="adc-appr-meta">Work Anniversary 🎉</div></div>
                <span class="adc-badge adc-tone-success">Wish</span>
              </li>
            @endforeach
          </ul>
        @endif
      </div>

      {{-- Today's Events --}}
      <div class="adc-card mb-4">
        <div class="adc-card-head"><div><h6 class="adc-card-title">Today's Snapshot</h6></div></div>
        <ul class="adc-events">
          @php
            $events = [
              ['icon'=>'bx-cake','label'=>'Birthdays Today','count'=>$todayBdays->count(),'tone'=>'primary'],
              ['icon'=>'bx-gift','label'=>'Anniversaries','count'=>$todayAnnis->count(),'tone'=>'info'],
              ['icon'=>'bx-time','label'=>'Probation Ending','count'=>($upcomingProbationEnds->count() ?? 0),'tone'=>'warning'],
              ['icon'=>'bx-calendar-event','label'=>'Leaves Pending','count'=>($pendingLeaveRequests ?? 0),'tone'=>'danger'],
              ['icon'=>'bx-user-plus','label'=>'New Hires (mo)','count'=>($newHiresThisMonth ?? 0),'tone'=>'success'],
            ];
          @endphp
          @foreach($events as $ev)
            <li><span class="adc-ev-icon adc-tone-{{ $ev['tone'] }}"><i class="bx {{ $ev['icon'] }}"></i></span><span class="adc-ev-label">{{ $ev['label'] }}</span><span class="adc-ev-count">{{ $ev['count'] }}</span></li>
          @endforeach
        </ul>
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
        @if(collect($upcomingProbationEnds ?? [])->isEmpty())
          <div class="adc-empty adc-empty-sm"><i class="bx bx-check-circle text-success"></i><span>None upcoming.</span></div>
        @else
          <ul class="adc-appr adc-rail-scroll">
            @foreach($upcomingProbationEnds as $u)
              @php $pd = \Carbon\Carbon::parse($u->probation_end_date); @endphp
              <li>
                <img class="adc-appr-av" src="https://ui-avatars.com/api/?name={{ urlencode($u->name) }}&background=d97706&color=fff" alt="">
                <div class="adc-appr-body"><div class="adc-appr-name">{{ $u->name }}</div><div class="adc-appr-meta">Ends {{ $pd->format('d M Y') }}</div></div>
                <span class="adc-badge adc-tone-{{ $pd->isPast()?'danger':'warning' }}">{{ $pd->diffForHumans(null, true) }}</span>
              </li>
            @endforeach
          </ul>
        @endif
      </div>

      {{-- Pending Approvals --}}
      <div class="adc-card mb-4">
        <div class="adc-card-head"><div><h6 class="adc-card-title">Pending Approvals</h6><span class="adc-card-sub"><b data-approval-count>{{ $approvalsTotal }}</b> awaiting action</span></div><a href="{{ $pendingApprovalsUrl ?? url('/leaveRequests') }}" class="adc-link">All</a></div>
        @if(collect($pendingApprovals ?? [])->isEmpty())
          <div class="adc-empty adc-empty-sm"><i class="bx bx-check-circle text-success"></i><span>All caught up!</span></div>
        @else
          <ul class="adc-appr adc-rail-scroll">
            @foreach(collect($pendingApprovals)->take(10) as $a)
              @php $tmap=['Leave'=>'info','Expense'=>'warning','Document'=>'primary','Loan'=>'danger']; $t=$tmap[$a['type']]??'primary'; @endphp
              <li data-approve data-type="{{ $a['type'] }}" data-id="{{ $a['id'] ?? '' }}">
                <img src="{{ $a['avatar'] ?? '' }}" class="adc-appr-av" onerror="this.src='https://ui-avatars.com/api/?name={{ urlencode($a['user'] ?? 'U') }}&background=0d9488&color=fff'" alt="">
                <div class="adc-appr-body"><div class="adc-appr-name">{{ $a['user'] ?? 'N/A' }} <span class="adc-badge adc-tone-{{ $t }}" style="font-size:.6rem">{{ $a['type'] }}</span></div><div class="adc-appr-meta">{{ $a['details'] ?? $a['type'] }}</div></div>
                <span class="adc-act"><button class="adc-ok" data-act="approve" title="Approve"><i class="bx bx-check"></i></button><button class="adc-no" data-act="reject" title="Reject"><i class="bx bx-x"></i></button></span>
              </li>
            @endforeach
          </ul>
        @endif
      </div>

      @include('tenant.users.dashboard._adc-important-contacts')

      {{-- Quick Actions --}}
      <div class="adc-card">
        <div class="adc-card-head"><div><h6 class="adc-card-title">Quick Actions</h6></div></div>
        <div class="adc-qa">
          <a href="{{ url('/employees') }}" class="adc-qa-btn"><i class="bx bx-user-plus"></i><span>Employees</span></a>
          <a href="{{ url('/job') }}" class="adc-qa-btn"><i class="bx bx-briefcase"></i><span>Post Job</span></a>
          <a href="{{ url('/leaveRequests') }}" class="adc-qa-btn"><i class="bx bx-check-shield"></i><span>Approvals</span></a>
          <a href="{{ url('/attendance') }}" class="adc-qa-btn"><i class="bx bx-time-five"></i><span>Attendance</span></a>
          <a href="{{ url('/payroll') }}" class="adc-qa-btn"><i class="bx bx-money"></i><span>Payroll</span></a>
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
  const axisColor = '#94a3b8', gridColor = '#f1f5f9';
  const empty = (el, icon, msg) => { if (el) el.innerHTML = '<div class="adc-empty"><i class="bx '+icon+'"></i><span>'+msg+'</span></div>'; };
  async function getApex() {
    for (let i = 0; i < 200 && !window.ApexCharts; i++) { await new Promise(r => setTimeout(r, 25)); }
    if (window.ApexCharts) return window.ApexCharts;
    const s = [...document.querySelectorAll('script[src]')].find(x => /apex-charts.*\.js/i.test(x.src));
    if (s) { try { const m = await import(s.src); if (m && (m.default || m.ApexCharts)) window.ApexCharts = m.default || m.ApexCharts; } catch (e) {} }
    return window.ApexCharts || null;
  }
  function build(AC) {
    const mk = (sel, opts) => { const el = document.querySelector(sel); if (!el) return; try { new AC(el, opts).render(); } catch (e) { console.error(sel, e); empty(el,'bx-error-circle','Chart failed'); } };

    const wfH = {!! json_encode($hiringTrend['hires'] ?? [], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!};
    const wfE = {!! json_encode($hiringTrend['attrition'] ?? [], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!};
    if (wfH.some(v=>v>0) || wfE.some(v=>v>0)) {
      mk('#adcWorkforce', {
        series:[{name:'New Joins',data:wfH},{name:'Exits',data:wfE}],
        chart:{type:'area',height:300,toolbar:{show:true,tools:{download:true,zoom:true,pan:true,reset:true}},zoom:{enabled:true},fontFamily:'Public Sans'},
        colors:['#0d9488','#dc2626'],stroke:{curve:'smooth',width:3},
        fill:{type:'gradient',gradient:{shadeIntensity:1,opacityFrom:0.28,opacityTo:0.03}},
        dataLabels:{enabled:false},legend:{position:'top',horizontalAlign:'right'},
        xaxis:{categories:{!! json_encode($hiringTrend['labels'] ?? [], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!},labels:{style:{colors:axisColor}},axisBorder:{show:false},axisTicks:{show:false}},
        yaxis:{labels:{style:{colors:axisColor}}},grid:{borderColor:gridColor,strokeDashArray:4}
      });
    } else empty(document.querySelector('#adcWorkforce'),'bx-line-chart','No hiring activity');

    const wfStatus = [{{ $present }}, {{ $onLeave }}, {{ $absent }}];
    if (wfStatus.reduce((a,b)=>a+b,0) > 0) {
      mk('#adcWfStatus', {
        series:wfStatus, labels:['Present','On Leave','Absent'],
        chart:{type:'donut',height:280,fontFamily:'Public Sans'}, colors:['#16a34a','#d97706','#dc2626'],
        legend:{position:'bottom',fontSize:'12px'}, dataLabels:{enabled:true,formatter:v=>Math.round(v)+'%'},
        plotOptions:{pie:{donut:{size:'68%',labels:{show:true,total:{show:true,label:'Active',fontSize:'13px',color:'#94a3b8',formatter:()=>{{ $activeCnt }}}}}}},
        stroke:{width:2,colors:['#fff']}
      });
    } else empty(document.querySelector('#adcWfStatus'),'bx-doughnut-chart','No attendance today');

    const deptData = {!! json_encode($departmentData ?? [], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!};
    if (deptData && deptData.length) {
      mk('#adcDept', {
        series:deptData.map(d=>Number(d.count)), labels:deptData.map(d=>d.name),
        chart:{type:'donut',height:300,fontFamily:'Public Sans'},
        colors:['#0d9488','#2563eb','#f59e0b','#8b5cf6','#ec4899','#14b8a6','#ef4444','#64748b','#22c55e','#0ea5e9'],
        legend:{position:'bottom',fontSize:'12px'}, dataLabels:{enabled:false},
        plotOptions:{pie:{donut:{size:'68%',labels:{show:true,total:{show:true,label:'Total',fontSize:'13px',color:'#94a3b8',formatter:w=>w.globals.seriesTotals.reduce((a,b)=>a+b,0)}}}}},
        stroke:{width:2,colors:['#fff']}
      });
    } else empty(document.querySelector('#adcDept'),'bx-pie-chart-alt-2','No department data');
  }
  getApex().then(AC => { if (AC) build(AC); else ['#adcWorkforce','#adcWfStatus','#adcDept'].forEach(s=>empty(document.querySelector(s),'bx-bar-chart-alt-2','Charts unavailable')); });
});
</script>
@endsection
