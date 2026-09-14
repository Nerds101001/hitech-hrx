@extends('layouts/layoutMaster')

@section('title', 'My Holidays')

@section('vendor-style')
  @vite(['resources/assets/vendor/scss/pages/hitech-portal.scss'])
@endsection

@section('page-style')
<style>
  .mh-scope-tag{display:inline-flex;align-items:center;gap:4px;font-size:.68rem;font-weight:700;color:#7c3aed;background:rgba(124,58,237,0.1);padding:.15rem .5rem;border-radius:6px;}
  .mh-row-past{opacity:.65;}
  .mh-today{background:var(--hitech-primary-light,rgba(0,128,128,.08));}
</style>
@endsection

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 mx-4 py-2">
    <div>
      <h3 class="fw-extrabold mb-0 text-dark">My Holidays</h3>
      <p class="text-muted mb-0 small">Every holiday that applies to you — your site, your department, and every company-wide one.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
      <a href="{{ url('/my-holidays?year=' . ($year - 1)) }}" class="btn btn-sm btn-outline-hitech"><i class="bx bx-chevron-left"></i></a>
      <span class="fw-bold px-2">{{ $year }}</span>
      <a href="{{ url('/my-holidays?year=' . ($year + 1)) }}" class="btn btn-sm btn-outline-hitech"><i class="bx bx-chevron-right"></i></a>
    </div>
  </div>

  <div class="px-4">
    <div class="hitech-card mb-4">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0"><i class="bx bx-calendar-star me-1"></i> Upcoming</h6>
        <span class="badge badge-brand-soft badge-hitech">{{ $upcoming->count() }}</span>
      </div>
      @if($upcoming->isEmpty())
        <div class="text-center text-muted py-5">
          <i class="bx bx-calendar-check" style="font-size:2rem"></i>
          <p class="mb-0 mt-2">No upcoming holidays left in {{ $year }} for you.</p>
        </div>
      @else
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="min-width:110px">Date</th>
                <th>Day</th>
                <th style="min-width:200px">Holiday</th>
                <th>Type</th>
                <th>Applies to</th>
              </tr>
            </thead>
            <tbody>
              @foreach($upcoming as $h)
                <tr class="{{ $h->date->isToday() ? 'mh-today' : '' }}">
                  <td class="fw-medium">{{ $h->date->format('d M Y') }}</td>
                  <td><small class="text-muted">{{ $h->date->format('l') }}</small>
                    @if($h->date->isToday())<span class="badge badge-hitech-success badge-hitech ms-1">Today</span>@endif
                  </td>
                  <td>{{ $h->name }}</td>
                  <td><span class="badge-hitech {{ $h->isFullDay() ? 'badge-hitech-success' : 'badge-brand-soft' }}">{{ $h->type_label }}</span></td>
                  <td>
                    @if($h->site_id)
                      <span class="mh-scope-tag"><i class="bx bx-map-pin"></i> {{ optional($h->site)->name ?? 'Your site' }}</span>
                    @endif
                    @if($h->department_id)
                      <span class="mh-scope-tag"><i class="bx bx-buildings"></i> {{ optional($h->department)->name ?? 'Your department' }}</span>
                    @endif
                    @if(!$h->site_id && !$h->department_id)
                      <span class="text-muted small">Company-wide</span>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>

    <div class="hitech-card mb-4">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0"><i class="bx bx-history me-1"></i> Already Passed ({{ $year }})</h6>
        <span class="badge badge-muted badge-hitech">{{ $past->count() }}</span>
      </div>
      @if($past->isEmpty())
        <div class="text-center text-muted py-4">
          <p class="mb-0">Nothing yet this year.</p>
        </div>
      @else
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="min-width:110px">Date</th>
                <th>Day</th>
                <th style="min-width:200px">Holiday</th>
                <th>Type</th>
              </tr>
            </thead>
            <tbody>
              @foreach($past as $h)
                <tr class="mh-row-past">
                  <td>{{ $h->date->format('d M Y') }}</td>
                  <td><small class="text-muted">{{ $h->date->format('l') }}</small></td>
                  <td>{{ $h->name }}</td>
                  <td><span class="badge-hitech badge-muted">{{ $h->type_label }}</span></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>
</div>
@endsection
