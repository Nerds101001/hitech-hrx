@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')

@section('title', '90-Day Goals — My Programs')

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4 mx-4">
    <div>
      <nav class="small text-muted mb-1"><a href="{{ route('tenant.dashboard') }}" class="text-muted">Dashboard</a> <i class="bx bx-chevron-right"></i> 90-Day Goals <i class="bx bx-chevron-right"></i> My Programs</nav>
      <h3 class="fw-extrabold mb-1 text-dark">My Programs</h3>
      <p class="text-muted mb-0 small">Your current 90-day program, where you add customers and submit each week's status, and every program you have finished.</p>
    </div>
  </div>

  @include('tenant.performance.ninety-day-goals._tabs', ['navTab' => 'programs'])

  <div class="px-4">
    <div class="hitech-card mb-6" style="border-color:#007a7a">
      <div class="card-body p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
          <div class="small text-muted text-uppercase fw-bold" style="font-size:.65rem">Current program</div>
          <div class="fw-bold text-dark fs-5">{{ optional($current->program)->name ?? ('Round ' . $current->round_no) }}</div>
          <div class="small text-muted">{{ $current->starts_on->format('d M Y') }} – {{ $current->ends_on->format('d M Y') }}</div>
        </div>
        <a href="{{ route('performance.ninety-day-goals.round', $current->id) }}" class="btn-hitech rounded-pill px-4 d-flex align-items-center gap-2"><i class="bx bx-right-arrow-alt"></i> Open my program</a>
      </div>
    </div>

    <div class="hitech-card mb-6">
      <div class="card-body p-4 border-bottom"><div class="fw-bold text-dark">Finished programs</div></div>
      <div class="card-body p-0">
        <table class="table align-middle mb-0" style="font-size:.85rem">
          <thead><tr><th>Program</th><th>Dates</th><th class="text-end">Customers</th><th class="text-end"></th></tr></thead>
          <tbody>
            @foreach($finished as $r)
              <tr>
                <td class="fw-bold text-dark">{{ optional($r->program)->name ?? ('Round ' . $r->round_no) }}</td>
                <td class="text-nowrap">{{ $r->starts_on->format('d M Y') }} – {{ $r->ends_on->format('d M Y') }}</td>
                <td class="text-end">{{ $stats[$r->id]['customers'] ?? 0 }}</td>
                <td class="text-end"><a href="{{ route('performance.ninety-day-goals.past.show', $r->id) }}" class="btn btn-sm btn-hitech-cancel rounded-pill px-3">View report</a></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
