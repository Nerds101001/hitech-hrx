@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')

@section('title', '90-Day Goals Trial Orders')

@section('content')
@php
  $G = \App\Services\NinetyDayGoalService::class;
  $fmtL = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.') ?: '0';
  $name = fn ($u) => trim(optional($u)->first_name . ' ' . optional($u)->last_name);

  // The path a customer takes, left to right: undecided, then converting, then the two ways it ends.
  $funnel = [
      'No Report' => $statuses['No Report'] ?? 0,
      'Off Track' => $statuses['Off Track'] ?? 0,
      'On Track'  => $statuses['On Track'] ?? 0,
      'Trial Order' => $statuses['Trial Order'] ?? 0,
      'Regular Order' => $statuses['Regular Order'] ?? 0,
  ];
  $maxF = max(1, ...array_values($funnel));
@endphp
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 mx-4">
    <div>
      <h3 class="fw-extrabold mb-1 text-dark">90-Day Goals</h3>
      <p class="text-muted mb-0 small">Trial orders are the clearest sign a target is converting. Every customer in the current round, by where they stand.</p>
    </div>
  </div>

  @include('tenant.performance.ninety-day-goals._tabs')

  <div class="px-4">

  <div class="hitech-card mb-6">
    <div class="card-body p-4 border-bottom fw-bold text-dark">Where the current round's {{ $total }} customers stand</div>
    <div class="card-body">
      <div class="ndg-fun">
        @foreach($funnel as $st => $n)
          @php [$bg] = $G::STATUS_COLORS[$st] ?? ['#94a3b8']; @endphp
          <div class="ndg-fun-row">
            <div class="ndg-fun-l">{{ $st }}</div>
            <div class="ndg-fun-bar"><div style="width:{{ round($n / $maxF * 100) }}%;background:{{ $bg }}"></div></div>
            <div class="ndg-fun-v">{{ $n }}<small>{{ $total ? round($n / $total * 100) : 0 }}% of all customers</small></div>
          </div>
        @endforeach
        @if(($statuses['Dropped'] ?? 0) || ($statuses['Transferred'] ?? 0))
          <div class="ndg-fun-note">
            Also this round: {{ $statuses['Dropped'] ?? 0 }} dropped, {{ $statuses['Transferred'] ?? 0 }} transferred out.
          </div>
        @endif
      </div>
    </div>
  </div>

  <div class="hitech-card mb-6">
    <div class="card-body p-4 border-bottom">
      <div class="fw-bold text-dark">At Trial Order right now</div>
      <div class="small text-muted">{{ $trialing->count() }} {{ \Illuminate\Support\Str::plural('customer', $trialing->count()) }} to watch — the ones closest to becoming a Regular Order.</div>
    </div>
    <div class="card-body p-0 table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr>
          @if($seesMany)<th>Salesperson</th>@endif
          <th style="min-width:180px">Customer</th><th>Brand</th><th class="text-end">Monthly ₹L</th>
        </tr></thead>
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

<style>
  .ndg-fun { display: flex; flex-direction: column; gap: 10px; }
  .ndg-fun-row { display: grid; grid-template-columns: minmax(120px, 170px) minmax(0, 1fr) 150px; gap: 14px; align-items: center; }
  .ndg-fun-bar { height: 26px; background: #eef2f5; border-radius: 5px; overflow: hidden; }
  .ndg-fun-bar div { height: 100%; border-radius: 5px; min-width: 2px; }
  .ndg-fun-l { font-size: .88rem; font-weight: 600; color: #0f172a; }
  .ndg-fun-v { text-align: right; font-weight: 700; font-size: .92rem; font-variant-numeric: tabular-nums; }
  .ndg-fun-v small { display: block; font-weight: 400; color: #94a3b8; font-size: .68rem; }
  .ndg-fun-note { font-size: .8rem; color: #64748b; margin-top: 4px; }
</style>
@endsection
