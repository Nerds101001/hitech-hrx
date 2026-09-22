@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')

@section('title', '90-Day Goals')



@section('content')
@php
  $G = \App\Services\NinetyDayGoalService::class;

  /**
   * A week's status as a coloured chip; "late" marks a Monday answered after its day. If $editable,
   * a submitted chip opens the same status form again, pre-filled, so a mistake can be corrected
   * instead of staying wrong forever.
   */
  $chip = function ($checkin, $due, $today, $goal = null, $editable = false) use ($G) {
      if ($checkin && $checkin->status) {
          [$bg, $fg] = $G::STATUS_COLORS[$checkin->status] ?? ['#64748b', '#ffffff'];
          $edited = $checkin->edited_at ? '<i class="ndg-edited" title="Edited ' . e($checkin->edited_at->format('d M, h:i A')) . '">edited</i>' : '';
          $late = $checkin->is_late ? '<i class="ndg-late">late, sent ' . e(optional($checkin->submitted_at)->format('d M')) . '</i>' : '';
          $attrs = $editable && $goal ? ' role="button" class="ndg-chip ndg-editable" data-edit-goal="' . $goal->id . '" data-edit-week="' . (int) $checkin->week_no
              . '" data-edit-user="' . $goal->user_id . '" data-edit-status="' . e($checkin->status) . '" data-edit-comment="' . e((string) $checkin->comment) . '" data-edit-title="' . e($goal->client_name) . ' · W' . (int) $checkin->week_no . '" title="Click to edit"'
              : ' class="ndg-chip"';
          return '<span' . $attrs . ' style="background:' . $bg . ';color:' . $fg . '">' . e($checkin->status) . '</span>' . $edited . $late;
      }
      if ($checkin && $checkin->due_on->lte($today)) {
          return '<span class="ndg-chip ndg-missing" title="Due ' . $checkin->due_on->format('D d M') . ', not submitted">not sent</span>';
      }
      return '<span class="ndg-none">·</span>';
  };
  /** A week's comment cell: what was written with the status, then comments added later by the salesperson or an admin, then the add button. */
  $noteCell = function ($goal, $checkin) use ($isAdmin) {
      if (!$checkin) {
          return '<span class="ndg-none">·</span>';
      }
      $h = '';
      if ($checkin->comment) {
          $h .= '<div class="ndg-n ndg-n-status">' . e($checkin->comment) . '</div>';
      }
      foreach ($checkin->notes as $n) {
          $who = trim(optional($n->author)->first_name . ' ' . optional($n->author)->last_name);
          $h .= '<div class="ndg-n" data-note="' . $n->id . '"><b>' . e($who) . '</b><span>' . $n->created_at->format('d M') . '</span>'
              . ($isAdmin ? '<button type="button" class="ndg-note-del" data-id="' . $n->id . '" title="Remove comment">&times;</button>' : '')
              . '<div>' . e($n->body) . '</div></div>';
      }

      return $h . '<button type="button" class="ndg-addnote" data-goal="' . $goal->id . '" data-week="' . (int) $checkin->week_no . '" data-title="' . e($goal->client_name) . ' · W' . (int) $checkin->week_no . '" title="Add a comment"><i class="bx bx-message-square-add"></i></button>';
  };
  $fmtL = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
@endphp
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6 mx-4">
    <div>
      <h3 class="fw-extrabold mb-1 text-dark">90-Day Goals</h3>
      <p class="text-muted mb-0 small">
        @if($mode === 'mine')
          Pick target customers from your own Sales Pipeline and Sales Report, then give each a status every Monday for 13 weeks.
        @else
          Every salesperson's target customers and their status for each Monday, W0 to W12.
        @endif
      </p>
    </div>
    <div class="d-flex gap-3 flex-wrap">
      @if($canSwitch)
        <a href="{{ route('performance.ninety-day-goals.index', ['view' => $mode === 'mine' ? 'overview' : 'mine']) }}"
           class="btn btn-hitech-cancel shadow-sm rounded-pill px-4 d-flex align-items-center gap-2">
          <i class="bx bx-{{ $mode === 'mine' ? 'group' : 'user' }} fs-5"></i><span class="fw-bold">{{ $mode === 'mine' ? 'Everyone' : 'My goals' }}</span>
        </a>
      @endif
      @if($mode === 'mine')
        <button type="button" class="btn-hitech shadow-sm rounded-pill px-5 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addCustomersModal">
          <i class="bx bx-plus-circle fs-5"></i><span class="fw-bold">Add customers</span>
        </button>
      @endif
    </div>
  </div>

  @php $tab = 'goals'; $seesMany = $mode === 'overview' || $canSwitch; @endphp
  @include('tenant.performance.ninety-day-goals._tabs')

  <div class="px-4">

@if($mode === 'mine')
  {{-- ───────────────────────── MY GOALS ───────────────────────── --}}
  @php $weekNo = $week; $pct = round(($weekNo + 1) / $weeks * 100); @endphp
  <div class="hitech-card mb-6">
    <div class="card-body p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
      <div>
        <div class="fw-bold text-dark">Round {{ $round->round_no }}
          <span class="text-muted fw-normal">· {{ $round->starts_on->format('d M Y') }} to {{ $round->ends_on->format('d M Y') }}</span></div>
        <div class="small text-muted">Week {{ $weekNo }} of {{ $weeks - 1 }} · a new round starts by itself on {{ $round->ends_on->copy()->addDay()->format('D d M') }}</div>
      </div>
      <div style="min-width:220px;flex:1;max-width:420px">
        <div class="progress" style="height:8px"><div class="progress-bar" style="width:{{ $pct }}%;background:#007a7a"></div></div>
      </div>
    </div>
  </div>

  {{-- Statuses owed --}}
  @foreach($pending as $wk => $rows)
    @php
      $due = $rows->first()->due_on;
      $isLate = $today->gt($due);
    @endphp
    <div class="hitech-card mb-6 ndg-week" data-week="{{ $wk }}" data-late="{{ $isLate ? 1 : 0 }}">
      <div class="card-body p-4 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h6 class="fw-bold mb-0 text-dark">W{{ $wk }} status
          <span class="text-muted fw-normal">· due {{ $due->format('D d M') }}</span></h6>
        @if($isLate)
          <span class="badge badge-hitech badge-hitech-danger">Monday has passed: pick a status and write a comment for each</span>
        @else
          <span class="badge badge-hitech badge-brand-soft">Due today</span>
        @endif
      </div>
      <div class="card-body p-0 table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th style="width:24%">Customer</th><th style="width:22%">Status</th><th>Comment{{ $isLate ? ' (needed)' : '' }}</th></tr></thead>
          <tbody>
          @foreach($rows as $c)
            <tr data-goal="{{ $c->goal_id }}">
              <td class="fw-semibold">{{ $c->goal->client_name }}<div class="small text-muted fw-normal">{{ $c->goal->product }}</div></td>
              <td>
                <select class="form-select form-select-hitech ndg-status">
                  <option value="">Choose…</option>
                  @foreach($statuses as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach
                </select>
              </td>
              <td><input type="text" class="form-control ndg-comment" maxlength="1000" placeholder="{{ $isLate ? 'Why was this not sent on Monday?' : 'Optional note' }}"></td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
      <div class="card-body p-4 d-flex align-items-center justify-content-between gap-3">
        <span class="small text-danger ndg-msg"></span>
        <button type="button" class="btn-hitech shadow-sm rounded-pill px-5 ndg-submit">Submit W{{ $wk }}</button>
      </div>
    </div>
  @endforeach

  @if($goals->isEmpty())
    <div class="hitech-card"><div class="card-body p-5 text-center">
      <h6 class="fw-bold text-dark mb-2">No customers in this round yet</h6>
      <p class="text-muted mb-4">Search your Sales Pipeline and Sales Report and add the customers you are going after.</p>
      <button type="button" class="btn-hitech shadow-sm rounded-pill px-5" data-bs-toggle="modal" data-bs-target="#addCustomersModal">Add customers</button>
    </div></div>
  @else
    <div class="hitech-card">
      <div class="card-body p-0 table-responsive">
        <table class="table align-middle mb-0 ndg-grid">
          <thead>
            <tr>
              <th rowspan="2" style="min-width:180px">Customer</th><th rowspan="2" style="min-width:150px">Product</th><th rowspan="2">Brand</th><th rowspan="2" style="min-width:130px">Goal</th>
              <th rowspan="2" class="text-end">Monthly ₹ L</th>
              @for($w = 0; $w < $weeks; $w++)
                <th colspan="2" class="text-center ndg-wk ndg-wkhead {{ $w === $weekNo ? 'ndg-now' : '' }}">W{{ $w }} <span class="ndg-date">{{ $dates[$w]->format('d M') }}</span></th>
              @endfor
              @if($isAdmin)<th rowspan="2"></th>@endif
            </tr>
            <tr class="ndg-sub">
              @for($w = 0; $w < $weeks; $w++)<th class="ndg-st {{ $w === $weekNo ? 'ndg-now' : '' }}">Status</th><th class="{{ $w === $weekNo ? 'ndg-now' : '' }}">Comment</th>@endfor
            </tr>
          </thead>
          <tbody>
          @foreach($goals as $g)
            <tr>
              <td class="fw-semibold">{{ $g->client_name }}
                <button type="button" class="ndg-edit-goal" title="Edit product, brand, goal or potential"
                  data-id="{{ $g->id }}" data-product="{{ e($g->product) }}" data-brand="{{ e($g->brand) }}" data-goal="{{ e($g->goal) }}" data-potential="{{ $g->monthly_potential }}"
                  data-title="{{ e($g->client_name) }}"><i class="bx bx-pencil"></i></button>
              </td>
              <td class="ndg-p-product">{{ $g->product }}</td>
              <td class="ndg-p-brand">{{ $g->brand }}</td>
              <td class="ndg-p-goal">{{ $g->goal }}</td>
              <td class="text-end fw-semibold ndg-p-potential">{{ $fmtL($g->monthly_potential) }}</td>
              @for($w = 0; $w < $weeks; $w++)
                @php $ck = $g->checkins->firstWhere('week_no', $w); @endphp
                <td class="ndg-st {{ $w === $weekNo ? 'ndg-now' : '' }}">{!! $chip($ck, $dates[$w], $today, $g, true) !!}</td>
                <td class="ndg-cm {{ $w === $weekNo ? 'ndg-now' : '' }}">{!! $noteCell($g, $ck) !!}</td>
              @endfor
              @if($isAdmin)<td class="text-end"><button type="button" class="btn btn-sm btn-icon icon-sophisticated text-danger ndg-remove" data-id="{{ $g->id }}" title="Remove"><i class="bx bx-trash"></i></button></td>@endif
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

@else
  {{-- ───────────────────────── EVERYONE ───────────────────────── --}}
  <div class="row g-6 mb-6 mx-0">
    @php
      $allRounds = $filters['round'] === 'all';
      $cards = [
        ['card-teal', 'bx-group', $summary['people'] . ' / ' . $summary['staff'], 'Sales people with goals' . ($allRounds ? ', all rounds' : '')],
        ['card-blue', 'bx-target-lock', $summary['goals'], 'Customers tracked' . ($allRounds ? ', all rounds' : '')],
        ['card-amber', 'bx-rupee', $fmtL($summary['potential']), 'Monthly potential (₹ L)' . ($allRounds ? ', all rounds' : '')],
        ['card-teal', 'bx-check-circle', $summary['thisWeekDone'] . ' / ' . $summary['thisWeekTotal'], 'This week submitted (current round only)'],
        ['card-red', 'bx-time-five', $summary['overdue'], 'Overdue statuses' . ($allRounds ? ', all rounds' : '')],
      ];
    @endphp
    @foreach($cards as [$cls, $icon, $val, $lab])
      <div class="col-xl col-md-4 col-6">
        <div class="hitech-stat-card dashboard-variant {{ $cls }} uniform-card">
          <div class="stat-card-header mb-2"><div class="stat-icon-wrap icon-{{ str_replace('card-', '', $cls) }}" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx {{ $icon }}"></i></div></div>
          <div><h3 class="stat-value mb-0">{{ $val }}</h3><span class="stat-label">{{ $lab }}</span></div>
        </div>
      </div>
    @endforeach
  </div>

  {{-- Week by week --}}
  <div class="hitech-card mb-6">
    <div class="card-body p-4 border-bottom">
      <h6 class="fw-bold mb-0 text-dark">Statuses submitted, week by week</h6>
      @if($allRounds)<div class="small text-muted">W0 to W12 of every round in the list, added together — not just the round running now.</div>@endif
    </div>
    <div class="card-body p-4 d-flex flex-wrap gap-2">
      @foreach($strip as $w => $s)
        <div class="ndg-strip">
          <div class="fw-bold">W{{ $w }}</div>
          <div class="small">{{ $s['done'] }} / {{ $s['total'] }}</div>
          @if($s['late'])<div class="small text-danger">{{ $s['late'] }} late</div>@endif
        </div>
      @endforeach
    </div>
    @if(!empty($summary['byStatus']))
      <div class="card-body p-4 pt-0 d-flex flex-wrap gap-2 align-items-center">
        <span class="small text-muted me-2">Latest status:</span>
        @foreach($statuses as $s)
          @if(!empty($summary['byStatus'][$s]))
            @php [$bg, $fg] = $G::STATUS_COLORS[$s]; @endphp
            <span class="ndg-chip" style="background:{{ $bg }};color:{{ $fg }}">{{ $s }} · {{ $summary['byStatus'][$s] }}</span>
          @endif
        @endforeach
      </div>
    @endif
  </div>

  {{-- The whole Sales team in one table. Filters and sorting work on the page itself. --}}
  @php
    $brandList = $goals->pluck('brand')->filter()->unique()->sort()->values();
    $personName = fn ($u) => trim(optional($u)->first_name . ' ' . optional($u)->last_name);
  @endphp
  <div class="hitech-card mb-6">
    <div class="card-body p-4">
      <div class="row g-3 align-items-end">
        <div class="col-lg-3 col-md-6">
          <label class="form-label fw-bold small text-muted text-uppercase mb-2">Search</label>
          <input type="search" id="fSearch" class="form-control" placeholder="Person, customer, product, comment…">
        </div>
        <div class="col-lg-3 col-md-6">
          <label class="form-label fw-bold small text-muted text-uppercase mb-2">Salesperson</label>
          <select id="fUser" class="form-select form-select-hitech">
            <option value="">Everyone</option>
            @foreach($people as $p)<option value="{{ $p->id }}" {{ (int) $preUser === $p->id ? 'selected' : '' }}>{{ $personName($p) }}</option>@endforeach
          </select>
        </div>
        <div class="col-lg-2 col-md-4">
          <label class="form-label fw-bold small text-muted text-uppercase mb-2">Brand</label>
          <select id="fBrand" class="form-select form-select-hitech">
            <option value="">All brands</option>
            @foreach($brandList as $b)<option>{{ $b }}</option>@endforeach
          </select>
        </div>
        <div class="col-lg-2 col-md-4">
          <label class="form-label fw-bold small text-muted text-uppercase mb-2">Status in</label>
          <select id="fWeek" class="form-select form-select-hitech">
            <option value="">Latest week</option>
            @for($w = 0; $w < $weeks; $w++)<option value="{{ $w }}">W{{ $w }}</option>@endfor
          </select>
        </div>
        <div class="col-lg-2 col-md-4">
          <label class="form-label fw-bold small text-muted text-uppercase mb-2">Status</label>
          <select id="fStatus" class="form-select form-select-hitech">
            <option value="">Any</option>
            <option value="__none">Nothing submitted</option>
            @foreach($statuses as $s)<option>{{ $s }}</option>@endforeach
          </select>
        </div>
        <div class="col-lg-3 col-md-4">
          <label class="form-label fw-bold small text-muted text-uppercase mb-2">Round</label>
          <select id="fRound" class="form-select form-select-hitech">
            <option value="current" {{ $filters['round'] === 'current' ? 'selected' : '' }}>Current rounds</option>
            <option value="all" {{ $filters['round'] === 'all' ? 'selected' : '' }}>All rounds, including closed</option>
          </select>
        </div>
        <div class="col-lg-6 col-md-8 d-flex flex-wrap gap-4 align-items-center pb-2">
          <div class="form-check mb-0"><input class="form-check-input" type="checkbox" id="fComments"><label class="form-check-label small fw-semibold" for="fComments">Has comments</label></div>
          <div class="form-check mb-0"><input class="form-check-input" type="checkbox" id="fOver"><label class="form-check-label small fw-semibold" for="fOver">Has statuses not sent</label></div>
          <div class="form-check mb-0"><input class="form-check-input" type="checkbox" id="fNoGoals" checked><label class="form-check-label small fw-semibold" for="fNoGoals">Show people with no customers</label></div>
        </div>
        <div class="col-lg-3 d-flex align-items-center justify-content-end gap-3 pb-1">
          <span class="small text-muted" id="fCount"></span>
          <button type="button" class="btn btn-hitech-cancel rounded-pill px-4" id="fClear">Clear</button>
        </div>
      </div>
    </div>
  </div>

  <div class="hitech-card mb-6">
    <div class="card-body p-0 ndg-scroll">
      <table class="table align-middle mb-0 ndg-grid" id="ndgTable">
        <thead>
          <tr>
            <th rowspan="2" class="ndg-sort" data-col="0" style="min-width:170px">Salesperson</th>
            <th rowspan="2" class="ndg-sort" data-col="1" style="min-width:90px" title="Which 90-day round the customer belongs to">Round</th>
            <th rowspan="2" class="ndg-sort" data-col="2" style="min-width:180px">Customer</th>
            <th rowspan="2" class="ndg-sort" data-col="3" style="min-width:150px">Product</th>
            <th rowspan="2" class="ndg-sort" data-col="4">Brand</th>
            <th rowspan="2" class="ndg-sort" data-col="5" style="min-width:130px">Goal</th>
            <th rowspan="2" class="ndg-sort text-end" data-col="6" data-num="1">Monthly ₹ L</th>
            @for($w = 0; $w < $weeks; $w++)<th colspan="2" class="text-center ndg-wk ndg-wkhead">W{{ $w }}</th>@endfor
          </tr>
          <tr class="ndg-sub">
            @for($w = 0; $w < $weeks; $w++)
              <th class="ndg-st ndg-sort" data-col="{{ 7 + 2 * $w }}">Status</th><th class="ndg-sort" data-col="{{ 8 + 2 * $w }}">Comment</th>
            @endfor
          </tr>
        </thead>
        <tbody>
        @foreach($goals as $g)
          @php
            $cks = $g->checkins->keyBy('week_no');
            $latest = $G::latestStatus($g);
            $hasComment = $g->checkins->contains(fn ($c) => $c->comment || $c->notes->isNotEmpty());
            $overdue = $g->checkins->contains(fn ($c) => $c->status === null && $c->due_on->lte($today));
            $wattrs = collect(range(0, $weeks - 1))->map(fn ($w) => 'data-w' . $w . '="' . e(optional($cks->get($w))->status) . '"')->implode(' ');
            $search = strtolower($personName($g->user) . ' ' . $g->client_name . ' ' . $g->product . ' ' . $g->brand . ' ' . ($g->goal ?: $g->goal_type) . ' '
              . $g->checkins->map(fn ($c) => $c->comment . ' ' . $c->notes->pluck('body')->implode(' '))->implode(' '));
          @endphp
          <tr class="ndg-tr" data-user="{{ $g->user_id }}" data-brand="{{ $g->brand }}" data-latest="{{ $latest }}" data-hasc="{{ $hasComment ? 1 : 0 }}" data-over="{{ $overdue ? 1 : 0 }}" data-q="{{ $search }}" {!! $wattrs !!}>
            <td class="fw-semibold" data-v="{{ strtolower($personName($g->user)) }}"><a href="{{ route('performance.ninety-day-goals.person', $g->user_id) }}" class="text-dark">{{ $personName($g->user) }}</a><div class="small text-muted fw-normal">{{ optional(optional($g->user)->department)->name }}</div></td>
            <td data-v="{{ (int) optional($g->round)->round_no }}">
              @if($g->round)
                <span class="ndg-round {{ $g->round->status !== 'active' ? 'ndg-round-closed' : '' }}" title="{{ $g->round->starts_on->format('d M Y') }} to {{ $g->round->ends_on->format('d M Y') }}">R{{ $g->round->round_no }}@if($g->round->status !== 'active') · closed @endif</span>
              @endif
            </td>
            <td class="fw-semibold" data-v="{{ strtolower($g->client_name) }}">{{ $g->client_name }}
              @if($isAdmin)
                <button type="button" class="ndg-edit-goal" title="Edit product, brand, goal or potential"
                  data-id="{{ $g->id }}" data-product="{{ e($g->product) }}" data-brand="{{ e($g->brand) }}" data-goal="{{ e($g->goal ?: $g->goal_type) }}" data-potential="{{ $g->monthly_potential }}"
                  data-title="{{ e($g->client_name) }}"><i class="bx bx-pencil"></i></button>
              @endif
            </td>
            <td class="ndg-p-product" data-v="{{ strtolower($g->product) }}">{{ $g->product }}</td>
            <td class="ndg-p-brand" data-v="{{ strtolower($g->brand) }}">{{ $g->brand }}</td>
            <td class="ndg-p-goal" data-v="{{ strtolower($g->goal ?: $g->goal_type) }}">{{ $g->goal ?: $g->goal_type }}</td>
            <td class="text-end fw-semibold ndg-p-potential" data-v="{{ (float) $g->monthly_potential }}">{{ $fmtL($g->monthly_potential) }}</td>
            @for($w = 0; $w < $weeks; $w++)
              @php $ck = $cks->get($w); @endphp
              <td class="ndg-st" data-v="{{ optional($ck)->status }}">{!! $chip($ck, null, $today, $g, $isAdmin) !!}</td>
              <td class="ndg-cm" data-v="{{ strtolower(optional($ck)->comment . ' ' . optional($ck)->notes?->pluck('body')->implode(' ')) }}">{!! $noteCell($g, $ck) !!}</td>
            @endfor
          </tr>
        @endforeach
        @foreach($notStarted as $p)
          <tr class="ndg-tr ndg-empty" data-user="{{ $p->id }}" data-brand="" data-latest="" data-hasc="0" data-over="0" data-empty="1" data-q="{{ strtolower($personName($p)) }}">
            <td class="fw-semibold" data-v="{{ strtolower($personName($p)) }}">{{ $personName($p) }}<div class="small text-muted fw-normal">{{ optional($p->department)->name }}</div></td>
            <td data-v="0"></td>
            <td class="text-muted" colspan="1" data-v="">No customers added yet</td>
            <td data-v=""></td><td data-v=""></td><td data-v=""></td><td data-v=""></td>
            @for($w = 0; $w < $weeks; $w++)<td class="ndg-st" data-v=""></td><td class="ndg-cm" data-v=""></td>@endfor
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endif

  </div>{{-- /px-4 --}}
</div>

@if($mode === 'mine')
{{-- Add customers: search the person's own Sales Pipeline and Sales Report, nothing typed in. --}}
<div class="modal fade" id="addCustomersModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add customers to this round</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="search" id="ndgSearch" class="form-control mb-3" placeholder="Search your pipeline and sales report by customer, product or brand…" autocomplete="off">
        <div id="ndgList" class="ndg-list"><div class="text-muted p-3">Loading your customers…</div></div>
      </div>
      <div class="modal-footer justify-content-between">
        <span class="small text-danger" id="ndgAddMsg"></span>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-hitech-cancel rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn-hitech rounded-pill px-5" id="ndgAddBtn">Add selected</button>
        </div>
      </div>
    </div>
  </div>
</div>
@endif

{{-- Add a comment to one week. Open to the salesperson and to admin/managers; never editable or removable by the sales team. --}}
<div class="modal fade" id="ndgNoteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title fw-bold" id="ndgNoteTitle">Add a comment</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <textarea id="ndgNoteBody" class="form-control" rows="4" maxlength="1000" placeholder="Write the comment"></textarea>
        <div class="small text-muted mt-2">Once added, a comment stays on record.</div>
      </div>
      <div class="modal-footer justify-content-between">
        <span class="small text-danger" id="ndgNoteMsg"></span>
        <div class="d-flex gap-2"><button type="button" class="btn btn-hitech-cancel rounded-pill px-4" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn-hitech rounded-pill px-5" id="ndgNoteSave">Add comment</button></div>
      </div>
    </div>
  </div>
</div>

{{-- Edit an already-submitted status. Nothing about when it was first reported changes — only the status and comment. --}}
<div class="modal fade" id="ndgEditModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title fw-bold" id="ndgEditTitle">Edit status</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <label class="form-label fw-bold small text-muted text-uppercase mb-2">Status</label>
        <select id="ndgEditStatus" class="form-select form-select-hitech mb-3">
          @foreach($statuses as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach
        </select>
        <label class="form-label fw-bold small text-muted text-uppercase mb-2">Comment</label>
        <textarea id="ndgEditComment" class="form-control" rows="4" maxlength="1000" placeholder="Write a comment"></textarea>
        <div class="small text-muted mt-2">The original report time stays on record; this only changes what was said.</div>
      </div>
      <div class="modal-footer justify-content-between">
        <span class="small text-danger" id="ndgEditMsg"></span>
        <div class="d-flex gap-2"><button type="button" class="btn btn-hitech-cancel rounded-pill px-4" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn-hitech rounded-pill px-5" id="ndgEditSave">Save</button></div>
      </div>
    </div>
  </div>
</div>

{{-- Edit a customer's product, brand, goal or monthly potential — the customer itself and its weekly history never change here. --}}
<div class="modal fade" id="ndgEditGoalModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title fw-bold" id="ndgEditGoalTitle">Edit customer</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-6">
            <label class="form-label fw-bold small text-muted text-uppercase mb-2">Product</label>
            <input type="text" id="ndgEditGoalProduct" class="form-control">
          </div>
          <div class="col-6">
            <label class="form-label fw-bold small text-muted text-uppercase mb-2">Brand</label>
            <select id="ndgEditGoalBrand" class="form-select form-select-hitech">
              @foreach($brands as $b)<option value="{{ $b }}">{{ $b }}</option>@endforeach
            </select>
          </div>
          <div class="col-6">
            <label class="form-label fw-bold small text-muted text-uppercase mb-2">Goal</label>
            <input type="text" id="ndgEditGoalGoal" class="form-control">
          </div>
          <div class="col-6">
            <label class="form-label fw-bold small text-muted text-uppercase mb-2">Monthly potential (₹L)</label>
            <input type="number" step="0.01" min="0" id="ndgEditGoalPotential" class="form-control">
          </div>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <span class="small text-danger" id="ndgEditGoalMsg"></span>
        <div class="d-flex gap-2"><button type="button" class="btn btn-hitech-cancel rounded-pill px-4" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn-hitech rounded-pill px-5" id="ndgEditGoalSave">Save</button></div>
      </div>
    </div>
  </div>
</div>
<style>
  .ndg-grid th, .ndg-grid td { white-space: nowrap; }
  .ndg-scroll { max-height: 74vh; overflow: auto; }
  .ndg-scroll thead th { position: sticky; top: 0; z-index: 3; background: #f3f6f9; }
  .ndg-scroll thead tr.ndg-sub th { z-index: 2; }
  th.ndg-sort { cursor: pointer; user-select: none; }
  th.ndg-sort:hover { color: #007a7a; }
  th.ndg-sort::after { content: ''; margin-left: 5px; font-size: .7rem; color: #007a7a; }
  th.ndg-sort.asc::after { content: '▲'; }
  th.ndg-sort.desc::after { content: '▼'; }
  .ndg-n { padding: 4px 0 5px; border-bottom: 1px dashed #e2e8f0; }
  .ndg-n b { font-size: .72rem; color: #0f172a; }
  .ndg-n span { font-size: .66rem; color: #94a3b8; margin-left: 6px; }
  .ndg-n-status { color: #334155; }
  .ndg-addnote { border: 0; background: none; padding: 2px 0 0; color: #94a3b8; font-size: 1.05rem; line-height: 1; }
  .ndg-addnote:hover { color: #007a7a; }
  .ndg-note-del { border: 0; background: none; color: #ef4444; float: right; line-height: 1; font-size: 1rem; padding: 0 2px; }
  .ndg-wk { font-size: .78rem; }
  .ndg-wkhead { border-left: 2px solid #cfe0de; }
  .ndg-round { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: .68rem; font-weight: 700; background: #f0f7f6; color: #007a7a; }
  .ndg-round-closed { background: #f1f5f9; color: #64748b; }
  .ndg-sub th { font-size: .62rem; text-transform: uppercase; letter-spacing: .04em; color: #64748b; font-weight: 700; }
  .ndg-grid td.ndg-st, .ndg-sub th.ndg-st { border-left: 2px solid #cfe0de; min-width: 118px; text-align: center; }
  .ndg-grid td.ndg-cm { white-space: normal; min-width: 200px; max-width: 260px; font-size: .78rem; line-height: 1.35; color: #334155; overflow-wrap: anywhere; white-space: pre-line; }
  .ndg-date { font-weight: 400; color: #94a3b8; font-size: .68rem; }
  .ndg-now { background: #f0f7f6; }
  .ndg-chip { display: inline-block; position: relative; padding: 3px 9px; border-radius: 6px; font-size: .68rem; font-weight: 700; line-height: 1.25; }
  .ndg-chip.ndg-missing { background: #fff; color: #b42318; border: 1px dashed #f0a5a0; font-weight: 600; }
  .ndg-chip.ndg-editable { cursor: pointer; box-shadow: 0 0 0 1px rgba(255,255,255,.001); transition: transform .1s; }
  .ndg-chip.ndg-editable:hover { transform: scale(1.04); box-shadow: 0 1px 4px rgba(0,0,0,.18); }
  .ndg-late { display: block; font-size: .6rem; font-style: normal; color: #b42318; font-weight: 700; margin-top: 2px; }
  .ndg-edited { display: block; font-size: .6rem; font-style: normal; color: #64748b; font-weight: 600; margin-top: 2px; }
  .ndg-edit-goal { border: 0; background: none; padding: 0 0 0 6px; color: #cbd5e1; font-size: .8rem; vertical-align: middle; }
  .ndg-edit-goal:hover { color: #007a7a; }
  .ndg-none { color: #cbd5e1; }
  .ndg-strip { border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px 12px; min-width: 78px; text-align: center; background: #fbfcfd; }
  .ndg-list .ndg-row { border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px; margin-bottom: 8px; }
  .ndg-list .ndg-row.on { border-color: #007a7a; background: #f0f7f6; }
  .ndg-list .ndg-extra { display: none; margin-top: 10px; }
  .ndg-list .ndg-row.on .ndg-extra { display: flex; }
  .ndg-src { font-size: .65rem; }
</style>
@endsection

@section('page-script')
<script>
(function () {
  var CSRF = '{{ csrf_token() }}';
  var URLS = {
    customers: '{{ route('performance.ninety-day-goals.pipeline-customers') }}',
    store: '{{ route('performance.ninety-day-goals.store') }}',
    checkin: '{{ route('performance.ninety-day-goals.checkin') }}',
    notes: '{{ url('performance/ninety-day-goals/notes') }}',
    base: '{{ url('performance/ninety-day-goals') }}'
  };
  function post(url, method, body) {
    return fetch(url, { method: method, headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }, body: body ? JSON.stringify(body) : undefined })
      .then(function (r) { return r.json().then(function (j) { j._status = r.status; return j; }); });
  }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

  // ── weekly status cards ──
  document.querySelectorAll('.ndg-week').forEach(function (card) {
    var btn = card.querySelector('.ndg-submit'), msg = card.querySelector('.ndg-msg');
    var late = card.getAttribute('data-late') === '1';
    btn.addEventListener('click', function () {
      var entries = [], problems = [];
      card.querySelectorAll('tbody tr').forEach(function (tr) {
        var st = tr.querySelector('.ndg-status').value, cm = tr.querySelector('.ndg-comment').value.trim();
        if (!st) { return; }
        if (late && !cm) { problems.push(tr.querySelector('td').firstChild.textContent.trim() + ': add a comment'); return; }
        entries.push({ goal_id: parseInt(tr.getAttribute('data-goal'), 10), status: st, comment: cm });
      });
      if (problems.length) { msg.textContent = problems.join('. ') + '.'; return; }
      if (!entries.length) { msg.textContent = 'Choose a status for at least one customer.'; return; }
      msg.textContent = ''; btn.disabled = true;
      post(URLS.checkin, 'POST', { week_no: parseInt(card.getAttribute('data-week'), 10), entries: entries }).then(function (j) {
        if (j.success) { location.reload(); return; }
        btn.disabled = false; msg.textContent = j.message || 'Could not save.';
        if (j.saved) { setTimeout(function () { location.reload(); }, 1500); }
      }).catch(function () { btn.disabled = false; msg.textContent = 'Request failed. Try again.'; });
    });
  });

  // ── comments on a week ──
  var noteModalEl = document.getElementById('ndgNoteModal'), noteTarget = null;
  function noteHtml(n) {
    return '<div class="ndg-n" data-note="' + n.id + '"><b>' + esc(n.by) + '</b><span>' + esc(n.at) + '</span>' +
      (n.can_delete ? '<button type="button" class="ndg-note-del" data-id="' + n.id + '" title="Remove comment">&times;</button>' : '') +
      '<div>' + esc(n.body) + '</div></div>';
  }
  document.addEventListener('click', function (e) {
    var add = e.target.closest ? e.target.closest('.ndg-addnote') : null;
    if (add) {
      noteTarget = add;
      document.getElementById('ndgNoteTitle').textContent = add.getAttribute('data-title');
      document.getElementById('ndgNoteBody').value = ''; document.getElementById('ndgNoteMsg').textContent = '';
      bootstrap.Modal.getOrCreateInstance(noteModalEl).show();
      setTimeout(function () { document.getElementById('ndgNoteBody').focus(); }, 300);
      return;
    }
    var del = e.target.closest ? e.target.closest('.ndg-note-del') : null;
    if (del && confirm('Remove this comment?')) {
      post(URLS.notes + '/' + del.getAttribute('data-id'), 'DELETE').then(function (j) {
        if (j.success) { var n = del.closest('.ndg-n'); if (n) { n.remove(); } } else { alert(j.message || 'Could not remove.'); }
      });
    }
  });
  document.getElementById('ndgNoteSave').addEventListener('click', function () {
    var body = document.getElementById('ndgNoteBody').value.trim(), msg = document.getElementById('ndgNoteMsg'), btn = this;
    if (!body) { msg.textContent = 'Write the comment first.'; return; }
    btn.disabled = true;
    post(URLS.notes, 'POST', { goal_id: parseInt(noteTarget.getAttribute('data-goal'), 10), week_no: parseInt(noteTarget.getAttribute('data-week'), 10), body: body }).then(function (j) {
      btn.disabled = false;
      if (!j.success) { msg.textContent = j.message || (j.errors ? Object.values(j.errors).join(' ') : 'Could not save.'); return; }
      noteTarget.insertAdjacentHTML('beforebegin', noteHtml(j.note));
      var tr = noteTarget.closest('tr'); if (tr) { tr.setAttribute('data-hasc', '1'); tr.setAttribute('data-q', (tr.getAttribute('data-q') || '') + ' ' + body.toLowerCase()); }
      bootstrap.Modal.getOrCreateInstance(noteModalEl).hide();
    }).catch(function () { btn.disabled = false; msg.textContent = 'Request failed. Try again.'; });
  });

  // ── edit an already-submitted status ──
  var editModalEl = document.getElementById('ndgEditModal'), editTarget = null;
  document.addEventListener('click', function (e) {
    var chip = e.target.closest ? e.target.closest('.ndg-editable') : null;
    if (!chip) { return; }
    editTarget = chip;
    document.getElementById('ndgEditTitle').textContent = 'Edit — ' + chip.getAttribute('data-edit-title');
    document.getElementById('ndgEditStatus').value = chip.getAttribute('data-edit-status');
    document.getElementById('ndgEditComment').value = chip.getAttribute('data-edit-comment') || '';
    document.getElementById('ndgEditMsg').textContent = '';
    bootstrap.Modal.getOrCreateInstance(editModalEl).show();
  });
  document.getElementById('ndgEditSave').addEventListener('click', function () {
    var status = document.getElementById('ndgEditStatus').value, comment = document.getElementById('ndgEditComment').value.trim();
    var msg = document.getElementById('ndgEditMsg'), btn = this;
    btn.disabled = true;
    post(URLS.checkin, 'POST', {
      week_no: parseInt(editTarget.getAttribute('data-edit-week'), 10),
      user_id: parseInt(editTarget.getAttribute('data-edit-user'), 10),
      entries: [{ goal_id: parseInt(editTarget.getAttribute('data-edit-goal'), 10), status: status, comment: comment }],
    }).then(function (j) {
      btn.disabled = false;
      if (!j.success) { msg.textContent = j.message || 'Could not save.'; return; }
      location.reload();
    }).catch(function () { btn.disabled = false; msg.textContent = 'Request failed. Try again.'; });
  });

  // ── edit a customer's product / brand / goal / monthly potential ──
  var editGoalModalEl = document.getElementById('ndgEditGoalModal'), editGoalTarget = null;
  document.querySelectorAll('.ndg-edit-goal').forEach(function (b) {
    b.addEventListener('click', function () {
      editGoalTarget = b;
      document.getElementById('ndgEditGoalTitle').textContent = 'Edit — ' + b.getAttribute('data-title');
      document.getElementById('ndgEditGoalProduct').value = b.getAttribute('data-product') || '';
      document.getElementById('ndgEditGoalBrand').value = b.getAttribute('data-brand') || '';
      document.getElementById('ndgEditGoalGoal').value = b.getAttribute('data-goal') || '';
      document.getElementById('ndgEditGoalPotential').value = b.getAttribute('data-potential') || '';
      document.getElementById('ndgEditGoalMsg').textContent = '';
      bootstrap.Modal.getOrCreateInstance(editGoalModalEl).show();
    });
  });
  document.getElementById('ndgEditGoalSave').addEventListener('click', function () {
    var product = document.getElementById('ndgEditGoalProduct').value.trim(), brand = document.getElementById('ndgEditGoalBrand').value;
    var goal = document.getElementById('ndgEditGoalGoal').value.trim(), potential = document.getElementById('ndgEditGoalPotential').value;
    var msg = document.getElementById('ndgEditGoalMsg'), btn = this;
    if (!product || !goal || potential === '' || isNaN(parseFloat(potential))) { msg.textContent = 'Fill in product, goal and a monthly potential.'; return; }
    btn.disabled = true;
    post(URLS.base + '/' + editGoalTarget.getAttribute('data-id'), 'PUT', { product: product, brand: brand, goal: goal, monthly_potential: parseFloat(potential) }).then(function (j) {
      btn.disabled = false;
      if (!j.success) { msg.textContent = j.message || 'Could not save.'; return; }
      var row = editGoalTarget.closest('tr');
      row.querySelector('.ndg-p-product').textContent = product;
      row.querySelector('.ndg-p-brand').textContent = brand;
      row.querySelector('.ndg-p-goal').textContent = goal;
      row.querySelector('.ndg-p-potential').textContent = String(parseFloat(potential));
      editGoalTarget.setAttribute('data-product', product); editGoalTarget.setAttribute('data-brand', brand);
      editGoalTarget.setAttribute('data-goal', goal); editGoalTarget.setAttribute('data-potential', potential);
      bootstrap.Modal.getOrCreateInstance(editGoalModalEl).hide();
    }).catch(function () { btn.disabled = false; msg.textContent = 'Request failed. Try again.'; });
  });

  // ── remove a customer ──
  document.querySelectorAll('.ndg-remove').forEach(function (b) {
    b.addEventListener('click', function () {
      if (!confirm('Remove this customer from your goals?')) { return; }
      post(URLS.base + '/' + b.getAttribute('data-id'), 'DELETE').then(function (j) {
        if (j.success) { location.reload(); } else { alert(j.message || 'Could not remove.'); }
      });
    });
  });

  // ── add customers from the pipeline ──
  var modal = document.getElementById('addCustomersModal');
  if (!modal) { return; }
  var list = document.getElementById('ndgList'), search = document.getElementById('ndgSearch'), addMsg = document.getElementById('ndgAddMsg');
  var customers = [], brands = [], loaded = false;

  function brandOptions(sel) {
    return '<option value="">Brand</option>' + brands.map(function (b) { return '<option' + (b === sel ? ' selected' : '') + '>' + esc(b) + '</option>'; }).join('');
  }
  function render() {
    var q = search.value.trim().toLowerCase();
    var rows = customers.filter(function (c) { return !q || (c.customer + ' ' + c.product + ' ' + c.brand).toLowerCase().indexOf(q) !== -1; });
    if (!customers.length) { list.innerHTML = '<div class="text-muted p-3">No customers left to add. Everything in your Sales Pipeline and Sales Report is already in this round, or you have none yet.</div>'; return; }
    if (!rows.length) { list.innerHTML = '<div class="text-muted p-3">Nothing matches that search.</div>'; return; }
    list.innerHTML = rows.map(function (c) {
      return '<div class="ndg-row' + (c.picked ? ' on' : '') + '" data-id="' + c.id + '">' +
        '<label class="d-flex align-items-center gap-2 mb-0" style="cursor:pointer"><input type="checkbox" class="form-check-input ndg-pick"' + (c.picked ? ' checked' : '') + '>' +
        '<span class="fw-semibold">' + esc(c.customer) + '</span> <span class="badge badge-hitech badge-muted ndg-src">' + esc(c.source) + '</span>' +
        '<span class="text-muted small ms-2">' + esc(c.product) + '</span></label>' +
        '<div class="ndg-extra gap-2 flex-wrap">' +
          '<input type="text" class="form-control form-control-sm ndg-product" style="max-width:220px" placeholder="Product" value="' + esc(c.pProduct != null ? c.pProduct : c.product) + '">' +
          '<select class="form-select form-select-sm ndg-brand" style="max-width:150px">' + brandOptions(c.pBrand != null ? c.pBrand : c.brand) + '</select>' +
          '<input type="text" class="form-control form-control-sm ndg-goal" style="max-width:220px" placeholder="Goal, for example Regular order" value="' + esc(c.pGoal != null ? c.pGoal : 'Order') + '">' +
          '<input type="number" step="0.01" min="0" class="form-control form-control-sm ndg-pot" style="max-width:150px" placeholder="Monthly ₹ lakh" value="' + esc(c.pPot != null ? c.pPot : '') + '">' +
        '</div></div>';
    }).join('');
  }
  function remember(row) {
    var c = customers.filter(function (x) { return String(x.id) === row.getAttribute('data-id'); })[0]; if (!c) { return; }
    c.picked = row.querySelector('.ndg-pick').checked;
    c.pProduct = row.querySelector('.ndg-product').value; c.pBrand = row.querySelector('.ndg-brand').value;
    c.pGoal = row.querySelector('.ndg-goal').value; c.pPot = row.querySelector('.ndg-pot').value;
  }
  list.addEventListener('input', function (e) { var r = e.target.closest('.ndg-row'); if (r) { remember(r); } });
  list.addEventListener('change', function (e) { var r = e.target.closest('.ndg-row'); if (r) { remember(r); r.classList.toggle('on', r.querySelector('.ndg-pick').checked); } });
  search.addEventListener('input', render);

  modal.addEventListener('shown.bs.modal', function () {
    if (loaded) { search.focus(); return; }
    fetch(URLS.customers, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (j) {
      customers = j.customers || []; brands = j.brands || []; loaded = true; render(); search.focus();
    }).catch(function () { list.innerHTML = '<div class="text-danger p-3">Could not load your customers. Close this and try again.</div>'; });
  });

  document.getElementById('ndgAddBtn').addEventListener('click', function () {
    var picked = customers.filter(function (c) { return c.picked; });
    if (!picked.length) { addMsg.textContent = 'Tick at least one customer.'; return; }
    for (var i = 0; i < picked.length; i++) {
      if (!String(picked[i].pGoal != null ? picked[i].pGoal : 'Order').trim()) { addMsg.textContent = picked[i].customer + ': write the goal.'; return; }
      if (picked[i].pPot === undefined || picked[i].pPot === '' || isNaN(parseFloat(picked[i].pPot))) { addMsg.textContent = picked[i].customer + ': enter the monthly potential in lakh.'; return; }
    }
    addMsg.textContent = ''; this.disabled = true; var btn = this;
    post(URLS.store, 'POST', { customers: picked.map(function (c) {
      return { sales_pipeline_id: c.id, product: c.pProduct != null ? c.pProduct : c.product, brand: c.pBrand != null ? c.pBrand : c.brand, goal: c.pGoal != null ? c.pGoal : 'Order', monthly_potential: parseFloat(c.pPot) };
    }) }).then(function (j) {
      if (j.success) { location.reload(); return; }
      btn.disabled = false; addMsg.textContent = (j.message || 'Could not add.') + (j.errors ? ' ' + Object.values(j.errors).join(' ') : '');
    }).catch(function () { btn.disabled = false; addMsg.textContent = 'Request failed. Try again.'; });
  });
})();
</script>
@if($mode === 'overview')
<script>
(function () {
  var table = document.getElementById('ndgTable'); if (!table) { return; }
  var body = table.tBodies[0];
  function $(id) { return document.getElementById(id); }
  var rows = function () { return Array.prototype.slice.call(body.querySelectorAll('tr.ndg-tr')); };

  // The second header row sticks just under the first.
  var head = table.tHead.rows;
  function stick() { var h = head[0].offsetHeight; Array.prototype.forEach.call(head[1].cells, function (c) { c.style.top = h + 'px'; }); }
  stick(); window.addEventListener('resize', stick);

  // ── filters ──
  function apply() {
    var q = $('fSearch').value.trim().toLowerCase(), user = $('fUser').value, brand = $('fBrand').value, week = $('fWeek').value, status = $('fStatus').value;
    var hasC = $('fComments').checked, over = $('fOver').checked, showEmpty = $('fNoGoals').checked, shown = 0, all = 0;
    rows().forEach(function (tr) {
      var empty = tr.getAttribute('data-empty') === '1', ok = true;
      all++;
      if (empty) {
        ok = showEmpty && !brand && !status && !hasC && !over && (!user || tr.getAttribute('data-user') === user) && (!q || tr.getAttribute('data-q').indexOf(q) !== -1);
      } else {
        var st = week === '' ? tr.getAttribute('data-latest') : tr.getAttribute('data-w' + week);
        if (q && tr.getAttribute('data-q').indexOf(q) === -1) { ok = false; }
        if (user && tr.getAttribute('data-user') !== user) { ok = false; }
        if (brand && tr.getAttribute('data-brand') !== brand) { ok = false; }
        if (status === '__none' ? st : (status && st !== status)) { ok = false; }
        if (hasC && tr.getAttribute('data-hasc') !== '1') { ok = false; }
        if (over && tr.getAttribute('data-over') !== '1') { ok = false; }
      }
      tr.style.display = ok ? '' : 'none';
      if (ok) { shown++; }
    });
    $('fCount').textContent = 'Showing ' + shown + ' of ' + all;
  }
  ['fSearch'].forEach(function (id) { $(id).addEventListener('input', apply); });
  ['fUser', 'fBrand', 'fWeek', 'fStatus', 'fComments', 'fOver', 'fNoGoals'].forEach(function (id) { $(id).addEventListener('change', apply); });
  $('fRound').addEventListener('change', function () { location.href = '{{ route('performance.ninety-day-goals.index') }}?view=overview&round=' + this.value; });
  $('fClear').addEventListener('click', function () {
    $('fSearch').value = ''; ['fUser', 'fBrand', 'fWeek', 'fStatus'].forEach(function (id) { $(id).value = ''; });
    $('fComments').checked = false; $('fOver').checked = false; $('fNoGoals').checked = true; apply();
  });

  // ── sorting: click any heading; click again to reverse ──
  var dir = {};
  table.querySelectorAll('th.ndg-sort').forEach(function (th) {
    th.addEventListener('click', function () {
      var col = parseInt(th.getAttribute('data-col'), 10), num = th.getAttribute('data-num') === '1';
      dir[col] = dir[col] === 1 ? -1 : 1;
      table.querySelectorAll('th.ndg-sort').forEach(function (x) { x.classList.remove('asc', 'desc'); });
      th.classList.add(dir[col] === 1 ? 'asc' : 'desc');
      var list = rows();
      list.sort(function (a, b) {
        var x = a.cells[col].getAttribute('data-v') || '', y = b.cells[col].getAttribute('data-v') || '';
        if (x === '' && y !== '') { return 1; }
        if (y === '' && x !== '') { return -1; }
        return (num ? parseFloat(x) - parseFloat(y) : x.localeCompare(y)) * dir[col];
      });
      list.forEach(function (tr) { body.appendChild(tr); });
    });
  });
  apply();
})();
</script>
@endif
@endsection
