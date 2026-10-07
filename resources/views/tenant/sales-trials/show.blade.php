@extends('layouts/layoutMaster')
@section('title', 'Trial #' . $trial->id)

@section('content')
@php
  $me = auth()->id();
  $canComplete = $trial->isBooked() && ($trial->salesperson_id === $me || ($isAdmin ?? false));
  $statusColor = match($trial->status){ 'completed' => '#15803d', 'cancelled' => '#b42318', default => '#b45309' };
  $sp = $trial->salesperson;
  $row = function ($icon, $label, $val) {
    $val = trim((string) $val);
    return '<div class="col-md-6 col-lg-4"><div class="d-flex gap-2">'
      . '<div class="tr-fi"><i class="bx '.$icon.'"></i></div>'
      . '<div class="min-w-0"><div class="tr-label">'.$label.'</div><div class="hitech-value-premium text-truncate">'.($val !== '' ? e($val) : '—').'</div></div>'
      . '</div></div>';
  };
@endphp

<style>
  .tr-hero{background:linear-gradient(135deg,#004d4d 0%,#008a8a 100%);border-radius:22px;box-shadow:0 14px 34px rgba(0,90,90,.22);position:relative;overflow:hidden}
  .tr-hero::after{content:'';position:absolute;right:-40px;top:-60px;width:220px;height:220px;background:rgba(255,255,255,.07);border-radius:50%;z-index:0;pointer-events:none}
  .tr-hero > div{position:relative;z-index:1}
  .tr-hero .btn{font-weight:700}
  .tr-hero .btn-complete{background:#fff;color:#007a7a;border:0;box-shadow:0 6px 16px rgba(0,0,0,.18)}
  .tr-hero .btn-complete:hover{background:#eafdfb;color:#005a5a}
  .tr-hero .btn-back{background:rgba(255,255,255,.12);color:#fff;border:1.5px solid rgba(255,255,255,.9)}
  .tr-hero .btn-back:hover{background:#fff;color:#007a7a;border-color:#fff}
  .tr-hero-icon{width:56px;height:56px;border-radius:16px;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.6rem;flex-shrink:0}
  .tr-pill{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.25);color:#fff;border-radius:999px;padding:3px 12px;font-size:.72rem;font-weight:700}
  .tr-fi{width:34px;height:34px;border-radius:9px;background:rgba(0,128,128,.08);color:#008080;display:flex;align-items:center;justify-content:center;font-size:1.05rem;flex-shrink:0}
  .tr-label{font-size:.68rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:#94a3b8}
  .tr-gallery .ph{height:210px;background:#f1f5f9;border:1px solid #e9eef3;border-radius:14px;overflow:hidden;display:flex;align-items:center;justify-content:center}
  .tr-gallery img{max-width:100%;max-height:100%;object-fit:contain;display:block;transition:transform .25s ease}
  .tr-gallery a:hover img{transform:scale(1.05)}
  .min-w-0{min-width:0}
</style>

<div class="hitech-portal"><div class="container-fluid py-2" style="max-width:1000px;">

  {{-- Hero header --}}
  <div class="tr-hero p-4 mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 position-relative">
      <div class="d-flex align-items-center gap-3">
        <div class="tr-hero-icon"><i class="bx bx-test-tube"></i></div>
        <div class="text-white">
          <h3 class="fw-bold mb-1" style="color:#fff !important">{{ $trial->company_name ?: 'Trial' }}</h3>
          <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="badge" style="background:#fff;color:{{ $statusColor }};font-weight:800">{{ ucfirst($trial->status) }}</span>
            <span class="tr-pill"><i class="bx bx-user-voice"></i> {{ $trial->sourceLabel() }}</span>
            <span class="tr-pill"><i class="bx bx-calendar"></i> {{ optional($trial->trial_date)->format('d M Y') }} @if($trial->trial_time){{ substr((string)$trial->trial_time,0,5) }}@endif</span>
          </div>
        </div>
      </div>
      <div class="d-flex gap-2">
        @if($canComplete)<button type="button" class="btn btn-complete px-4" data-bs-toggle="modal" data-bs-target="#completeModal"><i class="bx bx-check-circle me-1"></i> Complete this trial</button>@endif
        <a href="{{ route('sales-visits.trials.index') }}" class="btn btn-back"><i class="bx bx-arrow-back"></i></a>
      </div>
    </div>
  </div>

  @if(session('success'))<div class="alert alert-success py-2 small rounded-3">{{ session('success') }}</div>@endif

  {{-- Details --}}
  <div class="hitech-card rounded-4 mb-4"><div class="p-4">
    <div class="hitech-section-banner"><div class="hitech-section-icon"><i class="bx bx-detail"></i></div><div class="hitech-section-title">Trial details</div></div>
    <div class="row g-4">
      {!! $row('bx-user', 'Salesperson', trim(optional($sp)->first_name.' '.optional($sp)->last_name).(optional($sp)->code ? ' ('.$sp->code.')' : '')) !!}
      {!! $row('bx-buildings', 'Company', $trial->company_name) !!}
      {!! $row('bx-purchase-tag', 'Topic', $trial->topic) !!}
      {!! $row('bx-box', 'Product', $trial->product) !!}
      {!! $row('bx-user-pin', 'Contact person', $trial->contact_person) !!}
      {!! $row('bx-phone', 'Contact number', $trial->contact_number) !!}
    </div>
    @if($trial->requirement || $trial->description || $trial->result || $trial->remarks)
      <hr class="my-4" style="border-color:#eef2f6">
      <div class="row g-4">
        @if($trial->requirement)<div class="col-md-6 col-lg-4"><div class="tr-label mb-1">Customer requirement (at booking)</div><div>{{ $trial->requirement }}</div></div>@endif
        @if($trial->description)<div class="col-md-6 col-lg-4"><div class="tr-label mb-1">Description</div><div>{{ $trial->description }}</div></div>@endif
        @if($trial->result)<div class="col-md-6 col-lg-4"><div class="tr-label mb-1">Result</div><div class="fw-semibold" style="color:#0f172a">{{ $trial->result }}</div></div>@endif
        @if($trial->remarks)<div class="col-md-6 col-lg-4"><div class="tr-label mb-1">Remarks</div><div>{{ $trial->remarks }}</div></div>@endif
      </div>
    @endif
    @if($trial->latitude)
      <hr class="my-4" style="border-color:#eef2f6">
      <div class="d-flex align-items-center gap-2">
        <div class="tr-fi"><i class="bx bx-map"></i></div>
        <div><div class="tr-label">Location</div>
          <div class="hitech-value-premium">{{ number_format($trial->latitude,6) }}, {{ number_format($trial->longitude,6) }}@if($trial->location_accuracy) <span class="text-muted fw-normal">(±{{ round($trial->location_accuracy) }}m)</span>@endif
            &nbsp;<a href="https://maps.google.com/?q={{ $trial->latitude }},{{ $trial->longitude }}" target="_blank" rel="noopener" class="small">Open in Maps <i class="bx bx-link-external"></i></a></div>
        </div>
      </div>
    @endif
  </div></div>

  {{-- Photos --}}
  <div class="hitech-card rounded-4"><div class="p-4">
    <div class="hitech-section-banner"><div class="hitech-section-icon"><i class="bx bx-images"></i></div><div class="hitech-section-title">Photos ({{ $trial->images->count() }})</div></div>
    @if($trial->images->isEmpty())
      <div class="text-center text-muted py-4"><i class="bx bx-image-alt" style="font-size:2rem;opacity:.4"></i><div class="small mt-2">No photos yet.@if($trial->isBooked()) Added when the salesperson completes the trial.@endif</div></div>
    @else
      <div class="row g-3 tr-gallery">
        @foreach($trial->images as $img)
          <div class="col-6 col-md-4 col-lg-3"><a href="{{ $img->url }}" target="_blank" rel="noopener"><div class="ph"><img src="{{ $img->url }}" loading="lazy"></div></a></div>
        @endforeach
      </div>
    @endif
  </div></div>
</div>

{{-- Complete modal --}}
@if($canComplete)
<div class="modal fade" id="completeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center"><div class="modal-icon-header me-3"><i class="bx bx-check-circle"></i></div><h5 class="modal-title modal-title-hitech mb-0">Complete Trial</h5></div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal"><i class="bx bx-x"></i></button>
      </div>
      <form method="POST" action="{{ route('sales-visits.trials.complete', $trial->id) }}" enctype="multipart/form-data" id="compForm">
        @csrf
        <div class="modal-body modal-body-hitech">
          <div class="alert alert-info small"><i class="bx bx-info-circle me-1"></i> Booked by {{ optional($trial->bookedBy)->first_name ?: 'the office' }} ({{ $trial->sourceLabel() }}). Company, topic and product are pre-filled — add the rest + photos.</div>
          @include('tenant.sales-trials._fields', ['companies' => $completeCompanies, 'trial' => $trial, 'prefix' => 'comp'])
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-hitech-modal-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-hitech-modal-submit" id="compSubmitBtn">Complete trial <i class="bx bx-check ms-1"></i></button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif
</div>

@if($canComplete)
@include('tenant.sales-trials._form_js')
<script>(function(){ initTrialForm('comp'); var m=document.getElementById('completeModal'); if(m) m.addEventListener('shown.bs.modal',function(){captureTrialGeo('comp');}); })();</script>
@endif
@endsection
