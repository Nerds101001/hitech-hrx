@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')

@section('title', '90-Day Goals Dashboard')

@section('content')
@php
  $G = \App\Services\NinetyDayGoalService::class;
  $c = $d['cards'];
  $fmtL = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.') ?: '0';
  $pctText = fn ($v) => $v === null ? '–' : rtrim(rtrim(number_format($v, 1), '0'), '.') . '%';
  // Achievement colour: red under a third of target, amber under two thirds, teal beyond.
  $pctColor = fn ($v) => $v === null ? '#cbd5e1' : ($v >= 66 ? '#007a7a' : ($v >= 33 ? '#f5a623' : '#ef4444'));
  $name = fn ($u) => trim(optional($u)->first_name . ' ' . optional($u)->last_name);
  $maxCust = max(1, (float) optional($d['topCustomers']->first())->potential);
  $maxBrand = max(1, (float) optional($d['topBrands']->first())->potential);
  $maxAch = max(1, (float) optional($d['topAchieved']->first())->achieved);
  $maxStatus = max(1, max($d['statuses']));
@endphp
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 mx-4">
    <div>
      <h3 class="fw-extrabold mb-1 text-dark">90-Day Goals</h3>
      <p class="text-muted mb-0 small">Where the target customers are, how much they are worth and how much of it has been sold. Values are in ₹ lakh.</p>
    </div>
  </div>

  @include('tenant.performance.ninety-day-goals._tabs')

  <div class="px-4">
  <div class="row g-4">
    <div class="col-lg-2">
      @include('tenant.performance.ninety-day-goals._report_nav', ['sections' => [
        ['overview', 'Overview', 'bx-bar-chart-alt-2'],
        ['team', 'Team', 'bx-group'],
        ['weekly', 'Weekly tracker', 'bx-grid-alt'],
        ['trials', 'Trial orders', 'bx-filter-alt'],
        ['attention', 'Needs attention', 'bx-error-circle'],
      ]])
    </div>
    <div class="col-lg-10">
  <section id="overview" class="ndg-report-section">

  {{-- Filters --}}
  <div class="hitech-card mb-6">
    <div class="card-body p-4">
      <form method="GET" action="{{ route('performance.ninety-day-goals.dashboard') }}" class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label fw-bold small text-muted text-uppercase mb-2">Round</label>
          <select name="round" class="form-select form-select-hitech" onchange="this.form.submit()">
            <option value="current" {{ $filters['round'] === 'current' ? 'selected' : '' }}>Current rounds</option>
            <option value="all" {{ $filters['round'] === 'all' ? 'selected' : '' }}>All rounds, including closed</option>
          </select>
        </div>
        @if($seesMany)
        <div class="col-md-4">
          <label class="form-label fw-bold small text-muted text-uppercase mb-2">Salesperson</label>
          <select name="user_id" class="form-select form-select-hitech" onchange="this.form.submit()">
            <option value="">Everyone</option>
            @foreach($people as $p)<option value="{{ $p->id }}" {{ (int) $filters['user_id'] === $p->id ? 'selected' : '' }}>{{ $p->first_name }} {{ $p->last_name }}</option>@endforeach
          </select>
        </div>
        @endif
        <div class="col-md-3">
          <label class="form-label fw-bold small text-muted text-uppercase mb-2">Brand</label>
          <select name="brand" class="form-select form-select-hitech" onchange="this.form.submit()">
            <option value="">All brands</option>
            @foreach($brands as $b)<option value="{{ $b }}" {{ $filters['brand'] === $b ? 'selected' : '' }}>{{ $b }}</option>@endforeach
          </select>
        </div>
        <div class="col-md-2"><a href="{{ route('performance.ninety-day-goals.dashboard') }}" class="btn btn-hitech-cancel w-100 rounded-pill">Clear</a></div>
      </form>
    </div>
  </div>

  @if($c['goals'] === 0)
    <div class="hitech-card mb-6"><div class="card-body p-5 text-center text-muted">
      No goals match these filters yet. Customers show up here as soon as they are added to a round.
    </div></div>
  @else

  {{-- Summary --}}
  <div class="row g-3 mb-6">
    @php
      $cards = [
        ['bx-target-lock', 'Customers tracked', $c['goals'], ($seesMany ? $c['people'] . ' salespeople' : 'in your rounds')],
        ['bx-rupee', 'Monthly potential', $fmtL($c['potential']), '₹ lakh a month'],
        ['bx-flag', 'Round target', $fmtL($c['target']), 'potential × ' . $G::ROUND_MONTHS . ' months'],
        ['bx-trending-up', 'Sold so far', $fmtL($c['achieved']), 'in the Sales Report'],
        ['bx-pie-chart-alt-2', 'Achievement', $pctText($c['pct']), 'sold ÷ round target'],
        ['bx-cart-download', 'Turned into orders', $c['converted'], 'Trial or Regular order'],
        ['bx-time-five', 'Reported on time', $c['discipline'] === null ? '–' : $c['discipline'] . '%', $c['onTimeTotal'] . ' of ' . $c['dueTotal'] . ' Mondays'],
      ];
    @endphp
    @foreach($cards as [$icon, $label, $value, $sub])
      <div class="col-6 col-md-4 col-xl">
        <div class="ndg-kpi">
          <div class="ndg-kpi-l"><i class="bx {{ $icon }}"></i>{{ $label }}</div>
          <div class="ndg-kpi-v">{{ $value }}</div>
          <div class="ndg-kpi-s">{{ $sub }}</div>
        </div>
      </div>
    @endforeach
  </div>

  {{-- Rankings --}}
  <div class="row g-4 mb-6">
    <div class="col-xl-6">
      <div class="hitech-card h-100">
        <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Top 10 customers by monthly potential</div><div class="small text-muted">The biggest targets, largest first</div></div>
        <div class="card-body p-4">
          @foreach($d['topCustomers'] as $i => $r)
            <div class="ndg-bar">
              <div class="ndg-bar-top">
                <span class="ndg-rank">{{ $i + 1 }}</span>
                <span class="ndg-bar-name" title="{{ $r->goal->client_name }}">{{ $r->goal->client_name }}<em>{{ $r->brand }}@if($seesMany) · {{ $name($r->user) }}@endif</em></span>
                <span class="ndg-bar-val">{{ $fmtL($r->potential) }} L</span>
              </div>
              <div class="ndg-track"><div class="ndg-fill" style="width:{{ round($r->potential / $maxCust * 100) }}%"></div></div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
    <div class="col-xl-6">
      <div class="hitech-card h-100">
        <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Top 10 brands by monthly potential</div><div class="small text-muted">Combined potential of every target customer for that brand</div></div>
        <div class="card-body p-4">
          @foreach($d['topBrands'] as $i => $r)
            <div class="ndg-bar">
              <div class="ndg-bar-top">
                <span class="ndg-rank">{{ $i + 1 }}</span>
                <span class="ndg-bar-name">{{ $r['brand'] }}<em>{{ $r['goals'] }} customers · sold {{ $fmtL($r['achieved']) }} L of {{ $fmtL($r['target']) }} L ({{ $pctText($r['pct']) }})</em></span>
                <span class="ndg-bar-val">{{ $fmtL($r['potential']) }} L</span>
              </div>
              <div class="ndg-track"><div class="ndg-fill" style="width:{{ round($r['potential'] / $maxBrand * 100) }}%"></div></div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
    <div class="col-xl-6">
      <div class="hitech-card h-100">
        <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Top 10 customers by sales achieved</div><div class="small text-muted">What has actually been sold in this round, from the Sales Report</div></div>
        <div class="card-body p-4">
          @forelse($d['topAchieved'] as $i => $r)
            <div class="ndg-bar">
              <div class="ndg-bar-top">
                <span class="ndg-rank">{{ $i + 1 }}</span>
                <span class="ndg-bar-name" title="{{ $r->goal->client_name }}">{{ $r->goal->client_name }}<em>{{ $r->brand }}@if($seesMany) · {{ $name($r->user) }}@endif · target {{ $fmtL($r->target) }} L</em></span>
                <span class="ndg-bar-val">{{ $fmtL($r->achieved) }} L<small style="color:{{ $pctColor($r->pct) }}">{{ $pctText($r->pct) }}</small></span>
              </div>
              <div class="ndg-track"><div class="ndg-fill" style="width:{{ round($r->achieved / $maxAch * 100) }}%;background:{{ $pctColor($r->pct) }}"></div></div>
            </div>
          @empty
            <div class="text-muted small">No sales recorded against these customers in this round yet.</div>
          @endforelse
        </div>
      </div>
    </div>
    <div class="col-xl-6">
      <div class="hitech-card h-100">
        <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Where the customers stand</div><div class="small text-muted">Each customer's latest weekly status</div></div>
        <div class="card-body p-4">
          @foreach($d['statuses'] as $st => $n)
            @php [$bg, $fg] = $G::STATUS_COLORS[$st] ?? ['#e2e8f0', '#475569']; @endphp
            <div class="ndg-bar">
              <div class="ndg-bar-top">
                <span class="ndg-bar-name"><span class="ndg-chip" style="background:{{ $bg }};color:{{ $fg }}">{{ $st }}</span></span>
                <span class="ndg-bar-val">{{ $n }}<small>{{ $c['goals'] ? round($n / $c['goals'] * 100) : 0 }}%</small></span>
              </div>
              <div class="ndg-track"><div class="ndg-fill" style="width:{{ round($n / $maxStatus * 100) }}%;background:{{ $bg }}"></div></div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>

  {{-- Per person --}}
  @if($seesMany)
  <div class="hitech-card mb-6">
    <div class="card-body p-4 border-bottom">
      <div class="fw-bold text-dark">Achievement by salesperson</div>
      <div class="small text-muted">Ranked by share of round target sold. Click a heading to sort by it.</div>
    </div>
    <div class="card-body p-0 table-responsive">
      <table class="table align-middle mb-0 ndg-people" id="ndgPeople">
        <thead>
          <tr>
            <th>#</th><th data-k="s">Salesperson</th>
            <th class="text-end" data-k="n">Customers</th>
            <th class="text-end" data-k="n">Monthly ₹ L</th>
            <th class="text-end" data-k="n">Target ₹ L</th>
            <th class="text-end" data-k="n">Sold ₹ L</th>
            <th data-k="n" style="min-width:170px">Achievement</th>
            <th class="text-end" data-k="n">Orders</th>
            <th class="text-end" data-k="n">On time</th>
            <th class="text-end" data-k="n">Late</th>
            <th class="text-end" data-k="n">Missing</th>
          </tr>
        </thead>
        <tbody>
        @foreach($d['users'] as $i => $u)
          <tr>
            <td class="text-muted">{{ $i + 1 }}</td>
            <td data-v="{{ $u['name'] }}"><a href="{{ route('performance.ninety-day-goals.person', $u['id']) }}" class="fw-semibold text-dark">{{ $u['name'] }}</a>
              <div class="small text-muted">{{ $u['department'] }}</div></td>
            <td class="text-end" data-v="{{ $u['goals'] }}">{{ $u['goals'] }}</td>
            <td class="text-end" data-v="{{ $u['potential'] }}">{{ $fmtL($u['potential']) }}</td>
            <td class="text-end" data-v="{{ $u['target'] }}">{{ $fmtL($u['target']) }}</td>
            <td class="text-end" data-v="{{ $u['achieved'] }}">{{ $fmtL($u['achieved']) }}</td>
            <td data-v="{{ $u['pct'] ?? -1 }}">
              <div class="d-flex align-items-center gap-2">
                <div class="ndg-track flex-grow-1" style="height:8px"><div class="ndg-fill" style="width:{{ min(100, (float) $u['pct']) }}%;background:{{ $pctColor($u['pct']) }}"></div></div>
                <b class="small" style="min-width:44px;text-align:right">{{ $pctText($u['pct']) }}</b>
              </div>
            </td>
            <td class="text-end" data-v="{{ $u['converted'] }}">{{ $u['converted'] }}</td>
            <td class="text-end" data-v="{{ $u['discipline'] ?? -1 }}">{{ $u['discipline'] === null ? '–' : $u['discipline'] . '%' }}</td>
            <td class="text-end" data-v="{{ $u['late'] }}">{{ $u['late'] ?: '·' }}</td>
            <td class="text-end" data-v="{{ $u['missing'] }}"><span class="{{ $u['missing'] ? 'text-danger fw-bold' : '' }}">{{ $u['missing'] ?: '·' }}</span></td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @endif

  {{-- Week by week --}}
  <div class="hitech-card mb-6">
    <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Statuses submitted, week by week</div><div class="small text-muted">Done out of expected for each Monday; red is how many arrived late</div></div>
    <div class="card-body p-4 d-flex flex-wrap gap-2">
      @foreach($d['strip'] as $w => $s)
        <div class="ndg-strip">
          <div class="fw-bold small">W{{ $w }}</div>
          <div class="small">{{ $s['done'] }} / {{ $s['total'] }}</div>
          @if($s['late'])<div class="text-danger" style="font-size:.65rem">{{ $s['late'] }} late</div>@endif
        </div>
      @endforeach
    </div>
  </div>

  <p class="small text-muted mb-6">
    Sold is taken from the Sales Report for the months the round covers. Round target is the customer's monthly potential for three months.
    "Orders" counts customers whose latest status is Trial Order or Regular Order.
  </p>
  @endif

  </section>

  <section id="team" class="ndg-report-section">
  {{-- ═══════════════════ Team ═══════════════════ --}}
  <h5 class="fw-bold text-dark mb-3 mt-2">Team</h5>
  <div class="ndg-tiles mb-6">
    @foreach($team as $p)
      <a href="{{ route('performance.ninety-day-goals.person', $p['id']) }}" class="ndg-tile">
        <div class="ndg-tile-top"><span>{{ $p['department'] }}</span><b>{{ $p['goals'] }} {{ \Illuminate\Support\Str::plural('customer', $p['goals']) }}</b></div>
        <div class="ndg-tile-nm">{{ $p['name'] }}</div>
        @if($p['goals'] > 0)
          <div class="ndg-track mb-2"><div class="ndg-fill" style="width:{{ min(100, (float) $p['pct']) }}%;background:{{ $pctColor($p['pct']) }}"></div></div>
          <div class="ndg-tile-stats">
            <span><b>{{ $fmtL($p['potential']) }}</b><small>₹L potential</small></span>
            <span><b style="color:{{ $pctColor($p['pct']) }}">{{ $pctText($p['pct']) }}</b><small>achieved</small></span>
            <span><b>{{ $p['converted'] }}</b><small>converted</small></span>
            <span><b class="{{ $p['missing'] > 0 ? 'text-danger' : '' }}">{{ $p['missing'] }}</b><small>not sent</small></span>
          </div>
        @else
          <div class="text-muted small">No customers added yet this round.</div>
        @endif
      </a>
    @endforeach
  </div>

  </section>

  <section id="weekly" class="ndg-report-section">
  {{-- ═══════════════════ Weekly matrix ═══════════════════ --}}
  <h5 class="fw-bold text-dark mb-3">Weekly matrix</h5>
  @if($weeklyGoals->isEmpty())
    <div class="hitech-card mb-6"><div class="card-body p-5 text-center text-muted">No customers in the current round yet.</div></div>
  @else
    <div class="hitech-card mb-6">
      <div class="card-body p-4 border-bottom small text-muted">Every customer in the current round, one square per Monday — the fastest way to see the whole round's shape at once.</div>
      <div class="card-body p-0 ndg-mx-scroll">
        <table class="ndg-mx">
          <thead><tr><th class="ndg-mx-l">Customer</th>@for($w = 0; $w < $weeks; $w++)<th>W{{ $w }}</th>@endfor</tr></thead>
          <tbody>
          @foreach($weeklyGoals->groupBy('user_id') as $userGoals)
            @php $u = $userGoals->first()->user; @endphp
            @if($seesMany)
              <tr class="ndg-mx-grp"><td colspan="{{ $weeks + 1 }}">{{ trim($u->first_name . ' ' . $u->last_name) }} <span class="text-muted fw-normal">· {{ optional($u->department)->name }}</span></td></tr>
            @endif
            @foreach($userGoals as $g)
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
                  <td class="ndg-mx-c {{ !$st && $ck ? 'ndg-mx-notsent' : '' }}" style="{{ $bg ? 'background:' . $bg : '' }}" title="W{{ $w }}: {{ $title }}">@if(!$st && $ck)×@endif</td>
                @endfor
              </tr>
            @endforeach
          @endforeach
          </tbody>
        </table>
      </div>
      <div class="card-body pt-0">
        <div class="ndg-mx-legend">
          @foreach($G::STATUS_COLORS as $st => [$bg, $fg])<span><i style="background:{{ $bg }}"></i>{{ $st }}</span>@endforeach
          <span><i class="ndg-mx-x">×</i>Not sent</span>
          <span><i class="ndg-mx-empty"></i>Not due yet</span>
        </div>
      </div>
    </div>
  @endif

  </section>

  <section id="trials" class="ndg-report-section">
  {{-- ═══════════════════ Trial orders ═══════════════════ --}}
  <h5 class="fw-bold text-dark mb-3">Trial orders</h5>
  @php
    $funnel = ['No Report' => $statuses['No Report'] ?? 0, 'Off Track' => $statuses['Off Track'] ?? 0, 'On Track' => $statuses['On Track'] ?? 0,
      'Trial Order' => $statuses['Trial Order'] ?? 0, 'Regular Order' => $statuses['Regular Order'] ?? 0];
    $maxF = max(1, ...array_values($funnel));
  @endphp
  <div class="row g-4 mb-6">
    <div class="col-xl-6">
      <div class="hitech-card h-100">
        <div class="card-body p-4 border-bottom fw-bold text-dark">Where the current round's {{ $currentTotal }} customers stand</div>
        <div class="card-body">
          <div class="ndg-fun">
            @foreach($funnel as $st => $n)
              @php [$bg] = $G::STATUS_COLORS[$st] ?? ['#94a3b8']; @endphp
              <div class="ndg-fun-row">
                <div class="ndg-fun-l">{{ $st }}</div>
                <div class="ndg-fun-bar"><div style="width:{{ round($n / $maxF * 100) }}%;background:{{ $bg }}"></div></div>
                <div class="ndg-fun-v">{{ $n }}<small>{{ $currentTotal ? round($n / $currentTotal * 100) : 0 }}%</small></div>
              </div>
            @endforeach
            @if(($statuses['Dropped'] ?? 0) || ($statuses['Transferred'] ?? 0))
              <div class="ndg-fun-note">Also this round: {{ $statuses['Dropped'] ?? 0 }} dropped, {{ $statuses['Transferred'] ?? 0 }} transferred out.</div>
            @endif
          </div>
        </div>
      </div>
    </div>
    <div class="col-xl-6">
      <div class="hitech-card h-100">
        <div class="card-body p-4 border-bottom">
          <div class="fw-bold text-dark">At Trial Order right now</div>
          <div class="small text-muted">{{ $trialing->count() }} {{ \Illuminate\Support\Str::plural('customer', $trialing->count()) }} closest to becoming a Regular Order.</div>
        </div>
        <div class="card-body p-0 table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr>@if($seesMany)<th>Salesperson</th>@endif<th>Customer</th><th>Brand</th><th class="text-end">Monthly ₹L</th></tr></thead>
            <tbody>
            @forelse($trialing as $r)
              <tr>
                @if($seesMany)<td>{{ $name($r->user) }}</td>@endif
                <td class="fw-semibold">{{ $r->goal->client_name }}</td>
                <td>{{ $r->brand }}</td>
                <td class="text-end">{{ $fmtL($r->potential) }}</td>
              </tr>
            @empty
              <tr><td colspan="{{ $seesMany ? 4 : 3 }}" class="text-center text-muted p-4">Nobody is at Trial Order right now.</td></tr>
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
      <div class="fw-bold text-dark">{{ $attention->count() }} of {{ $currentTotal }} customers need a look</div>
      <div class="small text-muted">Off track, silent, dropped, or a status that never came — most urgent first.</div>
    </div>
    <div class="card-body p-0 table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr>
          @if($seesMany)<th>Salesperson</th>@endif
          <th>Customer</th><th>Brand</th><th class="text-end">Monthly ₹L</th><th>Latest status</th><th>Not sent</th>
        </tr></thead>
        <tbody>
        @forelse($attention as $r)
          @php [$bg, $fg] = $G::STATUS_COLORS[$r->status] ?? ['#e2e8f0', '#475569']; @endphp
          <tr>
            @if($seesMany)<td>{{ $name($r->user) }}</td>@endif
            <td class="fw-semibold">{{ $r->goal->client_name }}</td>
            <td>{{ $r->brand }}</td>
            <td class="text-end">{{ $fmtL($r->potential) }}</td>
            <td>@if($r->status)<span class="ndg-chip" style="background:{{ $bg }};color:{{ $fg }}">{{ $r->status }}</span>@else<span class="ndg-none">nothing yet</span>@endif</td>
            <td>
              @if($r->overdueWeeks > 0)
                <span class="ndg-overdue">{{ $r->overdueWeeks }} {{ \Illuminate\Support\Str::plural('week', $r->overdueWeeks) }}@if($r->oldestOverdue) · since {{ $r->oldestOverdue->format('d M') }}@endif</span>
              @else
                <span class="ndg-none">up to date</span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="{{ $seesMany ? 6 : 5 }}" class="text-center text-muted p-4">Nothing needs attention right now.</td></tr>
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
  .ndg-kpi { border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; background: #fff; height: 100%; }
  .ndg-kpi-l { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #64748b; display: flex; align-items: center; gap: 5px; }
  .ndg-kpi-l i { color: #007a7a; font-size: 1rem; }
  .ndg-kpi-v { font-size: 1.6rem; font-weight: 800; color: #0f172a; line-height: 1.25; margin-top: 4px; font-variant-numeric: tabular-nums; }
  .ndg-kpi-s { font-size: .7rem; color: #94a3b8; }
  .ndg-bar { margin-bottom: 14px; }
  .ndg-bar:last-child { margin-bottom: 0; }
  .ndg-bar-top { display: flex; align-items: center; gap: 10px; margin-bottom: 5px; }
  .ndg-rank { width: 22px; height: 22px; flex: none; border-radius: 6px; background: #f0f7f6; color: #007a7a; font-size: .72rem; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; }
  .ndg-bar-name { flex: 1; min-width: 0; font-weight: 600; font-size: .86rem; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .ndg-bar-name em { display: block; font-style: normal; font-weight: 400; font-size: .7rem; color: #94a3b8; overflow: hidden; text-overflow: ellipsis; }
  .ndg-bar-val { font-weight: 800; font-size: .88rem; font-variant-numeric: tabular-nums; white-space: nowrap; text-align: right; }
  .ndg-bar-val small { display: block; font-size: .68rem; font-weight: 700; color: #94a3b8; }
  .ndg-track { height: 7px; background: #eef2f5; border-radius: 99px; overflow: hidden; }
  .ndg-fill { height: 100%; background: #007a7a; border-radius: 99px; min-width: 2px; }
  .ndg-chip { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: .72rem; font-weight: 700; }
  .ndg-strip { border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px 12px; min-width: 78px; text-align: center; background: #fbfcfd; }
  .ndg-people th[data-k] { cursor: pointer; white-space: nowrap; }
  .ndg-people th[data-k]:hover { color: #007a7a; }
  .ndg-people td, .ndg-people th { white-space: nowrap; }

  .ndg-tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px; }
  .ndg-tile { display: block; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 18px; text-decoration: none; transition: border-color .15s; }
  .ndg-tile:hover { border-color: #007a7a; }
  .ndg-tile-top { display: flex; justify-content: space-between; font-size: .7rem; color: #64748b; }
  .ndg-tile-top b { color: #0f172a; font-weight: 700; }
  .ndg-tile-nm { font-size: 1.1rem; font-weight: 700; color: #0f172a; margin: 4px 0 10px; line-height: 1.25; }
  .ndg-tile-stats { display: grid; grid-template-columns: repeat(4, auto); justify-content: space-between; gap: 6px; margin-top: 12px; }
  .ndg-tile-stats b { display: block; font-size: .92rem; font-weight: 700; color: #0f172a; }
  .ndg-tile-stats small { font-size: .64rem; color: #94a3b8; }

  .ndg-mx-scroll { overflow: auto; max-height: 60vh; }
  .ndg-mx { border-collapse: separate; border-spacing: 3px; font-size: .78rem; padding: 0 20px 8px; width: 100%; }
  .ndg-mx thead th { position: sticky; top: 0; background: #fff; font-weight: 700; color: #64748b; font-size: .68rem; text-align: center; padding: 8px 0 6px; z-index: 2; }
  .ndg-mx-l { text-align: left !important; white-space: nowrap; padding-right: 14px !important; font-weight: 600; color: #0f172a; position: sticky; left: 0; background: #fff; z-index: 1; }
  .ndg-mx-sub { display: block; font-size: .64rem; font-weight: 400; color: #94a3b8; }
  .ndg-mx-c { width: 26px; height: 26px; min-width: 26px; border-radius: 4px; text-align: center; color: #fff; font-weight: 700; box-shadow: inset 0 0 0 1px #e2e8f0; background: #f1f5f9; }
  .ndg-mx-notsent { color: #b42318; background: #fff !important; border: 1px dashed #f0a5a0; }
  .ndg-mx-grp td { font-weight: 700; color: #0f172a; padding: 14px 8px 4px; font-size: .82rem; background: #fff; position: sticky; left: 0; }
  .ndg-mx-legend { display: flex; flex-wrap: wrap; gap: 8px 16px; font-size: .78rem; color: #64748b; }
  .ndg-mx-legend span { display: inline-flex; align-items: center; gap: 6px; }
  .ndg-mx-legend i { display: inline-block; width: 14px; height: 14px; border-radius: 3px; }
  .ndg-mx-legend i.ndg-mx-x { background: none; color: #b42318; text-align: center; line-height: 14px; font-weight: 800; box-shadow: inset 0 0 0 1px #e2e8f0; }
  .ndg-mx-legend i.ndg-mx-empty { background: #f1f5f9; box-shadow: inset 0 0 0 1px #e2e8f0; }

  .ndg-fun { display: flex; flex-direction: column; gap: 10px; }
  .ndg-fun-row { display: grid; grid-template-columns: minmax(110px, 150px) minmax(0, 1fr) 110px; gap: 14px; align-items: center; }
  .ndg-fun-bar { height: 26px; background: #eef2f5; border-radius: 5px; overflow: hidden; }
  .ndg-fun-bar div { height: 100%; border-radius: 5px; min-width: 2px; }
  .ndg-fun-l { font-size: .84rem; font-weight: 600; color: #0f172a; }
  .ndg-fun-v { text-align: right; font-weight: 700; font-size: .88rem; font-variant-numeric: tabular-nums; }
  .ndg-fun-v small { display: block; font-weight: 400; color: #94a3b8; font-size: .66rem; }
  .ndg-fun-note { font-size: .8rem; color: #64748b; margin-top: 4px; }

  .ndg-none { color: #cbd5e1; }
  .ndg-overdue { color: #b42318; font-weight: 700; font-size: .82rem; }
</style>
@endsection

@section('page-script')
<script>
  (function () {
    var table = document.getElementById('ndgPeople');
    if (!table) return;
    var body = table.tBodies[0];
    var dir = {};
    table.tHead.querySelectorAll('th[data-k]').forEach(function (th) {
      th.addEventListener('click', function () {
        var col = th.cellIndex, text = th.dataset.k === 's';
        dir[col] = dir[col] === 1 ? -1 : 1;
        Array.prototype.slice.call(body.rows).sort(function (a, b) {
          var x = a.cells[col].dataset.v, y = b.cells[col].dataset.v;
          return (text ? x.localeCompare(y) : parseFloat(x) - parseFloat(y)) * dir[col] * (text ? 1 : -1);
        }).forEach(function (r, i) { body.appendChild(r); r.cells[0].textContent = i + 1; });
      });
    });
  })();
</script>
@endsection
