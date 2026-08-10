@extends('layouts/layoutMaster')

@section('title', __('Employee Dashboard'))

@section('vendor-style')
  @vite(['resources/assets/vendor/libs/animate-css/animate.scss', 'resources/assets/vendor/libs/apex-charts/apex-charts.scss'])
  @vite(['resources/assets/vendor/scss/pages/hitech-portal.scss'])
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap">
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/apex-charts/apex-charts.min.js'])
@endsection

@php
  $hour = (int) now()->format('H');
  $greet = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
  $me = auth()->user();
  $firstName = $me->first_name ?? (explode(' ', $me->name ?? 'There')[0]);
  $sp = $salesProfile ?? null;
  $att = $todayAttendance ?? null;
  $attIn = $att && $att->check_in_time ? \Carbon\Carbon::parse($att->check_in_time)->format('h:i A') : null;
  $isPresent = (bool) $attIn;
  $wkDays = max(1, (int) ($weekWorkingDays ?? 6));
  $wkDone = min($wkDays, (int) ($presentThisWeek ?? 0));
  $nf1 = fn($n) => rtrim(rtrim(number_format((float)$n, 1), '0'), '.');
  $fmtL = fn($v) => ((float)$v) >= 100 ? '₹' . number_format((float)$v / 100, 2) . ' Cr' : (((float)$v) >= 1 ? '₹' . number_format((float)$v, 2) . ' L' : '₹' . number_format((float)$v * 100000));
  $todayVisits = $sp['todayVisits'] ?? collect();
  $visitTone = ['completed'=>'success','confirmed'=>'info','pending'=>'warning','cancelled'=>'danger'];
@endphp

@section('content')
<div class="adc emp">
  <div class="row g-4">
    {{-- ============ MAIN ============ --}}
    <div class="col-xxl-9 col-lg-8">

      {{-- HERO --}}
      <div class="emp-hero">
        <div class="emp-hero-main">
          <h3 class="emp-hero-greet">{{ $greet }}, {{ $firstName }}! <span class="adc-wave">☀️</span></h3>
          <p class="emp-hero-sub">Have a productive day ahead.</p>
          {{-- Click to call. Renders nothing unless this login is mapped to a dialer agent. --}}
          <div class="mb-2">@include('tenant.users.dashboard._click-to-call')</div>
          <div class="emp-hero-meta">
            <div><span class="emp-meta-l">Employee ID</span><span class="emp-meta-v">{{ $me->code ?? '—' }}</span></div>
            <div><span class="emp-meta-l">Department</span><span class="emp-meta-v">{{ $deptName ?? '—' }}</span></div>
            <div><span class="emp-meta-l">Reporting Manager</span><span class="emp-meta-v">{{ $managerName ?? '—' }}</span></div>
            <div><span class="emp-meta-l">Date</span><span class="emp-meta-v">{{ now()->format('d M Y, D') }}</span></div>
          </div>
        </div>
        <div class="emp-hero-illus"><i class="bx bxs-user-voice"></i></div>
        <div class="emp-shift">
          <div class="emp-shift-row">
            <div><span class="emp-meta-l">Today's Shift</span><span class="emp-shift-v">{{ $shiftWindow ?? '—' }}</span></div>
            <div><span class="emp-meta-l">Working Hours</span><span class="emp-shift-v">{{ $nf1($todayHours ?? 0) }} <small>hrs</small></span></div>
            <div><span class="emp-meta-l">Week's Status</span><span class="emp-shift-v {{ $isPresent ? 'text-success' : 'text-danger' }}">{{ $isPresent ? 'Present' : 'Absent' }}</span></div>
          </div>
          <div class="emp-week">
            <div class="d-flex justify-content-between"><span class="emp-meta-l">Weekly Progress</span><small class="fw-semibold">{{ $wkDone }} / {{ $wkDays }} days</small></div>
            <div class="adc-bar mt-1"><span class="adc-tone-success" style="width: {{ round($wkDone / $wkDays * 100) }}%"></span></div>
          </div>
        </div>
      </div>

      {{-- KPI cards --}}
      <div class="row g-3 mt-1">
        @php
          $kpis = [
            ['icon'=>'bx-calendar-check','tone'=>'success','title'=>'Attendance','value'=>$isPresent ? 'Present' : 'Absent','sub'=>'Today','btn'=>'View Attendance','url'=>url('/user/attendance'),'big'=>false],
            ['icon'=>'bx-calendar-heart','tone'=>'info','title'=>'Leave Balance','value'=>$nf1($leaveDaysAvailable ?? 0),'sub'=>'Days Available','btn'=>'Apply Leave','url'=>url('/user/leaves'),'big'=>true],
            ['icon'=>'bx-wallet','tone'=>'primary','title'=>'Payroll','value'=>'₹'.number_format($latestNetSalary ?? 0),'sub'=>'Take Home ('.now()->format('M').')','btn'=>'View Payslip','url'=>url('/user/payroll'),'big'=>true],
            ['icon'=>'bx-receipt','tone'=>'warning','title'=>'Expenses','value'=>'₹'.number_format($expensesThisMonth ?? 0),'sub'=>'This Month','btn'=>'View Claims','url'=>url('/travel-claims'),'big'=>true],
            /* Marks earned, not "periods ticked": the old card showed 100% for
               someone who completed every KPI and scored nothing. A Blade
               comment cannot be used here — inside @php it reaches PHP as raw
               tokens and is a parse error. */
            ['icon'=>'bx-line-chart','tone'=>'danger','title'=>'Performance',
             'value'=>($kpiPossible ?? 0) > 0
                 ? rtrim(rtrim(number_format($kpiEarned ?? 0, 1), '0'), '.').' / '.rtrim(rtrim(number_format($kpiPossible, 1), '0'), '.')
                 : '—',
             'sub'=>($kpiPossible ?? 0) > 0
                 ? ($kraOverall ?? 0).'% of marks'.(($kpiDueSoon ?? 0) > 0 ? ' · '.$kpiDueSoon.' due soon' : '')
                 : 'No KPIs assigned',
             'btn'=>'View Performance','url'=>url('/kpi/my'),'big'=>true],
          ];
        @endphp
        @foreach($kpis as $k)
          <div class="col-6 col-md-4 col-xl">
            <div class="emp-kpi adc-tone-{{ $k['tone'] }}">
              <div class="emp-kpi-top"><span class="emp-kpi-icon"><i class="bx {{ $k['icon'] }}"></i></span><i class="bx bx-dots-vertical-rounded emp-kpi-menu"></i></div>
              <div class="emp-kpi-title">{{ $k['title'] }}</div>
              <div class="emp-kpi-value {{ $k['big'] ? 'adc-kpi-value' : '' }}">{{ $k['value'] }}</div>
              <div class="emp-kpi-sub">{{ $k['sub'] }}</div>
              <a href="{{ $k['url'] }}" class="emp-kpi-btn adc-tone-{{ $k['tone'] }}">{{ $k['btn'] }}</a>
            </div>
          </div>
        @endforeach
      </div>

      {{-- Sales-only: Kingo/Bingo board + recent order customers --}}
      @if($sp && ($sp['isSalesDept'] ?? false))
      <div class="row g-4 mt-1">
        <div class="col-xl-7">
          <div class="adc-card h-100">
            <div class="adc-card-head">
              <div>
                <h6 class="adc-card-title">{{ $sp['hasTargets'] ? '🏆 Kingo · Bingo · Super Kingo' : '📈 My Sales' }}</h6>
                <span class="adc-card-sub">{{ now()->format('F Y') }} · achieved {{ $fmtL($sp['achieved']) }}</span>
              </div>
              <a href="{{ url($sp['hasTargets'] ? '/sales-visits/kingo-bingo' : '/sales-visits/pipeline') }}" class="adc-link">Details</a>
            </div>
            @if($sp['hasTargets'])
              <div class="adc-tier-grid">
                @foreach($sp['kingoTiers'] as $t)
                  @php $tone = $t['won'] ? 'success' : ($t['pct'] >= 75 ? 'warning' : 'danger'); @endphp
                  <div class="adc-tier adc-tone-{{ $tone }} {{ $t['won'] ? 'won' : '' }}">
                    <div class="adc-tier-top"><span class="adc-tier-name">{{ $t['name'] }}</span>@if($t['won'])<span class="adc-tier-badge">🏆 WON</span>@endif</div>
                    <div class="adc-tier-pct">{{ round($t['pct']) }}%</div>
                    <div class="adc-bar"><span class="adc-tone-{{ $tone }}" style="width: {{ min(100, $t['pct']) }}%"></span></div>
                    <div class="adc-tier-target">Target {{ $fmtL($t['target']) }}</div>
                  </div>
                @endforeach
              </div>
            @elseif(!empty($sp['monthly']))
              {{-- No Kingo/Bingo target is set for this person — the scheme only
                   covers the Sales department — so show their own six months
                   rather than an empty tier board. --}}
              @php
                $mMax = max(array_map(fn($m) => $m['value'], $sp['monthly'])) ?: 1;
                $mom  = $sp['momPct'];
              @endphp
              <div class="d-flex flex-wrap gap-4 mb-3">
                <div><div class="adc-card-sub">This month</div><div class="fw-bold" style="font-size:1.15rem;color:#0f172a">{{ $fmtL($sp['achieved']) }}</div></div>
                <div><div class="adc-card-sub">Last month</div><div class="fw-bold" style="font-size:1.15rem;color:#0f172a">{{ $fmtL($sp['lastMonth']) }}</div></div>
                <div><div class="adc-card-sub">Change</div>
                  <div class="fw-bold" style="font-size:1.15rem;color:{{ $mom === null ? '#64748b' : ($mom >= 0 ? '#0d7377' : '#dc2626') }}">
                    {{ $mom === null ? '—' : ($mom >= 0 ? '+' : '') . $mom . '%' }}
                  </div>
                </div>
                <div><div class="adc-card-sub">My customers</div><div class="fw-bold" style="font-size:1.15rem;color:#0f172a">{{ number_format($sp['customerCount']) }}</div></div>
              </div>
              <div class="emp-spark">
                @foreach($sp['monthly'] as $m)
                  <div class="emp-spark-col" title="{{ $m['label'] }} · {{ $fmtL($m['value']) }}">
                    <div class="emp-spark-bar" style="height:{{ max(3, round($m['value'] / $mMax * 100)) }}%"></div>
                    <span class="emp-spark-lbl">{{ $m['label'] }}</span>
                  </div>
                @endforeach
              </div>
            @else
              <div class="adc-empty adc-empty-sm"><i class="bx bx-target-lock"></i><span>No sales recorded against your name yet.</span></div>
            @endif
          </div>
        </div>
        <div class="col-xl-5">
          <div class="adc-card h-100">
            <div class="adc-card-head"><div><h6 class="adc-card-title">Recent Order Customers</h6><span class="adc-card-sub">Gave orders · {{ now()->format('M Y') }}</span></div><a href="{{ url('/sales-visits/pipeline') }}" class="adc-link">Sales Report</a></div>
            @if(collect($sp['orderCustomers'] ?? [])->isEmpty())
              <div class="adc-empty adc-empty-sm"><i class="bx bx-cart"></i><span>No orders recorded yet.</span></div>
            @else
              <div class="adc-staff-scroll" style="max-height:290px">
                <table class="table adc-table mb-0"><tbody>
                  @foreach($sp['orderCustomers'] as $c)
                    <tr>
                      <td class="fw-medium">{{ $c['name'] }}<small class="text-muted d-block" style="font-size:.7rem">{{ $c['product'] }}</small></td>
                      <td class="text-end"><span class="adc-badge adc-tone-success">{{ $c['stage'] ?: 'Order' }}</span></td>
                      <td class="text-end fw-semibold" style="width:80px">{{ $c['sale'] > 0 ? $fmtL($c['sale']) : '—' }}</td>
                    </tr>
                  @endforeach
                </tbody></table>
              </div>
            @endif
          </div>
        </div>
      </div>
      @endif

      {{-- Schedule + KRA + Announcements --}}
      <div class="row g-4 mt-1">
        <div class="col-xl-4">
          <div class="adc-card h-100">
            <div class="adc-card-head"><div><h6 class="adc-card-title">My Schedule</h6></div><a href="{{ url('/sales-visits') }}" class="adc-link">View Calendar</a></div>
            @if(collect($todayVisits)->isEmpty())
              <div class="adc-empty adc-empty-sm"><i class="bx bx-calendar"></i><span>No meetings scheduled today.</span></div>
            @else
              <ul class="adc-feed">
                @foreach(collect($todayVisits)->take(4) as $v)
                  @php $vt = $visitTone[$v->status] ?? 'primary'; @endphp
                  <li>
                    <span class="adc-feed-dot adc-tone-{{ $vt }}"><i class="bx bx-phone-call"></i></span>
                    <div class="adc-feed-body"><div class="adc-feed-title">{{ optional($v->client)->name ?? 'Client Visit' }}</div><div class="adc-feed-meta">{{ \Carbon\Carbon::parse($v->scheduled_at)->format('h:i A') }} · {{ ucfirst($v->status) }}</div></div>
                  </li>
                @endforeach
              </ul>
            @endif
          </div>
        </div>
        <div class="col-xl-4">
          <div class="adc-card h-100">
            <div class="adc-card-head"><div><h6 class="adc-card-title">My KRA Progress</h6></div><a href="{{ url('/kpi/my') }}" class="adc-link">View KRA</a></div>
            @if(collect($kraItems ?? [])->isEmpty())
              <div class="adc-empty adc-empty-sm"><i class="bx bx-target-lock"></i><span>No KRAs assigned yet.</span></div>
            @else
              <div class="d-flex justify-content-center"><div id="empKra"></div></div>
              <div class="emp-kra-list">
                @foreach($kraItems as $kr)
                  @php $kt = $kr['pct'] >= 85 ? 'success' : ($kr['pct'] >= 60 ? 'primary' : 'warning'); @endphp
                  <div class="emp-kra-item">
                    <div class="d-flex justify-content-between"><span>{{ $kr['name'] }}</span><b>{{ $kr['pct'] }}%</b></div>
                    <div class="adc-bar mt-1"><span class="adc-tone-{{ $kt }}" style="width: {{ min(100, $kr['pct']) }}%"></span></div>
                  </div>
                @endforeach
              </div>
            @endif
          </div>
        </div>
        <div class="col-xl-4">
          <div class="adc-card h-100">
            <div class="adc-card-head"><div><h6 class="adc-card-title">Announcements</h6></div>@if(auth()->user()->hasRole(['admin','hr']))<a href="{{ url('/employee-lifecycle/announcements') }}" class="adc-link"><i class="bx bx-plus"></i> Publish</a>@endif</div>
            @if(collect($recentNotices ?? [])->isEmpty())
              <div class="adc-empty adc-empty-sm"><i class="bx bx-megaphone"></i><span>No announcements.</span></div>
            @else
              <ul class="adc-feed">
                @foreach(collect($recentNotices)->take(4) as $n)
                  <li>
                    <span class="adc-feed-dot adc-tone-info"><i class="bx bx-news"></i></span>
                    <div class="adc-feed-body"><div class="adc-feed-title">{{ $n->title ?? 'Notice' }}</div><div class="adc-feed-meta">{{ optional($n->created_at)->diffForHumans() }}</div></div>
                  </li>
                @endforeach
              </ul>
            @endif
          </div>
        </div>
      </div>

      {{-- Timeline + Upcoming Leaves + Documents + Contacts --}}
      <div class="row g-4 mt-1">
        <div class="col-xl-3 col-md-6">
          <div class="adc-card h-100">
            <div class="adc-card-head"><div><h6 class="adc-card-title">My Timeline</h6></div><a href="{{ url('/user/attendance') }}" class="adc-link">View All</a></div>
            @if(collect($myTimeline ?? [])->isEmpty())
              <div class="adc-empty adc-empty-sm"><i class="bx bx-time"></i><span>No recent activity.</span></div>
            @else
              <ul class="adc-feed">
                @foreach($myTimeline as $t)
                  <li>
                    <span class="adc-feed-dot adc-tone-{{ $t['tone'] }}"><i class="bx {{ $t['icon'] }}"></i></span>
                    <div class="adc-feed-body"><div class="adc-feed-title">{{ $t['title'] }}</div><div class="adc-feed-meta">{{ $t['sub'] }}@if(!empty($t['time'])) · {{ $t['time'] }}@endif</div></div>
                  </li>
                @endforeach
              </ul>
            @endif
          </div>
        </div>
        <div class="col-xl-3 col-md-6">
          <div class="adc-card h-100">
            <div class="adc-card-head"><div><h6 class="adc-card-title">Upcoming Leaves</h6></div><a href="{{ url('/user/leaves') }}" class="adc-link">View All</a></div>
            @if(collect($upcomingLeaves ?? [])->isEmpty())
              <div class="adc-empty adc-empty-sm"><i class="bx bx-calendar-check"></i><span>No upcoming leaves.</span></div>
            @else
              <div class="emp-leaves">
                @foreach($upcomingLeaves as $l)
                  <div class="emp-leave">
                    <div class="emp-leave-date"><b>{{ $l['day'] }}</b><small>{{ $l['mon'] }} {{ substr($l['year'],2) }}</small></div>
                    <div class="emp-leave-body"><div class="emp-leave-type">{{ $l['type'] }}</div><span class="adc-badge adc-tone-{{ $l['status']==='approved'?'success':'warning' }}">{{ ucfirst($l['status']) }}</span></div>
                  </div>
                @endforeach
              </div>
            @endif
          </div>
        </div>
        <div class="col-xl-3 col-md-6">
          <div class="adc-card h-100">
            <div class="adc-card-head"><div><h6 class="adc-card-title">My Documents</h6></div><a href="{{ url('/my-policies') }}" class="adc-link">View All</a></div>
            <ul class="emp-docs">
              @foreach($myDocs ?? [] as $d)
                <li>
                  <span class="emp-doc-icon"><i class="bx bx-file"></i></span>
                  <span class="emp-doc-name">{{ $d['name'] }}</span>
                  <i class="bx {{ $d['ok'] ? 'bxs-check-circle text-success' : 'bx-time-five text-warning' }}"></i>
                </li>
              @endforeach
            </ul>
          </div>
        </div>
        <div class="col-xl-3 col-md-6">
          <div class="adc-card h-100">
            <div class="adc-card-head"><div><h6 class="adc-card-title">Important Contacts</h6></div></div>
            @if(collect($importantContacts ?? [])->isEmpty())
              <div class="adc-empty adc-empty-sm"><i class="bx bx-user-voice"></i><span>No contacts available.</span></div>
            @else
              <ul class="emp-contacts">
                @foreach($importantContacts as $c)
                  <li>
                    <img class="emp-contact-av" src="https://ui-avatars.com/api/?name={{ urlencode($c['name']) }}&background={{ $c['bg'] ?? '0d9488' }}&color=fff" alt="">
                    <div class="emp-contact-body"><div class="emp-contact-name">{{ $c['name'] }}</div><div class="emp-contact-role">{{ $c['role'] }}</div></div>
                    <span class="emp-contact-act">
                      @if(!empty($c['phone']))<a href="tel:{{ preg_replace('/[^0-9+]/','',$c['phone']) }}" title="Call {{ $c['phone'] }}"><i class="bx bx-phone"></i></a>@endif
                      @if(!empty($c['email']))<a href="mailto:{{ $c['email'] }}" title="Email {{ $c['email'] }}"><i class="bx bx-message-rounded"></i></a>@endif
                    </span>
                  </li>
                @endforeach
              </ul>
            @endif
          </div>
        </div>
      </div>

    </div>

    {{-- ============ RAIL ============ --}}
    <div class="col-xxl-3 col-lg-4">

      {{-- Today's Highlights --}}
      <div class="adc-card mb-4">
        <div class="adc-card-head"><div><h6 class="adc-card-title">Today's Highlights</h6></div></div>
        @php
          $todayBd = collect($todayBirthdays ?? [])->first();
          $todayAn = collect($todayAnniversaries ?? [])->first();
          $high = [];
          if ($todayBd) $high[] = ['icon'=>'bx-cake','tone'=>'primary','title'=>'Birthday','sub'=>$todayBd->name,'url'=>url('/organizationHierarchy')];
          if ($todayAn) $high[] = ['icon'=>'bx-gift','tone'=>'info','title'=>'Work Anniversary','sub'=>$todayAn->name,'url'=>url('/organizationHierarchy')];
          if (!empty($nextHoliday)) $high[] = ['icon'=>'bx-calendar-star','tone'=>'success','title'=>'Holiday','sub'=>($nextHoliday->name ?? 'Holiday').' · '.\Carbon\Carbon::parse($nextHoliday->date)->format('d M Y'),'url'=>url('/holidays')];
          $high[] = ['icon'=>'bx-shield-quarter','tone'=>'warning','title'=>'Policy Acknowledgement','sub'=>'Code of Conduct · Pending','url'=>url('/my-policies')];
        @endphp
        <ul class="adc-events">
          @foreach($high as $h)
            <li><a href="{{ $h['url'] }}" class="emp-hl-link"><span class="adc-ev-icon adc-tone-{{ $h['tone'] }}"><i class="bx {{ $h['icon'] }}"></i></span><div class="flex-grow-1"><div class="adc-feed-title">{{ $h['title'] }}</div><div class="adc-feed-meta">{{ $h['sub'] }}</div></div><i class="bx bx-chevron-right emp-hl-arrow"></i></a></li>
          @endforeach
        </ul>
      </div>

      {{-- Quick Actions --}}
      <div class="adc-card">
        <div class="adc-card-head"><div><h6 class="adc-card-title">Quick Actions</h6></div></div>
        <div class="emp-qa">
          <a href="{{ url('/user/leaves') }}" class="adc-qa-btn"><i class="bx bx-calendar-plus"></i><span>Apply Leave</span></a>
          <a href="{{ url('/user/attendance') }}" class="adc-qa-btn"><i class="bx bx-time-five"></i><span>Mark Attendance</span></a>
          <a href="{{ url('/travel-claims/create') }}" class="adc-qa-btn"><i class="bx bx-receipt"></i><span>Expense Claim</span></a>
          <a href="{{ url('/travel-claims/create') }}" class="adc-qa-btn"><i class="bx bx-trip"></i><span>Request Travel</span></a>
          <a href="{{ url('/user/payroll') }}" class="adc-qa-btn"><i class="bx bx-money"></i><span>My Payroll</span></a>
          <a href="{{ url('/training') }}" class="adc-qa-btn"><i class="bx bx-book"></i><span>Training</span></a>
          <a href="{{ url('/my-assets') }}" class="adc-qa-btn"><i class="bx bx-archive"></i><span>Asset Request</span></a>
          <a href="{{ url('/my-policies') }}" class="adc-qa-btn"><i class="bx bx-book-content"></i><span>HR Policies</span></a>
          <a href="{{ url('/holidays') }}" class="adc-qa-btn"><i class="bx bx-calendar-event"></i><span>Holidays</span></a>
          <a href="{{ url('/mind-speak') }}" class="adc-qa-btn"><i class="bx bx-bulb"></i><span>Mind Speak</span></a>
        </div>
      </div>

    </div>
  </div>
</div>

@include('tenant.users.dashboard._adc-styles')
@include('tenant.users.dashboard._adc-live')
<style>
  .emp-hero{position:relative;overflow:hidden;background:linear-gradient(120deg,#e6f7f2 0%,#f0fdfa 55%,#ffffff 100%);border:1px solid #cdeee6;border-radius:20px;padding:26px 28px;display:grid;grid-template-columns:1.35fr .9fr;gap:20px;box-shadow:0 1px 3px rgba(15,23,42,.05);}
  .emp-hero-greet{font-size:1.5rem;font-weight:800;color:#0f766e;margin:0 0 3px;letter-spacing:-.01em;}
  .emp-hero-sub{color:var(--muted);margin:0 0 18px;}
  .emp-hero-meta{display:grid;grid-template-columns:repeat(4,auto);gap:8px 28px;}
  .emp-meta-l{display:block;font-size:.7rem;color:var(--faint);text-transform:uppercase;letter-spacing:.04em;font-weight:600;}
  .emp-meta-v{display:block;font-size:.9rem;font-weight:700;color:var(--ink);}
  .emp-hero-illus{position:absolute;left:52%;top:50%;transform:translateY(-50%);font-size:6rem;color:rgba(13,148,136,.16);pointer-events:none;}
  .emp-shift{background:#fff;border:1px solid var(--line);border-radius:15px;padding:16px 18px;align-self:center;box-shadow:0 6px 20px -12px rgba(15,23,42,.2);z-index:1;}
  .emp-shift-row{display:flex;justify-content:space-between;gap:12px;margin-bottom:12px;}
  .emp-shift-v{display:block;font-size:.95rem;font-weight:800;color:var(--ink);margin-top:2px;}
  .emp-shift-v small{font-size:.7rem;color:var(--faint);font-weight:600;}
  .emp-week .adc-bar{height:8px;}

  .emp-kpi{background:#fff;border:1px solid var(--line);border-radius:16px;padding:16px;height:100%;display:flex;flex-direction:column;box-shadow:0 1px 3px rgba(15,23,42,.05);transition:transform .15s,box-shadow .15s;}
  .emp-kpi:hover{transform:translateY(-3px);box-shadow:0 12px 26px -14px rgba(15,23,42,.22);}
  .emp-kpi-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;}
  .emp-kpi-icon{width:42px;height:42px;border-radius:12px;display:grid;place-items:center;font-size:1.35rem;color:var(--c);background:color-mix(in srgb,var(--c) 12%,#fff);}
  .emp-kpi-menu{color:var(--faint);cursor:pointer;font-size:1.2rem;}
  .emp-kpi-title{font-size:.82rem;font-weight:600;color:var(--muted);}
  .emp-kpi-value{font-size:1.5rem;font-weight:800;color:var(--c);line-height:1.1;letter-spacing:-.02em;margin:2px 0;}
  .emp-kpi-sub{font-size:.72rem;color:var(--faint);margin-bottom:12px;}
  .emp-kpi-btn{margin-top:auto;display:block;text-align:center;font-size:.78rem;font-weight:700;padding:9px;border-radius:10px;text-decoration:none;color:var(--c);background:color-mix(in srgb,var(--c) 10%,#fff);transition:all .12s;}
  .emp-kpi-btn:hover{background:var(--c);color:#fff;}

  .emp-kra-list{display:flex;flex-direction:column;gap:11px;margin-top:8px;}
  .emp-kra-item{font-size:.8rem;font-weight:600;}
  .emp-kra-item b{font-weight:800;}

  .emp-leaves{display:flex;flex-direction:column;gap:12px;}
  .emp-leave{display:flex;gap:12px;align-items:center;}
  .emp-leave-date{width:52px;flex:none;text-align:center;background:var(--bg);border-radius:11px;padding:8px 4px;line-height:1.1;}
  .emp-leave-date b{display:block;font-size:1.15rem;font-weight:800;color:var(--p);}
  .emp-leave-date small{font-size:.62rem;color:var(--muted);text-transform:uppercase;}
  .emp-leave-type{font-size:.85rem;font-weight:600;margin-bottom:3px;}

  .emp-docs{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:4px;}
  .emp-docs li{display:flex;align-items:center;gap:10px;padding:8px 6px;border-radius:9px;transition:background .12s;}
  .emp-docs li:hover{background:var(--bg);}
  .emp-doc-icon{width:32px;height:32px;flex:none;border-radius:9px;display:grid;place-items:center;background:var(--bg);color:var(--muted);font-size:1.05rem;}
  .emp-doc-name{flex:1;font-size:.83rem;font-weight:500;}
  .emp-docs li > i{font-size:1.2rem;}

  .emp-contacts{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:12px;}
  .emp-contacts li{display:flex;gap:10px;align-items:center;}
  .emp-contact-av{width:36px;height:36px;border-radius:50%;flex:none;}
  .emp-contact-body{flex:1;min-width:0;}
  .emp-contact-name{font-size:.83rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .emp-contact-role{font-size:.72rem;color:var(--faint);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .emp-contact-act{display:flex;gap:6px;flex:none;}
  .emp-contact-act a{width:28px;height:28px;border-radius:8px;display:grid;place-items:center;background:var(--bg);color:var(--p);text-decoration:none;font-size:1rem;transition:all .12s;}
  .emp-contact-act a:hover{background:var(--p);color:#fff;}

  .emp-qa{display:grid;grid-template-columns:1fr 1fr;gap:9px;}
  .emp-hl-link{display:flex;align-items:center;gap:11px;width:100%;text-decoration:none;color:inherit;}
  .emp-hl-arrow{color:var(--faint);opacity:0;transition:opacity .12s,transform .12s;font-size:1.1rem;margin-left:auto;}
  .adc-events li:hover .emp-hl-arrow{opacity:1;transform:translateX(2px);}

  /* Six-month sales bars, shown when no Kingo/Bingo target is set. Uses the
     card's own tokens rather than a new palette. */
  .emp-spark{display:flex;align-items:flex-end;gap:10px;height:104px;padding-top:4px;}
  .emp-spark-col{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%;}
  .emp-spark-bar{width:100%;max-width:44px;border-radius:6px 6px 0 0;background:linear-gradient(180deg,var(--hitech-primary,#0d9488),color-mix(in srgb,var(--hitech-primary,#0d9488) 45%,#fff));}
  .emp-spark-col:last-child .emp-spark-bar{background:linear-gradient(180deg,#f59e0b,#fbbf24);}
  .emp-spark-lbl{margin-top:6px;font-size:.68rem;font-weight:700;color:#94a3b8;}

  /* Kingo/Bingo tier board (sales) */
  .adc-tier-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;}
  .adc-tier{background:var(--bg);border:1px solid var(--line);border-radius:13px;padding:14px;}
  .adc-tier.won{background:color-mix(in srgb,var(--success) 8%,#fff);border-color:color-mix(in srgb,var(--success) 35%,var(--line));}
  .adc-tier-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;}
  .adc-tier-name{font-size:.82rem;font-weight:700;color:var(--ink);}
  .adc-tier-badge{font-size:.6rem;font-weight:800;color:var(--success);background:color-mix(in srgb,var(--success) 15%,#fff);padding:2px 6px;border-radius:20px;}
  .adc-tier-pct{font-size:1.7rem;font-weight:800;line-height:1;color:var(--c,var(--ink));margin:2px 0 6px;}
  .adc-tier-target{font-size:.72rem;color:var(--faint);margin-top:5px;font-weight:600;}
  @media (max-width:575.98px){ .adc-tier-grid{grid-template-columns:1fr;} }

  @media (max-width:991.98px){ .emp-hero{grid-template-columns:1fr;} .emp-hero-illus{display:none;} .emp-hero-meta{grid-template-columns:1fr 1fr;} }
  @media (max-width:575.98px){ .emp-hero-meta{grid-template-columns:1fr 1fr;} .emp-shift-row{flex-wrap:wrap;} }
</style>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  var el = document.querySelector('#empKra'); if (!el) return;
  var pct = {{ (int) ($kraOverall ?? 0) }};
  function boot(AC){
    try {
      new AC(el, {
        series:[Math.min(100,pct)],
        chart:{type:'radialBar',height:180,fontFamily:'Public Sans'}, colors:['#0d9488'],
        plotOptions:{radialBar:{hollow:{size:'62%'},track:{background:'#eef1f5'},dataLabels:{name:{offsetY:20,color:'#94a3b8',fontSize:'11px'},value:{offsetY:-8,fontSize:'22px',fontWeight:800,color:'#1e293b',formatter:function(v){return Math.round(v)+'%';}}}}},
        fill:{type:'gradient',gradient:{shade:'light',shadeIntensity:.4,gradientToColors:['#5eead4'],stops:[0,100]}},
        stroke:{lineCap:'round'}, labels:['Completed']
      }).render();
    } catch(e){ console.error(e); }
  }
  (async function(){ for(var i=0;i<200 && !window.ApexCharts;i++){ await new Promise(r=>setTimeout(r,25)); }
    if(window.ApexCharts){ boot(window.ApexCharts); }
    else { var s=[...document.querySelectorAll('script[src]')].find(x=>/apex-charts.*\.js/i.test(x.src)); if(s){ try{ var m=await import(s.src); boot(m.default||window.ApexCharts);}catch(e){} } }
  })();
});
</script>
@endsection
