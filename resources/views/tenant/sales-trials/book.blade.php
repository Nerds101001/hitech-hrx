@extends('layouts/layoutMaster')
@section('title', 'Book a Trial')

@section('content')
<div class="container-fluid" style="max-width:640px;">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="fw-bold mb-0"><i class="bx bx-calendar-plus me-1"></i> Book a Trial</h4>
    <a href="{{ route('sales-visits.trials.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bx bx-arrow-back"></i></a>
  </div>
  <p class="text-muted small">Book a trial for a salesperson as per the customer's requirement. The salesperson fills the result, contact, photos and location when they do it in the field.</p>

  @if($errors->any())<div class="alert alert-danger py-2"><ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

  <form method="POST" action="{{ route('sales-visits.trials.book.store') }}">
    @csrf
    <div class="card border-0 shadow-sm rounded-4 mb-3"><div class="card-body">
      <div class="mb-3">
        <label class="form-label fw-semibold">Salesperson <span class="text-danger">*</span></label>
        <select name="salesperson_id" id="salesperson" class="form-select" required>
          <option value="">— select salesperson —</option>
          @foreach($salespeople as $p)<option value="{{ $p->id }}" {{ old('salesperson_id')==$p->id?'selected':'' }}>{{ $p->first_name }} {{ $p->last_name }} ({{ $p->code }})</option>@endforeach
        </select>
        @if($salespeople->isEmpty())<div class="form-text text-danger">You have no mapped salespeople to book for.</div>@endif
      </div>
      <div class="mb-3">
        <label class="form-label fw-semibold">Company <span class="text-danger">*</span> <span class="text-muted small">(from that salesperson's Pipeline / Sales Report)</span></label>
        <select name="sales_pipeline_id" id="company" class="form-select" required disabled>
          <option value="">— pick a salesperson first —</option>
        </select>
      </div>
      <div class="row g-3">
        <div class="col-6"><label class="form-label fw-semibold">Date <span class="text-danger">*</span></label><input type="date" name="trial_date" class="form-control" value="{{ old('trial_date', date('Y-m-d')) }}" required></div>
        <div class="col-6"><label class="form-label fw-semibold">Time <span class="text-danger">*</span></label><input type="time" name="trial_time" class="form-control" value="{{ old('trial_time') }}" required></div>
      </div>
      <div class="mt-3"><label class="form-label fw-semibold">Topic / Trial name <span class="text-danger">*</span></label><input type="text" name="topic" class="form-control" value="{{ old('topic') }}" maxlength="255" required></div>
      <div class="mt-3"><label class="form-label fw-semibold">Product <span class="text-danger">*</span></label><input type="text" name="product" class="form-control" value="{{ old('product') }}" maxlength="255" required></div>
      <div class="mt-3"><label class="form-label fw-semibold">Customer requirement <span class="text-muted small">(optional)</span></label><textarea name="requirement" class="form-control" rows="2" maxlength="5000">{{ old('requirement') }}</textarea></div>
    </div></div>
    <div class="text-end mb-5"><button type="submit" class="btn btn-primary px-4"><i class="bx bx-check me-1"></i> Book trial</button></div>
  </form>
</div>

<script>
(function () {
  var sp = document.getElementById('salesperson');
  var co = document.getElementById('company');
  sp.addEventListener('change', function () {
    co.innerHTML = '<option value="">loading…</option>'; co.disabled = true;
    if (!sp.value) { co.innerHTML = '<option value="">— pick a salesperson first —</option>'; return; }
    fetch('{{ route('sales-visits.trials.companies') }}?salesperson_id=' + encodeURIComponent(sp.value), { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (rows) {
        co.innerHTML = '<option value="">— select a company —</option>';
        if (!rows.length) { co.innerHTML = '<option value="">no companies in this salesperson\'s pipeline/report</option>'; return; }
        rows.forEach(function (x) { var o = document.createElement('option'); o.value = x.id; o.textContent = x.label; co.appendChild(o); });
        co.disabled = false;
      })
      .catch(function () { co.innerHTML = '<option value="">could not load companies</option>'; });
  });
})();
</script>
@endsection
