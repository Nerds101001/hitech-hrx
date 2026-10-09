@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')

@section('title', '90-Day Goals — Round ' . $round->round_no)

@section('content')
@php
  $G = \App\Services\NinetyDayGoalService::class;
  $c = $d['cards'];
  $fmtL = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.') ?: '0';
  $pctText = fn ($v) => $v === null ? '–' : rtrim(rtrim(number_format($v, 1), '0'), '.') . '%';
  $pctColor = fn ($v) => $v === null ? '#94a3b8' : ($v >= 66 ? '#007a7a' : ($v >= 33 ? '#f5a623' : '#ef4444'));

  /** Same look as the Goals grid, but read-only: no chip is clickable, nothing can be added or removed here. */
  $chip = function ($checkin) use ($G) {
      if (!$checkin || !$checkin->status) {
          return $checkin ? '<span class="ndg-chip ndg-missing">not sent</span>' : '<span class="ndg-none">·</span>';
      }
      [$bg, $fg] = $G::STATUS_COLORS[$checkin->status] ?? ['#64748b', '#ffffff'];
      $late = $checkin->is_late ? '<i class="ndg-late">late, sent ' . e(optional($checkin->submitted_at)->format('d M')) . '</i>' : '';

      return '<span class="ndg-chip" style="background:' . $bg . ';color:' . $fg . '">' . e($checkin->status) . '</span>' . $late;
  };
  $noteCell = function ($checkin) {
      if (!$checkin) {
          return '<span class="ndg-none">·</span>';
      }
      $h = $checkin->comment ? '<div class="ndg-n ndg-n-status">' . e($checkin->comment) . '</div>' : '';
      foreach ($checkin->notes as $n) {
          $who = trim(optional($n->author)->first_name . ' ' . optional($n->author)->last_name);
          $h .= '<div class="ndg-n"><b>' . e($who) . '</b><span>' . $n->created_at->format('d M') . '</span><div>' . e($n->body) . '</div></div>';
      }

      return $h ?: '<span class="ndg-none">·</span>';
  };
@endphp
<div class="layout-full-width animate__animated animate__fadeIn">

  @php $name = fn ($u) => trim(optional($u)->first_name . ' ' . optional($u)->last_name); @endphp
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 mx-4">
    <div>
      <div class="d-flex align-items-center gap-2 mb-1">
        <a href="{{ \App\Services\NinetyDayGoalService::seesEveryone(auth()->user()) ? route('performance.ninety-day-goals.programs.index', ['status' => 'ended']) : route('performance.ninety-day-goals.my-program') }}" class="text-muted" title="Back"><i class="bx bx-arrow-back"></i></a>
        <h3 class="fw-extrabold mb-0 text-dark">Round {{ $round->round_no }}@if($seesMany) · <a href="{{ route('performance.ninety-day-goals.person', $round->user_id) }}" class="text-dark">{{ $name($round->user) }}</a>@endif</h3>
        <span class="ndg-closed-badge">closed</span>
      </div>
      <p class="text-muted mb-0 small">{{ $round->starts_on->format('d M Y') }} to {{ $round->ends_on->format('d M Y') }} · {{ optional($round->user->department)->name }}</p>
    </div>
  </div>

  @include('tenant.performance.ninety-day-goals._tabs')

  <div class="px-4">
  <div class="row g-4">
    <div class="col-lg-2">
      @include('tenant.performance.ninety-day-goals._report_nav', ['sections' => [
        ['overview', 'Overview', 'bx-bar-chart-alt-2'],
        ['result', 'Result', 'bx-list-ol'],
        ['matrix', 'Weekly tracker', 'bx-grid-alt'],
        ['trials', 'Trial orders', 'bx-filter-alt'],
        ['attention', 'Needs attention', 'bx-error-circle'],
        ['detail', 'Full detail', 'bx-table'],
      ]])
    </div>
    <div class="col-lg-10">
  <section id="overview" class="ndg-report-section">

  {{-- The headline: how much of the target landed, and the full spread of outcomes it took to get there. --}}
  <div class="ndg-hero mb-6">
    <div class="ndg-hero-num" style="color: {{ $pctColor($c['pct']) }}">{{ $pctText($c['pct']) }}</div>
    <div class="ndg-hero-what">of round target sold — {{ $fmtL($c['achieved']) }} ₹L of {{ $fmtL($c['target']) }} ₹L, across {{ $c['goals'] }} {{ \Illuminate\Support\Str::plural('customer', $c['goals']) }}</div>
    @php $statusTotal = max(1, array_sum($d['statuses'])); @endphp
    <div class="ndg-strip">
      @foreach($d['statuses'] as $st => $n)
        @continue($n === 0)
        @php [$bg] = $G::STATUS_COLORS[$st] ?? ['#94a3b8', '#fff']; @endphp
        <div class="ndg-seg" style="flex-grow:{{ $n }};background:{{ $bg }}" title="{{ $st }}: {{ $n }}">{{ $n }}</div>
      @endforeach
    </div>
    <div class="ndg-legend">
      @foreach($d['statuses'] as $st => $n)
        @continue($n === 0)
        @php [$bg] = $G::STATUS_COLORS[$st] ?? ['#94a3b8', '#fff']; @endphp
        <div class="ndg-lg"><span class="ndg-sw" style="background:{{ $bg }}"></span><div><b>{{ $n }}</b><span>{{ $st }} · {{ round($n / $statusTotal * 100) }}%</span></div></div>
      @endforeach
    </div>
  </div>

  <div class="row g-3 mb-6">
    @php
      $cards = [
        ['bx-target-lock', 'Customers', $c['goals']],
        ['bx-flag', 'Round target (₹L)', $fmtL($c['target'])],
        ['bx-trending-up', 'Sold (₹L)', $fmtL($c['achieved'])],
        ['bx-pie-chart-alt-2', 'Achievement', $pctText($c['pct'])],
        ['bx-cart-download', 'Converted', $c['converted']],
        ['bx-time-five', 'Reported on time', $c['discipline'] === null ? '–' : $c['discipline'] . '%'],
      ];
    @endphp
    @foreach($cards as [$icon, $label, $value])
      <div class="col-6 col-md-4 col-xl">
        <div class="ndg-kpi">
          <div class="ndg-kpi-l"><i class="bx {{ $icon }}"></i>{{ $label }}</div>
          <div class="ndg-kpi-v">{{ $value }}</div>
        </div>
      </div>
    @endforeach
  </div>

  </section>

  <section id="result" class="ndg-report-section">
  {{-- The result, ranked by how much of the target actually landed. --}}
  <div class="hitech-card mb-6">
    <div class="card-body p-4 border-bottom">
      <div class="fw-bold text-dark">Result, by customer</div>
      <div class="small text-muted">Sold against target, best achievement first. Ended at {{ $round->ends_on->format('d M Y') }}.</div>
    </div>
    <div class="card-body p-0 table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr>
          <th style="min-width:180px">Customer</th><th>Brand</th><th class="text-end">Monthly ₹L</th>
          <th class="text-end">Target ₹L</th><th class="text-end">Sold ₹L</th><th style="min-width:170px">Achievement</th><th>Final status</th>
        </tr></thead>
        <tbody>
        @forelse($d['rows']->sortByDesc(fn ($r) => [$r->pct ?? -1, $r->achieved])->values() as $r)
          @php [$bg, $fg] = $G::STATUS_COLORS[$r->status] ?? ['#e2e8f0', '#475569']; @endphp
          <tr>
            <td class="fw-semibold">{{ $r->goal->client_name }}</td>
            <td>{{ $r->brand }}</td>
            <td class="text-end">{{ $fmtL($r->potential) }}</td>
            <td class="text-end">{{ $fmtL($r->target) }}</td>
            <td class="text-end fw-semibold">{{ $fmtL($r->achieved) }}</td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="ndg-track flex-grow-1"><div class="ndg-fill" style="width:{{ min(100, (float) $r->pct) }}%;background:{{ $pctColor($r->pct) }}"></div></div>
                <b class="small" style="min-width:44px;text-align:right;color:{{ $pctColor($r->pct) }}">{{ $pctText($r->pct) }}</b>
              </div>
            </td>
            <td>@if($r->status)<span class="ndg-chip" style="background:{{ $bg }};color:{{ $fg }}">{{ $r->status }}</span>@include('tenant.performance.ninety-day-goals.partials.order-check', ['r' => $r])@else<span class="ndg-none">no status left</span>@endif</td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted p-4">No customers in this round.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>

  </section>

  <section id="matrix" class="ndg-report-section">
  {{-- Every customer, every week, at a glance. --}}
  <div class="hitech-card mb-6">
    <div class="card-body p-4 border-bottom">
      <div class="fw-bold text-dark">Every customer, every week</div>
      <div class="small text-muted">One square per Monday. Hover a square for the status; the table below has the comment behind it.</div>
    </div>
    <div class="card-body p-0 ndg-mx-scroll">
      <table class="ndg-mx">
        <thead>
          <tr>
            <th class="ndg-mx-l">Customer</th>
            @for($w = 0; $w < $weeks; $w++)<th>W{{ $w + 1 }}</th>@endfor
          </tr>
        </thead>
        <tbody>
        @forelse($goals as $g)
          @php $cks = $g->checkins->keyBy('week_no'); @endphp
          <tr>
            <td class="ndg-mx-l">{{ $g->client_name }}<span class="ndg-mx-sub">{{ $g->brand }}</span></td>
            @for($w = 0; $w < $weeks; $w++)
              @php
                $ck = $cks->get($w);
                $st = optional($ck)->status;
                [$bg] = $st ? ($G::STATUS_COLORS[$st] ?? ['#94a3b8']) : [null];
                $title = $st ? ($st . ($ck->is_late ? ' (late)' : '')) : ($ck ? 'Not sent' : 'Not due yet');
              @endphp
              <td class="ndg-mx-c {{ !$st && $ck ? 'ndg-mx-notsent' : '' }}" style="{{ $bg ? 'background:' . $bg : '' }}" title="W{{ $w + 1 }}: {{ $title }}">
                @if(!$st && $ck)×@endif
              </td>
            @endfor
          </tr>
        @empty
          <tr><td colspan="{{ $weeks + 1 }}" class="text-center text-muted p-4">No customers in this round.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-body pt-0">
      <div class="ndg-mx-legend">
        @foreach($G::STATUS_COLORS as $st => [$bg, $fg])
          <span><i style="background:{{ $bg }}"></i>{{ $st }}</span>
        @endforeach
        <span><i class="ndg-mx-x">×</i>Not sent</span>
        <span><i class="ndg-mx-empty"></i>Not due yet</span>
      </div>
    </div>
  </div>

  </section>

  <section id="trials" class="ndg-report-section">
  {{-- ═══════════════════ Trial orders ═══════════════════ --}}
  <h5 class="fw-bold text-dark mb-3">Trial orders</h5>
  @php
    $funnel = ['No Report' => $d['statuses']['No Report'] ?? 0, 'Off Track' => $d['statuses']['Off Track'] ?? 0, 'On Track' => $d['statuses']['On Track'] ?? 0,
      'Trial Order' => $d['statuses']['Trial Order'] ?? 0, 'Regular Order' => $d['statuses']['Regular Order'] ?? 0];
    $maxF = max(1, ...array_values($funnel));
  @endphp
  <div class="row g-4 mb-6">
    <div class="col-xl-6">
      <div class="hitech-card h-100">
        <div class="card-body p-4 border-bottom fw-bold text-dark">Where this round's {{ $c['goals'] }} customers stand</div>
        <div class="card-body">
          <div class="ndg-fun">
            @foreach($funnel as $st => $n)
              @php [$bg] = $G::STATUS_COLORS[$st] ?? ['#94a3b8']; @endphp
              <div class="ndg-fun-row">
                <div class="ndg-fun-l">{{ $st }}</div>
                <div class="ndg-fun-bar"><div style="width:{{ round($n / $maxF * 100) }}%;background:{{ $bg }}"></div></div>
                <div class="ndg-fun-v">{{ $n }}<small>{{ $c['goals'] ? round($n / $c['goals'] * 100) : 0 }}%</small></div>
              </div>
            @endforeach
            @if(($d['statuses']['Dropped'] ?? 0) || ($d['statuses']['Transferred'] ?? 0))
              <div class="ndg-fun-note">Also: {{ $d['statuses']['Dropped'] ?? 0 }} dropped, {{ $d['statuses']['Transferred'] ?? 0 }} transferred out.</div>
            @endif
          </div>
        </div>
      </div>
    </div>
    <div class="col-xl-6">
      <div class="hitech-card h-100">
        <div class="card-body p-4 border-bottom">
          <div class="fw-bold text-dark">At Trial Order</div>
          <div class="small text-muted">{{ $trialing->count() }} {{ \Illuminate\Support\Str::plural('customer', $trialing->count()) }} closest to becoming a Regular Order.</div>
        </div>
        <div class="card-body p-0 table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr><th>Customer</th><th>Brand</th><th class="text-end">Monthly ₹L</th></tr></thead>
            <tbody>
            @forelse($trialing as $r)
              <tr><td class="fw-semibold">{{ $r->goal->client_name }}</td><td>{{ $r->brand }}</td><td class="text-end">{{ $fmtL($r->potential) }}</td></tr>
            @empty
              <tr><td colspan="3" class="text-center text-muted p-4">Nobody was at Trial Order in this round.</td></tr>
            @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  </section>

  <section id="attention" class="ndg-report-section">
  {{-- ═══════════════════ Needs attention ═══════════════════ --}}
  <h5 class="fw-bold text-dark mb-3">Needs attention</h5>
  <div class="hitech-card mb-6">
    <div class="card-body p-4 border-bottom">
      <div class="fw-bold text-dark">{{ $attention->count() }} of {{ $c['goals'] }} customers needed a look</div>
      <div class="small text-muted">Off track, silent, dropped, or a status that never came, worst first.</div>
    </div>
    <div class="card-body p-0 table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Customer</th><th>Brand</th><th class="text-end">Monthly ₹L</th><th>Latest status</th><th>Not sent</th></tr></thead>
        <tbody>
        @forelse($attention as $r)
          @php [$bg, $fg] = $G::STATUS_COLORS[$r->status] ?? ['#e2e8f0', '#475569']; @endphp
          <tr>
            <td class="fw-semibold">{{ $r->goal->client_name }}</td>
            <td>{{ $r->brand }}</td>
            <td class="text-end">{{ $fmtL($r->potential) }}</td>
            <td>@if($r->status)<span class="ndg-chip" style="background:{{ $bg }};color:{{ $fg }}">{{ $r->status }}</span>@include('tenant.performance.ninety-day-goals.partials.order-check', ['r' => $r])@else<span class="ndg-none">nothing yet</span>@endif</td>
            <td>
              @if($r->overdueWeeks > 0)
                <span class="ndg-overdue">{{ $r->overdueWeeks }} {{ \Illuminate\Support\Str::plural('week', $r->overdueWeeks) }}@if($r->oldestOverdue) · since {{ $r->oldestOverdue->format('d M') }}@endif</span>
              @else
                <span class="ndg-none">up to date</span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="text-center text-muted p-4">Nothing needed attention in this round.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
  </section>

  <section id="detail" class="ndg-report-section">
  {{-- Full week-by-week detail: exactly how the result above was reached, every status and every comment. --}}
  <div class="hitech-card mb-6">
    <div class="card-body p-4 border-bottom fw-bold text-dark">Week-by-week detail, W1 to W{{ $weeks }}</div>
    <div class="card-body p-0 ndg-scroll">
      <table class="table align-middle mb-0 ndg-grid">
        <thead>
          <tr>
            <th rowspan="2" style="min-width:180px">Customer</th><th rowspan="2" style="min-width:150px">Product</th>
            <th rowspan="2">Brand</th><th rowspan="2" style="min-width:130px">Goal</th><th rowspan="2" class="text-end">Monthly ₹ L</th>
            @for($w = 0; $w < $weeks; $w++)<th colspan="2" class="text-center ndg-wk ndg-wkhead">W{{ $w + 1 }}</th>@endfor
          </tr>
          <tr class="ndg-sub">
            @for($w = 0; $w < $weeks; $w++)<th class="ndg-st">Status</th><th>Comment</th>@endfor
          </tr>
        </thead>
        <tbody>
        @forelse($goals as $g)
          @php $cks = $g->checkins->keyBy('week_no'); @endphp
          <tr>
            <td class="fw-semibold">{{ $g->client_name }}</td>
            <td>{{ $g->product }}</td>
            <td>{{ $g->brand }}</td>
            <td>{{ $g->goal ?: $g->goal_type }}</td>
            <td class="text-end fw-semibold">{{ $fmtL($g->monthly_potential) }}</td>
            @for($w = 0; $w < $weeks; $w++)
              @php $ck = $cks->get($w); @endphp
              <td class="ndg-st">{!! $chip($ck) !!}</td>
              <td class="ndg-cm">{!! $noteCell($ck) !!}</td>
            @endfor
          </tr>
        @empty
          <tr><td colspan="{{ 5 + 2 * $weeks }}" class="text-center text-muted p-4">No customers in this round.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>

  </section>

    </div>
  </div>
  </div>
</div>

<style>
  .ndg-closed-badge { background: #f1f5f9; color: #64748b; font-size: .68rem; font-weight: 700; padding: 3px 10px; border-radius: 999px; }
  .ndg-hero { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 24px 26px; }
  .ndg-hero-num { font-size: clamp(40px, 6vw, 64px); font-weight: 800; line-height: 1; font-variant-numeric: tabular-nums; letter-spacing: -.02em; }
  .ndg-hero-what { color: #64748b; font-size: .92rem; margin-top: 6px; }
  .ndg-strip { display: flex; gap: 2px; height: 34px; border-radius: 6px; overflow: hidden; margin-top: 20px; background: #eef2f5; }
  .ndg-seg { display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: .78rem; min-width: 2px; }
  .ndg-legend { display: flex; flex-wrap: wrap; gap: 16px 22px; margin-top: 16px; }
  .ndg-lg { display: flex; gap: 8px; align-items: flex-start; }
  .ndg-lg .ndg-sw { width: 11px; height: 11px; border-radius: 3px; margin-top: 4px; flex: none; }
  .ndg-lg b { display: block; font-size: 1.05rem; font-weight: 800; line-height: 1.2; }
  .ndg-lg span { display: block; font-size: .72rem; color: #64748b; }
  .ndg-kpi { border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; background: #fff; height: 100%; }
  .ndg-kpi-l { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #64748b; display: flex; align-items: center; gap: 5px; }
  .ndg-kpi-l i { color: #007a7a; font-size: 1rem; }
  .ndg-kpi-v { font-size: 1.4rem; font-weight: 800; color: #0f172a; margin-top: 4px; font-variant-numeric: tabular-nums; }
  .ndg-track { height: 7px; background: #eef2f5; border-radius: 99px; overflow: hidden; }
  .ndg-fill { height: 100%; border-radius: 99px; min-width: 2px; }
  .ndg-chip { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: .68rem; font-weight: 700; }
  .ndg-none { color: #cbd5e1; }
  .ndg-mx-scroll { overflow: auto; max-height: 60vh; }
  .ndg-mx { border-collapse: separate; border-spacing: 3px; font-size: .78rem; padding: 0 20px 8px; }
  .ndg-mx thead th { position: sticky; top: 0; background: #fff; font-weight: 700; color: #64748b; font-size: .68rem; text-align: center; padding: 2px 0 6px; z-index: 1; }
  .ndg-mx-l { text-align: left !important; white-space: nowrap; padding-right: 14px !important; font-weight: 600; color: #0f172a; position: sticky; left: 0; background: #fff; z-index: 2; }
  .ndg-mx-sub { display: block; font-size: .64rem; font-weight: 400; color: #94a3b8; }
  .ndg-mx-c { width: 26px; height: 26px; min-width: 26px; border-radius: 4px; text-align: center; color: #fff; font-weight: 700; box-shadow: inset 0 0 0 1px #e2e8f0; background: #f1f5f9; }
  .ndg-mx-notsent { color: #b42318; background: #fff !important; border: 1px dashed #f0a5a0; }
  .ndg-mx-legend { display: flex; flex-wrap: wrap; gap: 8px 16px; font-size: .78rem; color: #64748b; }
  .ndg-mx-legend span { display: inline-flex; align-items: center; gap: 6px; }
  .ndg-mx-legend i { display: inline-block; width: 14px; height: 14px; border-radius: 3px; }
  .ndg-mx-legend i.ndg-mx-x { background: none; color: #b42318; text-align: center; line-height: 14px; font-weight: 800; box-shadow: inset 0 0 0 1px #e2e8f0; }
  .ndg-mx-legend i.ndg-mx-empty { background: #f1f5f9; box-shadow: inset 0 0 0 1px #e2e8f0; }
  .ndg-scroll { max-height: 74vh; overflow: auto; }
  .ndg-scroll thead th { position: sticky; top: 0; z-index: 2; background: #f3f6f9; }
  .ndg-grid th, .ndg-grid td { white-space: nowrap; }
  .ndg-wk { font-size: .78rem; }
  .ndg-wkhead { border-left: 2px solid #cfe0de; }
  .ndg-sub th { font-size: .62rem; text-transform: uppercase; letter-spacing: .04em; color: #64748b; font-weight: 700; }
  .ndg-grid td.ndg-st { border-left: 2px solid #cfe0de; min-width: 118px; text-align: center; }
  .ndg-grid td.ndg-cm { white-space: normal; min-width: 200px; max-width: 260px; font-size: .78rem; line-height: 1.35; color: #334155; }
  .ndg-chip.ndg-missing { background: #fff; color: #b42318; border: 1px dashed #f0a5a0; font-weight: 600; }
  .ndg-late { display: block; font-size: .6rem; color: #b42318; font-weight: 700; margin-top: 2px; }
  .ndg-n { padding: 4px 0 5px; border-bottom: 1px dashed #e2e8f0; }
  .ndg-n b { font-size: .72rem; color: #0f172a; }
  .ndg-n span { font-size: .66rem; color: #94a3b8; margin-left: 6px; }
  .ndg-n-status { color: #334155; }
  .ndg-fun { display: flex; flex-direction: column; gap: 10px; }
  .ndg-fun-row { display: grid; grid-template-columns: minmax(110px, 150px) minmax(0, 1fr) 110px; gap: 14px; align-items: center; }
  .ndg-fun-bar { height: 26px; background: #eef2f5; border-radius: 5px; overflow: hidden; }
  .ndg-fun-bar div { height: 100%; border-radius: 5px; min-width: 2px; }
  .ndg-fun-l { font-size: .84rem; font-weight: 600; color: #0f172a; }
  .ndg-fun-v { text-align: right; font-weight: 700; font-size: .88rem; font-variant-numeric: tabular-nums; }
  .ndg-fun-v small { display: block; font-weight: 400; color: #94a3b8; font-size: .66rem; }
  .ndg-fun-note { font-size: .8rem; color: #64748b; margin-top: 4px; }
  .ndg-overdue { color: #b42318; font-weight: 700; font-size: .82rem; }
</style>
@endsection
