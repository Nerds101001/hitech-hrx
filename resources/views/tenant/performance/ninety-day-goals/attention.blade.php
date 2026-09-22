@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')

@section('title', '90-Day Goals Needs Attention')

@section('content')
@php
  $G = \App\Services\NinetyDayGoalService::class;
  $fmtL = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.') ?: '0';
  $name = fn ($u) => trim(optional($u)->first_name . ' ' . optional($u)->last_name);
@endphp
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 mx-4">
    <div>
      <h3 class="fw-extrabold mb-1 text-dark">90-Day Goals</h3>
      <p class="text-muted mb-0 small">Customers in the current round that are slipping, silent, dropped, or waiting on a status that never came — most urgent first.</p>
    </div>
  </div>

  @include('tenant.performance.ninety-day-goals._tabs')

  <div class="px-4">

  <div class="hitech-card mb-6">
    <div class="card-body p-4 border-bottom fw-bold text-dark">{{ $rows->count() }} of {{ $total }} customers need a look</div>
    <div class="card-body p-0 table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr>
          @if($seesMany)<th>Salesperson</th>@endif
          <th style="min-width:180px">Customer</th><th>Brand</th><th class="text-end">Monthly ₹L</th>
          <th>Latest status</th><th>Not sent</th>
        </tr></thead>
        <tbody>
        @forelse($rows as $r)
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

  </div>
</div>

<style>
  .ndg-chip { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: .68rem; font-weight: 700; }
  .ndg-none { color: #cbd5e1; }
  .ndg-overdue { color: #b42318; font-weight: 700; font-size: .82rem; }
</style>
@endsection
