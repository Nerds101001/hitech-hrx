@extends('layouts/layoutMaster')
@section('title', 'Trial #' . $trial->id)

@section('content')
@php $me = auth()->id(); @endphp
<div class="container-fluid" style="max-width:860px;">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bx bx-test-tube me-1"></i> {{ $trial->company_name ?: 'Trial' }}
        <span class="badge {{ $trial->statusBadge() }} align-middle">{{ ucfirst($trial->status) }}</span></h4>
      <div class="text-muted small">{{ $trial->sourceLabel() }} · {{ optional($trial->trial_date)->format('d M Y') }} {{ $trial->trial_time ? substr((string)$trial->trial_time,0,5) : '' }}</div>
    </div>
    <div class="d-flex gap-2">
      @if($trial->isBooked() && ($trial->salesperson_id === $me || true))
        <a href="{{ route('sales-visits.trials.complete.form', $trial->id) }}" class="btn btn-success"><i class="bx bx-check me-1"></i> Complete this trial</a>
      @endif
      <a href="{{ route('sales-visits.trials.index') }}" class="btn btn-outline-secondary"><i class="bx bx-arrow-back"></i></a>
    </div>
  </div>

  @if(session('success'))<div class="alert alert-success py-2 small">{{ session('success') }}</div>@endif

  <div class="card border-0 shadow-sm rounded-4 mb-3"><div class="card-body">
    <div class="row g-3 small">
      <div class="col-md-4"><div class="text-muted">Salesperson</div><div class="fw-semibold">{{ optional($trial->salesperson)->first_name }} {{ optional($trial->salesperson)->last_name }} ({{ optional($trial->salesperson)->code }})</div></div>
      <div class="col-md-4"><div class="text-muted">Company</div><div class="fw-semibold">{{ $trial->company_name }}</div></div>
      <div class="col-md-4"><div class="text-muted">Topic</div><div class="fw-semibold">{{ $trial->topic ?: '—' }}</div></div>
      <div class="col-md-4"><div class="text-muted">Product</div><div class="fw-semibold">{{ $trial->product ?: '—' }}</div></div>
      <div class="col-md-4"><div class="text-muted">Contact person</div><div class="fw-semibold">{{ $trial->contact_person ?: '—' }}</div></div>
      <div class="col-md-4"><div class="text-muted">Contact number</div><div class="fw-semibold">{{ $trial->contact_number ?: '—' }}</div></div>
      @if($trial->requirement)<div class="col-12"><div class="text-muted">Customer requirement (at booking)</div><div>{{ $trial->requirement }}</div></div>@endif
      @if($trial->description)<div class="col-12"><div class="text-muted">Description</div><div>{{ $trial->description }}</div></div>@endif
      @if($trial->result)<div class="col-12"><div class="text-muted">Result</div><div class="fw-semibold">{{ $trial->result }}</div></div>@endif
      @if($trial->remarks)<div class="col-12"><div class="text-muted">Remarks</div><div>{{ $trial->remarks }}</div></div>@endif
      @if($trial->latitude)
        <div class="col-md-6"><div class="text-muted">Location</div>
          <div class="fw-semibold">{{ number_format($trial->latitude,6) }}, {{ number_format($trial->longitude,6) }}@if($trial->location_accuracy) <span class="text-muted">(±{{ round($trial->location_accuracy) }}m)</span>@endif</div>
          <a href="https://maps.google.com/?q={{ $trial->latitude }},{{ $trial->longitude }}" target="_blank" rel="noopener" class="small">Open in Maps <i class="bx bx-link-external"></i></a>
        </div>
      @endif
    </div>
  </div></div>

  <div class="card border-0 shadow-sm rounded-4"><div class="card-body">
    <h6 class="fw-bold mb-3"><i class="bx bx-image me-1"></i> Photos ({{ $trial->images->count() }})</h6>
    @if($trial->images->isEmpty())
      <p class="text-muted small mb-0">No photos yet. @if($trial->isBooked())They're added when the salesperson completes the trial.@endif</p>
    @else
      <div class="row g-2">
        @foreach($trial->images as $img)
          <div class="col-6 col-md-3"><a href="{{ $img->url }}" target="_blank" rel="noopener"><img src="{{ $img->url }}" class="img-fluid rounded-3" style="aspect-ratio:1;object-fit:cover;width:100%" loading="lazy"></a></div>
        @endforeach
      </div>
    @endif
  </div></div>
</div>
@endsection
