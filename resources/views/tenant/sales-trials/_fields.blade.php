@php $t = $trial ?? null; $p = $prefix; @endphp
<div class="row g-3">
  <div class="col-12">
    <label class="form-label-hitech">Company <span class="text-danger">*</span> <span class="text-muted" style="font-weight:400;font-size:.75rem">(from the Sales Pipeline / Sales Report)</span></label>
    <select name="sales_pipeline_id" class="form-select form-select-hitech" required>
      <option value="">— select a company —</option>
      @foreach($companies as $c)
        <option value="{{ $c->id }}" {{ (optional($t)->sales_pipeline_id == $c->id) ? 'selected' : '' }}>{{ $c->party_name }}@if($c->product) — {{ $c->product }}@endif</option>
      @endforeach
    </select>
  </div>
  <div class="col-6">
    <label class="form-label-hitech">Date <span class="text-danger">*</span></label>
    <input type="date" name="trial_date" class="form-control form-control-hitech" value="{{ $t ? optional($t->trial_date)->format('Y-m-d') : date('Y-m-d') }}" required>
  </div>
  <div class="col-6">
    <label class="form-label-hitech">Time <span class="text-danger">*</span></label>
    <input type="time" name="trial_time" class="form-control form-control-hitech" value="{{ $t ? substr((string) $t->trial_time, 0, 5) : date('H:i') }}" required>
  </div>
  <div class="col-md-6">
    <label class="form-label-hitech">Topic / Trial name <span class="text-danger">*</span></label>
    <input type="text" name="topic" class="form-control form-control-hitech" value="{{ optional($t)->topic }}" maxlength="255" required>
  </div>
  <div class="col-md-6">
    <label class="form-label-hitech">Product <span class="text-danger">*</span></label>
    <input type="text" name="product" class="form-control form-control-hitech" value="{{ optional($t)->product }}" maxlength="255" required>
  </div>
  <div class="col-12">
    <label class="form-label-hitech">Description <span class="text-danger">*</span></label>
    <textarea name="description" class="form-control form-control-hitech" rows="2" maxlength="5000" required>{{ optional($t)->description }}</textarea>
  </div>
  <div class="col-md-6">
    <label class="form-label-hitech">Contact person <span class="text-danger">*</span></label>
    <input type="text" name="contact_person" class="form-control form-control-hitech" value="{{ optional($t)->contact_person }}" maxlength="255" required>
  </div>
  <div class="col-md-6">
    <label class="form-label-hitech">Contact number <span class="text-danger">*</span></label>
    <input type="tel" name="contact_number" class="form-control form-control-hitech" value="{{ optional($t)->contact_number }}" maxlength="30" required>
  </div>
  <div class="col-12">
    <label class="form-label-hitech">Result <span class="text-danger">*</span></label>
    <textarea name="result" class="form-control form-control-hitech" rows="2" maxlength="5000" required>{{ optional($t)->result }}</textarea>
  </div>
  <div class="col-12">
    <label class="form-label-hitech">Remarks <span class="text-muted" style="font-weight:400;font-size:.75rem">(optional)</span></label>
    <textarea name="remarks" class="form-control form-control-hitech" rows="1" maxlength="2000">{{ optional($t)->remarks }}</textarea>
  </div>

  {{-- Photos --}}
  <div class="col-12">
    <label class="form-label-hitech">Photos <span class="text-danger">*</span> <span class="text-muted" style="font-weight:400;font-size:.75rem">(at least 3 — compressed automatically)</span></label>
    <input type="file" name="photos[]" id="{{ $p }}PhotoBag" accept="image/*" multiple hidden>
    <input type="file" id="{{ $p }}PhotoPick" accept="image/*" capture="environment" hidden>
    <div class="d-flex align-items-center gap-2 mb-2">
      <button type="button" class="btn btn-hitech-secondary btn-sm" id="{{ $p }}AddPhotoBtn"><i class="bx bx-camera me-1"></i> Add photo</button>
      <span class="small text-danger" id="{{ $p }}PhotoCount">0 added (need 3)</span>
    </div>
    <div class="row g-2" id="{{ $p }}PhotoPreview"></div>
  </div>
</div>

<input type="hidden" name="latitude" id="{{ $p }}Lat"><input type="hidden" name="longitude" id="{{ $p }}Lng"><input type="hidden" name="location_accuracy" id="{{ $p }}Acc">
<div class="small text-muted mt-3" id="{{ $p }}GeoStatus"><i class="bx bx-map-pin"></i> location will be captured when you open this form</div>
