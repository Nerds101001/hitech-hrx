@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')

@section('title', '90-Day Goals Weekly Matrix')

@section('content')
@php $G = \App\Services\NinetyDayGoalService::class; @endphp
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 mx-4">
    <div>
      <h3 class="fw-extrabold mb-1 text-dark">90-Day Goals</h3>
      <p class="text-muted mb-0 small">Every customer in the current round, one square per Monday. The fastest way to see the whole round's shape at once.</p>
    </div>
  </div>

  @include('tenant.performance.ninety-day-goals._tabs')

  <div class="px-4">

  @if($goals->isEmpty())
    <div class="hitech-card"><div class="card-body p-5 text-center text-muted">No customers in the current round yet.</div></div>
  @else
    <div class="hitech-card mb-6">
      <div class="card-body p-0 ndg-mx-scroll">
        <table class="ndg-mx">
          <thead>
            <tr>
              <th class="ndg-mx-l">Customer</th>
              @for($w = 0; $w < $weeks; $w++)<th>W{{ $w }}</th>@endfor
            </tr>
          </thead>
          <tbody>
          @foreach($goals->groupBy('user_id') as $userGoals)
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
                  <td class="ndg-mx-c {{ !$st && $ck ? 'ndg-mx-notsent' : '' }}" style="{{ $bg ? 'background:' . $bg : '' }}" title="W{{ $w }}: {{ $title }}">
                    @if(!$st && $ck)×@endif
                  </td>
                @endfor
              </tr>
            @endforeach
          @endforeach
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
  @endif

  </div>
</div>

<style>
  .ndg-mx-scroll { overflow: auto; max-height: 74vh; }
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
</style>
@endsection
