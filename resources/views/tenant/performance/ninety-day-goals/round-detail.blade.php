@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')

@section('title', '90-Day Goals — ' . (optional($round->program)->name ?? 'My Program'))

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
  $rowsByGoal = $d['rows']->keyBy(fn ($r) => $r->goal->id);
  $title = optional($round->program)->name ?: ('90-Day Goals — Round ' . $round->round_no);
  $isActive = $round->status === 'active';
  $target = (float) $d['cards']['target'];
  $achieved = (float) $d['cards']['achieved'];
  $pct = $d['cards']['pct'];
  $updatedThisWeek = $weekSummary['submitted'];
  $pendingThisWeek = $counts['total'] - $updatedThisWeek;
  $nextWeekIdx = min($weeks - 1, $currentWeek + 1);
  $daysLeft = max(0, $today->diffInDays($round->ends_on, false));
  // The expected stage for a customer — a 90-day target isn't only ever "an order by a date";
  // it might genuinely be a trial, a renewal, an upsell. Same list the Add Customer dropdown
  // uses, so "what we're aiming for" reads the same everywhere in this module.
  $stageOptions = ['Order', 'New Order', 'Regular Order', 'Repeat Order', 'Trial Order', 'Renewal', 'Upsell'];
  $stageChoices = fn ($current) => $current && !in_array($current, $stageOptions, true) ? array_merge($stageOptions, [$current]) : $stageOptions;

  // ── Phone app shell (below) reuses all of the above, plus: ──
  // "This week" per-goal classification — same three-way rule the controller already used to
  // build $weekSummary (NinetyDayGoalController.php roundDetail(), ~line 855), just kept
  // per-goal instead of only summed, so the mobile lists and that donut always agree.
  $thisWeekDue = $dates[$currentWeek];
  $weekState = function ($g) use ($currentWeek, $thisWeekDue, $today) {
    $ck = $g->checkins->firstWhere('week_no', $currentWeek);
    if (!$ck) {
      return 'notStarted';
    }
    if ($ck->status !== null && $ck->status !== '') {
      return 'submitted';
    }
    return $thisWeekDue->lt($today) ? 'overdue' : 'pending';
  };
  // The mockup's funnel stepper (Enquiry/Trial Order/Order/Upsell) has no real field behind it —
  // derive it from signals this module already treats as meaningful: the same "converted"
  // statuses used client-side (ORDER_STATUSES, further down this file), plus the existing
  // repeat/upsell flag already shown on the Orders & Results tab.
  $stepLabels = ['Enquiry', 'Trial Order', 'Order', 'Upsell'];
  $stepOf = function ($g) use ($rowsByGoal) {
    $r = $rowsByGoal->get($g->id);
    $ever = $g->checkins->pluck('status')->filter()->all();
    if ($r && $r->repeat) {
      return 4;
    }
    if (($r && $r->achieved > 0) || in_array('Regular Order', $ever, true)) {
      return 3;
    }
    return in_array('Trial Order', $ever, true) ? 2 : 1;
  };
  $topThisWeek = $rowsByGoal->sortByDesc(fn ($r) => (float) $r->achieved)->take(5);
  $pendingGoals = $goals->filter(fn ($g) => in_array($weekState($g), ['pending', 'overdue'], true));
@endphp
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4 mx-4">
    <div>
      <nav class="small text-muted mb-1">
        <a href="{{ route('tenant.dashboard') }}" class="text-muted">Dashboard</a> <i class="bx bx-chevron-right"></i>
        @if($round->program_id && \App\Services\NinetyDayGoalService::seesEveryone(auth()->user()))
          <a href="{{ route('performance.ninety-day-goals.program', $round->program_id) }}" class="text-muted">{{ optional($round->program)->name ?? 'Program' }}</a> <i class="bx bx-chevron-right"></i>
        @else
          <a href="{{ route('performance.ninety-day-goals.my-dashboard') }}" class="text-muted">My 90-Day Goals</a> <i class="bx bx-chevron-right"></i>
        @endif
        {{ $title }} <i class="bx bx-chevron-right"></i> Weekly Submission
      </nav>
      <h3 class="fw-extrabold mb-1 text-dark d-flex align-items-center gap-2">
        {{ $title }}
        <span class="badge rounded-pill fw-bold" style="font-size:.62rem;background:{{ $isActive ? '#d7f3ee' : '#e2e8f0' }};color:{{ $isActive ? '#0b5b48' : '#475569' }}">{{ ucfirst($round->status) }}</span>
      </h3>
      <p class="text-muted mb-0 small">{{ $round->starts_on->format('d M Y') }} – {{ $round->ends_on->format('d M Y') }} ({{ $weeks * 7 }} days) · Submit your weekly updates, track progress and achieve your sales goals.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <div class="ndg-infobox"><div class="small text-muted fw-semibold">Current Week</div><div class="fw-bold text-dark fs-5">W{{ $currentWeek + 1 }}</div><div class="small text-muted">{{ $dates[$currentWeek]->format('d M') }} – {{ $dates[$currentWeek]->copy()->addDays(6)->format('d M Y') }}</div></div>
      <div class="ndg-infobox"><div class="small text-muted fw-semibold">Next Week</div><div class="fw-bold text-dark fs-5">W{{ $nextWeekIdx + 1 }}</div><div class="small text-muted">{{ $dates[$nextWeekIdx]->format('d M') }} – {{ $dates[$nextWeekIdx]->copy()->addDays(6)->format('d M Y') }}</div></div>
      <div class="ndg-infobox"><div class="small text-muted fw-semibold"><i class="bx bx-calendar"></i> Days Left</div><div class="fw-bold text-dark fs-5">{{ $daysLeft }}</div><div class="small text-muted">&nbsp;</div></div>
    </div>
  </div>

  <div class="px-4">

  {{-- This whole stretch — the desktop KPI row, the pending-upsell banner, the customer-rules
       strip — is recreated (the parts that matter) in the phone app shell further down, so it
       would otherwise show twice on a phone. Nothing here is ever toggled by JS, so a plain
       d-none/d-md-block pair is safe (unlike .ndg-rd-section below, which JS does toggle). --}}
  <div class="d-none d-md-block">

  {{-- Order detected from the Sales Report a second time for a customer that already had one:
       never assumed to be an upsell, confirmed here first. --}}
  @if(($pendingUpsells ?? collect())->isNotEmpty() && ($canEdit ?? false))
    <div class="hitech-card mb-6 mx-4" style="border-color:#f5a623">
      <div class="card-body p-4 border-bottom">
        <h6 class="fw-bold mb-0 text-dark"><i class="bx bx-receipt" style="color:#f5a623"></i> {{ $pendingUpsells->count() }} order{{ $pendingUpsells->count() === 1 ? '' : 's' }} detected from the Sales Report need confirming</h6>
        <div class="small text-muted">A customer already had one order this round, and the Sales Report shows another. Confirm it is a repeat order / upsell, or leave it to fill in yourself.</div>
      </div>
      <div class="card-body p-0">
        @foreach($pendingUpsells as $ck)
          <div class="ndg-upsell-row" data-checkin="{{ $ck->id }}">
            <div class="flex-grow-1">
              <div class="fw-semibold text-dark">{{ optional($ck->goal)->client_name }} <span class="text-muted fw-normal">· W{{ $ck->week_no + 1 }}, due {{ optional($ck->due_on)->format('d M') }}</span></div>
              <div class="small text-muted">{{ optional($ck->goal)->product }} · {{ optional($ck->goal)->brand }}</div>
            </div>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-sm btn-hitech ndg-upsell-yes">Yes, it's an upsell</button>
              <button type="button" class="btn btn-sm btn-hitech-cancel ndg-upsell-no">No</button>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  @endif

  {{-- KPI cards + Submit All Updates --}}
  <div class="row g-3 mb-6 align-items-stretch">
    <div class="col-6 col-lg">
      <div class="ndg-kpi3"><div class="ndg-kpi3-icon" style="background:#e0f2fe;color:#0369a1"><i class="bx bx-group"></i></div><div><div class="small text-muted fw-semibold">My Customers</div><div class="ndg-kpi3-v">{{ $counts['total'] }}</div></div></div>
    </div>
    <div class="col-6 col-lg">
      <div class="ndg-kpi3"><div class="ndg-kpi3-icon" style="background:#fff7ed;color:#c2540c"><i class="bx bx-rupee"></i></div><div><div class="small text-muted fw-semibold">My Target</div><div class="ndg-kpi3-v">{{ '₹' . $fmtL($target) . ' L' }}</div></div></div>
    </div>
    <div class="col-6 col-lg">
      <div class="ndg-kpi3"><div class="ndg-kpi3-icon" style="background:#dcfce7;color:#166534"><i class="bx bx-bar-chart-alt-2"></i></div>
        <div class="flex-grow-1"><div class="small text-muted fw-semibold">My Achieved</div><div class="ndg-kpi3-v">{{ '₹' . $fmtL($achieved) . ' L' }}</div>
          <div class="d-flex align-items-center gap-2 mt-1"><div class="ndg-track flex-grow-1" style="height:6px"><div class="ndg-fill" style="width:{{ $pct === null ? 0 : min(100, $pct) }}%;background:{{ $pctColor($pct) }}"></div></div><b class="small" style="color:{{ $pctColor($pct) }}">{{ $pctText($pct) }}</b></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg">
      <div class="ndg-kpi3"><div class="ndg-kpi3-icon" style="background:#ede9fe;color:#5b21b6"><i class="bx bx-file"></i></div><div><div class="small text-muted fw-semibold">Updated This Week</div><div class="ndg-kpi3-v">{{ $updatedThisWeek }} / {{ $counts['total'] }}</div></div></div>
    </div>
    <div class="col-6 col-lg">
      <div class="ndg-kpi3"><div class="ndg-kpi3-icon" style="background:#fee2e2;color:#b42318"><i class="bx bx-hourglass"></i></div><div><div class="small text-muted fw-semibold">Pending Updates</div><div class="ndg-kpi3-v">{{ $pendingThisWeek }}</div></div></div>
    </div>
    @if($canEdit && $isActive)
    <div class="col-12 col-lg-auto d-flex align-items-stretch">
      <button type="button" class="btn-hitech rounded-pill px-4 d-flex align-items-center justify-content-center gap-2 w-100" id="ndgSubmitAllTop"><i class="bx bx-send"></i> Submit All Updates</button>
    </div>
    @endif
  </div>

  {{-- The customer rules, always in view: how many customers the round needs, whether the
       window to add them is open (with a live countdown), and what can still be edited. --}}
  @php
    $w = $window;
    $nLocked = count($lockedTargetIds);
    $nGoals = $goals->whereNull('closed_at')->count();
    // Can this viewer add right now? Then every red alert opens the add-customer popup.
    $canAddNow = $canEdit && $isActive && ($w['open'] || $managesAll);
    $addAttrs = $canAddNow ? 'role=button tabindex=0 data-bs-toggle=modal data-bs-target=#ndgRdAddModal title="Click to add customers"' : '';
  @endphp
  @if(!$w['meets_min'] && $isActive)
    {{-- Short of the minimum: a red alert, clickable straight into the add-customer popup. --}}
    <div class="ndg-alert-red mb-3 {{ $canAddNow ? 'ndg-alert-click' : '' }}" id="ndgMinAlert" {!! $addAttrs !!}>
      <i class="bx bx-error-circle fs-3"></i>
      <div class="flex-grow-1">
        <div class="fw-bold">This round is incomplete - add {{ $w['short_by'] }} more {{ \Illuminate\Support\Str::plural('customer', $w['short_by']) }} (minimum {{ $w['min'] }}, you have {{ $w['count'] }})</div>
        <div class="small">
          @if($w['open'])
            The window to add customers closes {{ $w['closes_at']->format('d M Y, h:i A') }} - a round below {{ $w['min'] }} customers is not counted.
          @else
            The window to add customers closed {{ $w['closes_at']->format('d M Y, h:i A') }}.@unless($managesAll) Ask admin/HR to reopen it.@endunless
          @endif
        </div>
      </div>
      @if($canAddNow)
        <span class="btn btn-hitech-danger rounded-pill px-4 d-flex align-items-center gap-2 flex-shrink-0"><i class="bx bx-plus"></i> Add customers now</span>
      @endif
    </div>
  @endif
  <div class="row g-3 mb-6" id="ndgRulesStrip">
    <div class="col-12 col-md-4">
      <div class="ndg-kpi3 h-100 {{ $w['meets_min'] ? '' : 'ndg-kpi3-red' }} {{ !$w['meets_min'] && $canAddNow ? 'ndg-alert-click' : '' }}" {!! !$w['meets_min'] ? $addAttrs : '' !!}>
        <div class="ndg-kpi3-icon" style="background:{{ $w['meets_min'] ? '#dcfce7;color:#166534' : '#ffe4e6;color:#e11d48' }}"><i class="bx {{ $w['meets_min'] ? 'bx-check-circle' : 'bx-error-circle' }}"></i></div>
        <div>
          <div class="small text-muted fw-semibold">Customers (minimum {{ $w['min'] }})</div>
          <div class="ndg-kpi3-v">{{ $w['count'] }} / {{ $w['min'] }}
            @if($w['meets_min'])
              <span class="badge bg-label-success align-middle" style="font-size:.65rem">Complete</span>
            @else
              <span class="badge bg-label-danger align-middle" style="font-size:.65rem">Incomplete</span>
            @endif
          </div>
          <div class="small text-muted">
            @if($w['meets_min'])
              The round counts. You can keep adding while the window is open.
            @else
              Add {{ $w['short_by'] }} more {{ \Illuminate\Support\Str::plural('customer', $w['short_by']) }} - until then this round does not count.
            @endif
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="ndg-kpi3 h-100 {{ $w['meets_min'] ? '' : 'ndg-kpi3-red' }} {{ !$w['meets_min'] && $canAddNow ? 'ndg-alert-click' : '' }}" {!! !$w['meets_min'] ? $addAttrs : '' !!}>
        <div class="ndg-kpi3-icon" style="background:{{ $w['meets_min'] ? ($w['open'] ? '#e0f2f1;color:#007a7a' : '#e2e8f0;color:#475569') : '#ffe4e6;color:#e11d48' }}"><i class="bx {{ $w['open'] ? 'bx-time-five' : 'bx-lock-alt' }}"></i></div>
        <div>
          <div class="small text-muted fw-semibold">Window to add customers</div>
          @if($w['open'] && $isActive)
            <div class="ndg-kpi3-v" id="ndgWinCountdown" data-closes="{{ $w['closes_at']->toIso8601String() }}">{{ $w['hours_left'] }}h left</div>
            <div class="small text-muted">Closes {{ $w['closes_at']->format('d M Y, h:i A') }}</div>
          @else
            <div class="ndg-kpi3-v">Closed</div>
            <div class="small text-muted">Closed {{ $w['closes_at']->format('d M Y, h:i A') }}.@unless($managesAll) Ask admin/HR to reopen it if you need to add more.@endunless</div>
          @endif
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="ndg-kpi3 h-100">
        <div class="ndg-kpi3-icon" style="background:#ede9fe;color:#5b21b6"><i class="bx bx-edit"></i></div>
        <div>
          <div class="small text-muted fw-semibold">Editing customers</div>
          @if(!$isActive)
            <div class="ndg-kpi3-v">Locked</div>
            <div class="small text-muted">This round has ended.</div>
          @elseif($managesAll)
            <div class="ndg-kpi3-v">Open</div>
            <div class="small text-muted">Admin/HR can change any customer, including fixed targets.</div>
          @else
            <div class="ndg-kpi3-v">{{ $nGoals - $nLocked }} of {{ $nGoals }} targets editable</div>
            <div class="small text-muted">Product, brand, stage and expected date can be edited until the round ends. A customer's target is fixed once you report a status on it. Customers can't be removed.</div>
          @endif
        </div>
      </div>
    </div>
  </div>

  </div>

  {{-- Sub-tabs --}}
  <div class="d-flex gap-2 flex-wrap mb-6 ndg-subtabs" id="ndgRdTabs">
    @foreach(['weekly' => ['Weekly Submission','bx-calendar-check'], 'customers' => ['Customer Summary','bx-list-ul'], 'orders' => ['Orders & Results','bx-cart-download'], 'details' => ['Program Details','bx-info-circle']] as $key => [$lbl, $ic])
      <button type="button" class="ndg-subtab {{ $key === 'weekly' ? 'on' : '' }}" data-tab="{{ $key }}"><i class="bx {{ $ic }}"></i>{{ $lbl }}</button>
    @endforeach
  </div>

  {{-- ═══════════════════ Weekly Submission ═══════════════════ --}}
  <div class="ndg-rd-section" data-section="weekly">

    {{-- Week strip — a future week has nothing to submit yet, so it's shown but not a link. --}}
    <div class="card-body p-0 d-flex gap-2 mb-6 ndg-week-strip" id="ndgRdWeekStrip">
      @foreach($weekStrip as $w)
        @if($w['week'] > $currentWeek)
          <span class="ndg-week-pill is-future" title="Not due yet">
            <div class="fw-bold">W{{ $w['week'] + 1 }}</div>
            <div class="small">{{ $dates[$w['week']]->format('d-M') }}</div>
            <div class="small">—</div>
          </span>
        @else
          <a href="?{{ http_build_query(['w' => $w['week']]) }}" class="ndg-week-pill {{ $w['week'] === $selectedWeek ? 'on' : '' }} {{ $w['week'] === $currentWeek ? 'is-current' : '' }}">
            <div class="fw-bold">W{{ $w['week'] + 1 }}</div>
            <div class="small">{{ $dates[$w['week']]->format('d-M') }}</div>
            <div class="small">{{ $w['total'] ? $w['pct'] . '%' : '—' }}</div>
          </a>
        @endif
      @endforeach
    </div>

    <div class="row g-4">
      <div class="col-xl-9">
        <div class="hitech-card mb-6">
          <div class="card-body p-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
              <div class="fw-bold text-dark">Weekly Updates — Week {{ $selectedWeek }} ({{ $weekDue->format('d M') }} – {{ $weekDue->copy()->addDays(6)->format('d M Y') }})</div>
              <div class="small text-muted" id="ndgRdMsg"></div>
            </div>
            <div class="d-flex align-items-center gap-2">
              @if($weekDue->gt($today))
                <span class="badge badge-hitech badge-brand-soft">Not due yet</span>
              @elseif($weekDue->lt($today))
                <span class="badge badge-hitech badge-hitech-danger">Monday has passed — a comment is needed with any new status</span>
              @else
                <span class="badge badge-hitech badge-brand-soft">Due today</span>
              @endif
              @if($canEdit && $isActive && ($window['open'] || $managesAll))
                <button type="button" class="btn-hitech rounded-pill px-3 py-2 d-flex align-items-center gap-2" style="font-size:.8rem" data-bs-toggle="modal" data-bs-target="#ndgRdAddModal"><i class="bx bx-plus"></i> Add Customer</button>
              @elseif($canEdit && $isActive)
                <span class="small text-muted" title="The window to add customers closed {{ $window['closes_at']->format('d M, h:i A') }}"><i class="bx bx-lock-alt"></i> Adding closed</span>
              @endif
            </div>
          </div>
          <div class="card-body p-0 ndg-mx-scroll">
            <table class="table align-middle mb-0" id="ndgWeeklyTable" style="min-width:1100px">
              <thead class="small text-uppercase text-muted">
                <tr>
                  <th class="ps-3">#</th><th style="min-width:160px">Customer</th><th>Product</th><th class="text-end">Target (₹ L)</th><th style="min-width:150px">Expected Stage</th>
                  <th style="min-width:150px">Status (W{{ $selectedWeek + 1 }})</th><th style="min-width:200px">Comment / Remark</th><th style="min-width:160px">Next Action</th><th class="pe-3"></th>
                </tr>
              </thead>
              <tbody>
              @forelse($goals as $i => $g)
                @php $ck = $g->checkins->firstWhere('week_no', $selectedWeek); @endphp
                <tr class="ndg-wk-row" data-goal="{{ $g->id }}">
                  <td class="ps-3 text-muted" data-label="#">{{ $i + 1 }}</td>
                  <td class="fw-semibold text-dark" data-label="Customer">{{ $g->client_name }}</td>
                  <td class="small text-muted" data-label="Product">{{ $g->product }}</td>
                  <td class="text-end" data-label="Target (₹ L)">{{ $fmtL($g->monthly_potential) }}</td>
                  <td data-label="Expected Stage">
                    @php $curStage = $g->goal ?: 'Order'; @endphp
                    <select class="form-select form-select-hitech ndg-wk-expected" data-orig="{{ $curStage }}" {{ $canEdit && $isActive ? '' : 'disabled' }}>
                      @foreach($stageChoices($curStage) as $stage)
                        <option value="{{ $stage }}" {{ $curStage === $stage ? 'selected' : '' }}>{{ $stage }}</option>
                      @endforeach
                    </select>
                  </td>
                  <td data-label="Status (W{{ $selectedWeek + 1 }})">
                    <div class="ndg-td-value">
                      <select class="form-select form-select-hitech ndg-wk-status" {{ $canEdit && $isActive ? '' : 'disabled' }}>
                        <option value="">Choose…</option>
                        @foreach($statuses as $s)<option value="{{ $s }}" {{ optional($ck)->status === $s ? 'selected' : '' }}>{{ $s }}</option>@endforeach
                      </select>
                      @if($rowsByGoal->has($g->id))<div class="mt-1">@include('tenant.performance.ninety-day-goals.partials.order-check', ['r' => $rowsByGoal[$g->id]])</div>@endif
                    </div>
                  </td>
                  <td data-label="Comment"><input type="text" class="form-control ndg-wk-comment" maxlength="1000" placeholder="Optional note" value="{{ optional($ck)->comment }}" {{ $canEdit && $isActive ? '' : 'disabled' }}></td>
                  <td data-label="Next Action"><input type="text" class="form-control ndg-wk-next-action" maxlength="255" placeholder="e.g. Share quotation" value="{{ optional($ck)->next_action }}" {{ $canEdit && $isActive ? '' : 'disabled' }}></td>
                  <td class="pe-3 text-end ndg-td-actions">
                    @if($canEdit && $isActive)
                      <button type="button" class="btn btn-sm btn-hitech-cancel ndg-wk-save w-100">Save</button>
                    @endif
                  </td>
                </tr>
              @empty
                <tr><td colspan="9" class="text-center text-muted p-5">No target customers yet — add one from Customer Summary.</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
          @if($canEdit && $isActive && $goals->isNotEmpty())
          <div class="card-body pt-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <button type="button" class="btn btn-hitech-cancel rounded-pill px-4" id="ndgWkPrev"><i class="bx bx-chevron-left"></i> Previous Week</button>
            <div class="d-flex gap-2">
              <button type="button" class="btn-hitech rounded-pill px-5" id="ndgSubmitAllBottom"><i class="bx bx-send"></i> Submit All Updates</button>
            </div>
          </div>
          @endif
        </div>
      </div>

      <div class="col-xl-3">
        <div class="hitech-card mb-4">
          <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Week {{ $selectedWeek }} Progress</div></div>
          <div class="card-body p-3 text-center"><div id="ndgWeekDonut"></div></div>
        </div>
        <div class="hitech-card mb-4" style="background:#fffbeb;border-color:#fde68a">
          <div class="card-body p-3 small">
            <i class="bx bx-bulb" style="color:#f5a623"></i> Please submit your weekly updates by Monday, 11:59 PM. Your updates help in tracking progress and enable better support from the team.
          </div>
        </div>
        <div class="hitech-card">
          <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Status Reference</div></div>
          <div class="card-body p-3">
            @foreach($G::STATUS_COLORS as $st => [$bg, $fg])
              <div class="d-flex align-items-center gap-2 small mb-2">
                <span style="width:9px;height:9px;border-radius:50%;display:inline-block;background:{{ $fg }};flex:none"></span>
                <span><b>{{ $st }}</b> — {{ ['On Track' => 'Progress as planned', 'Off Track' => 'Significant delay', 'Trial Order' => 'Trial order placed', 'Regular Order' => 'Order received', 'No Report' => 'Not updated this week', 'Dropped' => 'Not interested', 'Transferred' => 'Moved to another team'][$st] }}</span>
              </div>
            @endforeach
          </div>
        </div>
      </div>
    </div>

    <div class="hitech-card">
      <div class="card-body p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div><div class="fw-bold text-dark">Need Help?</div><div class="small text-muted">Contact your Sales Head or check the program guide.</div></div>
        <button type="button" class="btn btn-hitech-cancel rounded-pill px-4">View Guide</button>
      </div>
    </div>
  </div>

  {{-- ═══════════════════ Customer Summary ═══════════════════ --}}
  <div class="ndg-rd-section d-none" data-section="customers">

    {{-- KPI cards --}}
    <div class="row g-3 mb-6">
      @php
        $kpis = [
          ['bx-building-house', $counts['total'], 'Total Customers', '#e0f2fe', '#0369a1'],
          ['bx-file', $counts['orders'], 'Orders Received', '#dcfce7', '#166534'],
          ['bx-loader-circle', $counts['inProgress'], 'In Progress', '#e0f2fe', '#0369a1'],
          ['bx-error-alt', $counts['atRisk'], 'At Risk', '#fee2e2', '#b42318'],
          ['bx-shield-x', $counts['dropped'], 'Dropped', '#ede9fe', '#5b21b6'],
          ['bx-target-lock', $fmtL($achieved) . ' L', 'Achieved Value', '#ccfbf1', '#0f766e'],
          ['bx-pie-chart-alt-2', $pctText($pct), 'Target Achievement', '#dbeafe', '#1d4ed8'],
        ];
      @endphp
      @foreach($kpis as [$icon, $val, $lbl, $bg, $fg])
        <div class="col-6 col-md-4 col-xl">
          <div class="ndg-kpi2">
            <div class="ndg-kpi2-icon" style="background:{{ $bg }};color:{{ $fg }}"><i class="bx {{ $icon }}"></i></div>
            <div><div class="ndg-kpi2-v">{{ $val }}</div><div class="ndg-kpi2-l">{{ $lbl }}</div></div>
          </div>
        </div>
      @endforeach
    </div>

    {{-- Filters --}}
    <div class="hitech-card mb-4">
      <div class="card-body p-3 d-flex flex-wrap align-items-center gap-2">
        <div class="flex-grow-1" style="min-width:200px">
          <input type="search" id="ndgRdSearch" class="form-control" placeholder="Search customer, product…">
        </div>
        <select id="ndgRdStatus" class="form-select form-select-hitech" style="max-width:160px">
          <option value="">All Statuses</option>
          @foreach($statuses as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach
        </select>
        <select id="ndgRdBrand" class="form-select form-select-hitech" style="max-width:150px">
          <option value="">All Products</option>
          @foreach($brands as $b)<option value="{{ $b }}">{{ $b }}</option>@endforeach
        </select>
        <span class="small text-muted" id="ndgRdCount"></span>
        <div class="ms-auto d-flex gap-2">
          @if($canEdit && $isActive && ($window['open'] || $managesAll))
          <button type="button" class="btn-hitech rounded-pill px-4 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#ndgRdAddModal"><i class="bx bx-plus"></i> Add Customer</button>
          @endif
          @if($isAdmin)
          <a href="{{ route('performance.ninety-day-goals.admin-view.export', ['user_id' => $round->user_id, 'round_id' => $round->id]) }}" class="btn btn-hitech-cancel rounded-pill px-4 d-flex align-items-center gap-2"><i class="bx bx-download"></i> Export</a>
          @endif
        </div>
      </div>
    </div>

    {{-- Customer table --}}
    <div class="hitech-card mb-6">
      <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Customer Targets &amp; Weekly Updates</div></div>
      <div class="card-body p-0 ndg-mx-scroll">
        <table class="table align-middle mb-0" id="ndgRdTable" style="min-width:1100px">
          <thead>
            <tr class="small text-uppercase text-muted">
              <th style="width:30px"></th>
              <th class="ps-3">#</th><th>Customer</th><th>Product</th><th class="text-end">Goal (₹ L)</th><th>Expected Order</th>
              @for($w = 0; $w < $weeks; $w++)<th class="text-center">W{{ $w + 1 }}</th>@endfor
              <th class="text-end">Actual Order (₹ L)</th><th>Status</th><th class="pe-3"></th>
            </tr>
          </thead>
          <tbody>
          @foreach($goals as $i => $g)
            @php
              $r = $rowsByGoal->get($g->id);
              $cks = $g->checkins->keyBy('week_no');
              $status = optional($r)->status;
              $bucket = $droppedGoalIds->contains($g->id) ? 'dropped' : ($r && $r->achieved > 0 ? 'orders' : ($atRiskGoalIds->contains($g->id) ? 'risk' : 'progress'));
              $bucketLabel = ['dropped' => 'Dropped', 'orders' => optional($r)->status ?: 'Order', 'risk' => 'At Risk', 'progress' => 'In Progress'][$bucket];
              $bucketColor = ['dropped' => ['#ede9fe', '#5b21b6'], 'orders' => ['#dcfce7', '#166534'], 'risk' => ['#fee2e2', '#b42318'], 'progress' => ['#e0f2fe', '#0369a1']][$bucket];
            @endphp
            <tr class="ndg-rd-row" data-goal="{{ $g->id }}" data-q="{{ strtolower($g->client_name . ' ' . $g->product) }}" data-status="{{ $status }}" data-brand="{{ $g->brand }}">
              <td class="ps-3 ndg-td-actions"><button type="button" class="ndg-rd-expand" data-target="ndgRdExp{{ $g->id }}"><i class="bx bx-chevron-down"></i></button></td>
              <td class="text-muted" data-label="#">{{ $i + 1 }}</td>
              <td class="fw-semibold text-dark" data-label="Customer">{{ $g->client_name }}@if($g->pendingDeleteRequest)<span class="badge badge-hitech ms-1" style="background:#fee2e2;color:#b42318;font-size:.6rem" title="Removal requested">Removal pending</span>@endif</td>
              <td class="small text-muted" data-label="Product">{{ $g->product }}</td>
              <td class="text-end" data-label="Goal (₹ L)">{{ $fmtL($g->monthly_potential) }}</td>
              <td class="small text-muted" data-label="Expected Order">{{ $g->expected_order_date ? $g->expected_order_date->format('d M Y') : '—' }}</td>
              @for($w = 0; $w < $weeks; $w++)
                @php
                  $ck = $cks->get($w);
                  $st = optional($ck)->status;
                  [$bg, $fg] = $st ? ($G::STATUS_COLORS[$st] ?? ['#94a3b8', '#fff']) : [null, null];
                @endphp
                <td class="text-center" data-label="W{{ $w + 1 }}">
                  @if($st)<span class="ndg-chip" style="background:{{ $bg }};color:{{ $fg }};font-size:.62rem;padding:2px 6px" title="{{ $st }}">{{ \Illuminate\Support\Str::limit($st, 8, '') }}</span>
                  @else<span class="ndg-none">-</span>@endif
                </td>
              @endfor
              <td class="text-end fw-semibold" data-label="Actual Order (₹ L)">{{ $r ? $fmtL($r->achieved) : 0 }}</td>
              <td data-label="Status"><span class="ndg-chip" style="background:{{ $bucketColor[0] }};color:{{ $bucketColor[1] }}">{{ $bucketLabel }}</span></td>
              <td class="pe-3 text-end ndg-td-actions">
                <div class="dropdown">
                  <button class="btn btn-sm btn-icon" data-bs-toggle="dropdown"><i class="bx bx-dots-vertical-rounded"></i></button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li><button type="button" class="dropdown-item ndg-rd-expand" data-target="ndgRdExp{{ $g->id }}">View details</button></li>
                    @if($canEdit)<li><button type="button" class="dropdown-item ndg-edit-goal" data-id="{{ $g->id }}" data-product="{{ e($g->product) }}" data-brand="{{ e($g->brand) }}" data-goal="{{ e($g->goal ?: $g->goal_type) }}" data-potential="{{ $g->monthly_potential }}" data-expected="{{ optional($g->expected_order_date)->format('Y-m-d') }}" data-title="{{ e($g->client_name) }}">Edit</button></li>@endif
                    @if($managesAll)
                      @if($g->pendingDeleteRequest)
                        <li><button type="button" class="dropdown-item text-success ndg-decide-delete" data-id="{{ $g->pendingDeleteRequest->id }}" data-status="approved" data-title="{{ e($g->client_name) }}">Approve removal request</button></li>
                        <li><button type="button" class="dropdown-item text-danger ndg-decide-delete" data-id="{{ $g->pendingDeleteRequest->id }}" data-status="rejected" data-title="{{ e($g->client_name) }}">Reject removal request</button></li>
                      @else
                        <li><button type="button" class="dropdown-item text-danger ndg-remove-goal" data-id="{{ $g->id }}" data-title="{{ e($g->client_name) }}">Remove customer</button></li>
                      @endif
                    @elseif($canEdit)
                      @if($g->pendingDeleteRequest)
                        <li><span class="dropdown-item disabled text-muted">Removal requested</span></li>
                      @else
                        <li><button type="button" class="dropdown-item text-danger ndg-request-delete" data-id="{{ $g->id }}" data-title="{{ e($g->client_name) }}">Request removal</button></li>
                      @endif
                    @endif
                  </ul>
                </div>
              </td>
            </tr>
            <tr class="ndg-rd-exp d-none" id="ndgRdExp{{ $g->id }}">
              <td colspan="{{ 8 + $weeks }}" class="p-0">
                <div class="ndg-rd-weekly">
                  <div class="small fw-bold text-muted text-uppercase mb-2"><i class="bx bx-message-square-detail"></i> Weekly Comments</div>
                  <div class="d-flex flex-wrap gap-3">
                    @forelse($g->checkins->whereNotNull('status') as $ck)
                      <div class="ndg-rd-wk-card">
                        <div class="small text-muted">W{{ $ck->week_no + 1 }} · {{ $ck->due_on->format('d M') }}</div>
                        <span class="ndg-chip mt-1" style="background:{{ $G::STATUS_COLORS[$ck->status][0] ?? '#e2e8f0' }};color:{{ $G::STATUS_COLORS[$ck->status][1] ?? '#475569' }}">{{ $ck->status }}</span>
                        <div class="small mt-1">{{ $ck->comment ?: 'No comment added.' }}</div>
                        @if($ck->next_action)<div class="small mt-1 text-muted"><b>Next:</b> {{ $ck->next_action }}@if($ck->next_follow_up_date) · {{ $ck->next_follow_up_date->format('d M') }}@endif</div>@endif
                      </div>
                    @empty
                      <div class="small text-muted">No weekly updates submitted yet.</div>
                    @endforelse
                  </div>
                </div>
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- Bottom detail panel: one per customer, shown/hidden by the expand toggle (desktop) or
       tapping a customer card (phone, where it becomes a full-screen overlay — see the
       @media(max-width:767.98px) block below). Lives OUTSIDE the Customer Summary section so a
       phone-width media query can hide that section's table/filters while still showing this —
       display:none on an ancestor can't be selectively undone for a child, so it has to be a
       sibling. It still carries data-section="customers" so the desktop sub-tab JS
       (querySelectorAll('.ndg-rd-section')) keeps toggling it together with that tab, same as
       before this file was ever split. --}}
  <div class="ndg-rd-section d-none" data-section="customers" id="ndgRdDetail">
      @foreach($goals as $g)
        @php $r = $rowsByGoal->get($g->id); $step = $stepOf($g); @endphp
        <div class="ndg-rd-detail-panel d-none" data-goal="{{ $g->id }}">
          <div class="ndg-mob-detail-head d-md-none">
            <button type="button" class="ndg-mob-back"><i class="bx bx-arrow-back"></i></button>
            <div class="flex-grow-1">
              <div class="fw-bold text-dark">{{ $g->client_name }}@if(optional($r)->status)<span class="ndg-chip ms-2" style="background:{{ $G::STATUS_COLORS[$r->status][0] ?? '#e2e8f0' }};color:{{ $G::STATUS_COLORS[$r->status][1] ?? '#475569' }}">{{ $r->status }}</span>@endif</div>
              <div class="small text-muted">{{ $g->brand }} · {{ $g->product }}</div>
            </div>
          </div>
          <div class="ndg-mob-stepper d-md-none">
            @foreach($stepLabels as $i => $lbl)
              <div class="ndg-mob-step {{ $i + 1 <= $step ? 'on' : '' }}"><span>{{ $i + 1 }}</span><small>{{ $lbl }}</small></div>
              @if(!$loop->last)<div class="ndg-mob-step-line {{ $i + 1 < $step ? 'on' : '' }}"></div>@endif
            @endforeach
          </div>
          <div class="hitech-card mb-3 d-md-none">
            <div class="card-body p-3">
              <div class="fw-bold text-dark mb-2">Targets &amp; Achievement</div>
              <div class="d-flex justify-content-between small"><span class="text-muted">Target</span><b>₹{{ $fmtL(optional($r)->target ?? $g->monthly_potential) }} L</b></div>
              <div class="d-flex justify-content-between small"><span class="text-muted">Achieved</span><b>₹{{ $fmtL(optional($r)->achieved ?? 0) }} L</b></div>
              <div class="mt-2"><div class="ndg-track"><div class="ndg-fill" style="width:{{ optional($r)->pct === null ? 0 : min(100, $r->pct) }}%;background:{{ $pctColor(optional($r)->pct) }}"></div></div></div>
            </div>
          </div>
          @if($canEdit && $isActive)
          @php $gck = $g->checkins->firstWhere('week_no', $selectedWeek); @endphp
          <div class="hitech-card mb-3 d-md-none ndg-mob-edit-card" data-goal="{{ $g->id }}">
            <div class="card-body p-3">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="fw-bold text-dark">Week {{ $selectedWeek + 1 }} ({{ $weekDue->format('d M') }})</div>
                <div class="d-flex gap-1">
                  <a href="?w={{ max(0, $selectedWeek - 1) }}" class="btn btn-sm btn-icon btn-hitech-cancel"><i class="bx bx-chevron-left"></i></a>
                  @if($selectedWeek < $currentWeek)<a href="?w={{ $selectedWeek + 1 }}" class="btn btn-sm btn-icon btn-hitech-cancel"><i class="bx bx-chevron-right"></i></a>@endif
                </div>
              </div>
              <label class="small text-muted fw-semibold mb-1">Expected Stage</label>
              <select class="form-select form-select-hitech ndg-mob-stage mb-2">
                @foreach($stageChoices($g->goal ?: 'Order') as $stage)<option value="{{ $stage }}" {{ ($g->goal ?: 'Order') === $stage ? 'selected' : '' }}>{{ $stage }}</option>@endforeach
              </select>
              <label class="small text-muted fw-semibold mb-1">Status</label>
              <select class="form-select form-select-hitech ndg-mob-status mb-2">
                <option value="">Choose…</option>
                @foreach($statuses as $s)<option value="{{ $s }}" {{ optional($gck)->status === $s ? 'selected' : '' }}>{{ $s }}</option>@endforeach
              </select>
              <label class="small text-muted fw-semibold mb-1">Comment / Remark</label>
              <textarea class="form-control ndg-mob-comment mb-2" rows="2" maxlength="1000" placeholder="Optional note">{{ optional($gck)->comment }}</textarea>
              <button type="button" class="btn-hitech rounded-pill w-100 ndg-mob-save">Save Update</button>
            </div>
          </div>
          @endif
          @if($canEdit || $managesAll)
          <div class="hitech-card mb-3 d-md-none">
            <div class="card-body p-3">
              <div class="fw-bold text-dark mb-2">Quick Actions</div>
              <div class="d-grid gap-2">
                @if($canEdit)
                  <button type="button" class="btn btn-hitech-cancel rounded-pill text-start ndg-edit-goal" data-id="{{ $g->id }}" data-product="{{ e($g->product) }}" data-brand="{{ e($g->brand) }}" data-goal="{{ e($g->goal ?: $g->goal_type) }}" data-potential="{{ $g->monthly_potential }}" data-expected="{{ optional($g->expected_order_date)->format('Y-m-d') }}" data-title="{{ e($g->client_name) }}"><i class="bx bx-edit"></i> Edit Customer</button>
                @endif
                @if($managesAll)
                  @if($g->pendingDeleteRequest)
                    <button type="button" class="btn btn-hitech-cancel rounded-pill text-start text-success ndg-decide-delete" data-id="{{ $g->pendingDeleteRequest->id }}" data-status="approved" data-title="{{ e($g->client_name) }}"><i class="bx bx-check"></i> Approve Removal Request</button>
                    <button type="button" class="btn btn-hitech-cancel rounded-pill text-start text-danger ndg-decide-delete" data-id="{{ $g->pendingDeleteRequest->id }}" data-status="rejected" data-title="{{ e($g->client_name) }}"><i class="bx bx-x"></i> Reject Removal Request</button>
                  @else
                    <button type="button" class="btn btn-hitech-cancel rounded-pill text-start text-danger ndg-remove-goal" data-id="{{ $g->id }}" data-title="{{ e($g->client_name) }}"><i class="bx bx-trash"></i> Remove Customer</button>
                  @endif
                @elseif($canEdit)
                  @if($g->pendingDeleteRequest)
                    <div class="small text-muted">Removal requested</div>
                  @else
                    <button type="button" class="btn btn-hitech-cancel rounded-pill text-start text-danger ndg-request-delete" data-id="{{ $g->id }}" data-title="{{ e($g->client_name) }}"><i class="bx bx-trash"></i> Request Removal</button>
                  @endif
                @endif
              </div>
            </div>
          </div>
          @endif
          <div class="d-flex gap-2 flex-wrap mb-3 ndg-rd-dtabs" data-goal="{{ $g->id }}">
            @foreach(['progress' => 'Details & Progress', 'comments' => 'Weekly Comments', 'orders' => 'Orders', 'documents' => 'Documents'] as $dk => $dl)
              <button type="button" class="ndg-subtab ndg-rd-dtab {{ $dk === 'progress' ? 'on' : '' }}" data-dtab="{{ $dk }}">{{ $dl }}</button>
            @endforeach
          </div>

          <div class="ndg-rd-dsection" data-dsection="progress">
            <div class="row g-4">
              <div class="col-md-4">
                <div class="hitech-card h-100">
                  <div class="card-body p-4">
                    <div class="fw-bold text-dark mb-3"><i class="bx bx-buildings" style="color:#007a7a"></i> Customer Information</div>
                    <div class="fw-semibold">{{ $g->client_name }}</div>
                    <div class="small text-muted mb-3">{{ $g->brand }}@if(optional($g->creator)->id) · Owner: {{ trim($g->creator->first_name . ' ' . $g->creator->last_name) }}@endif</div>
                    <a href="{{ route('sales-visits.pipeline.index', ['search' => $g->client_name]) }}" class="btn btn-sm btn-hitech-cancel rounded-pill">View in Sales Report →</a>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="hitech-card h-100">
                  <div class="card-body p-4">
                    <div class="fw-bold text-dark mb-3"><i class="bx bx-target-lock" style="color:#007a7a"></i> Target Details</div>
                    <div class="d-flex justify-content-between small py-1 border-bottom"><span class="text-muted">Product</span><b>{{ $g->product }}</b></div>
                    <div class="d-flex justify-content-between small py-1 border-bottom"><span class="text-muted">Goal Amount</span><b>₹{{ $fmtL($g->monthly_potential) }} L</b></div>
                    <div class="d-flex justify-content-between small py-1 border-bottom"><span class="text-muted">Expected Order Date</span><b>{{ $g->expected_order_date ? $g->expected_order_date->format('d M Y') : '—' }}</b></div>
                    <div class="d-flex justify-content-between small py-1"><span class="text-muted">Current Status</span>
                      @if(optional($r)->status)<span class="ndg-chip" style="background:{{ $G::STATUS_COLORS[$r->status][0] ?? '#e2e8f0' }};color:{{ $G::STATUS_COLORS[$r->status][1] ?? '#475569' }}">{{ $r->status }}</span>@include('tenant.performance.ninety-day-goals.partials.order-check', ['r' => $r])@else<span class="ndg-none">·</span>@endif
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="hitech-card h-100">
                  <div class="card-body p-4">
                    <div class="fw-bold text-dark mb-3"><i class="bx bx-trending-up" style="color:#007a7a"></i> Progress</div>
                    <div class="d-flex justify-content-between small py-1 border-bottom"><span class="text-muted">Target</span><b>{{ $fmtL(optional($r)->target ?? 0) }} L</b></div>
                    <div class="d-flex justify-content-between small py-1 border-bottom"><span class="text-muted">Achieved</span><b>{{ $fmtL(optional($r)->achieved ?? 0) }} L</b></div>
                    <div class="mt-2"><div class="progress" style="height:7px"><div class="progress-bar" style="width:{{ min(100, (float) optional($r)->pct) }}%;background:{{ $pctColor(optional($r)->pct) }}"></div></div></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="ndg-rd-dsection d-none" data-dsection="comments">
            <div class="hitech-card">
              <div class="card-body p-4 border-bottom d-flex align-items-center justify-content-between">
                <div class="fw-bold text-dark">Weekly Timeline &amp; Comments</div>
              </div>
              <div class="card-body p-4 d-flex flex-wrap gap-3">
                {{-- Weeks with a status, a comment, or already due - a lead or admin can comment
                     on a week before the salesperson has filled it in. --}}
                @forelse($g->checkins->filter(fn ($c) => $c->status !== null || $c->notes->isNotEmpty() || $c->due_on->lte(today())) as $ck)
                  <div class="ndg-rd-wk-card" style="min-width:150px">
                    <div class="small text-muted">W{{ $ck->week_no + 1 }} · {{ $ck->due_on->format('d M') }}</div>
                    <span class="ndg-chip mt-1" style="background:{{ $G::STATUS_COLORS[$ck->status][0] ?? '#e2e8f0' }};color:{{ $G::STATUS_COLORS[$ck->status][1] ?? '#475569' }}">{{ $ck->status ?? 'Not submitted' }}</span>
                    <div class="small mt-1">{{ $ck->comment ?: '—' }}</div>
                    @if($ck->next_action)<div class="small mt-1 text-muted"><b>Next:</b> {{ $ck->next_action }}@if($ck->next_follow_up_date) · {{ $ck->next_follow_up_date->format('d M') }}@endif</div>@endif
                    @foreach($ck->notes as $n)
                      @php $isAdminNote = \App\Services\NinetyDayGoalService::managesEveryone($n->author); @endphp
                      <div class="small mt-1 pt-1 border-top">
                        @if($canRemoveComment ?? false)<button type="button" class="ndg-note-del" data-id="{{ $n->id }}" title="Remove comment">&times;</button>@endif
                        @if($isAdminNote)<span class="badge badge-hitech" style="background:#fef3c7;color:#92400e;font-size:.6rem;vertical-align:1px">Admin</span>@endif
                        <b>{{ trim(optional($n->author)->first_name . ' ' . optional($n->author)->last_name) }}:</b> {{ $n->body }}
                      </div>
                    @endforeach
                    @if($canComment ?? false)
                      <button type="button" class="btn btn-link btn-sm p-0 mt-1 ndg-addnote" data-goal="{{ $g->id }}" data-week="{{ $ck->week_no }}"><i class="bx bx-comment-add"></i> {{ $managesAll ? 'Add admin remark' : 'Add comment' }}</button>
                    @endif
                  </div>
                @empty
                  <div class="small text-muted p-2">No weekly updates yet.</div>
                @endforelse
              </div>
            </div>
          </div>

          <div class="ndg-rd-dsection d-none" data-dsection="orders">
            <div class="hitech-card">
              <div class="card-body p-4">
                @if($r && $r->achieved > 0)
                  <div class="d-flex justify-content-between small py-1 border-bottom"><span class="text-muted">Order value (from Sales Report)</span><b>₹{{ $fmtL($r->achieved) }} L</b></div>
                  <div class="d-flex justify-content-between small py-1"><span class="text-muted">Repeat / upsell</span><b>{{ $r->repeat ? 'Yes' : 'No' }}</b></div>
                @else
                  <div class="small text-muted">No qualifying order in the Sales Report yet for this customer.</div>
                @endif
              </div>
            </div>
          </div>

          <div class="ndg-rd-dsection d-none" data-dsection="documents">
            <div class="hitech-card"><div class="card-body p-4 text-center text-muted small">Document uploads aren't part of 90-Day Goals yet.</div></div>
          </div>
        </div>
      @endforeach
      @if($goals->isEmpty())
        <div class="hitech-card"><div class="card-body p-5 text-center text-muted">Add a customer above to see their details here.</div></div>
      @endif
  </div>

  {{-- ═══════════════════ Orders & Results ═══════════════════ --}}
  <div class="ndg-rd-section d-none" data-section="orders">
    <div class="hitech-card mb-6">
      <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Qualifying orders</div><div class="small text-muted">Real Sales Report sales only, never typed in</div></div>
      <div class="card-body p-0 table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th class="ps-4">Customer</th><th>Product</th><th class="text-end">Order value ₹L</th><th class="pe-4">Status</th></tr></thead>
          <tbody>
          @forelse($d['rows']->filter(fn ($r) => $r->achieved > 0)->sortByDesc('achieved') as $r)
            <tr>
              <td class="ps-4 fw-semibold">{{ $r->goal->client_name }}</td>
              <td class="small text-muted">{{ $r->goal->product }}</td>
              <td class="text-end fw-semibold">{{ $fmtL($r->achieved) }}@if($r->repeat)<span class="badge bg-label-info rounded-pill ms-1" style="font-size:.6rem">repeat</span>@endif</td>
              <td class="pe-4">@if($r->status)<span class="ndg-chip" style="background:{{ $G::STATUS_COLORS[$r->status][0] ?? '#e2e8f0' }};color:{{ $G::STATUS_COLORS[$r->status][1] ?? '#475569' }}">{{ $r->status }}</span>@include('tenant.performance.ninety-day-goals.partials.order-check', ['r' => $r])@endif</td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-center text-muted p-4">No qualifying orders yet.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- ═══════════════════ Program Details ═══════════════════ --}}
  <div class="ndg-rd-section d-none" data-section="details">
    <div class="hitech-card mb-6">
      <div class="card-body p-4 d-flex flex-wrap align-items-center gap-4">
        <div><div class="small text-muted text-uppercase fw-bold" style="font-size:.65rem">Program</div><div class="fw-bold text-dark">{{ $title }}</div></div>
        <div><div class="small text-muted text-uppercase fw-bold" style="font-size:.65rem">Salesperson</div><div class="fw-bold text-dark">{{ trim($round->user->first_name . ' ' . $round->user->last_name) }}</div></div>
        <div><div class="small text-muted text-uppercase fw-bold" style="font-size:.65rem">Department</div><div class="fw-bold text-dark">{{ optional($round->user->department)->name ?? '—' }}</div></div>
        <div><div class="small text-muted text-uppercase fw-bold" style="font-size:.65rem">Duration</div><div class="fw-bold text-dark">{{ $round->starts_on->format('d M Y') }} – {{ $round->ends_on->format('d M Y') }} ({{ $weeks * 7 }} days)</div></div>
        <div><div class="small text-muted text-uppercase fw-bold" style="font-size:.65rem">Status</div><div class="fw-bold text-dark">{{ ucfirst($round->status) }}</div></div>
      </div>
    </div>
    <div class="row g-4">
      <div class="col-xl-6">
        <div class="hitech-card h-100">
          <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Top customers by target</div></div>
          <div class="card-body p-4">
            @php $maxCust = max(1, (float) optional($d['topCustomers']->first())->potential); @endphp
            @forelse($d['topCustomers'] as $tc)
              <div class="ndg-bar">
                <div class="ndg-bar-top"><span class="ndg-bar-name">{{ $tc->goal->client_name }}<em>{{ $tc->brand }}</em></span><span class="ndg-bar-val">{{ $fmtL($tc->potential) }} L</span></div>
                <div class="ndg-track"><div class="ndg-fill" style="width:{{ round($tc->potential / $maxCust * 100) }}%"></div></div>
              </div>
            @empty
              <div class="small text-muted">No customers yet.</div>
            @endforelse
          </div>
        </div>
      </div>
      <div class="col-xl-6">
        <div class="hitech-card h-100">
          <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Latest status, all customers</div></div>
          <div class="card-body p-3"><div id="ndgRdStatusDonut"></div></div>
        </div>
      </div>
    </div>
    <div class="hitech-card mt-6">
      <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Compliance by week</div></div>
      <div class="card-body p-0 table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th class="ps-4">Week</th><th class="text-end">Expected</th><th class="text-end">Submitted</th><th class="text-end">Pending</th><th class="text-end pe-4">Compliance</th></tr></thead>
          <tbody>
          @foreach($d['strip'] as $w => $s)
            <tr>
              <td class="ps-4 fw-semibold">W{{ $w + 1 }}</td>
              <td class="text-end">{{ $s['total'] }}</td>
              <td class="text-end">{{ $s['done'] }}</td>
              <td class="text-end"><span class="{{ $s['total'] - $s['done'] ? 'text-danger fw-bold' : '' }}">{{ ($s['total'] - $s['done']) ?: '·' }}</span></td>
              <td class="text-end pe-4">{{ $s['total'] > 0 ? round($s['done'] / $s['total'] * 100) : 0 }}%</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- ═══════════════════ Phone app shell (<768px) ═══════════════════
       A parallel presentation of the SAME data above (no new queries, every action posts to the
       same endpoints already used higher up this file) as a bottom-tab app, since the sections
       above — even the stacked-card tables — are still a lot of desktop-shaped UI to work
       through on a phone. Desktop is untouched: see the max-width:767.98px media query below,
       which hides #ndgRdTabs/.ndg-rd-section here and shows this block instead. --}}
  <div class="ndg-mobile-app d-md-none" id="ndgMobileApp">

    <div class="ndg-mscreen" data-mscreen="overview">
      <div class="row g-3 mb-4">
        <div class="col-6"><div class="ndg-kpi3"><div class="ndg-kpi3-icon" style="background:#e0f2fe;color:#0369a1"><i class="bx bx-group"></i></div><div><div class="small text-muted fw-semibold">My Customers</div><div class="ndg-kpi3-v">{{ $counts['total'] }}</div></div></div></div>
        <div class="col-6"><div class="ndg-kpi3"><div class="ndg-kpi3-icon" style="background:#fff7ed;color:#c2540c"><i class="bx bx-rupee"></i></div><div><div class="small text-muted fw-semibold">My Target</div><div class="ndg-kpi3-v">₹{{ $fmtL($target) }} L</div></div></div></div>
        <div class="col-6"><div class="ndg-kpi3"><div class="ndg-kpi3-icon" style="background:#dcfce7;color:#166534"><i class="bx bx-bar-chart-alt-2"></i></div><div><div class="small text-muted fw-semibold">My Achieved</div><div class="ndg-kpi3-v">₹{{ $fmtL($achieved) }} L</div><b class="small" style="color:{{ $pctColor($pct) }}">{{ $pctText($pct) }}</b></div></div></div>
        <div class="col-6"><div class="ndg-kpi3"><div class="ndg-kpi3-icon" style="background:#fee2e2;color:#b42318"><i class="bx bx-hourglass"></i></div><div><div class="small text-muted fw-semibold">Pending Updates</div><div class="ndg-kpi3-v">{{ $pendingGoals->count() }}</div></div></div></div>
      </div>

      @if(!$window['meets_min'] && $isActive)
        {{-- $w got reassigned to an int by the @for/@foreach loops further up this file (Customer
             Summary table, compliance-by-week), so this re-reads $window directly rather than
             the now-stale $w alias. $canAddNow/$addAttrs are untouched and still safe to reuse. --}}
        <div class="ndg-alert-red mb-4 {{ $canAddNow ? 'ndg-alert-click' : '' }}" {!! $addAttrs !!}>
          <i class="bx bx-error-circle fs-3"></i>
          <div class="flex-grow-1">
            <div class="fw-bold">Add {{ $window['short_by'] }} more {{ \Illuminate\Support\Str::plural('customer', $window['short_by']) }} (minimum {{ $window['min'] }})</div>
            <div class="small">This round doesn't count yet.</div>
          </div>
        </div>
      @endif

      @if(($pendingUpsells ?? collect())->isNotEmpty() && ($canEdit ?? false))
        <div class="hitech-card mb-4" style="border-color:#f5a623">
          <div class="card-body p-3">
            <div class="fw-bold text-dark small mb-2"><i class="bx bx-receipt" style="color:#f5a623"></i> {{ $pendingUpsells->count() }} order{{ $pendingUpsells->count() === 1 ? '' : 's' }} need confirming</div>
            @foreach($pendingUpsells as $ck)
              <div class="ndg-upsell-row" data-checkin="{{ $ck->id }}" style="padding:8px 0">
                <div class="flex-grow-1 small">{{ optional($ck->goal)->client_name }} <span class="text-muted">· W{{ $ck->week_no + 1 }}</span></div>
                <div class="d-flex gap-2">
                  <button type="button" class="btn btn-sm btn-hitech ndg-upsell-yes">Yes</button>
                  <button type="button" class="btn btn-sm btn-hitech-cancel ndg-upsell-no">No</button>
                </div>
              </div>
            @endforeach
          </div>
        </div>
      @endif

      <div class="hitech-card mb-4">
        <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Week {{ $currentWeek + 1 }} Progress</div></div>
        <div class="card-body p-3 text-center"><div id="ndgMobOverviewDonut"></div></div>
      </div>

      <div class="hitech-card mb-4">
        <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Top Customers this Week</div></div>
        <div class="card-body p-0">
          @forelse($topThisWeek as $i => $r)
            <div class="ndg-mob-rank-row ndg-mob-open-goal" data-goal="{{ $r->goal->id }}">
              <span class="ndg-mob-rank">{{ $i + 1 }}</span>
              <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold text-dark small text-truncate">{{ $r->goal->client_name }}</div>
                <div class="ndg-track mt-1"><div class="ndg-fill" style="width:{{ $r->pct === null ? 0 : min(100, $r->pct) }}%;background:{{ $pctColor($r->pct) }}"></div></div>
              </div>
              <span class="small text-muted text-nowrap">₹{{ $fmtL($r->achieved) }}L / ₹{{ $fmtL($r->target) }}L</span>
            </div>
          @empty
            <div class="small text-muted p-3">No customers yet.</div>
          @endforelse
        </div>
      </div>

      <div class="hitech-card mb-4">
        <div class="card-body p-4 border-bottom d-flex align-items-center justify-content-between">
          <div class="fw-bold text-dark">Pending Updates ({{ $pendingGoals->count() }})</div>
          @if($pendingGoals->count() > 3)<button type="button" class="btn btn-link btn-sm p-0 ndg-mob-goto-pending">See all</button>@endif
        </div>
        <div class="card-body p-0">
          @forelse($pendingGoals->take(3) as $g)
            <div class="ndg-mob-pending-row ndg-mob-open-goal" data-goal="{{ $g->id }}">
              <i class="bx bx-hourglass" style="color:#b42318"></i>
              <div class="flex-grow-1"><div class="fw-semibold text-dark small">{{ $g->client_name }}</div><div class="small text-muted">{{ $g->product }}</div></div>
              <i class="bx bx-chevron-right text-muted"></i>
            </div>
          @empty
            <div class="small text-muted p-3">Nothing pending — nice work.</div>
          @endforelse
        </div>
      </div>

      @if($canEdit && $isActive)
      <div class="hitech-card mb-3" style="background:#fffbeb;border-color:#fde68a">
        <div class="card-body p-3 small"><i class="bx bx-bulb" style="color:#f5a623"></i> Please submit your weekly updates by Monday, 11:59 PM.</div>
      </div>
      <button type="button" class="btn-hitech rounded-pill w-100 d-flex align-items-center justify-content-center gap-2 mb-3" id="ndgMobSubmitAll"><i class="bx bx-send"></i> Submit All Updates</button>
      @endif
    </div>

    <div class="ndg-mscreen d-none" data-mscreen="customers">
      @if(($canEdit && $isActive && ($window['open'] || $managesAll)) || $isAdmin)
      <div class="d-flex gap-2 mb-3">
        @if($canEdit && $isActive && ($window['open'] || $managesAll))
          <button type="button" class="btn-hitech rounded-pill px-4 flex-grow-1 d-flex align-items-center justify-content-center gap-2" data-bs-toggle="modal" data-bs-target="#ndgRdAddModal"><i class="bx bx-plus"></i> Add Customer</button>
        @endif
        @if($isAdmin)
          <a href="{{ route('performance.ninety-day-goals.admin-view.export', ['user_id' => $round->user_id, 'round_id' => $round->id]) }}" class="btn btn-hitech-cancel rounded-pill px-3 d-flex align-items-center justify-content-center"><i class="bx bx-download"></i></a>
        @endif
      </div>
      @endif
      <div class="hitech-card mb-3">
        <div class="card-body p-3">
          <input type="search" id="ndgMobSearch" class="form-control mb-2" placeholder="Search customers…">
          <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="ndg-mob-chip on" data-filter="all">All {{ $counts['total'] }}</button>
            <button type="button" class="ndg-mob-chip" data-filter="pending">Pending {{ $pendingGoals->count() }}</button>
            <button type="button" class="ndg-mob-chip" data-filter="overdue">Overdue {{ $goals->filter(fn ($g) => $weekState($g) === 'overdue')->count() }}</button>
            <button type="button" class="ndg-mob-chip" data-filter="submitted">Submitted {{ $goals->filter(fn ($g) => $weekState($g) === 'submitted')->count() }}</button>
          </div>
        </div>
      </div>
      <div id="ndgMobCustomerList">
        @forelse($goals as $g)
          @php $r = $rowsByGoal->get($g->id); @endphp
          <div class="ndg-mob-cust-card ndg-mob-open-goal" data-goal="{{ $g->id }}" data-q="{{ strtolower($g->client_name . ' ' . $g->product) }}" data-state="{{ $weekState($g) }}">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <div class="min-w-0"><div class="fw-semibold text-dark text-truncate">{{ $g->client_name }}</div><div class="small text-muted text-truncate">{{ $g->product }} · {{ $g->brand }}</div></div>
              @if(optional($r)->status)<span class="ndg-chip flex-shrink-0" style="background:{{ $G::STATUS_COLORS[$r->status][0] ?? '#e2e8f0' }};color:{{ $G::STATUS_COLORS[$r->status][1] ?? '#475569' }}">{{ $r->status }}</span>@endif
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2">
              <div class="ndg-track flex-grow-1 me-2"><div class="ndg-fill" style="width:{{ optional($r)->pct === null ? 0 : min(100, $r->pct) }}%;background:{{ $pctColor(optional($r)->pct) }}"></div></div>
              <span class="small text-muted text-nowrap">₹{{ $fmtL(optional($r)->achieved ?? 0) }}L / ₹{{ $fmtL($g->monthly_potential) }}L</span>
            </div>
          </div>
        @empty
          <div class="hitech-card"><div class="card-body p-5 text-center text-muted">No customers yet.</div></div>
        @endforelse
      </div>
    </div>

    <div class="ndg-mscreen d-none" data-mscreen="orders">
      <div class="small text-muted mb-3">Real Sales Report sales only, never typed in</div>
      @forelse($d['rows']->filter(fn ($r) => $r->achieved > 0)->sortByDesc('achieved') as $r)
        <div class="hitech-card mb-2">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between"><div class="fw-semibold text-dark">{{ $r->goal->client_name }}</div><div class="fw-bold">₹{{ $fmtL($r->achieved) }}L</div></div>
            <div class="small text-muted">{{ $r->goal->product }}</div>
            <div class="mt-1">
              @if($r->status)<span class="ndg-chip" style="background:{{ $G::STATUS_COLORS[$r->status][0] ?? '#e2e8f0' }};color:{{ $G::STATUS_COLORS[$r->status][1] ?? '#475569' }}">{{ $r->status }}</span>@endif
              @if($r->repeat)<span class="badge bg-label-info rounded-pill ms-1" style="font-size:.6rem">repeat</span>@endif
            </div>
          </div>
        </div>
      @empty
        <div class="hitech-card"><div class="card-body p-5 text-center text-muted">No qualifying orders yet.</div></div>
      @endforelse
    </div>

    <div class="ndg-mscreen d-none" data-mscreen="insights">
      <div class="hitech-card mb-4">
        <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Latest status, all customers</div></div>
        <div class="card-body p-3"><div id="ndgMobInsightsDonut"></div></div>
      </div>
      <div class="hitech-card mb-4">
        <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Top customers by target</div></div>
        <div class="card-body p-4">
          @php $maxCustMob = max(1, (float) optional($d['topCustomers']->first())->potential); @endphp
          @forelse($d['topCustomers'] as $tc)
            <div class="ndg-bar">
              <div class="ndg-bar-top"><span class="ndg-bar-name">{{ $tc->goal->client_name }}<em>{{ $tc->brand }}</em></span><span class="ndg-bar-val">{{ $fmtL($tc->potential) }} L</span></div>
              <div class="ndg-track"><div class="ndg-fill" style="width:{{ round($tc->potential / $maxCustMob * 100) }}%"></div></div>
            </div>
          @empty
            <div class="small text-muted">No customers yet.</div>
          @endforelse
        </div>
      </div>
      <div class="hitech-card mb-4">
        <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Compliance by week</div></div>
        <div class="card-body p-0">
          @foreach($d['strip'] as $w => $s)
            <div class="ndg-mob-compliance-row">
              <span class="fw-semibold">W{{ $w + 1 }}</span>
              <span class="small text-muted">{{ $s['done'] }}/{{ $s['total'] }} submitted</span>
              <span class="small {{ $s['total'] - $s['done'] ? 'text-danger fw-bold' : 'text-muted' }}">{{ $s['total'] > 0 ? round($s['done'] / $s['total'] * 100) : 0 }}%</span>
            </div>
          @endforeach
        </div>
      </div>
    </div>

    <div class="ndg-mob-tabbar">
      @foreach(['overview' => ['Overview', 'bx-grid-alt'], 'customers' => ['Customers', 'bx-group'], 'orders' => ['Orders', 'bx-cart'], 'insights' => ['Insights', 'bx-bar-chart-alt-2']] as $key => [$lbl, $ic])
        <button type="button" class="ndg-mob-tab {{ $key === 'overview' ? 'on' : '' }}" data-mscreen="{{ $key }}"><i class="bx {{ $ic }}"></i><span>{{ $lbl }}</span></button>
      @endforeach
    </div>
  </div>

  </div>
</div>

@if($canEdit && $isActive)
  {{-- Add Customer: same source of truth as the Goals page — only the round owner's own
       Sales Pipeline, nobody types a customer in. --}}
  <div class="modal fade" id="ndgRdAddModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Add target customers</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <input type="search" id="ndgRdAddSearch" class="form-control mb-3" placeholder="Search your pipeline and sales report by customer, product or brand…" autocomplete="off">
          <div id="ndgRdAddList" class="ndg-list"><div class="text-muted p-3">Loading your customers…</div></div>
        </div>
        <div class="modal-footer justify-content-between">
          <span class="small text-danger" id="ndgRdAddMsg"></span>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-hitech-cancel rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn-hitech rounded-pill px-5" id="ndgRdAddSave">Add selected</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Edit an existing target: product, brand, goal, overall target, expected order date. --}}
  <div class="modal fade" id="ndgEditGoalModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form onsubmit="return false">
          <input type="hidden" name="goal_id">
          <div class="modal-header"><h5 class="modal-title">Edit <span class="modal-title-goal"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <div class="mb-3"><label class="form-label fw-bold small text-muted text-uppercase">Product</label><input type="text" name="product" class="form-control"></div>
            <div class="mb-3"><label class="form-label fw-bold small text-muted text-uppercase">Brand</label><input type="text" name="brand" class="form-control"></div>
            <div class="mb-3"><label class="form-label fw-bold small text-muted text-uppercase">Goal</label><input type="text" name="goal" class="form-control"></div>
            <div class="mb-3"><label class="form-label fw-bold small text-muted text-uppercase">Target for the program (₹ lakh)</label><input type="number" step="0.01" min="0" name="monthly_potential" class="form-control"></div>
            <div class="mb-3"><label class="form-label fw-bold small text-muted text-uppercase">Expected order date</label><input type="date" name="expected_order_date" class="form-control"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-hitech-cancel" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn-hitech" id="ndgRdSaveGoal">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endif

<style>
  .ndg-subtab { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 10px; border: 1px solid #d5e3e1; background: #fff; color: #334155; font-weight: 700; font-size: .8rem; cursor: pointer; }
  .ndg-subtab:hover { border-color: #007a7a; color: #007a7a; }
  .ndg-subtab.on { background: #007a7a; border-color: #007a7a; color: #fff; }
  .ndg-infobox { border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px 16px; background: #fff; min-width: 130px; }
  .ndg-kpi2 { border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 14px; background: #fff; height: 100%; display: flex; align-items: center; gap: 10px; }
  .ndg-kpi2-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex: none; }
  .ndg-kpi2-v { font-size: 1.2rem; font-weight: 800; color: #0f172a; line-height: 1.2; font-variant-numeric: tabular-nums; }
  .ndg-kpi2-l { font-size: .68rem; color: #94a3b8; font-weight: 600; }
  /* Customer-rule alerts: red while the round is short of its minimum, and clickable into the add-customer popup. */
  .ndg-alert-red { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; padding: 14px 18px; border-radius: 12px; border: 1px solid #e11d48; background: #fff1f2; color: #9f1239; }
  .ndg-alert-red .bx { color: #e11d48; }
  .ndg-alert-red .btn .bx { color: #fff; }
  .ndg-kpi3.ndg-kpi3-red { border-color: #e11d48; background: #fff1f2; }
  .ndg-kpi3.ndg-kpi3-red .ndg-kpi3-v, .ndg-kpi3.ndg-kpi3-red .small { color: #9f1239 !important; }
  .ndg-alert-click { cursor: pointer; transition: box-shadow .15s, transform .15s; }
  .ndg-alert-click:hover, .ndg-alert-click:focus { box-shadow: 0 6px 18px rgba(225, 29, 72, .22); transform: translateY(-1px); outline: none; }
  .ndg-kpi3 { border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; background: #fff; height: 100%; display: flex; align-items: flex-start; gap: 12px; }
  .ndg-kpi3-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex: none; }
  .ndg-kpi3-v { font-size: 1.2rem; font-weight: 800; color: #0f172a; line-height: 1.25; font-variant-numeric: tabular-nums; }
  .ndg-week-strip { overflow-x: auto; }
  .ndg-week-pill { flex: none; min-width: 78px; border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px 12px; text-align: center; text-decoration: none; color: #334155; }
  .ndg-week-pill:hover { border-color: #007a7a; }
  .ndg-week-pill.on { background: #fff; border-color: #007a7a; box-shadow: 0 0 0 1px #007a7a; }
  .ndg-week-pill.is-current .fw-bold { color: #007a7a; }
  .ndg-week-pill.is-future { display: inline-block; background: #f8fafc; color: #cbd5e1; cursor: not-allowed; }
  .ndg-week-pill.is-future:hover { border-color: #e2e8f0; }
  .ndg-rd-expand { border: 0; background: none; color: #64748b; padding: 0; cursor: pointer; }
  .ndg-rd-expand.on i { transform: rotate(-180deg); }
  .ndg-rd-expand i { transition: transform .15s; }
  .ndg-rd-row.on { box-shadow: inset 3px 0 0 #007a7a; }
  .ndg-rd-weekly { background: #f8fafc; padding: 14px 20px; border-top: 1px dashed #e2e8f0; border-bottom: 1px dashed #e2e8f0; }
  .ndg-rd-wk-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px; min-width: 140px; }
  .ndg-chip { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: .72rem; font-weight: 700; }
  .ndg-none { color: #cbd5e1; }
  .ndg-bar { margin-bottom: 12px; }
  .ndg-bar-top { display: flex; justify-content: space-between; gap: 10px; margin-bottom: 4px; font-size: .84rem; }
  .ndg-bar-name { font-weight: 600; color: #0f172a; }
  .ndg-bar-name em { display: block; font-style: normal; font-weight: 400; font-size: .7rem; color: #94a3b8; }
  .ndg-bar-val { font-weight: 800; white-space: nowrap; }
  .ndg-track { height: 7px; background: #eef2f5; border-radius: 99px; overflow: hidden; }
  .ndg-fill { height: 100%; background: #007a7a; border-radius: 99px; min-width: 2px; }
  .ndg-mx-scroll { overflow: auto; }
  .ndg-list { max-height: 55vh; overflow: auto; padding: 4px; }
  .ndg-list .ndg-row { border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px; margin-bottom: 8px; }
  .ndg-list .ndg-row.on { border-color: #007a7a; background: #f0f7f6; }
  .ndg-list .ndg-row:hover { background: #f8fafc; }
  .ndg-list .ndg-row.on:hover { background: #f0f7f6; }
  .ndg-list .ndg-extra { display: none; margin-top: 10px; }
  .ndg-list .ndg-row.on .ndg-extra { display: flex; }
  .ndg-src { font-size: .65rem; }
  #ndgWeeklyTable input, #ndgWeeklyTable select { font-size: .82rem; }
  #ndgWeeklyTable .ndg-needs-stage { border-color: #f5a623 !important; box-shadow: 0 0 0 1px #f5a623; }
  .ndg-upsell-row { display: flex; align-items: center; gap: 12px; padding: 12px 20px; border-bottom: 1px solid #f1f5f9; }
  .ndg-upsell-row:last-child { border-bottom: none; }
  .ndg-note-del { border: 0; background: none; color: #ef4444; float: right; line-height: 1; font-size: 1rem; padding: 0 2px; }

  /* ── Phone layout ──
     Below 768px the two dense data tables (#ndgWeeklyTable, #ndgRdTable) stop being tables
     a finger can use — most columns sit off-screen behind a horizontal scroll nobody finds.
     Turn every row into its own stacked card instead: thead disappears, each <td> becomes a
     label/value line using its data-label attribute, and inputs/selects go full width. */
  @media (max-width: 767.98px) {
    .ndg-infobox { min-width: 0; flex: 1 1 auto; padding: 8px 10px; }
    .ndg-kpi3 { padding: 10px 12px; gap: 8px; }
    .ndg-kpi3-icon { width: 34px; height: 34px; font-size: 1rem; }
    .ndg-kpi3-v { font-size: 1.05rem; }

    .ndg-mx-scroll { overflow-x: visible; }
    #ndgWeeklyTable, #ndgRdTable { min-width: 0 !important; width: 100% !important; }
    #ndgWeeklyTable thead, #ndgRdTable thead { display: none; }
    #ndgWeeklyTable tbody, #ndgRdTable tbody,
    #ndgWeeklyTable tr, #ndgRdTable tr,
    #ndgWeeklyTable td, #ndgRdTable td { display: block; width: 100%; }
    #ndgWeeklyTable tr.ndg-wk-row, #ndgRdTable tr.ndg-rd-row {
      border: 1px solid #e2e8f0; border-radius: 12px; padding: 4px 0; margin-bottom: 12px; background: #fff;
    }
    #ndgRdTable tr.ndg-rd-exp { border: none; }
    #ndgWeeklyTable td, #ndgRdTable td {
      display: flex; align-items: center; justify-content: space-between; gap: 10px;
      padding: 8px 12px; text-align: right; border-bottom: 1px solid #f1f5f9;
    }
    #ndgWeeklyTable td:last-child, #ndgRdTable td:last-child { border-bottom: none; }
    #ndgWeeklyTable td[data-label]::before, #ndgRdTable td[data-label]::before {
      content: attr(data-label); font-weight: 700; font-size: .68rem; text-transform: uppercase;
      color: #64748b; text-align: left; flex-shrink: 0;
    }
    #ndgWeeklyTable td select, #ndgWeeklyTable td input,
    #ndgRdTable td select, #ndgRdTable td input { max-width: 62%; margin-left: auto; }
    #ndgWeeklyTable td .ndg-td-value { max-width: 62%; margin-left: auto; display: flex; flex-direction: column; align-items: flex-end; }
    #ndgWeeklyTable td .ndg-td-value select { max-width: 100%; }
    #ndgWeeklyTable td.ndg-td-actions, #ndgRdTable td.ndg-td-actions {
      justify-content: center; padding: 10px 12px;
    }
    #ndgWeeklyTable td.ndg-td-actions::before, #ndgRdTable td.ndg-td-actions::before { content: none; }
    #ndgRdTable td[colspan] { display: block; padding: 0; }
    .ndg-rd-weekly { padding: 14px 16px; }

    .ndg-week-pill { min-width: 68px; }
    .ndg-subtab { padding: 7px 12px; font-size: .75rem; }

    /* ── Phone app shell ──
       Below 768px the page becomes a bottom-tab app instead: hide the desktop sub-tabs and all
       four of their sections entirely (display:none on an ancestor can't be selectively undone
       for a child — which is exactly why #ndgRdDetail was moved out to be a sibling of these,
       not a descendant: it needs to stay reachable here as the full-screen customer overlay). */
    #ndgRdTabs, .ndg-rd-section { display: none !important; }
    #ndgRdDetail { display: block !important; }
    #ndgRdDetail .ndg-rd-detail-panel.d-none { display: none !important; }
    #ndgRdDetail .ndg-rd-detail-panel:not(.d-none) {
      display: block !important; position: fixed; top: 62px; left: 0; right: 0; bottom: 0; z-index: 1035;
      background: #f8fafc; overflow-y: auto; padding: 16px 16px 90px;
    }

    .ndg-mobile-app { padding-bottom: 76px; }
    .min-w-0 { min-width: 0; }

    .ndg-mob-tabbar {
      position: fixed; left: 0; right: 0; bottom: 0; z-index: 1030; display: flex;
      background: #fff; border-top: 1px solid #e2e8f0; padding: 6px 4px calc(6px + env(safe-area-inset-bottom));
      box-shadow: 0 -4px 14px rgba(15, 23, 42, .06);
    }
    .ndg-mob-tab {
      flex: 1 1 0; border: 0; background: none; display: flex; flex-direction: column; align-items: center;
      gap: 2px; padding: 6px 2px; color: #94a3b8; font-size: .66rem; font-weight: 700;
    }
    .ndg-mob-tab i { font-size: 1.25rem; }
    .ndg-mob-tab.on { color: #007a7a; }

    .ndg-mob-rank-row, .ndg-mob-pending-row {
      display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-bottom: 1px solid #f1f5f9;
    }
    .ndg-mob-rank-row:last-child, .ndg-mob-pending-row:last-child { border-bottom: none; }
    .ndg-mob-rank {
      width: 22px; height: 22px; border-radius: 50%; background: #e0f2f1; color: #007a7a; font-weight: 800;
      font-size: .72rem; display: flex; align-items: center; justify-content: center; flex: none;
    }
    .ndg-mob-pending-row { cursor: pointer; }

    .ndg-mob-chip {
      border: 1px solid #d5e3e1; background: #fff; color: #334155; border-radius: 999px; padding: 5px 12px;
      font-size: .72rem; font-weight: 700;
    }
    .ndg-mob-chip.on { background: #007a7a; border-color: #007a7a; color: #fff; }

    .ndg-mob-cust-card {
      border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 14px; background: #fff;
      margin-bottom: 10px; cursor: pointer;
    }

    .ndg-mob-compliance-row {
      display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 16px;
      border-bottom: 1px solid #f1f5f9;
    }
    .ndg-mob-compliance-row:last-child { border-bottom: none; }

    .ndg-mob-detail-head { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
    .ndg-mob-back {
      width: 36px; height: 36px; border-radius: 10px; border: 1px solid #e2e8f0; background: #fff;
      color: #334155; flex: none; display: flex; align-items: center; justify-content: center;
    }

    .ndg-mob-stepper { display: flex; align-items: flex-start; margin-bottom: 16px; }
    .ndg-mob-step { display: flex; flex-direction: column; align-items: center; flex: none; width: 60px; }
    .ndg-mob-step span {
      width: 26px; height: 26px; border-radius: 50%; background: #e2e8f0; color: #64748b; font-weight: 800;
      font-size: .74rem; display: flex; align-items: center; justify-content: center;
    }
    .ndg-mob-step.on span { background: #007a7a; color: #fff; }
    .ndg-mob-step small { font-size: .62rem; color: #64748b; text-align: center; margin-top: 4px; line-height: 1.1; }
    .ndg-mob-step-line { flex: 1 1 auto; height: 2px; background: #e2e8f0; margin-top: 13px; }
    .ndg-mob-step-line.on { background: #007a7a; }
  }
</style>
@endsection

@section('page-script')
<script>
// The red customer alerts are role=button: Enter/Space opens the popup like a click does.
// Arriving with ?add=1 (from the My Dashboard alert) opens the add-customer popup straight away;
// it waits for 'load' because Bootstrap is loaded deferred by Vite.
(function () {
  document.addEventListener('keydown', function (e) {
    var el = e.target;
    if ((e.key === 'Enter' || e.key === ' ') && el && el.classList && el.classList.contains('ndg-alert-click')) { e.preventDefault(); el.click(); }
  });
  if (new URLSearchParams(location.search).get('add') === '1' && document.getElementById('ndgRdAddModal')) {
    window.addEventListener('load', function () {
      if (window.bootstrap && window.bootstrap.Modal) { window.bootstrap.Modal.getOrCreateInstance(document.getElementById('ndgRdAddModal')).show(); }
    });
  }
})();
// Live countdown to the close of the add-customer window; reloads once it closes so the
// add buttons disappear without a stale page accepting a click the server will refuse.
(function () {
  var el = document.getElementById('ndgWinCountdown');
  if (!el) { return; }
  var closes = new Date(el.getAttribute('data-closes')).getTime();
  if (isNaN(closes)) { return; }
  var pad = function (n) { return (n < 10 ? '0' : '') + n; };
  var tick = function () {
    var left = Math.floor((closes - Date.now()) / 1000);
    if (left <= 0) { el.textContent = 'Closed'; clearInterval(timer); setTimeout(function () { location.reload(); }, 1500); return; }
    var d = Math.floor(left / 86400), h = Math.floor(left % 86400 / 3600), m = Math.floor(left % 3600 / 60), sec = left % 60;
    el.textContent = (d ? d + 'd ' : '') + pad(h) + 'h ' + pad(m) + 'm ' + pad(sec) + 's left';
  };
  var timer = setInterval(tick, 1000);
  tick();
})();
(function () {
  var CSRF = '{{ csrf_token() }}';
  var ROUND_USER_ID = {{ (int) $round->user_id }};
  var SELECTED_WEEK = {{ (int) $selectedWeek }};
  var URLS = {
    confirmUpsell: '{{ route('performance.ninety-day-goals.confirm-upsell') }}',
    notes: '{{ url('performance/ninety-day-goals/notes') }}',
    checkin: '{{ route('performance.ninety-day-goals.checkin') }}',
    update: '{{ url('performance/ninety-day-goals') }}',
    customers: '{{ route('performance.ninety-day-goals.pipeline-customers') }}',
    store: '{{ route('performance.ninety-day-goals.store') }}',
    deleteRequest: '{{ url('performance/ninety-day-goals') }}',
    decideDelete: '{{ url('performance/ninety-day-goals/delete-request') }}'
  };
  function post(url, method, body) {
    return fetch(url, { method: method, headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }, body: body ? JSON.stringify(body) : undefined })
      .then(function (r) { return r.json().then(function (j) { j._status = r.status; return j; }); });
  }

  // ── comments on a customer's week: add (owner, lead, admin/HR) / remove (admin/HR) ──
  document.addEventListener('click', function (e) {
    var add = e.target.closest ? e.target.closest('.ndg-addnote') : null;
    if (add) {
      var body = window.prompt('Comment for W' + add.getAttribute('data-week') + ':');
      if (body === null || !body.trim()) { return; }
      add.disabled = true;
      post(URLS.notes, 'POST', { goal_id: parseInt(add.getAttribute('data-goal'), 10), week_no: parseInt(add.getAttribute('data-week'), 10), body: body.trim() }).then(function (j) {
        if (!j.success) { add.disabled = false; alert(j.message || 'Could not save the comment.'); return; }
        location.reload();
      }).catch(function () { add.disabled = false; alert('Request failed. Try again.'); });
      return;
    }
    var del = e.target.closest ? e.target.closest('.ndg-note-del') : null;
    if (del) {
      if (!confirm('Remove this comment?')) { return; }
      post(URLS.notes + '/' + del.getAttribute('data-id'), 'DELETE').then(function (j) {
        if (!j.success) { alert(j.message || 'Could not remove it.'); return; }
        location.reload();
      }).catch(function () { alert('Request failed. Try again.'); });
      return;
    }
    var rmGoal = e.target.closest ? e.target.closest('.ndg-remove-goal') : null;
    if (rmGoal) {
      if (!confirm('Remove "' + (rmGoal.getAttribute('data-title') || 'this customer') + '" from the program? This also removes its weekly history.')) { return; }
      post(URLS.update + '/' + rmGoal.getAttribute('data-id'), 'DELETE').then(function (j) {
        if (!j.success) { alert(j.message || 'Could not remove it.'); return; }
        location.reload();
      }).catch(function () { alert('Request failed. Try again.'); });
      return;
    }
    var reqDel = e.target.closest ? e.target.closest('.ndg-request-delete') : null;
    if (reqDel) {
      var reason = window.prompt('Why should "' + (reqDel.getAttribute('data-title') || 'this customer') + '" be removed? (optional)');
      if (reason === null) { return; }
      reqDel.disabled = true;
      post(URLS.deleteRequest + '/' + reqDel.getAttribute('data-id') + '/delete-request', 'POST', { reason: reason.trim() }).then(function (j) {
        if (!j.success) { reqDel.disabled = false; alert(j.message || 'Could not send the request.'); return; }
        location.reload();
      }).catch(function () { reqDel.disabled = false; alert('Request failed. Try again.'); });
      return;
    }
    var decideDel = e.target.closest ? e.target.closest('.ndg-decide-delete') : null;
    if (decideDel) {
      var status = decideDel.getAttribute('data-status');
      var verb = status === 'approved' ? 'approve (this removes the customer)' : 'reject';
      if (!confirm('Really ' + verb + ' the removal request for "' + (decideDel.getAttribute('data-title') || 'this customer') + '"?')) { return; }
      var remarks = window.prompt('Remark for the requester (optional):') || '';
      post(URLS.decideDelete + '/' + decideDel.getAttribute('data-id'), 'PUT', { status: status, admin_remarks: remarks.trim() }).then(function (j) {
        if (!j.success) { alert(j.message || 'Could not save that.'); return; }
        location.reload();
      }).catch(function () { alert('Request failed. Try again.'); });
    }
  });

  // ── upsell confirmation ──
  document.querySelectorAll('.ndg-upsell-row').forEach(function (row) {
    var checkinId = parseInt(row.getAttribute('data-checkin'), 10);
    function answer(isUpsell) {
      var buttons = row.querySelectorAll('button');
      buttons.forEach(function (b) { b.disabled = true; });
      post(URLS.confirmUpsell, 'POST', { checkin_id: checkinId, is_upsell: isUpsell }).then(function (j) {
        if (!j.success) { buttons.forEach(function (b) { b.disabled = false; }); alert(j.message || 'Could not save that.'); return; }
        location.reload();
      }).catch(function () { buttons.forEach(function (b) { b.disabled = false; }); alert('Request failed. Try again.'); });
    }
    row.querySelector('.ndg-upsell-yes').addEventListener('click', function () { answer(true); });
    row.querySelector('.ndg-upsell-no').addEventListener('click', function () { answer(false); });
  });
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

  // ── top-level sub-tabs ──
  var tabs = document.querySelectorAll('#ndgRdTabs .ndg-subtab');
  var sections = document.querySelectorAll('.ndg-rd-section');
  tabs.forEach(function (t) {
    t.addEventListener('click', function () {
      tabs.forEach(function (x) { x.classList.toggle('on', x === t); });
      sections.forEach(function (s) { s.classList.toggle('d-none', s.dataset.section !== t.dataset.tab); });
    });
  });
  var strip = document.getElementById('ndgRdWeekStrip');
  var onWeek = strip ? strip.querySelector('.ndg-week-pill.on') : null;
  if (onWeek) { onWeek.scrollIntoView({ inline: 'center', block: 'nearest' }); }

  // The per-row "..." action menu (View details / Edit / Remove) lives inside a horizontally
  // scrollable table (.ndg-mx-scroll). Bootstrap positions a dropdown-menu with CSS position:
  // absolute by default, so on a narrow phone screen the scroll container's overflow clips it
  // and the options never appear. Forcing Popper's "fixed" strategy makes the menu position
  // itself against the viewport instead, so it escapes that clipping.
  if (window.bootstrap && window.bootstrap.Dropdown) {
    document.querySelectorAll('.ndg-mx-scroll [data-bs-toggle="dropdown"]').forEach(function (el) {
      window.bootstrap.Dropdown.getOrCreateInstance(el, { popperConfig: { strategy: 'fixed' } });
    });
  }

  // ── Weekly Submission: read every row, save one or many via the same checkin endpoint ──
  var wkMsg = document.getElementById('ndgRdMsg');
  // Status values that mean a real order is on the books — the same two the rest of this module
  // already treats as "converted" (see attentionRows()/counts elsewhere). Reported this way, the
  // customer's Expected Stage is worth being specific, not left at the generic default.
  var ORDER_STATUSES = ['Trial Order', 'Regular Order'];
  function readRow(row) {
    var status = row.querySelector('.ndg-wk-status'), comment = row.querySelector('.ndg-wk-comment'),
        nextAction = row.querySelector('.ndg-wk-next-action'), nextDate = row.querySelector('.ndg-wk-next-date'),
        expected = row.querySelector('.ndg-wk-expected');
    return {
      goal_id: parseInt(row.dataset.goal, 10),
      status: status ? status.value : '',
      comment: comment ? comment.value.trim() : '',
      next_action: nextAction ? nextAction.value.trim() : '',
      next_follow_up_date: nextDate ? (nextDate.value || null) : null,
      expected_stage: expected ? expected.value : null,
    };
  }
  // Expected Stage lives on the goal itself (the same "goal" field the add-customers dropdown
  // sets), not the weekly checkin, so it saves through the existing per-goal update endpoint —
  // a small extra call alongside the checkin save.
  // Only stages actually changed since the page loaded, on any row (with or without a status).
  function changedStages(rows) {
    return rows.map(function (row) {
      var ex = row.querySelector('.ndg-wk-expected');
      return ex && !ex.disabled && ex.value !== ex.dataset.orig ? { goal_id: parseInt(row.dataset.goal, 10), stage: ex.value, el: ex } : null;
    }).filter(Boolean);
  }
  // Resolves to the names of customers whose stage did NOT save.
  function saveExpectedStages(rows) {
    return Promise.all(changedStages(rows).map(function (c) {
      return post(URLS.update + '/' + c.goal_id, 'PUT', { goal: c.stage }).then(function (j) {
        if (j.success) { c.el.dataset.orig = c.stage; return null; }
        return c.el.closest('.ndg-wk-row').querySelector('td:nth-child(2)').textContent.trim();
      }).catch(function () { return c.el.closest('.ndg-wk-row').querySelector('td:nth-child(2)').textContent.trim(); });
    })).then(function (failed) { return failed.filter(Boolean); });
  }
  function saveEntries(entries, btn, rows) {
    rows = rows || [];
    var stageChanges = changedStages(rows).length;
    if (!entries.length && !stageChanges) { return; }
    if (btn) { btn.disabled = true; }
    wkMsg.textContent = 'Saving…'; wkMsg.className = 'small text-muted';
    var statusSave = entries.length
      ? post(URLS.checkin, 'POST', { user_id: ROUND_USER_ID, week_no: SELECTED_WEEK, entries: entries })
      : Promise.resolve({ success: true, message: 'Expected stage saved.' });
    Promise.all([saveExpectedStages(rows), statusSave]).then(function (res) {
      var failedStages = res[0], j = res[1];
      if (btn) { btn.disabled = false; }
      var stageNote = failedStages.length ? ' Expected stage NOT saved for: ' + failedStages.join(', ') + '.' : '';
      if (j.success) {
        wkMsg.textContent = (j.message || 'Saved.') + stageNote; wkMsg.className = failedStages.length ? 'small text-warning' : 'small text-success';
        if (typeof window.showSuccessSwal === 'function' && entries.length > 1 && !failedStages.length) { window.showSuccessSwal(j.message); }
      } else {
        wkMsg.textContent = (j.message || (j.errors ? j.errors.join(' ') : 'Nothing saved — check the statuses above.')) + stageNote; wkMsg.className = 'small text-danger';
      }
    }).catch(function () {
      if (btn) { btn.disabled = false; }
      wkMsg.textContent = 'Request failed. Try again.'; wkMsg.className = 'small text-danger';
    });
  }
  document.querySelectorAll('.ndg-wk-save').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var row = btn.closest('.ndg-wk-row'), e = readRow(row);
      saveEntries(e.status ? [e] : [], btn, [row]);
    });
  });
  // Marking a customer Trial Order / Regular Order for the week is exactly the moment the
  // Expected Stage is worth being specific — nudge to pick the real one (New Order, Repeat
  // Order, Upsell…) instead of leaving it at the generic "Order" default. Never blocks the
  // save; just draws the eye to the field.
  document.querySelectorAll('.ndg-wk-status').forEach(function (sel) {
    sel.addEventListener('change', function () {
      var row = sel.closest('.ndg-wk-row'), expected = row.querySelector('.ndg-wk-expected');
      if (!expected) { return; }
      var needsStage = ORDER_STATUSES.indexOf(sel.value) !== -1 && expected.value === 'Order';
      expected.classList.toggle('ndg-needs-stage', needsStage);
      if (needsStage) { expected.focus(); wkMsg.textContent = 'Set the expected stage for ' + row.querySelector('td:nth-child(2)').textContent.trim() + ' — still just "Order".'; wkMsg.className = 'small text-warning'; }
    });
  });
  function rowEls() { return Array.prototype.slice.call(document.querySelectorAll('.ndg-wk-row')); }
  function allRows() { return rowEls().map(readRow).filter(function (e) { return e.status; }); }
  ['ndgSubmitAllTop', 'ndgSubmitAllBottom', 'ndgMobSubmitAll'].forEach(function (id) {
    var btn = document.getElementById(id);
    if (btn) { btn.addEventListener('click', function () {
      var entries = allRows();
      if (!entries.length && !changedStages(rowEls()).length) { wkMsg.textContent = 'Pick a status for at least one customer first.'; wkMsg.className = 'small text-danger'; return; }
      saveEntries(entries, btn, rowEls());
    }); }
  });
  var prevBtn = document.getElementById('ndgWkPrev');
  if (prevBtn) { prevBtn.addEventListener('click', function () {
    var w = Math.max(0, SELECTED_WEEK - 1);
    location.href = '?w=' + w;
  }); }

  // ── expand a customer row (Customer Summary tab): shows its weekly-comments sub-row and its detail panel ──
  function selectGoal(goalId) {
    document.querySelectorAll('.ndg-rd-row').forEach(function (r) { r.classList.toggle('on', r.dataset.goal === String(goalId)); });
    document.querySelectorAll('.ndg-rd-exp').forEach(function (r) { r.classList.toggle('d-none', r.id !== 'ndgRdExp' + goalId); });
    document.querySelectorAll('.ndg-rd-expand[data-target]').forEach(function (b) { b.classList.toggle('on', b.dataset.target === 'ndgRdExp' + goalId); });
    document.querySelectorAll('.ndg-rd-detail-panel').forEach(function (p) { p.classList.toggle('d-none', p.dataset.goal !== String(goalId)); });
  }
  document.querySelectorAll('.ndg-rd-expand').forEach(function (b) {
    b.addEventListener('click', function () {
      var goalId = b.dataset.target.replace('ndgRdExp', '');
      var already = b.classList.contains('on');
      if (already) {
        document.querySelectorAll('.ndg-rd-exp, .ndg-rd-detail-panel').forEach(function (x) { x.classList.add('d-none'); });
        document.querySelectorAll('.ndg-rd-row, .ndg-rd-expand').forEach(function (x) { x.classList.remove('on'); });
      } else {
        selectGoal(goalId);
      }
    });
  });
  // Desktop only: the Customer Summary tab opens showing the first customer's detail panel
  // already expanded. On phone #ndgRdDetail's active panel is a full-screen overlay (see the
  // max-width:767.98px CSS), so auto-opening one here would cover the Overview screen on load.
  var firstRow = document.querySelector('.ndg-rd-row');
  if (firstRow && window.matchMedia('(min-width: 768px)').matches) {
    document.querySelector('.ndg-rd-detail-panel[data-goal="' + firstRow.dataset.goal + '"]')?.classList.remove('d-none');
  }

  document.querySelectorAll('.ndg-rd-detail-panel').forEach(function (panel) {
    panel.querySelectorAll('.ndg-rd-dtab').forEach(function (t) {
      t.addEventListener('click', function () {
        panel.querySelectorAll('.ndg-rd-dtab').forEach(function (x) { x.classList.toggle('on', x === t); });
        panel.querySelectorAll('.ndg-rd-dsection').forEach(function (s) { s.classList.toggle('d-none', s.dataset.dsection !== t.dataset.dtab); });
      });
    });
  });

  // ── Phone app shell: bottom tabs, opening a customer's detail as a full-screen overlay
  // (reusing selectGoal() above — same panels the desktop expand view uses), filtering the
  // Customers list, and saving a status straight from the overlay. ──
  var mobileApp = document.getElementById('ndgMobileApp');
  if (mobileApp) {
    function showMobScreen(key) {
      document.querySelectorAll('.ndg-mscreen').forEach(function (s) { s.classList.toggle('d-none', s.dataset.mscreen !== key); });
      document.querySelectorAll('.ndg-mob-tab').forEach(function (t) { t.classList.toggle('on', t.dataset.mscreen === key); });
    }
    document.querySelectorAll('.ndg-mob-tab').forEach(function (t) {
      t.addEventListener('click', function () { showMobScreen(t.dataset.mscreen); });
    });

    document.querySelectorAll('.ndg-mob-open-goal').forEach(function (el) {
      el.addEventListener('click', function () { selectGoal(el.dataset.goal); });
    });
    document.addEventListener('click', function (e) {
      var back = e.target.closest ? e.target.closest('.ndg-mob-back') : null;
      if (back) {
        document.querySelectorAll('.ndg-rd-detail-panel').forEach(function (p) { p.classList.add('d-none'); });
        document.querySelectorAll('.ndg-rd-row, .ndg-rd-expand').forEach(function (x) { x.classList.remove('on'); });
      }
    });
    var gotoPending = document.querySelector('.ndg-mob-goto-pending');
    if (gotoPending) {
      gotoPending.addEventListener('click', function () {
        showMobScreen('customers');
        var chip = document.querySelector('.ndg-mob-chip[data-filter="pending"]');
        if (chip) { chip.click(); }
      });
    }

    // ── Customers screen: search + status chips ──
    var mobSearch = document.getElementById('ndgMobSearch');
    var mobChips = document.querySelectorAll('.ndg-mob-chip');
    function applyMobFilter() {
      var q = (mobSearch ? mobSearch.value : '').trim().toLowerCase();
      var filter = document.querySelector('.ndg-mob-chip.on') ? document.querySelector('.ndg-mob-chip.on').dataset.filter : 'all';
      document.querySelectorAll('.ndg-mob-cust-card').forEach(function (card) {
        var matchesQ = !q || card.dataset.q.indexOf(q) !== -1;
        var matchesFilter = filter === 'all' || filter === card.dataset.state;
        card.style.display = matchesQ && matchesFilter ? '' : 'none';
      });
    }
    if (mobSearch) { mobSearch.addEventListener('input', applyMobFilter); }
    mobChips.forEach(function (chip) {
      chip.addEventListener('click', function () {
        mobChips.forEach(function (c) { c.classList.toggle('on', c === chip); });
        applyMobFilter();
      });
    });

    // ── Customer detail overlay: save this week's status straight from the stepper card ──
    document.querySelectorAll('.ndg-mob-save').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var card = btn.closest('.ndg-mob-edit-card');
        var goalId = parseInt(card.dataset.goal, 10);
        var status = card.querySelector('.ndg-mob-status').value;
        var stage = card.querySelector('.ndg-mob-stage').value;
        var comment = card.querySelector('.ndg-mob-comment').value.trim();
        if (!status) { alert('Pick a status first.'); return; }
        btn.disabled = true;
        Promise.all([
          post(URLS.checkin, 'POST', { user_id: ROUND_USER_ID, week_no: SELECTED_WEEK, entries: [{ goal_id: goalId, status: status, comment: comment, next_action: '', expected_stage: null }] }),
          post(URLS.update + '/' + goalId, 'PUT', { goal: stage }),
        ]).then(function (res) {
          if (res[0].success) { location.reload(); return; }
          btn.disabled = false;
          alert(res[0].message || 'Could not save.');
        }).catch(function () { btn.disabled = false; alert('Request failed. Try again.'); });
      });
    });
  }

  // ── filter the customer table ──
  var search = document.getElementById('ndgRdSearch'), statusSel = document.getElementById('ndgRdStatus'), brandSel = document.getElementById('ndgRdBrand'), countEl = document.getElementById('ndgRdCount');
  function applyFilter() {
    var q = search.value.trim().toLowerCase(), status = statusSel.value, brand = brandSel.value, shown = 0, total = 0;
    document.querySelectorAll('.ndg-rd-row').forEach(function (row) {
      total++;
      var ok = (!q || row.dataset.q.indexOf(q) !== -1) && (!status || row.dataset.status === status) && (!brand || row.dataset.brand === brand);
      row.style.display = ok ? '' : 'none';
      var exp = document.getElementById('ndgRdExp' + row.dataset.goal);
      if (exp && !ok) { exp.classList.add('d-none'); }
      if (ok) { shown++; }
    });
    countEl.textContent = 'Showing ' + shown + ' of ' + total;
  }
  [search].forEach(function (el) { el.addEventListener('input', applyFilter); });
  [statusSel, brandSel].forEach(function (el) { el.addEventListener('change', applyFilter); });
  applyFilter();

  // ── ApexCharts — waits for DOMContentLoaded since Vite's build emits ApexCharts as an ES
  // module, which the spec defers until after the document has parsed.
  document.addEventListener('DOMContentLoaded', function () {
    if (typeof ApexCharts === 'undefined') { return; }
    var teal = '#007a7a';
    function renderDonut(id, series, labels, colors, height) {
      var el = document.querySelector(id);
      if (!el) { return; }
      new ApexCharts(el, {
        chart: { type: 'donut', height: height, fontFamily: 'inherit' },
        series: series, labels: labels, colors: colors,
        legend: { position: 'bottom', fontSize: '11px' },
        dataLabels: { enabled: false },
        plotOptions: { pie: { donut: { size: '68%' } } },
      }).render();
    }
    var statuses = @json($d['statuses']);
    var colorMap = @json(collect($G::STATUS_COLORS)->map(fn ($c) => $c[0]));
    var statusLabels = Object.keys(statuses).filter(function (k) { return statuses[k] > 0; });
    var statusSeries = statusLabels.map(function (l) { return statuses[l]; });
    var statusColors = statusLabels.map(function (l) { return colorMap[l] || '#94a3b8'; });
    renderDonut('#ndgRdStatusDonut', statusSeries, statusLabels, statusColors, 260);
    renderDonut('#ndgMobInsightsDonut', statusSeries, statusLabels, statusColors, 240);

    var ws = @json($weekSummary);
    var weekSeries = [ws.submitted, ws.pending, ws.overdue, ws.notStarted];
    var weekLabels = ['Submitted', 'Pending', 'Overdue', 'Not Started'];
    var weekColors = [teal, '#f5a623', '#ef4444', '#cbd5e1'];
    renderDonut('#ndgWeekDonut', weekSeries, weekLabels, weekColors, 220);
    renderDonut('#ndgMobOverviewDonut', weekSeries, weekLabels, weekColors, 220);
  });

  // ── edit-goal modal (product/brand/goal/potential/expected order date) ──
  document.querySelectorAll('.ndg-edit-goal').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var modalEl = document.getElementById('ndgEditGoalModal');
      if (!modalEl) { return; }
      modalEl.querySelector('[name=goal_id]').value = btn.dataset.id;
      modalEl.querySelector('[name=product]').value = btn.dataset.product || '';
      modalEl.querySelector('[name=brand]').value = btn.dataset.brand || '';
      modalEl.querySelector('[name=goal]').value = btn.dataset.goal || '';
      modalEl.querySelector('[name=monthly_potential]').value = btn.dataset.potential || '';
      if (modalEl.querySelector('[name=expected_order_date]')) { modalEl.querySelector('[name=expected_order_date]').value = btn.dataset.expected || ''; }
      modalEl.querySelector('.modal-title-goal').textContent = btn.dataset.title || '';
      new bootstrap.Modal(modalEl).show();
    });
  });
  var saveGoalBtn = document.getElementById('ndgRdSaveGoal');
  if (saveGoalBtn) {
    saveGoalBtn.addEventListener('click', function () {
      var modalEl = document.getElementById('ndgEditGoalModal');
      var id = modalEl.querySelector('[name=goal_id]').value;
      post(URLS.update + '/' + id, 'PUT', {
        product: modalEl.querySelector('[name=product]').value,
        brand: modalEl.querySelector('[name=brand]').value,
        goal: modalEl.querySelector('[name=goal]').value,
        monthly_potential: parseFloat(modalEl.querySelector('[name=monthly_potential]').value || '0'),
        expected_order_date: modalEl.querySelector('[name=expected_order_date]') ? modalEl.querySelector('[name=expected_order_date]').value : null,
      }).then(function (j) {
        if (j.success) { location.reload(); return; }
        alert(j.message || 'Could not save.');
      });
    });
  }

  // ── add customer modal: only this round owner's own Sales Pipeline, nobody types one in.
  // Checking a customer expands Product / Brand / Goal / Target right there, editable
  // before it's added — not just accepted blind from whatever the pipeline already says. ──
  var addModalEl = document.getElementById('ndgRdAddModal');
  if (addModalEl) {
    var addList = document.getElementById('ndgRdAddList'), addSearch = document.getElementById('ndgRdAddSearch'), addMsg = document.getElementById('ndgRdAddMsg');
    var pipeline = [], addBrands = [], addLoaded = false;

    var GOAL_OPTIONS = ['Order', 'New Order', 'Regular Order', 'Repeat Order', 'Trial Order', 'Renewal', 'Upsell'];
    function brandOptions(sel) {
      return '<option value="">Brand</option>' + addBrands.map(function (b) { return '<option' + (b === sel ? ' selected' : '') + '>' + esc(b) + '</option>'; }).join('');
    }
    function goalOptions(sel) {
      sel = sel || 'Order';
      // A custom value already on record (typed before this was a dropdown) stays selectable —
      // never silently swapped out for the nearest preset just because the list changed.
      var opts = GOAL_OPTIONS.indexOf(sel) === -1 ? GOAL_OPTIONS.concat([sel]) : GOAL_OPTIONS;
      return opts.map(function (g) { return '<option' + (g === sel ? ' selected' : '') + '>' + esc(g) + '</option>'; }).join('');
    }
    function renderList() {
      var q = addSearch.value.trim().toLowerCase();
      var shown = pipeline.filter(function (c) { return !q || (c.customer + ' ' + c.product + ' ' + c.brand).toLowerCase().indexOf(q) !== -1; });
      if (!pipeline.length) { addList.innerHTML = '<div class="text-muted p-3">No customers left to add. Everything in your Sales Pipeline and Sales Report is already in this round, or you have none yet.</div>'; return; }
      if (!shown.length) { addList.innerHTML = '<div class="text-muted p-3">Nothing matches that search.</div>'; return; }
      addList.innerHTML = shown.map(function (c) {
        return '<div class="ndg-row' + (c.picked ? ' on' : '') + '" data-id="' + c.id + '">' +
          '<label class="d-flex align-items-center gap-2 mb-0" style="cursor:pointer"><input type="checkbox" class="form-check-input ndg-pick"' + (c.picked ? ' checked' : '') + '>' +
          '<span class="fw-semibold">' + esc(c.customer) + '</span> <span class="badge badge-hitech badge-muted ndg-src">' + esc(c.source) + '</span>' +
          '<span class="text-muted small ms-2">' + esc(c.product) + '</span></label>' +
          '<div class="ndg-extra gap-2 flex-wrap">' +
            '<input type="text" class="form-control form-control-sm ndg-product" style="max-width:220px" placeholder="Product" value="' + esc(c.pProduct != null ? c.pProduct : c.product) + '">' +
            '<select class="form-select form-select-sm ndg-brand" style="max-width:150px">' + brandOptions(c.pBrand != null ? c.pBrand : c.brand) + '</select>' +
            '<select class="form-select form-select-sm ndg-goal" style="max-width:170px">' + goalOptions(c.pGoal != null ? c.pGoal : 'Order') + '</select>' +
            '<input type="number" step="0.01" min="0" class="form-control form-control-sm ndg-pot" style="max-width:150px" placeholder="Target ₹ lakh"' + (c.potential ? ' title="Sales Report potential: ' + esc(c.potential) + ' lakh a month"' : '') + ' value="' + esc(c.pPot != null ? c.pPot : '') + '">' +
          '</div></div>';
      }).join('');
    }
    function remember(row) {
      var c = pipeline.filter(function (x) { return String(x.id) === row.getAttribute('data-id'); })[0]; if (!c) { return; }
      c.picked = row.querySelector('.ndg-pick').checked;
      c.pProduct = row.querySelector('.ndg-product').value; c.pBrand = row.querySelector('.ndg-brand').value;
      c.pGoal = row.querySelector('.ndg-goal').value; c.pPot = row.querySelector('.ndg-pot').value;
    }
    addList.addEventListener('input', function (e) { var r = e.target.closest('.ndg-row'); if (r) { remember(r); } });
    addList.addEventListener('change', function (e) { var r = e.target.closest('.ndg-row'); if (r) { remember(r); r.classList.toggle('on', r.querySelector('.ndg-pick').checked); } });
    addSearch.addEventListener('input', renderList);

    addModalEl.addEventListener('show.bs.modal', function () {
      if (addLoaded) { addSearch.focus(); return; }
      addMsg.textContent = ''; addList.innerHTML = '<div class="text-muted p-3">Loading your customers…</div>';
      fetch(URLS.customers + '?user_id=' + ROUND_USER_ID, { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          pipeline = j.success ? j.customers : []; addBrands = j.brands || []; addLoaded = true;
          renderList(); addSearch.focus();
        })
        .catch(function () { addList.innerHTML = '<div class="text-danger p-3">Could not load your customers. Close this and try again.</div>'; });
    });

    document.getElementById('ndgRdAddSave').addEventListener('click', function () {
      var picked = pipeline.filter(function (c) { return c.picked; });
      if (!picked.length) { addMsg.textContent = 'Tick at least one customer.'; return; }
      for (var i = 0; i < picked.length; i++) {
        if (!String(picked[i].pGoal != null ? picked[i].pGoal : 'Order').trim()) { addMsg.textContent = picked[i].customer + ': write the goal.'; return; }
        if (picked[i].pPot === undefined || picked[i].pPot === '' || isNaN(parseFloat(picked[i].pPot))) { addMsg.textContent = picked[i].customer + ': enter the target in lakh.'; return; }
      }
      addMsg.textContent = ''; this.disabled = true; var btn = this;
      var customers = picked.map(function (c) {
        return { sales_pipeline_id: c.id, product: c.pProduct != null ? c.pProduct : c.product, brand: c.pBrand != null ? c.pBrand : c.brand, goal: c.pGoal != null ? c.pGoal : 'Order', monthly_potential: parseFloat(c.pPot) };
      });
      post(URLS.store, 'POST', { user_id: ROUND_USER_ID, customers: customers }).then(function (j) {
        if (j.success) { location.reload(); return; }
        btn.disabled = false;
        addMsg.textContent = j.message || (j.errors ? Object.values(j.errors).join(' ') : 'Could not add.');
      }).catch(function () { btn.disabled = false; addMsg.textContent = 'Request failed. Try again.'; });
    });
  }
})();
</script>
@endsection
