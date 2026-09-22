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
@endphp
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4 mx-4">
    <div>
      <h3 class="fw-extrabold mb-1 text-dark d-flex align-items-center gap-2">
        {{ $title }}
        <span class="badge rounded-pill fw-bold" style="font-size:.62rem;background:{{ $isActive ? '#d7f3ee' : '#e2e8f0' }};color:{{ $isActive ? '#0b5b48' : '#475569' }}">{{ ucfirst($round->status) }}</span>
      </h3>
      <p class="text-muted mb-0 small">Track weekly progress and results for every target customer in this round.</p>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ url()->previous() ?: route('performance.ninety-day-goals.index') }}" class="btn btn-hitech-cancel rounded-pill px-4 d-flex align-items-center gap-2"><i class="bx bx-arrow-back"></i> Back</a>
    </div>
  </div>

  <div class="px-4">

  {{-- Round summary strip --}}
  <div class="hitech-card mb-6">
    <div class="card-body p-4 d-flex flex-wrap align-items-center gap-4">
      <div class="d-flex align-items-center gap-2">
        <i class="bx bx-calendar fs-4" style="color:#007a7a"></i>
        <div>
          <div class="fw-bold text-dark">{{ $round->starts_on->format('d M Y') }} - {{ $round->ends_on->format('d M Y') }}</div>
          <div class="small text-muted">({{ $weeks * 7 }} days)</div>
        </div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <i class="bx bx-user fs-4" style="color:#007a7a"></i>
        <div>
          <div class="fw-bold text-dark">{{ trim($round->user->first_name . ' ' . $round->user->last_name) }}</div>
          <div class="small text-muted">{{ optional($round->user->department)->name }}</div>
        </div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <i class="bx bx-target-lock fs-4" style="color:#007a7a"></i>
        <div>
          <div class="fw-bold text-dark">{{ $fmtRs($target * 100000 / 100) }}</div>
          <div class="small text-muted">Target (₹{{ $fmtL($target) }} L)</div>
        </div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <i class="bx bx-line-chart fs-4" style="color:#007a7a"></i>
        <div>
          <div class="fw-bold text-dark">₹{{ $fmtL($achieved) }} L <span style="color:{{ $pctColor($pct) }}">({{ $pctText($pct) }})</span></div>
          <div class="small text-muted">Achievement · {{ $counts['orders'] }} of {{ $counts['total'] }} converted</div>
        </div>
      </div>
      <div class="flex-grow-1" style="min-width:180px">
        <div class="progress" style="height:8px"><div class="progress-bar" style="width:{{ $pct === null ? 0 : min(100, $pct) }}%;background:{{ $pctColor($pct) }}"></div></div>
      </div>
    </div>
  </div>

  {{-- Sub-tabs --}}
  <div class="d-flex gap-2 flex-wrap mb-6 ndg-subtabs" id="ndgRdTabs">
    @foreach(['customers' => ['Customers','bx-list-ul'], 'overview' => ['Overview','bx-grid-alt'], 'weekly' => ['Weekly Updates','bx-calendar-check'], 'orders' => ['Orders & Results','bx-cart-download'], 'activity' => ['Activity Log','bx-history'], 'comments' => ['Comments','bx-comment-detail']] as $key => [$lbl, $ic])
      <button type="button" class="ndg-subtab {{ $key === 'customers' ? 'on' : '' }}" data-tab="{{ $key }}"><i class="bx {{ $ic }}"></i>{{ $lbl }}</button>
    @endforeach
  </div>

  {{-- ═══════════════════ Customers ═══════════════════ --}}
  <div class="ndg-rd-section" data-section="customers">

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

  {{-- ═══════════════════ Overview ═══════════════════ --}}
  <div class="ndg-rd-section d-none" data-section="overview">
    <div class="row g-4 mb-6">
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
  </div>

  {{-- ═══════════════════ Weekly Updates ═══════════════════ --}}
  <div class="ndg-rd-section d-none" data-section="weekly">
    <div class="hitech-card mb-6">
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

  {{-- ═══════════════════ Activity Log ═══════════════════ --}}
  <div class="ndg-rd-section d-none" data-section="activity">
    <div class="hitech-card mb-6">
      <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">{{ $activity->count() }} events</div></div>
      <div class="card-body p-0">
        @forelse($activity as $a)
          <div class="ndg-alert-row">
            <i class="bx {{ $a->kind === 'note' ? 'bx-message-square-detail' : 'bx-radio-circle-marked' }}"></i>
            <div class="flex-grow-1">
              <div class="small text-dark"><b>{{ trim(optional($a->by)->first_name . ' ' . optional($a->by)->last_name) ?: 'Someone' }}</b>
                {{ $a->kind === 'note' ? 'commented on' : 'set status for' }} {{ $a->goal->client_name }}
                @if($a->kind !== 'note' && $a->status)· <span class="ndg-chip" style="background:{{ $G::STATUS_COLORS[$a->status][0] ?? '#e2e8f0' }};color:{{ $G::STATUS_COLORS[$a->status][1] ?? '#475569' }}">{{ $a->status }}</span>@endif
              </div>
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

  {{-- ═══════════════════ Comments ═══════════════════ --}}
  <div class="ndg-rd-section d-none" data-section="comments">
    <div class="hitech-card mb-6">
      <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Every comment, newest first</div></div>
      <div class="card-body p-0">
        @forelse($activity->filter(fn ($a) => $a->text) as $a)
          <div class="ndg-alert-row">
            <i class="bx bx-message-square-detail"></i>
            <div class="flex-grow-1">
              <div class="small text-dark"><b>{{ trim(optional($a->by)->first_name . ' ' . optional($a->by)->last_name) ?: 'Someone' }}</b> on {{ $a->goal->client_name }} <span class="text-muted">· W{{ $a->week_no }}</span></div>
              <div class="small text-muted">{{ $a->text }}</div>
              <div class="small text-muted">{{ optional($a->at)->diffForHumans() }}</div>
            </div>
          </div>
        @empty
          <div class="p-4 text-center text-muted small">No comments yet.</div>
        @endforelse
      </div>
    </div>
  </div>

  </div>
</div>

@if($canEdit)
  {{-- Add Customer: same source of truth as the Goals page — only the round owner's own
       Sales Pipeline, nobody types a customer in. --}}
  <div class="modal fade" id="ndgRdAddModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Add target customers</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <input type="search" id="ndgRdAddSearch" class="form-control mb-3" placeholder="Search the pipeline…">
          <div id="ndgRdAddList" class="ndg-list"></div>
          <div class="small text-danger mt-2" id="ndgRdAddMsg"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-hitech-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn-hitech" id="ndgRdAddSave">Add selected</button>
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
  .ndg-kpi2 { border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 14px; background: #fff; height: 100%; display: flex; align-items: center; gap: 10px; }
  .ndg-kpi2-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex: none; }
  .ndg-kpi2-v { font-size: 1.2rem; font-weight: 800; color: #0f172a; line-height: 1.2; font-variant-numeric: tabular-nums; }
  .ndg-kpi2-l { font-size: .68rem; color: #94a3b8; font-weight: 600; }
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
  .ndg-alert-row { display: flex; align-items: start; gap: 10px; padding: 10px 16px; border-bottom: 1px solid #f1f5f9; }
  .ndg-alert-row:last-child { border-bottom: none; }
  .ndg-alert-row i { font-size: 1.1rem; color: #007a7a; flex: none; margin-top: 2px; }
  .ndg-mx-scroll { overflow: auto; }
  .ndg-list { max-height: 50vh; overflow: auto; border: 1px solid #e2e8f0; border-radius: 10px; }
  .ndg-list .ndg-row:last-child { border-bottom: none !important; }
  .ndg-list .ndg-row:hover { background: #f8fafc; }
</style>
@endsection

@section('page-script')
<script>
(function () {
  var CSRF = '{{ csrf_token() }}';
  var ROUND_USER_ID = {{ (int) $round->user_id }};
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

  // ── expand a customer row: shows its weekly-comments sub-row and its detail panel ──
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
  // Show the first customer's detail panel by default.
  var firstRow = document.querySelector('.ndg-rd-row');
  if (firstRow) { document.querySelector('.ndg-rd-detail-panel[data-goal="' + firstRow.dataset.goal + '"]')?.classList.remove('d-none'); }

  // ── detail panel's own inner tabs (Details & Progress / Weekly Comments / Orders / Documents) ──
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

  // ── ApexCharts (Overview tab) — waits for DOMContentLoaded since Vite's build emits
  // ApexCharts as an ES module, which the spec defers until after the document has parsed.
  document.addEventListener('DOMContentLoaded', function () {
    if (typeof ApexCharts === 'undefined') { return; }
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

  // ── add customer modal: only this round owner's own Sales Pipeline, nobody types one in ──
  var addModalEl = document.getElementById('ndgRdAddModal');
  if (addModalEl) {
    var addList = document.getElementById('ndgRdAddList'), addSearch = document.getElementById('ndgRdAddSearch'), addMsg = document.getElementById('ndgRdAddMsg');
    var pipeline = [];
    addModalEl.addEventListener('show.bs.modal', function () {
      addMsg.textContent = ''; addList.innerHTML = '<div class="small text-muted">Loading…</div>';
      fetch(URLS.customers + '?user_id=' + ROUND_USER_ID, { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          pipeline = j.success ? j.customers : [];
          renderList();
        });
    });
    function renderList() {
      var q = addSearch.value.trim().toLowerCase();
      var shown = pipeline.filter(function (c) { return !q || (c.customer + ' ' + c.product).toLowerCase().indexOf(q) !== -1; });
      if (!shown.length) { addList.innerHTML = '<div class="small text-muted p-2">' + (pipeline.length ? 'Nothing matches that search.' : 'Every pipeline customer is already in this round.') + '</div>'; return; }
      addList.innerHTML = shown.map(function (c) {
        return '<label class="ndg-row d-flex align-items-start gap-2 p-2 border-bottom">' +
          '<input type="checkbox" class="form-check-input mt-1" data-id="' + c.id + '" data-customer="' + esc(c.customer) + '" data-product="' + esc(c.product) + '" data-brand="' + esc(c.brand) + '" data-potential="' + c.potential + '">' +
          '<span class="flex-grow-1"><b>' + esc(c.customer) + '</b><br><span class="small text-muted">' + esc(c.product) + ' · ' + esc(c.brand) + ' · ₹' + c.potential + ' L/mo · ' + esc(c.source) + '</span></span>' +
          '</label>';
      }).join('');
    }
    addSearch.addEventListener('input', renderList);
    document.getElementById('ndgRdAddSave').addEventListener('click', function () {
      var checked = Array.prototype.filter.call(addList.querySelectorAll('input[type=checkbox]'), function (c) { return c.checked; });
      if (!checked.length) { addMsg.textContent = 'Pick at least one customer.'; return; }
      var customers = checked.map(function (c) {
        return { sales_pipeline_id: parseInt(c.dataset.id, 10), product: c.dataset.product, brand: c.dataset.brand, goal: 'Order', monthly_potential: parseFloat(c.dataset.potential) };
      });
      post(URLS.store, 'POST', { user_id: ROUND_USER_ID, customers: customers }).then(function (j) {
        if (j.success) { location.reload(); return; }
        addMsg.textContent = j.message || (j.errors ? Object.values(j.errors).join(' ') : 'Could not add.');
      });
    });
  }
})();
</script>
@endsection
