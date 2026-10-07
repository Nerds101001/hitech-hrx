@extends('layouts/layoutMaster')
@section('title', 'Trials')

@section('content')
<div class="hitech-portal">
  <div class="container-fluid py-2">

    <style>
      .tr-hero{background:linear-gradient(135deg,#004d4d 0%,#008a8a 100%);border-radius:22px;box-shadow:0 14px 34px rgba(0,90,90,.22);position:relative;overflow:hidden}
      .tr-hero::after{content:'';position:absolute;right:-40px;top:-60px;width:220px;height:220px;background:rgba(255,255,255,.07);border-radius:50%}
      .tr-hero-icon{width:56px;height:56px;border-radius:16px;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.6rem;flex-shrink:0}
      .tr-stat{border-radius:16px;border:1px solid #e9eef3;background:#fff;padding:1.1rem 1.25rem;box-shadow:0 4px 12px rgba(0,0,0,.03);transition:transform .25s ease,box-shadow .25s ease}
      .tr-stat:hover{transform:translateY(-3px);box-shadow:0 12px 26px rgba(0,90,90,.08)}
      .tr-stat .n{font-size:1.7rem;font-weight:800;line-height:1}
      .tr-stat .l{font-size:.72rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:#94a3b8}
      .tr-stat .si{width:40px;height:40px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.2rem}
      .tr-table thead th{background:#f8fafc;font-size:.68rem;font-weight:800;letter-spacing:.04em;color:#64748b;border-bottom:1px solid #eef2f6}
      .tr-table tbody tr{border-bottom:1px solid #f1f5f9}
      .tr-table tbody tr:hover{background:#f8fafc}
    </style>

    {{-- Hero --}}
    <div class="tr-hero p-4 mb-4">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 position-relative">
        <div class="d-flex align-items-center gap-3">
          <div class="tr-hero-icon"><i class="bx bx-test-tube"></i></div>
          <div class="text-white">
            <h3 class="fw-bold mb-1" style="color:#fff !important">Trials</h3>
            <div style="opacity:.85;font-size:.85rem">Product trials — booked by CCare / New Biz, or lodged by the salesperson in the field.</div>
          </div>
        </div>
        <div class="d-flex gap-2">
          @if($canBook)<button type="button" class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#bookTrialModal"><i class="bx bx-calendar-plus me-1"></i> Book Trial</button>@endif
          @if($canLodge)<button type="button" class="btn btn-light fw-bold" style="color:#007a7a" data-bs-toggle="modal" data-bs-target="#lodgeTrialModal"><i class="bx bx-plus-circle me-1"></i> Lodge Trial</button>@endif
        </div>
      </div>
    </div>

    {{-- Stat cards --}}
    <div class="row g-3 mb-4">
      @foreach([['Total',$stats['total'],'bx-layer','#0f172a','#eef2f6'],['Booked',$stats['booked'],'bx-calendar-event','#b45309','#fef3c7'],['Completed',$stats['completed'],'bx-check-circle','#15803d','#dcfce7']] as [$l,$n,$ic,$col,$bg])
        <div class="col-12 col-md-4"><div class="tr-stat d-flex align-items-center justify-content-between">
          <div><div class="l mb-1">{{ $l }}</div><div class="n" style="color:{{ $col }}">{{ number_format($n) }}</div></div>
          <div class="si" style="background:{{ $bg }};color:{{ $col }}"><i class="bx {{ $ic }}"></i></div>
        </div></div>
      @endforeach
    </div>

    @if(session('success'))<div class="alert alert-success py-2 small rounded-3">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger py-2 rounded-3"><ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    {{-- Filters --}}
    <form method="GET" class="hitech-card-white rounded-4 p-3 mb-4">
      <div class="row g-2 align-items-end">
        <div class="col-6 col-md-2"><label class="form-label-hitech">Status</label>
          <select name="status" class="form-select form-select-hitech"><option value="">All</option>
            @foreach(['booked','completed','cancelled'] as $s)<option value="{{ $s }}" {{ ($filters['status']??'')===$s?'selected':'' }}>{{ ucfirst($s) }}</option>@endforeach
          </select></div>
        @if($people->isNotEmpty())
          <div class="col-6 col-md-3"><label class="form-label-hitech">Salesperson</label>
            <select name="salesperson_id" class="form-select form-select-hitech"><option value="">All</option>
              @foreach($people as $p)<option value="{{ $p->id }}" {{ ($filters['salesperson_id']??'')==$p->id?'selected':'' }}>{{ $p->first_name }} {{ $p->last_name }}</option>@endforeach
            </select></div>
        @endif
        <div class="col-6 col-md-2"><label class="form-label-hitech">From</label><input type="date" name="from" value="{{ $filters['from']??'' }}" class="form-control form-control-hitech"></div>
        <div class="col-6 col-md-2"><label class="form-label-hitech">To</label><input type="date" name="to" value="{{ $filters['to']??'' }}" class="form-control form-control-hitech"></div>
        <div class="col-12 col-md-3 d-flex gap-2"><button class="btn btn-hitech-primary flex-grow-1"><i class="bx bx-filter-alt me-1"></i>Filter</button><a href="{{ route('sales-visits.trials.index') }}" class="btn btn-hitech-cancel">Reset</a></div>
      </div>
    </form>

    {{-- List --}}
    <div class="hitech-card rounded-4 overflow-hidden">
      <div class="table-responsive">
        <table class="table align-middle mb-0 tr-table">
          <thead><tr class="small text-muted text-uppercase">
            <th class="ps-4">Company</th><th>Salesperson</th><th>Topic / Product</th><th>Date</th><th>Source</th><th class="text-center">Photos</th><th>Status</th><th></th>
          </tr></thead>
          <tbody>
          @forelse($trials as $t)
            <tr>
              <td class="ps-4 fw-semibold">{{ $t->company_name ?: '—' }}</td>
              <td class="small">{{ optional($t->salesperson)->first_name }} {{ optional($t->salesperson)->last_name }}<br><span class="text-muted">{{ optional($t->salesperson)->code }}</span></td>
              <td class="small">{{ $t->topic ?: '—' }}<br><span class="text-muted">{{ $t->product }}</span></td>
              <td class="small">{{ optional($t->trial_date)->format('d M Y') ?: '—' }}<br><span class="text-muted">{{ $t->trial_time ? substr((string)$t->trial_time,0,5) : '' }}</span></td>
              <td class="small">{{ $t->sourceLabel() }}</td>
              <td class="text-center"><span class="badge bg-label-secondary">{{ $t->images->count() }}</span></td>
              <td><span class="badge {{ $t->statusBadge() }}">{{ ucfirst($t->status) }}</span></td>
              <td class="text-end pe-4"><a href="{{ route('sales-visits.trials.show', $t->id) }}" class="btn btn-sm btn-hitech-secondary">Open</a></td>
            </tr>
          @empty
            <tr><td colspan="8" class="text-center text-muted py-5">No trials yet. @if($canLodge)Tap <b>Lodge Trial</b> to add one.@endif</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
      @if($trials->hasPages())<div class="p-3">{{ $trials->links() }}</div>@endif
    </div>
  </div>

  {{-- ─────────── Lodge Trial modal ─────────── --}}
  @if($canLodge)
  <div class="modal fade" id="lodgeTrialModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
      <div class="modal-content modal-content-hitech">
        <div class="modal-header modal-header-hitech">
          <div class="d-flex align-items-center"><div class="modal-icon-header me-3"><i class="bx bx-test-tube"></i></div><h5 class="modal-title modal-title-hitech mb-0">Lodge a Trial</h5></div>
          <button type="button" class="btn-close-hitech" data-bs-dismiss="modal"><i class="bx bx-x"></i></button>
        </div>
        <form method="POST" action="{{ route('sales-visits.trials.store') }}" enctype="multipart/form-data" id="lodgeForm">
          @csrf
          <div class="modal-body modal-body-hitech">
            @if($lodgeCompanies->isEmpty())<div class="alert alert-warning small">You have no companies in your Sales Pipeline / Sales Report yet — add one there first.</div>@endif
            @include('tenant.sales-trials._fields', ['companies' => $lodgeCompanies, 'trial' => null, 'prefix' => 'lodge'])
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-hitech-modal-cancel" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-hitech-modal-submit" id="lodgeSubmitBtn">Lodge trial <i class="bx bx-check ms-1"></i></button>
          </div>
        </form>
      </div>
    </div>
  </div>
  @endif

  {{-- ─────────── Book Trial modal ─────────── --}}
  @if($canBook)
  <div class="modal fade" id="bookTrialModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content modal-content-hitech">
        <div class="modal-header modal-header-hitech">
          <div class="d-flex align-items-center"><div class="modal-icon-header me-3"><i class="bx bx-calendar-plus"></i></div><h5 class="modal-title modal-title-hitech mb-0">Book a Trial</h5></div>
          <button type="button" class="btn-close-hitech" data-bs-dismiss="modal"><i class="bx bx-x"></i></button>
        </div>
        <form method="POST" action="{{ route('sales-visits.trials.book.store') }}" id="bookForm">
          @csrf
          <div class="modal-body modal-body-hitech">
            <p class="text-muted small">Book a trial for a salesperson as per the customer's requirement — they fill the result, contact, photos and location in the field.</p>
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label-hitech">Salesperson <span class="text-danger">*</span></label>
                <select name="salesperson_id" id="bookSalesperson" class="form-select form-select-hitech" required>
                  <option value="">— select salesperson —</option>
                  @foreach($bookSalespeople as $sp)<option value="{{ $sp->id }}">{{ $sp->first_name }} {{ $sp->last_name }} ({{ $sp->code }})</option>@endforeach
                </select>
                @if($bookSalespeople->isEmpty())<div class="form-text text-danger">No mapped salespeople to book for.</div>@endif
              </div>
              <div class="col-md-6"><label class="form-label-hitech">Company <span class="text-danger">*</span></label>
                <select name="sales_pipeline_id" id="bookCompany" class="form-select form-select-hitech" required disabled><option value="">— pick a salesperson first —</option></select>
              </div>
              <div class="col-6"><label class="form-label-hitech">Date <span class="text-danger">*</span></label><input type="date" name="trial_date" class="form-control form-control-hitech" value="{{ date('Y-m-d') }}" required></div>
              <div class="col-6"><label class="form-label-hitech">Time <span class="text-danger">*</span></label><input type="time" name="trial_time" class="form-control form-control-hitech" required></div>
              <div class="col-md-6"><label class="form-label-hitech">Topic / Trial name <span class="text-danger">*</span></label><input type="text" name="topic" class="form-control form-control-hitech" maxlength="255" required></div>
              <div class="col-md-6"><label class="form-label-hitech">Product <span class="text-danger">*</span></label><input type="text" name="product" class="form-control form-control-hitech" maxlength="255" required></div>
              <div class="col-12"><label class="form-label-hitech">Customer requirement <span class="text-muted" style="font-weight:400;font-size:.75rem">(optional)</span></label><textarea name="requirement" class="form-control form-control-hitech" rows="2" maxlength="5000"></textarea></div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-hitech-modal-cancel" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-hitech-modal-submit">Book trial <i class="bx bx-check ms-1"></i></button>
          </div>
        </form>
      </div>
    </div>
  </div>
  @endif
</div>

@include('tenant.sales-trials._form_js')
<script>
(function () {
  @if($canLodge)
  initTrialForm('lodge');
  var lm = document.getElementById('lodgeTrialModal');
  if (lm) lm.addEventListener('shown.bs.modal', function () { captureTrialGeo('lodge'); });
  @endif
  @if($canBook)
  var sp = document.getElementById('bookSalesperson'), co = document.getElementById('bookCompany');
  if (sp) sp.addEventListener('change', function () {
    co.innerHTML = '<option value="">loading…</option>'; co.disabled = true;
    if (!sp.value) { co.innerHTML = '<option value="">— pick a salesperson first —</option>'; return; }
    fetch('{{ route('sales-visits.trials.companies') }}?salesperson_id=' + encodeURIComponent(sp.value), { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (rows) {
        co.innerHTML = '<option value="">— select a company —</option>';
        if (!rows.length) { co.innerHTML = '<option value="">no companies in this salesperson\'s pipeline/report</option>'; return; }
        rows.forEach(function (x) { var o = document.createElement('option'); o.value = x.id; o.textContent = x.label; co.appendChild(o); });
        co.disabled = false;
      }).catch(function () { co.innerHTML = '<option value="">could not load</option>'; });
  });
  @endif
})();
</script>
@endsection
