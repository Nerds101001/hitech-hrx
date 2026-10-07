@extends('layouts/layoutMaster')

@php $isComplete = isset($trial) && $trial; @endphp
@section('title', $isComplete ? 'Complete Trial' : 'Lodge Trial')

@section('content')
<div class="container-fluid" style="max-width:720px;">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="fw-bold mb-0"><i class="bx bx-test-tube me-1"></i> {{ $isComplete ? 'Complete Trial' : 'Lodge a Trial' }}</h4>
    <a href="{{ route('sales-visits.trials.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bx bx-arrow-back"></i></a>
  </div>

  @if($errors->any())
    <div class="alert alert-danger py-2"><ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
  @endif
  @if($isComplete)
    <div class="alert alert-info py-2 small"><i class="bx bx-info-circle me-1"></i> Completing a trial booked by {{ optional($trial->bookedBy)->first_name }} ({{ $trial->sourceLabel() }}). Company, topic and product are pre-filled — add the rest.</div>
  @endif

  <form method="POST" action="{{ $isComplete ? route('sales-visits.trials.complete', $trial->id) : route('sales-visits.trials.store') }}" enctype="multipart/form-data" id="trialForm">
    @csrf
    <input type="hidden" name="latitude" id="lat"><input type="hidden" name="longitude" id="lng"><input type="hidden" name="location_accuracy" id="acc">

    <div class="card border-0 shadow-sm rounded-4 mb-3"><div class="card-body">
      <div class="mb-3">
        <label class="form-label fw-semibold">Company <span class="text-danger">*</span> <span class="text-muted small">(from your Sales Pipeline / Sales Report)</span></label>
        <select name="sales_pipeline_id" class="form-select" required>
          <option value="">— select a company —</option>
          @foreach($companies as $c)
            <option value="{{ $c->id }}" {{ (old('sales_pipeline_id', $isComplete ? $trial->sales_pipeline_id : null) == $c->id) ? 'selected' : '' }}>{{ $c->party_name }}@if($c->product) — {{ $c->product }}@endif</option>
          @endforeach
        </select>
      </div>

      <div class="row g-3">
        <div class="col-6"><label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
          <input type="date" name="trial_date" class="form-control" value="{{ old('trial_date', $isComplete ? optional($trial->trial_date)->format('Y-m-d') : date('Y-m-d')) }}" required></div>
        <div class="col-6"><label class="form-label fw-semibold">Time <span class="text-danger">*</span></label>
          <input type="time" name="trial_time" class="form-control" value="{{ old('trial_time', $isComplete ? substr((string)$trial->trial_time,0,5) : date('H:i')) }}" required></div>
      </div>

      <div class="mt-3"><label class="form-label fw-semibold">Topic / Trial name <span class="text-danger">*</span></label>
        <input type="text" name="topic" class="form-control" value="{{ old('topic', $isComplete ? $trial->topic : '') }}" maxlength="255" required></div>
      <div class="mt-3"><label class="form-label fw-semibold">Product <span class="text-danger">*</span></label>
        <input type="text" name="product" class="form-control" value="{{ old('product', $isComplete ? $trial->product : '') }}" maxlength="255" required></div>
      <div class="mt-3"><label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
        <textarea name="description" class="form-control" rows="3" maxlength="5000" required>{{ old('description') }}</textarea></div>
    </div></div>

    <div class="card border-0 shadow-sm rounded-4 mb-3"><div class="card-body">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label fw-semibold">Contact person <span class="text-danger">*</span></label>
          <input type="text" name="contact_person" class="form-control" value="{{ old('contact_person') }}" maxlength="255" required></div>
        <div class="col-md-6"><label class="form-label fw-semibold">Contact number <span class="text-danger">*</span></label>
          <input type="tel" name="contact_number" class="form-control" value="{{ old('contact_number') }}" maxlength="30" required></div>
      </div>
      <div class="mt-3"><label class="form-label fw-semibold">Result <span class="text-danger">*</span></label>
        <textarea name="result" class="form-control" rows="3" maxlength="5000" required>{{ old('result') }}</textarea></div>
      <div class="mt-3"><label class="form-label fw-semibold">Remarks <span class="text-muted small">(optional)</span></label>
        <textarea name="remarks" class="form-control" rows="2" maxlength="2000">{{ old('remarks') }}</textarea></div>
    </div></div>

    {{-- Photos: accumulate ≥3 via camera/gallery --}}
    <div class="card border-0 shadow-sm rounded-4 mb-3"><div class="card-body">
      <label class="form-label fw-semibold">Photos <span class="text-danger">*</span> <span class="text-muted small">(at least 3 — they're compressed automatically)</span></label>
      <input type="file" name="photos[]" id="photoBag" accept="image/*" multiple hidden>
      <input type="file" id="photoPick" accept="image/*" capture="environment" hidden>
      <div class="d-flex align-items-center gap-2 mb-2">
        <button type="button" class="btn btn-outline-primary" id="addPhotoBtn"><i class="bx bx-camera me-1"></i> Add photo</button>
        <span class="small" id="photoCount">0 added (need 3)</span>
      </div>
      <div class="row g-2" id="photoPreview"></div>
    </div></div>

    <div class="d-flex align-items-center justify-content-between mb-5">
      <span class="small text-muted" id="geoStatus"><i class="bx bx-map-pin"></i> getting location…</span>
      <button type="submit" class="btn btn-success px-4" id="submitBtn"><i class="bx bx-check me-1"></i> {{ $isComplete ? 'Complete trial' : 'Lodge trial' }}</button>
    </div>
  </form>
</div>

<script>
(function () {
  // ── Geolocation (best-effort) ──
  var geoStatus = document.getElementById('geoStatus');
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function (p) {
      document.getElementById('lat').value = p.coords.latitude;
      document.getElementById('lng').value = p.coords.longitude;
      document.getElementById('acc').value = Math.round(p.coords.accuracy);
      geoStatus.innerHTML = '<i class="bx bx-map-pin text-success"></i> location captured (±' + Math.round(p.coords.accuracy) + 'm)';
    }, function () {
      geoStatus.innerHTML = '<i class="bx bx-map-pin text-muted"></i> location unavailable (trial still saves)';
    }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
  } else { geoStatus.textContent = ''; }

  // ── Accumulating photo picker ──
  var dt = new DataTransfer();
  var bag = document.getElementById('photoBag');
  var pick = document.getElementById('photoPick');
  var preview = document.getElementById('photoPreview');
  var count = document.getElementById('photoCount');
  document.getElementById('addPhotoBtn').addEventListener('click', function () { pick.click(); });
  pick.addEventListener('change', function () {
    Array.prototype.forEach.call(pick.files, function (f) { dt.items.add(f); });
    pick.value = '';
    sync();
  });
  function sync() {
    bag.files = dt.files;
    preview.innerHTML = '';
    Array.prototype.forEach.call(dt.files, function (f, i) {
      var col = document.createElement('div'); col.className = 'col-4 col-md-3';
      var url = URL.createObjectURL(f);
      col.innerHTML = '<div class="position-relative"><img src="' + url + '" class="img-fluid rounded-3" style="aspect-ratio:1;object-fit:cover;width:100%"><button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 p-0 px-1" data-i="' + i + '">&times;</button></div>';
      preview.appendChild(col);
    });
    preview.querySelectorAll('button[data-i]').forEach(function (b) {
      b.addEventListener('click', function () { dt.items.remove(+b.dataset.i); sync(); });
    });
    var n = dt.files.length;
    count.textContent = n + ' added' + (n < 3 ? ' (need ' + (3 - n) + ' more)' : ' ✓');
    count.className = 'small ' + (n < 3 ? 'text-danger' : 'text-success');
  }
  document.getElementById('trialForm').addEventListener('submit', function (e) {
    if (dt.files.length < 3) { e.preventDefault(); alert('Please add at least 3 photos of the trial.'); }
    else { document.getElementById('submitBtn').disabled = true; }
  });
})();
</script>
@endsection
