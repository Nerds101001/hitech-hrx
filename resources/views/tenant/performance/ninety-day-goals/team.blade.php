@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')

@section('title', '90-Day Goals Team')

@section('content')
@php
  $fmtL = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.') ?: '0';
  $pctText = fn ($v) => $v === null ? '–' : rtrim(rtrim(number_format($v, 1), '0'), '.') . '%';
  $pctColor = fn ($v) => $v === null ? '#94a3b8' : ($v >= 66 ? '#007a7a' : ($v >= 33 ? '#f5a623' : '#ef4444'));
@endphp
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 mx-4">
    <div>
      <h3 class="fw-extrabold mb-1 text-dark">90-Day Goals</h3>
      <p class="text-muted mb-0 small">{{ $people->count() }} {{ \Illuminate\Support\Str::plural('salesperson', $people->count()) }} on the current round. Select anyone to open their page.</p>
    </div>
  </div>

  @include('tenant.performance.ninety-day-goals._tabs')

  <div class="px-4">

  <div class="ndg-tiles">
    @foreach($people as $p)
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

  </div>
</div>

<style>
  .ndg-tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px; }
  .ndg-tile { display: block; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 18px; text-decoration: none; transition: border-color .15s; }
  .ndg-tile:hover { border-color: #007a7a; }
  .ndg-tile-top { display: flex; justify-content: space-between; font-size: .7rem; color: #64748b; }
  .ndg-tile-top b { color: #0f172a; font-weight: 700; }
  .ndg-tile-nm { font-size: 1.1rem; font-weight: 700; color: #0f172a; margin: 4px 0 10px; line-height: 1.25; }
  .ndg-track { height: 6px; background: #eef2f5; border-radius: 99px; overflow: hidden; }
  .ndg-fill { height: 100%; border-radius: 99px; min-width: 2px; }
  .ndg-tile-stats { display: grid; grid-template-columns: repeat(4, auto); justify-content: space-between; gap: 6px; margin-top: 12px; }
  .ndg-tile-stats b { display: block; font-size: .92rem; font-weight: 700; color: #0f172a; }
  .ndg-tile-stats small { font-size: .64rem; color: #94a3b8; }
</style>
@endsection
