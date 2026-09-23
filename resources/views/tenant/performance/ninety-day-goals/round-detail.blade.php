@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')

@section('title', '90-Day Goals — ' . (optional($round->program)->name ?? 'My Program'))

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
  $fmtRs = fn ($v) => '₹' . number_format((float) $v, 0);
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
@endphp
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4 mx-4">
    <div>
      <nav class="small text-muted mb-1">
        <a href="{{ route('tenant.dashboard') }}" class="text-muted">Dashboard</a> <i class="bx bx-chevron-right"></i>
        <a href="{{ route('performance.ninety-day-goals.index') }}" class="text-muted">90-Day Goals</a> <i class="bx bx-chevron-right"></i>
        {{ $title }} <i class="bx bx-chevron-right"></i> Weekly Submission
      </nav>
      <h3 class="fw-extrabold mb-1 text-dark d-flex align-items-center gap-2">
        {{ $title }}
        <span class="badge rounded-pill fw-bold" style="font-size:.62rem;background:{{ $isActive ? '#d7f3ee' : '#e2e8f0' }};color:{{ $isActive ? '#0b5b48' : '#475569' }}">{{ ucfirst($round->status) }}</span>
      </h3>
      <p class="text-muted mb-0 small">{{ $round->starts_on->format('d M Y') }} – {{ $round->ends_on->format('d M Y') }} ({{ $weeks * 7 }} days) · Submit your weekly updates, track progress and achieve your sales goals.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <div class="ndg-infobox"><div class="small text-muted fw-semibold">Current Week</div><div class="fw-bold text-dark fs-5">W{{ $currentWeek }}</div><div class="small text-muted">{{ $dates[$currentWeek]->format('d M') }} – {{ $dates[$currentWeek]->copy()->addDays(6)->format('d M Y') }}</div></div>
      <div class="ndg-infobox"><div class="small text-muted fw-semibold">Next Week</div><div class="fw-bold text-dark fs-5">W{{ $nextWeekIdx }}</div><div class="small text-muted">{{ $dates[$nextWeekIdx]->format('d M') }} – {{ $dates[$nextWeekIdx]->copy()->addDays(6)->format('d M Y') }}</div></div>
      <div class="ndg-infobox"><div class="small text-muted fw-semibold"><i class="bx bx-calendar"></i> Days Left</div><div class="fw-bold text-dark fs-5">{{ $daysLeft }}</div><div class="small text-muted">&nbsp;</div></div>
    </div>
  </div>

  <div class="px-4">

  {{-- KPI cards + Submit All Updates --}}
  <div class="row g-3 mb-6 align-items-stretch">
    <div class="col-6 col-lg">
      <div class="ndg-kpi3"><div class="ndg-kpi3-icon" style="background:#e0f2fe;color:#0369a1"><i class="bx bx-group"></i></div><div><div class="small text-muted fw-semibold">My Customers</div><div class="ndg-kpi3-v">{{ $counts['total'] }}</div></div></div>
    </div>
    <div class="col-6 col-lg">
      <div class="ndg-kpi3"><div class="ndg-kpi3-icon" style="background:#fff7ed;color:#c2540c"><i class="bx bx-rupee"></i></div><div><div class="small text-muted fw-semibold">My Target</div><div class="ndg-kpi3-v">{{ $fmtRs($target * 100000 / 100) }}</div></div></div>
    </div>
    <div class="col-6 col-lg">
      <div class="ndg-kpi3"><div class="ndg-kpi3-icon" style="background:#dcfce7;color:#166534"><i class="bx bx-bar-chart-alt-2"></i></div>
        <div class="flex-grow-1"><div class="small text-muted fw-semibold">My Achieved</div><div class="ndg-kpi3-v">{{ $fmtRs($achieved * 100000 / 100) }}</div>
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
            <div class="fw-bold">W{{ $w['week'] }}</div>
            <div class="small">{{ $dates[$w['week']]->format('d-M') }}</div>
            <div class="small">—</div>
          </span>
        @else
          <a href="?{{ http_build_query(['w' => $w['week']]) }}" class="ndg-week-pill {{ $w['week'] === $selectedWeek ? 'on' : '' }} {{ $w['week'] === $currentWeek ? 'is-current' : '' }}">
            <div class="fw-bold">W{{ $w['week'] }}</div>
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
              @if($canEdit && $isActive)
                <button type="button" class="btn-hitech rounded-pill px-3 py-2 d-flex align-items-center gap-2" style="font-size:.8rem" data-bs-toggle="modal" data-bs-target="#ndgRdAddModal"><i class="bx bx-plus"></i> Add Customer</button>
              @endif
            </div>
          </div>
          <div class="card-body p-0 ndg-mx-scroll">
            <table class="table align-middle mb-0" id="ndgWeeklyTable" style="min-width:1100px">
              <thead class="small text-uppercase text-muted">
                <tr>
                  <th class="ps-3">#</th><th style="min-width:160px">Customer</th><th>Product</th><th class="text-end">Target (₹)</th><th style="min-width:150px">Expected Stage</th>
                  <th style="min-width:150px">Status (W{{ $selectedWeek }})</th><th style="min-width:200px">Comment / Remark</th><th style="min-width:160px">Next Action</th><th style="min-width:150px">Next Follow-up Date</th><th class="pe-3"></th>
                </tr>
              </thead>
              <tbody>
              @forelse($goals as $i => $g)
                @php $ck = $g->checkins->firstWhere('week_no', $selectedWeek); @endphp
                <tr class="ndg-wk-row" data-goal="{{ $g->id }}">
                  <td class="ps-3 text-muted">{{ $i + 1 }}</td>
                  <td class="fw-semibold text-dark">{{ $g->client_name }}</td>
                  <td class="small text-muted">{{ $g->product }}</td>
                  <td class="text-end">{{ number_format($g->monthly_potential * 100000 / 100, 0) }}</td>
                  <td>
                    @php $curStage = $g->goal ?: 'Order'; @endphp
                    <select class="form-select form-select-hitech ndg-wk-expected" {{ $canEdit && $isActive ? '' : 'disabled' }}>
                      @foreach($stageChoices($curStage) as $stage)
                        <option value="{{ $stage }}" {{ $curStage === $stage ? 'selected' : '' }}>{{ $stage }}</option>
                      @endforeach
                    </select>
                  </td>
                  <td>
                    <select class="form-select form-select-hitech ndg-wk-status" {{ $canEdit && $isActive ? '' : 'disabled' }}>
                      <option value="">Choose…</option>
                      @foreach($statuses as $s)<option value="{{ $s }}" {{ optional($ck)->status === $s ? 'selected' : '' }}>{{ $s }}</option>@endforeach
                    </select>
                  </td>
                  <td><input type="text" class="form-control ndg-wk-comment" maxlength="1000" placeholder="Optional note" value="{{ optional($ck)->comment }}" {{ $canEdit && $isActive ? '' : 'disabled' }}></td>
                  <td><input type="text" class="form-control ndg-wk-next-action" maxlength="255" placeholder="e.g. Share quotation" value="{{ optional($ck)->next_action }}" {{ $canEdit && $isActive ? '' : 'disabled' }}></td>
                  <td><input type="date" class="form-control ndg-wk-next-date" value="{{ optional($ck)->next_follow_up_date ? $ck->next_follow_up_date->format('Y-m-d') : '' }}" {{ $canEdit && $isActive ? '' : 'disabled' }}></td>
                  <td class="pe-3 text-end">
                    @if($canEdit && $isActive)
                      <button type="button" class="btn btn-sm btn-hitech-cancel ndg-wk-save">Save</button>
                    @endif
                  </td>
                </tr>
              @empty
                <tr><td colspan="10" class="text-center text-muted p-5">No target customers yet — add one from Customer Summary.</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
          @if($canEdit && $isActive && $goals->isNotEmpty())
          <div class="card-body pt-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <button type="button" class="btn btn-hitech-cancel rounded-pill px-4" id="ndgWkPrev"><i class="bx bx-chevron-left"></i> Previous Week</button>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-hitech-cancel rounded-pill px-4" id="ndgSaveDraft">Save Draft</button>
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
          @if($canEdit && $isActive)
          <button type="button" class="btn-hitech rounded-pill px-4 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#ndgRdAddModal"><i class="bx bx-plus"></i> Add Customer</button>
          @endif
          <a href="{{ route('performance.ninety-day-goals.admin-view.export', ['user_id' => $round->user_id, 'program_id' => optional($round->program)->id]) }}" class="btn btn-hitech-cancel rounded-pill px-4 d-flex align-items-center gap-2"><i class="bx bx-download"></i> Export</a>
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
              <th class="ps-3">#</th><th>Customer</th><th>Product</th><th class="text-end">Goal (₹)</th><th>Expected Order</th>
              @for($w = 0; $w < $weeks; $w++)<th class="text-center">W{{ $w }}</th>@endfor
              <th class="text-end">Actual Order (₹)</th><th>Status</th><th class="pe-3"></th>
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
              <td class="ps-3"><button type="button" class="ndg-rd-expand" data-target="ndgRdExp{{ $g->id }}"><i class="bx bx-chevron-down"></i></button></td>
              <td class="text-muted">{{ $i + 1 }}</td>
              <td class="fw-semibold text-dark">{{ $g->client_name }}</td>
              <td class="small text-muted">{{ $g->product }}</td>
              <td class="text-end">{{ number_format($g->monthly_potential * 100000 / 100, 0) }}</td>
              <td class="small text-muted">{{ $g->expected_order_date ? $g->expected_order_date->format('d M Y') : '—' }}</td>
              @for($w = 0; $w < $weeks; $w++)
                @php
                  $ck = $cks->get($w);
                  $st = optional($ck)->status;
                  [$bg, $fg] = $st ? ($G::STATUS_COLORS[$st] ?? ['#94a3b8', '#fff']) : [null, null];
                @endphp
                <td class="text-center">
                  @if($st)<span class="ndg-chip" style="background:{{ $bg }};color:{{ $fg }};font-size:.62rem;padding:2px 6px" title="{{ $st }}">{{ \Illuminate\Support\Str::limit($st, 8, '') }}</span>
                  @else<span class="ndg-none">-</span>@endif
                </td>
              @endfor
              <td class="text-end fw-semibold">{{ $r ? number_format($r->achieved * 100000 / 100, 0) : 0 }}</td>
              <td><span class="ndg-chip" style="background:{{ $bucketColor[0] }};color:{{ $bucketColor[1] }}">{{ $bucketLabel }}</span></td>
              <td class="pe-3 text-end">
                <div class="dropdown">
                  <button class="btn btn-sm btn-icon" data-bs-toggle="dropdown"><i class="bx bx-dots-vertical-rounded"></i></button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li><button type="button" class="dropdown-item ndg-rd-expand" data-target="ndgRdExp{{ $g->id }}">View details</button></li>
                    @if($canEdit)<li><button type="button" class="dropdown-item ndg-edit-goal" data-id="{{ $g->id }}" data-product="{{ e($g->product) }}" data-brand="{{ e($g->brand) }}" data-goal="{{ e($g->goal ?: $g->goal_type) }}" data-potential="{{ $g->monthly_potential }}" data-expected="{{ optional($g->expected_order_date)->format('Y-m-d') }}" data-title="{{ e($g->client_name) }}">Edit</button></li>@endif
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
                        <div class="small text-muted">W{{ $ck->week_no }} · {{ $ck->due_on->format('d M') }}</div>
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

    {{-- Bottom detail panel: one per customer, shown/hidden by the expand toggle --}}
    <div id="ndgRdDetail">
      @foreach($goals as $g)
        @php $r = $rowsByGoal->get($g->id); @endphp
        <div class="ndg-rd-detail-panel d-none" data-goal="{{ $g->id }}">
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
                    <div class="d-flex justify-content-between small py-1 border-bottom"><span class="text-muted">Goal Amount</span><b>{{ number_format($g->monthly_potential * 100000 / 100, 0) }}</b></div>
                    <div class="d-flex justify-content-between small py-1 border-bottom"><span class="text-muted">Expected Order Date</span><b>{{ $g->expected_order_date ? $g->expected_order_date->format('d M Y') : '—' }}</b></div>
                    <div class="d-flex justify-content-between small py-1"><span class="text-muted">Current Status</span>
                      @if(optional($r)->status)<span class="ndg-chip" style="background:{{ $G::STATUS_COLORS[$r->status][0] ?? '#e2e8f0' }};color:{{ $G::STATUS_COLORS[$r->status][1] ?? '#475569' }}">{{ $r->status }}</span>@else<span class="ndg-none">·</span>@endif
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="hitech-card h-100">
                  <div class="card-body p-4">
                    <div class="fw-bold text-dark mb-3"><i class="bx bx-trending-up" style="color:#007a7a"></i> Progress</div>
                    <div class="d-flex justify-content-between small py-1 border-bottom"><span class="text-muted">Target (3 mo.)</span><b>{{ $fmtL(optional($r)->target ?? 0) }} L</b></div>
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
                @forelse($g->checkins->whereNotNull('status') as $ck)
                  <div class="ndg-rd-wk-card" style="min-width:150px">
                    <div class="small text-muted">W{{ $ck->week_no }} · {{ $ck->due_on->format('d M') }}</div>
                    <span class="ndg-chip mt-1" style="background:{{ $G::STATUS_COLORS[$ck->status][0] ?? '#e2e8f0' }};color:{{ $G::STATUS_COLORS[$ck->status][1] ?? '#475569' }}">{{ $ck->status }}</span>
                    <div class="small mt-1">{{ $ck->comment ?: '—' }}</div>
                    @if($ck->next_action)<div class="small mt-1 text-muted"><b>Next:</b> {{ $ck->next_action }}@if($ck->next_follow_up_date) · {{ $ck->next_follow_up_date->format('d M') }}@endif</div>@endif
                    @foreach($ck->notes as $n)
                      <div class="small mt-1 pt-1 border-top"><b>{{ trim(optional($n->author)->first_name . ' ' . optional($n->author)->last_name) }}:</b> {{ $n->body }}</div>
                    @endforeach
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
              <td class="pe-4">@if($r->status)<span class="ndg-chip" style="background:{{ $G::STATUS_COLORS[$r->status][0] ?? '#e2e8f0' }};color:{{ $G::STATUS_COLORS[$r->status][1] ?? '#475569' }}">{{ $r->status }}</span>@endif</td>
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
          <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Top customers by monthly potential</div></div>
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
              <td class="ps-4 fw-semibold">W{{ $w }}</td>
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

  </div>
</div>

@if($canEdit)
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

  {{-- Edit an existing target: product, brand, goal, monthly potential, expected order date. --}}
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
            <div class="mb-3"><label class="form-label fw-bold small text-muted text-uppercase">Monthly potential (₹ lakh)</label><input type="number" step="0.01" min="0" name="monthly_potential" class="form-control"></div>
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
</style>
@endsection

@section('page-script')
<script>
(function () {
  var CSRF = '{{ csrf_token() }}';
  var ROUND_USER_ID = {{ (int) $round->user_id }};
  var SELECTED_WEEK = {{ (int) $selectedWeek }};
  var URLS = {
    checkin: '{{ route('performance.ninety-day-goals.checkin') }}',
    update: '{{ url('performance/ninety-day-goals') }}',
    customers: '{{ route('performance.ninety-day-goals.pipeline-customers') }}',
    store: '{{ route('performance.ninety-day-goals.store') }}'
  };
  function post(url, method, body) {
    return fetch(url, { method: method, headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }, body: body ? JSON.stringify(body) : undefined })
      .then(function (r) { return r.json().then(function (j) { j._status = r.status; return j; }); });
  }
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
  // Expected Stage lives on the goal itself (the same "goal" field Add Customer's own dropdown
  // sets), not the weekly checkin, so it saves through the existing per-goal update endpoint —
  // a small extra call alongside the checkin save.
  function saveExpectedStages(entries) {
    entries.forEach(function (e) {
      if (!e.expected_stage) { return; }
      post(URLS.update + '/' + e.goal_id, 'PUT', { goal: e.expected_stage });
    });
  }
  function saveEntries(entries, btn) {
    if (!entries.length) { return; }
    if (btn) { btn.disabled = true; }
    wkMsg.textContent = 'Saving…'; wkMsg.className = 'small text-muted';
    saveExpectedStages(entries);
    post(URLS.checkin, 'POST', { user_id: ROUND_USER_ID, week_no: SELECTED_WEEK, entries: entries }).then(function (j) {
      if (btn) { btn.disabled = false; }
      if (j.success) {
        wkMsg.textContent = j.message || 'Saved.'; wkMsg.className = 'small text-success';
        if (typeof window.showSuccessSwal === 'function' && entries.length > 1) { window.showSuccessSwal(j.message); }
      } else {
        wkMsg.textContent = j.message || (j.errors ? j.errors.join(' ') : 'Nothing saved — check the statuses above.'); wkMsg.className = 'small text-danger';
      }
    }).catch(function () {
      if (btn) { btn.disabled = false; }
      wkMsg.textContent = 'Request failed. Try again.'; wkMsg.className = 'small text-danger';
    });
  }
  document.querySelectorAll('.ndg-wk-save').forEach(function (btn) {
    btn.addEventListener('click', function () { saveEntries([readRow(btn.closest('.ndg-wk-row'))], btn); });
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
  function allRows() { return Array.prototype.map.call(document.querySelectorAll('.ndg-wk-row'), readRow).filter(function (e) { return e.status; }); }
  ['ndgSubmitAllTop', 'ndgSubmitAllBottom'].forEach(function (id) {
    var btn = document.getElementById(id);
    if (btn) { btn.addEventListener('click', function () {
      var entries = allRows();
      if (!entries.length) { wkMsg.textContent = 'Pick a status for at least one customer first.'; wkMsg.className = 'small text-danger'; return; }
      saveEntries(entries, btn);
    }); }
  });
  var draftBtn = document.getElementById('ndgSaveDraft');
  if (draftBtn) { draftBtn.addEventListener('click', function () {
    var entries = allRows();
    if (!entries.length) { wkMsg.textContent = 'Nothing filled in yet.'; wkMsg.className = 'small text-muted'; return; }
    saveEntries(entries, draftBtn);
  }); }
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
  var firstRow = document.querySelector('.ndg-rd-row');
  if (firstRow) { document.querySelector('.ndg-rd-detail-panel[data-goal="' + firstRow.dataset.goal + '"]')?.classList.remove('d-none'); }

  document.querySelectorAll('.ndg-rd-detail-panel').forEach(function (panel) {
    panel.querySelectorAll('.ndg-rd-dtab').forEach(function (t) {
      t.addEventListener('click', function () {
        panel.querySelectorAll('.ndg-rd-dtab').forEach(function (x) { x.classList.toggle('on', x === t); });
        panel.querySelectorAll('.ndg-rd-dsection').forEach(function (s) { s.classList.toggle('d-none', s.dataset.dsection !== t.dataset.dtab); });
      });
    });
  });

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
    var donutEl = document.querySelector('#ndgRdStatusDonut');
    if (donutEl) {
      var statuses = @json($d['statuses']);
      var colorMap = @json(collect($G::STATUS_COLORS)->map(fn ($c) => $c[0]));
      var labels = Object.keys(statuses).filter(function (k) { return statuses[k] > 0; });
      new ApexCharts(donutEl, {
        chart: { type: 'donut', height: 260, fontFamily: 'inherit' },
        series: labels.map(function (l) { return statuses[l]; }),
        labels: labels,
        colors: labels.map(function (l) { return colorMap[l] || '#94a3b8'; }),
        legend: { position: 'bottom', fontSize: '11px' },
        dataLabels: { enabled: false },
        plotOptions: { pie: { donut: { size: '68%' } } },
      }).render();
    }
    var weekDonutEl = document.querySelector('#ndgWeekDonut');
    if (weekDonutEl) {
      var ws = @json($weekSummary);
      new ApexCharts(weekDonutEl, {
        chart: { type: 'donut', height: 220, fontFamily: 'inherit' },
        series: [ws.submitted, ws.pending, ws.overdue, ws.notStarted],
        labels: ['Submitted', 'Pending', 'Overdue', 'Not Started'],
        colors: [teal, '#f5a623', '#ef4444', '#cbd5e1'],
        legend: { position: 'bottom', fontSize: '11px' },
        dataLabels: { enabled: false },
        plotOptions: { pie: { donut: { size: '68%' } } },
      }).render();
    }
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
  // Checking a customer expands Product / Brand / Goal / Monthly potential right there, editable
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
            '<input type="number" step="0.01" min="0" class="form-control form-control-sm ndg-pot" style="max-width:150px" placeholder="Monthly ₹ lakh" value="' + esc(c.pPot != null ? c.pPot : c.potential) + '">' +
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
        if (picked[i].pPot === undefined || picked[i].pPot === '' || isNaN(parseFloat(picked[i].pPot))) { addMsg.textContent = picked[i].customer + ': enter the monthly potential in lakh.'; return; }
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
