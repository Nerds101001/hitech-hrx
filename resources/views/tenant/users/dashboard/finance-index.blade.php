@extends('layouts/layoutMaster')

@section('title', __('Finance Command Center'))

@section('vendor-style')
  @vite([
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
  $firstName = auth()->user()->first_name ?? (explode(' ', auth()->user()->name ?? 'Accounts')[0]);
@endphp

@section('content')
@php
  // Accounts holds payroll/attendance rights but not travel-claim, expense,
  // loan or report rights, so half of these tiles used to land on a 403. Ask
  // the router what this user can actually open: navigation tiles disappear,
  // informational cards keep the number but drop the link.
  $mayOpen = fn($u) => !empty($u) && \App\Support\Nav::allows($u);
@endphp

<div class="adc">

  {{-- Hero --}}
  <div class="adc-hero d-flex flex-wrap align-items-center justify-content-between mb-4">
    <div>
      <h4 class="adc-hero-title mb-1">{{ $greet }}, {{ $firstName }} <span class="adc-wave">💰</span></h4>
      <p class="adc-hero-sub mb-0">Money in motion across the company — {{ now()->format('l, d M Y') }}.</p>
    </div>
    <div class="d-flex align-items-center gap-2 mt-2 mt-md-0">
      <span class="adc-chip"><i class="bx bx-wallet"></i> Finance Console</span>
      @if($mayOpen(url('/payroll')))
        <a href="{{ url('/payroll') }}" class="adc-chip adc-chip-accent"><i class="bx bx-money"></i> Payroll</a>
      @endif
    </div>
  </div>

  {{-- KPI cards --}}
  <div class="row g-3 mb-2">
    @php
      $kpis = [
        ['label'=>'Cash-Out Queue','value'=>$money($totalCashOut),'icon'=>'bx-wallet-alt','tone'=>'danger','sub'=>$totalCashOutCount.' items awaiting payment','url'=>url('/travel-claims/admin/dashboard')],
        ['label'=>'Payroll (This Month)','value'=>$money($payrollThisMonth),'icon'=>'bx-money','tone'=>'primary','sub'=>$payslipsPending.' payslips not yet paid','url'=>url('/payroll')],
        ['label'=>'Monthly Salary Cost','value'=>$money($monthlySalaryCost),'icon'=>'bx-trending-up','tone'=>'info','sub'=>number_format($activeHeadcount).' active staff','url'=>url('/employees')],
        ['label'=>'Claims to Verify','value'=>number_format($awaitingVerification['count']),'icon'=>'bx-search-alt','tone'=>'warning','sub'=>$money($awaitingVerification['amount']).' submitted','url'=>url('/travel-claims/admin/dashboard')],
        ['label'=>'Claims to Pay','value'=>number_format($awaitingPayment['count']),'icon'=>'bx-credit-card','tone'=>'danger','sub'=>$money($awaitingPayment['amount']).' approved','url'=>url('/travel-claims/admin/dashboard')],
        ['label'=>'On Objection','value'=>number_format($onObjection['count']),'icon'=>'bx-error-circle','tone'=>'warning','sub'=>'need claimant action','url'=>url('/travel-claims/admin/dashboard')],
      ];
    @endphp
    @foreach($kpis as $k)
      @php $kHref = $mayOpen($k['url']) ? $k['url'] : null; @endphp
      <div class="col-6 col-md-4 col-xxl-2">
        <a @if($kHref) href="{{ $kHref }}" @endif class="adc-kpi adc-tone-{{ $k['tone'] }} h-100 d-block">
          <div class="adc-kpi-top">
            <span class="adc-kpi-icon"><i class="bx {{ $k['icon'] }}"></i></span>
            @if($kHref)<i class="bx bx-chevron-right adc-kpi-go"></i>@endif
          </div>
          <div class="adc-kpi-value">{{ $k['value'] }}</div>
          <div class="adc-kpi-label">{{ $k['label'] }}</div>
          <div class="adc-kpi-sub">{{ $k['sub'] }}</div>
        </a>
      </div>
    @endforeach
  </div>

  <div class="row g-4 mt-0">
    {{-- MAIN --}}
    <div class="col-xxl-9 col-lg-8">

      {{-- My Tasks + Claim Pipeline, paired so neither half of the row sits
           empty. --}}
      <div class="row g-4">
        <div class="col-xl-6">
          @include('tenant.users.dashboard._adc-task-widget')
        </div>
        <div class="col-xl-6">
          <div class="adc-card h-100">
            <div class="adc-card-head"><div><h6 class="adc-card-title">Claim Pipeline</h6><span class="adc-card-sub">Travel claims by stage</span></div>@if($mayOpen(url('/travel-claims/admin/dashboard')))<a href="{{ url('/travel-claims/admin/dashboard') }}" class="adc-link">All</a>@endif</div>
            <div id="finClaimPipeline"></div>
          </div>
        </div>
      </div>

      {{-- Cash-out queue --}}
      <div class="adc-card">
        <div class="adc-card-head">
          <div><h6 class="adc-card-title">Cash-Out Queue</h6><span class="adc-card-sub">Everything waiting on money to leave — total {{ $money($totalCashOut) }}</span></div>
        </div>
        @if($totalCashOutCount === 0)
          <div class="adc-empty adc-empty-sm"><i class="bx bx-check-circle text-success"></i><span>Nothing pending payment. All clear.</span></div>
        @else
          <div class="row g-3">
            @foreach($cashOutQueue as $q)
              @php $qHref = $mayOpen($q['url'] ?? null) ? $q['url'] : null; @endphp
              <div class="col-6 col-xl-3">
                <a @if($qHref) href="{{ $qHref }}" @endif class="adc-tile adc-tone-{{ $q['tone'] }} d-block h-100">
                  <div class="adc-tile-value">{{ $money($q['amount']) }}</div>
                  <div class="adc-tile-name">{{ $q['label'] }}</div>
                  <div class="adc-tile-sub">{{ $q['count'] }} {{ \Illuminate\Support\Str::plural('item', $q['count']) }}</div>
                </a>
              </div>
            @endforeach
          </div>
        @endif
      </div>

      <div class="row g-4 mt-1">
        <div class="col-12">
          <div class="adc-card">
            <div class="adc-card-head"><div><h6 class="adc-card-title">Payroll Cost Trend</h6><span class="adc-card-sub">Net salary paid · last 12 months</span></div><a href="{{ url('/payroll') }}" class="adc-link">Payroll</a></div>
            <div id="finPayrollTrend"></div>
          </div>
        </div>
      </div>

      {{-- New joiners waiting on their employee ID / biometric enrolment.
           Queried here rather than passed from the controller so the card
           works on any dashboard this partial is dropped into. --}}
      @php
        $setupQueue = collect();
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('employee_setup_tasks')) {
                $setupQueue = \App\Models\EmployeeSetupTask::pending()
                    ->with(['user.department', 'user.designation'])
                    ->orderBy('created_at')->get();
            }
        } catch (\Throwable $e) { $setupQueue = collect(); }
      @endphp

      <div class="adc-card mt-4">
        <div class="adc-card-head">
          <div>
            <h6 class="adc-card-title">Attendance Setup — New Joiners</h6>
            <span class="adc-card-sub">Record employee ID and biometric enrolment so payroll can start</span>
          </div>
          <a href="{{ url('/employee-setup') }}" class="adc-link">View all</a>
        </div>

        @if($setupQueue->isEmpty())
          <div class="adc-empty adc-empty-sm">
            <i class="bx bx-check-circle"></i><span>No pending setups — everyone is enrolled.</span>
          </div>
        @else
          <div class="table-responsive">
            <table class="table adc-table mb-0">
              <thead>
                <tr><th>Employee</th><th>Department</th><th>Joined</th><th>Required</th><th class="text-end">Action</th></tr>
              </thead>
              <tbody>
                @foreach($setupQueue as $t)
                  <tr>
                    <td class="fw-medium">
                      {{ optional($t->user)->getFullName() ?? '—' }}
                      <small class="text-muted d-block" style="font-size:.7rem">
                        {{ optional(optional($t->user)->designation)->name }}
                      </small>
                    </td>
                    <td>{{ optional(optional($t->user)->department)->name ?? '—' }}</td>
                    <td>
                      {{ optional($t->user)->date_of_joining
                          ? \Carbon\Carbon::parse($t->user->date_of_joining)->format('d M Y') : '—' }}
                    </td>
                    <td>
                      @if($t->needs_emp_id)
                        <span class="adc-badge adc-tone-info">Emp ID</span>
                      @endif
                      @if($t->needs_biometric)
                        <span class="adc-badge adc-tone-warning">Biometric</span>
                      @else
                        <span class="adc-badge adc-tone-success">Biometric exempt</span>
                      @endif
                    </td>
                    <td class="text-end">
                      <button type="button" class="adc-badge adc-tone-primary js-setup-task"
                              style="border:none;cursor:pointer"
                              data-id="{{ $t->id }}"
                              data-name="{{ optional($t->user)->getFullName() }}"
                              data-meta="{{ trim(implode(' · ', array_filter([
                                  optional(optional($t->user)->designation)->name,
                                  optional(optional($t->user)->department)->name,
                              ]))) }}"
                              data-emp="{{ optional($t->user)->code }}"
                              data-needs-emp="{{ $t->needs_emp_id ? 1 : 0 }}"
                              data-needs-bio="{{ $t->needs_biometric ? 1 : 0 }}">Update</button>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>

      {{-- WFH attendance queue — accounts marks these from the tracking report --}}
      <div class="adc-card mt-4">
        <div class="adc-card-head">
          <div><h6 class="adc-card-title">Work From Home — Attendance To Mark</h6><span class="adc-card-sub">Approved WFH · mark attendance from the tracking-software report</span></div>
          <a href="{{ url('/attendance') }}" class="adc-link">Attendance</a>
        </div>
        @if(collect($wfhQueue)->isEmpty())
          <div class="adc-empty adc-empty-sm"><i class="bx bx-laptop"></i><span>No approved work-from-home days awaiting attendance.</span></div>
        @else
          <div class="table-responsive">
            <table class="table adc-table mb-0">
              <thead><tr><th>Employee</th><th>From</th><th>To</th><th>Days</th><th class="text-end">Action</th></tr></thead>
              <tbody>
                @foreach($wfhQueue as $w)
                  @php
                    $from = \Carbon\Carbon::parse($w->from_date);
                    $to   = \Carbon\Carbon::parse($w->to_date);
                    $days = $w->is_half_day ? 0.5 : ($from->diffInDays($to) + 1);
                  @endphp
                  <tr>
                    <td class="fw-medium">{{ optional($w->user)->getFullName() ?? '—' }}<small class="text-muted d-block" style="font-size:.7rem">{{ optional($w->user)->code }}</small></td>
                    <td>{{ $from->format('d M Y') }}</td>
                    <td>{{ $to->format('d M Y') }}</td>
                    <td>{{ $days }}</td>
                    <td class="text-end"><a href="{{ url('/attendance') }}" class="adc-badge adc-tone-primary">Mark</a></td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>

      {{-- Finance module tiles --}}
      <div class="adc-card mt-4">
        <div class="adc-card-head"><div><h6 class="adc-card-title">Finance Modules</h6><span class="adc-card-sub">Quick access</span></div></div>
        <div class="row g-3">
          @php
            $tiles = [
              ['name'=>'Payroll','icon'=>'bx-money','value'=>$money($payrollThisMonth),'sub'=>now()->format('M Y'),'url'=>url('/payroll'),'tone'=>'primary'],
              ['name'=>'Travel Claims','icon'=>'bx-car','value'=>$awaitingPayment['count'] + $awaitingVerification['count'],'sub'=>'Awaiting action','url'=>url('/travel-claims/admin/dashboard'),'tone'=>'warning'],
              ['name'=>'Expenses','icon'=>'bx-receipt','value'=>$cashOutQueue[2]['count'],'sub'=>'Pending','url'=>url('/expenseRequests'),'tone'=>'info'],
              ['name'=>'Loans','icon'=>'bx-wallet','value'=>$cashOutQueue[3]['count'],'sub'=>'Pending','url'=>url('/loan'),'tone'=>'success'],
              ['name'=>'Attendance','icon'=>'bx-time-five','value'=>number_format($presentUsersCount),'sub'=>'Present today','url'=>url('/attendance'),'tone'=>'success'],
              ['name'=>'Leaves','icon'=>'bx-calendar-event','value'=>number_format($onLeaveUsersCount),'sub'=>'On leave today','url'=>url('/leaveRequests'),'tone'=>'warning'],
              ['name'=>'Employees','icon'=>'bx-group','value'=>number_format($activeHeadcount),'sub'=>'Directory','url'=>url('/employees'),'tone'=>'primary'],
              ['name'=>'Reports','icon'=>'bx-file','value'=>'','sub'=>'Export','url'=>url('/reports'),'tone'=>'info'],
            ];
            // Pure navigation — a tile that 403s is worse than no tile.
            $tiles = \App\Support\Nav::filter($tiles);
          @endphp
          @forelse($tiles as $t)
            <div class="col-6 col-md-3">
              <a href="{{ $t['url'] }}" class="adc-tile adc-tone-{{ $t['tone'] }} d-block h-100">
                <span class="adc-tile-icon"><i class="bx {{ $t['icon'] }}"></i></span>
                <div class="adc-tile-value">{{ $t['value'] }}</div>
                <div class="adc-tile-name">{{ $t['name'] }}</div>
                <div class="adc-tile-sub">{{ $t['sub'] }}</div>
              </a>
            </div>
          @empty
            <div class="col-12">
              <div class="adc-empty adc-empty-sm">
                <i class="bx bx-lock-alt"></i><span>No finance modules are enabled for your role.</span>
              </div>
            </div>
          @endforelse
        </div>
      </div>
    </div>

    {{-- RAIL --}}
    <div class="col-xxl-3 col-lg-4">

      @include('tenant.users.dashboard._adc-announcements')


      {{-- Top claimants --}}
      <div class="adc-card mb-4">
        <div class="adc-card-head"><div><h6 class="adc-card-title">Top Claimants</h6><span class="adc-card-sub">{{ now()->format('M Y') }}</span></div></div>
        @if(collect($topClaimants)->isEmpty())
          <div class="adc-empty adc-empty-sm"><i class="bx bx-user"></i><span>No claims this month.</span></div>
        @else
          <ul class="adc-appr adc-rail-scroll">
            @foreach($topClaimants as $tc)
              <li>
                <img class="adc-appr-av" src="https://ui-avatars.com/api/?name={{ urlencode(optional($tc->user)->getFullName() ?? 'NA') }}&background=0d9488&color=fff&bold=true" alt="">
                <div class="adc-appr-body">
                  <div class="adc-appr-name">{{ optional($tc->user)->getFullName() ?? '—' }}</div>
                  <div class="adc-appr-meta">{{ $tc->c }} {{ \Illuminate\Support\Str::plural('claim', $tc->c) }}</div>
                </div>
                <span class="adc-badge adc-tone-info">{{ $money($tc->amt) }}</span>
              </li>
            @endforeach
          </ul>
        @endif
      </div>

      {{-- Recent payments --}}
      <div class="adc-card mb-4">
        <div class="adc-card-head"><div><h6 class="adc-card-title">Recent Payments</h6><span class="adc-card-sub">Claims marked paid</span></div>@if($mayOpen(url('/travel-claims/admin/dashboard')))<a href="{{ url('/travel-claims/admin/dashboard') }}" class="adc-link">All</a>@endif</div>
        @if(collect($recentPayments)->isEmpty())
          <div class="adc-empty adc-empty-sm"><i class="bx bx-transfer"></i><span>No payments recorded yet.</span></div>
        @else
          <ul class="adc-feed adc-rail-scroll">
            @foreach($recentPayments as $p)
              <li>
                <span class="adc-feed-dot adc-tone-success"><i class="bx bx-check"></i></span>
                <div class="adc-feed-body">
                  <div class="adc-feed-title">{{ optional($p->user)->getFullName() ?? 'Claim' }} · {{ $money($p->net_payable ?? $p->total_amount) }}</div>
                  <div class="adc-feed-meta">{{ $p->claim_month }} · paid {{ \Carbon\Carbon::parse($p->paid_at)->diffForHumans() }}</div>
                </div>
              </li>
            @endforeach
          </ul>
        @endif
      </div>

      @include('tenant.users.dashboard._adc-important-contacts')

      {{-- Quick actions --}}
      <div class="adc-card">
        <div class="adc-card-head"><div><h6 class="adc-card-title">Quick Actions</h6></div></div>
        @php
          $actions = \App\Support\Nav::filter([
            ['url'=>url('/employee-setup'),'icon'=>'bx-id-card','label'=>'Attendance Setup'],
            ['url'=>url('/payroll'),'icon'=>'bx-money','label'=>'Run Payroll'],
            ['url'=>url('/travel-claims/admin/dashboard'),'icon'=>'bx-car','label'=>'Verify Claims'],
            ['url'=>url('/expenseRequests'),'icon'=>'bx-receipt','label'=>'Expenses'],
            ['url'=>url('/loan'),'icon'=>'bx-wallet','label'=>'Loans'],
            ['url'=>url('/attendance'),'icon'=>'bx-time-five','label'=>'Attendance'],
            ['url'=>url('/reports'),'icon'=>'bx-file','label'=>'Reports'],
            ['url'=>url('/my-tasks'),'icon'=>'bx-task','label'=>'My Tasks'],
          ]);
        @endphp
        <div class="adc-qa">
          @foreach($actions as $a)
            <a href="{{ $a['url'] }}" class="adc-qa-btn">
              <i class="bx {{ $a['icon'] }}"></i><span>{{ $a['label'] }}</span>
            </a>
          @endforeach
        </div>
      </div>
    </div>
  </div>
</div>

{{-- The adc-* card styling shared by every command-centre dashboard. Without
     this the whole page renders as unstyled text — admin, hr, manager and
     employee all include it; finance was missing it. --}}
@include('tenant.users.dashboard._adc-styles')
@include('tenant.users.dashboard._adc-live')

{{-- Same dialog the queue page and My Tasks use, so "Update" completes the
     task here instead of bouncing to another page. --}}
@include('tenant.employees._setup-modal')

@endsection

@section('page-script')
<script>
(function () {
  const payrollTrend  = @json($payrollTrendChart);
  const claimPipeline = @json($claimPipelineChart);

  function whenApex(cb, tries) {
    tries = tries || 0;
    if (typeof ApexCharts !== 'undefined') return cb();
    if (tries > 100) return;
    setTimeout(function () { whenApex(cb, tries + 1); }, 50);
  }

  whenApex(function () {
    const trendEl = document.querySelector('#finPayrollTrend');
    if (trendEl) {
      new ApexCharts(trendEl, {
        series: [{ name: 'Net Salary Paid', data: payrollTrend.data }],
        chart: { type: 'area', height: 300, toolbar: { show: false }, fontFamily: 'Public Sans, sans-serif' },
        colors: ['#0d9488'],
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.05 } },
        xaxis: { categories: payrollTrend.labels, labels: { style: { colors: '#64748b' } } },
        yaxis: { labels: { formatter: function (v) { return '₹' + Math.round(v).toLocaleString('en-IN'); } } },
        tooltip: { y: { formatter: function (v) { return '₹' + Math.round(v).toLocaleString('en-IN'); } } },
        grid: { borderColor: '#e2e8f0', strokeDashArray: 4 }
      }).render();
    }

    const pipeEl = document.querySelector('#finClaimPipeline');
    if (pipeEl) {
      new ApexCharts(pipeEl, {
        series: [{ name: 'Claims', data: claimPipeline.data }],
        chart: { type: 'bar', height: 300, toolbar: { show: false }, fontFamily: 'Public Sans, sans-serif' },
        plotOptions: { bar: { borderRadius: 6, horizontal: true, distributed: true, barHeight: '60%' } },
        colors: ['#94a3b8', '#f59e0b', '#3b82f6', '#10b981', '#ef4444'],
        dataLabels: { enabled: true },
        legend: { show: false },
        xaxis: { categories: claimPipeline.labels, labels: { style: { colors: '#64748b' } } },
        grid: { borderColor: '#e2e8f0', strokeDashArray: 4 }
      }).render();
    }
  });
})();
</script>
@endsection
