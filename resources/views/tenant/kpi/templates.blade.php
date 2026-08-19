@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')

@section('title', 'KPI / KRA Templates')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="mb-1">KPI / KRA Templates</h4>
    <p class="text-muted mb-0 small">
      Define a scorecard once for a department — every active employee in it receives the KPI.
      A <strong>KRA</strong> is a heading that groups KPIs and carries no marks of its own.
    </p>
  </div>
  <div class="d-flex gap-2">
    <a href="{{ route('tenant.kpi.manage') }}" class="btn btn-outline-secondary">Per-employee view</a>
    <button class="btn btn-outline-primary" id="btnNewKra"><i class="bx bx-folder-plus me-1"></i> New KRA</button>
    <button class="btn btn-primary" id="btnNewKpi"><i class="bx bx-plus me-1"></i> New KPI</button>
  </div>
</div>

<div class="card mb-4">
  <div class="card-body">
    <form method="GET" action="{{ route('tenant.kpi.templates') }}" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label">Department</label>
        <select name="department_id" class="form-select" onchange="this.form.submit()">
          <option value="">All departments</option>
          @foreach($departments as $d)
            <option value="{{ $d->id }}" {{ (string)$departmentId === (string)$d->id ? 'selected' : '' }}>{{ $d->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-8 text-muted small">
        A template with no department applies to every department in the company.
      </div>
    </form>
  </div>
</div>

@php
  // Split the flat list into headings and scored KPIs, then group each by
  // department so the page reads department-by-department instead of one long
  // mixed table where a KRA and a KPI looked the same.
  $allKras = $templates->where('category', 'KRA');
  $allKpis = $templates->where('category', '!=', 'KRA');
  $kraById = $allKras->keyBy('id');
  $deptName = fn($t) => $t->department->name ?? 'All departments';
  $kpisByDept = $allKpis->groupBy($deptName);
  $krasByDept = $allKras->groupBy($deptName);
  $marksOf = fn($t) => rtrim(rtrim((string) $t->max_marks, '0'), '.');
@endphp

<ul class="nav nav-tabs mb-0" id="tplTabs">
  <li class="nav-item">
    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabKpis" type="button">
      KPIs <span class="badge bg-label-primary ms-1">{{ $allKpis->count() }}</span>
    </button>
  </li>
  <li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabKras" type="button">
      KRAs <span class="badge bg-label-dark ms-1">{{ $allKras->count() }}</span>
    </button>
  </li>
</ul>

<div class="tab-content p-0 border-0">

  {{-- ===================== KPIs ===================== --}}
  <div class="tab-pane fade show active" id="tabKpis">
    <div class="card border-top-0 rounded-top-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="min-width:220px">KPI</th>
              <th style="min-width:150px">Sits under (KRA)</th>
              <th>Scoring</th>
              <th class="text-end">Marks</th>
              <th>Late policy</th>
              <th>Frequency</th>
              <th class="text-center">Assigned</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse($kpisByDept as $dept => $rows)
              <tr class="table-secondary">
                <td colspan="8" class="fw-semibold py-2">
                  <i class="bx bx-buildings me-1"></i>{{ $dept }}
                  <span class="text-muted fw-normal small ms-2">
                    {{ $rows->count() }} KPI{{ $rows->count() === 1 ? '' : 's' }} ·
                    {{ rtrim(rtrim((string) $rows->sum('max_marks'), '0'), '.') }} marks total
                  </span>
                </td>
              </tr>
              @foreach($rows as $t)
                <tr>
                  <td>
                    <div class="fw-medium">{{ $t->title }}</div>
                    @if($t->description)<small class="text-muted">{{ $t->description }}</small>@endif
                  </td>
                  <td>
                    @if($t->parent_template_id && $kraById->has($t->parent_template_id))
                      <span class="badge bg-label-dark">{{ $kraById[$t->parent_template_id]->title }}</span>
                    @elseif($t->parent_template_id)
                      <span class="badge bg-label-danger" title="The KRA this points at no longer exists">orphaned</span>
                    @else
                      <span class="text-muted">— ungrouped —</span>
                    @endif
                  </td>
                  <td>
                    <span class="badge bg-label-info">{{ $t->scoring_type }}</span>
                    @if($t->target_value)<small class="text-muted d-block">target {{ rtrim(rtrim((string)$t->target_value,'0'),'.') }}</small>@endif
                  </td>
                  <td class="text-end fw-semibold">{{ $marksOf($t) }}</td>
                  <td>
                    @if($t->late_policy === 'fixed'){{ $marksOf((object)['max_marks' => $t->late_score]) }} marks
                    @elseif($t->late_policy === 'percent'){{ $t->late_percent }}%
                    @elseif($t->late_policy === 'per_day'){{ $t->grace_days }}d grace, -{{ $t->per_day_penalty }}/day
                    @else <span class="text-muted">none</span>@endif
                  </td>
                  <td>{{ ucfirst($t->frequency) }}</td>
                  <td class="text-center">
                    @php $c = $assignmentCounts[$t->id] ?? null; @endphp
                    @if($c)<span class="badge bg-label-success">{{ $c->active }}</span>
                    @else<span class="text-muted">—</span>@endif
                  </td>
                  <td class="text-end text-nowrap">
                    <button class="btn btn-sm btn-icon text-success tpl-apply" data-id="{{ $t->id }}"
                            title="Apply to everyone in this department"><i class="bx bx-user-plus"></i></button>
                    <button class="btn btn-sm btn-icon tpl-edit" data-json="{{ json_encode($t) }}" title="Edit"><i class="bx bx-edit"></i></button>
                    <button class="btn btn-sm btn-icon text-danger tpl-retire" data-id="{{ $t->id }}" title="Retire"><i class="bx bx-x-circle"></i></button>
                  </td>
                </tr>
              @endforeach
            @empty
              <tr><td colspan="8" class="text-center text-muted py-5">
                No KPIs yet. Create a KRA heading first, then add the KPIs that sit under it.
              </td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- ===================== KRAs ===================== --}}
  <div class="tab-pane fade" id="tabKras">
    <div class="card border-top-0 rounded-top-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="min-width:240px">KRA</th>
              <th>Frequency</th>
              <th class="text-center">KPIs under it</th>
              <th class="text-end">Marks in this KRA</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse($krasByDept as $dept => $rows)
              <tr class="table-secondary">
                <td colspan="5" class="fw-semibold py-2"><i class="bx bx-buildings me-1"></i>{{ $dept }}</td>
              </tr>
              @foreach($rows as $t)
                @php $children = $allKpis->where('parent_template_id', $t->id); @endphp
                <tr>
                  <td>
                    <span class="badge bg-label-dark me-1">KRA</span>
                    <span class="fw-medium">{{ $t->title }}</span>
                    @if($t->description)<small class="text-muted d-block">{{ $t->description }}</small>@endif
                  </td>
                  <td>{{ ucfirst($t->frequency) }}</td>
                  <td class="text-center">
                    @if($children->count())<span class="badge bg-label-primary">{{ $children->count() }}</span>
                    @else<span class="badge bg-label-warning" title="Nothing sits under this heading yet">empty</span>@endif
                  </td>
                  <td class="text-end fw-semibold">{{ rtrim(rtrim((string) $children->sum('max_marks'), '0'), '.') }}</td>
                  <td class="text-end text-nowrap">
                    <button class="btn btn-sm btn-icon tpl-edit" data-json="{{ json_encode($t) }}" title="Edit"><i class="bx bx-edit"></i></button>
                    <button class="btn btn-sm btn-icon text-danger tpl-retire" data-id="{{ $t->id }}" title="Retire"><i class="bx bx-x-circle"></i></button>
                  </td>
                </tr>
              @endforeach
            @empty
              <tr><td colspan="5" class="text-center text-muted py-5">
                No KRAs yet. A KRA is the heading a group of KPIs sits under — create one first.
              </td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="tplModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <form id="tplForm">
        <div class="modal-header">
          <h5 class="modal-title" id="tplModalTitle">New KPI / KRA</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="tpl_id">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Title <span class="text-danger">*</span></label>
              <input type="text" id="tpl_title" class="form-control" required placeholder="e.g. Revenue (Newbiz/CCare)">
              {{-- Type comes from which button opened the dialog, not a dropdown:
                   a KRA and a KPI are different things and no longer share a form. --}}
              <input type="hidden" id="tpl_category" value="KPI">
            </div>

            <div class="col-md-6">
              <label class="form-label">Department</label>
              <select id="tpl_department_id" class="form-select">
                <option value="">All departments</option>
                @foreach($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
              </select>
            </div>
            <div class="col-md-6 kpi-only">
              <label class="form-label">Sits under (KRA)</label>
              <select id="tpl_parent_template_id" class="form-select">
                <option value="">— none —</option>
                @foreach($kras as $k)<option value="{{ $k->id }}" data-dept="{{ $k->department_id }}">{{ $k->title }}</option>@endforeach
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Frequency</label>
              <select id="tpl_frequency" class="form-select">
                @foreach(['daily','weekly','fortnightly','monthly','quarterly','yearly'] as $f)
                  <option value="{{ $f }}" {{ $f === 'monthly' ? 'selected' : '' }}>{{ ucfirst($f) }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-4 kpi-only">
              <label class="form-label">Scoring</label>
              <select id="tpl_scoring_type" class="form-select">
                <option value="target">Target — proportional</option>
                <option value="threshold">Threshold — all or nothing</option>
                <option value="banded">Banded — tiered marks</option>
                <option value="timeline">Timeline — done on time</option>
              </select>
            </div>
            <div class="col-md-4 kpi-only">
              <label class="form-label">Marks <span class="text-danger">*</span></label>
              <input type="number" step="0.5" min="0" id="tpl_max_marks" class="form-control" value="10">
            </div>

            <div class="col-md-6 needs-target">
              <label class="form-label">Target value</label>
              <input type="number" step="0.01" id="tpl_target_value" class="form-control" placeholder="e.g. 100">
              <div class="form-text">What 100% looks like. Score compares achieved against this.</div>
            </div>
            <div class="col-md-6 needs-threshold">
              <label class="form-label">Earns full marks at (% of target)</label>
              <input type="number" step="0.01" id="tpl_threshold_percent" class="form-control" placeholder="e.g. 90">
              <div class="form-text">Below this, the KPI scores zero.</div>
            </div>

            <div class="col-12 needs-bands">
              <label class="form-label">Bands</label>
              <div id="bandRows"></div>
              <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="addBand">
                <i class="bx bx-plus"></i> Add band
              </button>
              <div class="form-text">Highest band whose “from %” is met wins. e.g. 90→10, 75→7, 60→4.</div>
            </div>

            <div class="col-12 kpi-only"><hr class="my-1"></div>

            <div class="col-md-4 kpi-only">
              <label class="form-label">If late</label>
              <select id="tpl_late_policy" class="form-select">
                <option value="fixed">Fixed marks</option>
                <option value="percent">% of marks</option>
                <option value="per_day">Lose marks per day</option>
                <option value="none">No penalty</option>
              </select>
            </div>
            <div class="col-md-4 late-fixed">
              <label class="form-label">Marks when late</label>
              <input type="number" step="0.5" min="0" id="tpl_late_score" class="form-control" value="0">
            </div>
            <div class="col-md-4 late-percent">
              <label class="form-label">% of marks when late</label>
              <input type="number" step="1" min="0" max="100" id="tpl_late_percent" class="form-control" placeholder="50">
            </div>
            <div class="col-md-4 late-perday">
              <label class="form-label">Grace days</label>
              <input type="number" step="1" min="0" id="tpl_grace_days" class="form-control" value="0">
            </div>
            <div class="col-md-4 late-perday">
              <label class="form-label">Marks lost per day</label>
              <input type="number" step="0.5" min="0" id="tpl_per_day_penalty" class="form-control" placeholder="1">
            </div>

            <div class="col-md-6 kpi-only">
              <label class="form-label">How it is measured</label>
              <select id="tpl_data_source" class="form-select">
                <option value="manual">Manual entry</option>
                <option value="approval">Manual, needs approval</option>
                <option value="auto:revenue">Auto — revenue</option>
                <option value="auto:target_achievement">Auto — target achievement</option>
                <option value="auto:customer_health">Auto — customer health</option>
                <option value="auto:total_pipeline">Auto — total pipeline</option>
                <option value="auto:customers_added">Auto — customers added</option>
                <option value="auto:customers_retained">Auto — customers retained</option>
                <option value="auto:bingo_achievement">Auto — Bingo achievement</option>
                <option value="auto:kingo_achievement">Auto — Kingo achievement</option>
                <option value="auto:super_kingo_achievement">Auto — Super Kingo achievement</option>
                <option value="auto:rate_increase_visits">Auto — rate increase visits</option>
                <option value="auto:upsell_visits">Auto — upsell visits</option>
                <option value="auto:nif_recovery_visits">Auto — NIF recovery visits</option>
                <option value="auto:competitor_insight_visits">Auto — competitor insight visits</option>
                <option value="auto:product_idea_visits">Auto — product idea visits</option>
                <option value="auto:new_customer_visits">Auto — new customer visits</option>
                <option value="auto:trial_demo_visits">Auto — trial/demo visits</option>
                <option value="auto:sales_pitches">Auto — sales pitches (Mind Speak)</option>
                <option value="auto:seminars_conducted">Auto — seminars conducted (Mind Speak)</option>
                <option value="auto:learn_hitech_sessions">Auto — Learn@HiTech sessions</option>
              </select>
            </div>
            <div class="col-md-6 kpi-only">
              <label class="form-label">Approver</label>
              <select id="tpl_approver_id" class="form-select">
                <option value="">— none —</option>
                @foreach($approvers as $a)<option value="{{ $a->id }}">{{ $a->first_name }} {{ $a->last_name }}</option>@endforeach
              </select>
            </div>

            <div class="col-12">
              <label class="form-label">Note (optional)</label>
              <input type="text" id="tpl_description" class="form-control" maxlength="255">
            </div>
          </div>
          <div class="alert alert-danger mt-3 mb-0 d-none" id="tplError"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="tplSave">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
(function () {
  const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  const URL_STORE  = "{{ route('tenant.kpi.templates.store') }}";
  const URL_ONE    = "{{ route('tenant.kpi.templates.update', ['id' => '__ID__']) }}";
  const URL_APPLY  = "{{ route('tenant.kpi.templates.apply',  ['id' => '__ID__']) }}";
  const URL_RETIRE = "{{ route('tenant.kpi.templates.destroy',['id' => '__ID__']) }}";

  const $ = (id) => document.getElementById(id);
  const modal = () => bootstrap.Modal.getOrCreateInstance($('tplModal'));

  // Only show the fields the chosen scoring type and late policy actually use,
  // so nobody fills in a threshold on a KPI that ignores it.
  function toggleFields() {
    const isKra   = $('tpl_category').value === 'KRA';
    const scoring = $('tpl_scoring_type').value;
    const late    = $('tpl_late_policy').value;

    document.querySelectorAll('.kpi-only').forEach(el => el.classList.toggle('d-none', isKra));
    const show = (sel, on) => document.querySelectorAll(sel).forEach(el => el.classList.toggle('d-none', !on));
    show('.needs-target',    !isKra && ['target','threshold','banded'].includes(scoring));
    show('.needs-threshold', !isKra && scoring === 'threshold');
    show('.needs-bands',     !isKra && scoring === 'banded');
    show('.late-fixed',      !isKra && late === 'fixed');
    show('.late-percent',    !isKra && late === 'percent');
    show('.late-perday',     !isKra && late === 'per_day');
  }
  ['tpl_scoring_type','tpl_late_policy'].forEach(id => $(id).addEventListener('change', toggleFields));

  // A KPI may only sit under a KRA of the same department (or a company-wide
  // one). Showing every KRA regardless of department invited nonsense pairings.
  function filterKras() {
    const dept = $('tpl_department_id').value;
    const sel = $('tpl_parent_template_id');
    let clearedCurrent = false;
    Array.from(sel.options).forEach(function (o) {
      if (!o.value) return;
      const od = o.dataset.dept || '';
      const ok = !dept || !od || od === dept;
      o.hidden = !ok;
      o.disabled = !ok;
      if (!ok && sel.value === o.value) clearedCurrent = true;
    });
    if (clearedCurrent) sel.value = '';
  }
  $('tpl_department_id').addEventListener('change', filterKras);

  function bandRow(min, marks) {
    const wrap = document.createElement('div');
    wrap.className = 'row g-2 mb-2 band-row';
    wrap.innerHTML =
      '<div class="col-5"><div class="input-group input-group-sm">' +
        '<span class="input-group-text">from</span>' +
        '<input type="number" step="0.01" class="form-control band-min" value="' + (min ?? '') + '">' +
        '<span class="input-group-text">%</span></div></div>' +
      '<div class="col-5"><div class="input-group input-group-sm">' +
        '<span class="input-group-text">marks</span>' +
        '<input type="number" step="0.5" class="form-control band-marks" value="' + (marks ?? '') + '">' +
        '</div></div>' +
      '<div class="col-2"><button type="button" class="btn btn-sm btn-outline-danger w-100 band-del">&times;</button></div>';
    wrap.querySelector('.band-del').addEventListener('click', () => wrap.remove());
    return wrap;
  }
  $('addBand').addEventListener('click', () => $('bandRows').appendChild(bandRow('', '')));

  function readBands() {
    return Array.from(document.querySelectorAll('.band-row')).map(r => ({
      min:   parseFloat(r.querySelector('.band-min').value),
      marks: parseFloat(r.querySelector('.band-marks').value)
    })).filter(b => !isNaN(b.min) && !isNaN(b.marks));
  }

  function openModal(t, kind) {
    // kind is only supplied when creating; editing takes it from the record.
    const cat = t ? (t.category ?? 'KPI') : (kind ?? 'KPI');
    const isKra = cat === 'KRA';

    $('tplError').classList.add('d-none');
    $('bandRows').innerHTML = '';
    $('tpl_id').value = t ? t.id : '';
    $('tpl_category').value = cat;
    $('tplModalTitle').textContent = (t ? 'Edit ' : 'New ') + (isKra ? 'KRA — heading' : 'KPI — scored');
    $('tpl_title').placeholder = isKra ? 'e.g. Revenue & Target' : 'e.g. Revenue (Newbiz/CCare)';

    const set = (id, v, dflt) => $(id).value = (v === null || v === undefined) ? (dflt ?? '') : v;
    set('tpl_title', t?.title);
    set('tpl_description', t?.description);
    set('tpl_department_id', t?.department_id);
    filterKras();
    set('tpl_parent_template_id', t?.parent_template_id);
    $('tpl_frequency').value          = t?.frequency ?? 'monthly';
    $('tpl_scoring_type').value       = t?.scoring_type ?? 'target';
    set('tpl_max_marks', t?.max_marks, 10);
    set('tpl_target_value', t?.target_value);
    set('tpl_threshold_percent', t?.threshold_percent);
    $('tpl_late_policy').value        = t?.late_policy ?? 'fixed';
    set('tpl_late_score', t?.late_score, 0);
    set('tpl_late_percent', t?.late_percent);
    set('tpl_grace_days', t?.grace_days, 0);
    set('tpl_per_day_penalty', t?.per_day_penalty);
    $('tpl_data_source').value        = t?.data_source ?? 'manual';
    set('tpl_approver_id', t?.approver_id);

    let bands = t?.bands;
    if (typeof bands === 'string') { try { bands = JSON.parse(bands); } catch (e) { bands = null; } }
    (bands || []).forEach(b => $('bandRows').appendChild(bandRow(b.min, b.marks)));

    toggleFields();
    modal().show();
  }

  $('btnNewKpi').addEventListener('click', () => openModal(null, 'KPI'));
  $('btnNewKra').addEventListener('click', () => openModal(null, 'KRA'));
  document.querySelectorAll('.tpl-edit').forEach(b =>
    b.addEventListener('click', () => openModal(JSON.parse(b.dataset.json))));

  const numOrNull = (id) => { const v = $(id).value; return v === '' ? null : parseFloat(v); };

  $('tplForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const id = $('tpl_id').value;
    const payload = {
      title: $('tpl_title').value,
      description: $('tpl_description').value || null,
      category: $('tpl_category').value,
      parent_template_id: $('tpl_parent_template_id').value || null,
      department_id: $('tpl_department_id').value || null,
      frequency: $('tpl_frequency').value,
      scoring_type: $('tpl_scoring_type').value,
      max_marks: numOrNull('tpl_max_marks') ?? 0,
      target_value: numOrNull('tpl_target_value'),
      threshold_percent: numOrNull('tpl_threshold_percent'),
      bands: readBands(),
      late_policy: $('tpl_late_policy').value,
      late_score: numOrNull('tpl_late_score'),
      late_percent: numOrNull('tpl_late_percent'),
      grace_days: numOrNull('tpl_grace_days'),
      per_day_penalty: numOrNull('tpl_per_day_penalty'),
      data_source: $('tpl_data_source').value,
      approver_id: $('tpl_approver_id').value || null,
    };
    if (!payload.bands.length) payload.bands = null;

    $('tplSave').disabled = true;
    try {
      const res = await fetch(id ? URL_ONE.replace('__ID__', id) : URL_STORE, {
        method: id ? 'PUT' : 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify(payload), credentials: 'same-origin'
      });
      const data = await res.json();
      if (!res.ok) {
        const first = data.errors ? Object.values(data.errors)[0][0] : data.message;
        $('tplError').textContent = first || 'Could not save.';
        $('tplError').classList.remove('d-none');
        return;
      }
      location.reload();
    } catch (err) {
      $('tplError').textContent = 'Network error. Please try again.';
      $('tplError').classList.remove('d-none');
    } finally {
      $('tplSave').disabled = false;
    }
  });

  async function post(url, confirmMsg) {
    if (confirmMsg && !confirm(confirmMsg)) return;
    const res = await fetch(url, {
      method: url.endsWith('/apply') ? 'POST' : 'DELETE',
      headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, credentials: 'same-origin'
    });
    const data = await res.json().catch(() => ({}));
    alert(data.message || (res.ok ? 'Done.' : 'Something went wrong.'));
    if (res.ok) location.reload();
  }

  document.querySelectorAll('.tpl-apply').forEach(b => b.addEventListener('click', () =>
    post(URL_APPLY.replace('__ID__', b.dataset.id),
         'Give this KPI to every active employee in the department?')));

  document.querySelectorAll('.tpl-retire').forEach(b => b.addEventListener('click', () =>
    post(URL_RETIRE.replace('__ID__', b.dataset.id),
         'Retire this template? Existing assignments are deactivated but past scores are kept.')));

  toggleFields();
})();
</script>
@endsection
