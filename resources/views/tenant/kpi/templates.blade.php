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
    <button class="btn btn-primary" id="btnNewTemplate"><i class="bx bx-plus me-1"></i> New KPI / KRA</button>
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

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Title</th>
          <th>Department</th>
          <th>Scoring</th>
          <th class="text-end">Marks</th>
          <th>Late policy</th>
          <th>Frequency</th>
          <th class="text-center">Assigned</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($templates as $t)
          @php $counts = $assignmentCounts[$t->id] ?? null; @endphp
          <tr class="{{ $t->is_active ? '' : 'opacity-50' }}">
            <td>
              @if($t->category === 'KRA')
                <span class="badge bg-label-dark me-1">KRA</span>
                <strong>{{ $t->title }}</strong>
              @else
                <span class="ms-3 text-muted">&#8627;</span>
                {{ $t->title }}
                @if($t->parent)<div class="small text-muted ms-4">under {{ $t->parent->title }}</div>@endif
              @endif
              @if(!$t->is_active)<span class="badge bg-label-secondary ms-1">retired</span>@endif
              @if($t->description)<div class="small text-muted">{{ $t->description }}</div>@endif
            </td>
            <td>{{ $t->department->name ?? 'All departments' }}</td>
            <td>
              @if($t->category === 'KRA')
                <span class="text-muted">—</span>
              @else
                <span class="badge bg-label-info">{{ $t->scoring_type }}</span>
                @if($t->scoring_type === 'threshold')
                  <div class="small text-muted">≥ {{ rtrim(rtrim((string)$t->threshold_percent,'0'),'.') }}% of target</div>
                @elseif($t->scoring_type === 'banded')
                  <div class="small text-muted">{{ count($t->bands ?? []) }} band(s)</div>
                @elseif($t->target_value !== null)
                  <div class="small text-muted">target {{ rtrim(rtrim((string)$t->target_value,'0'),'.') }}</div>
                @endif
              @endif
            </td>
            <td class="text-end">{{ $t->category === 'KRA' ? '—' : rtrim(rtrim((string)$t->max_marks,'0'),'.') }}</td>
            <td>
              @if($t->category === 'KRA')<span class="text-muted">—</span>
              @elseif($t->late_policy === 'none')<span class="text-muted">not penalised</span>
              @elseif($t->late_policy === 'percent'){{ rtrim(rtrim((string)$t->late_percent,'0'),'.') }}% of marks
              @elseif($t->late_policy === 'per_day'){{ rtrim(rtrim((string)$t->per_day_penalty,'0'),'.') }}/day after {{ $t->grace_days }}d
              @else {{ rtrim(rtrim((string)$t->late_score,'0'),'.') }} marks
              @endif
            </td>
            <td>{{ ucfirst($t->frequency) }}</td>
            <td class="text-center">
              @if($t->category === 'KRA')<span class="text-muted">—</span>
              @else <span class="badge bg-label-success">{{ $counts->active ?? 0 }}</span>
              @endif
            </td>
            <td class="text-end text-nowrap">
              @if($t->category !== 'KRA')
                <button class="btn btn-sm btn-icon text-success tpl-apply" data-id="{{ $t->id }}"
                        title="Give this KPI to everyone in the department"><i class="bx bx-user-plus"></i></button>
              @endif
              <button class="btn btn-sm btn-icon text-primary tpl-edit" data-json='@json($t)' title="Edit"><i class="bx bx-edit"></i></button>
              <button class="btn btn-sm btn-icon text-danger tpl-retire" data-id="{{ $t->id }}" title="Retire"><i class="bx bx-x-circle"></i></button>
            </td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-center text-muted py-5">
            No templates yet. Create a KRA heading first, then the KPIs that sit under it.
          </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- Create / edit --}}
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
            <div class="col-md-8">
              <label class="form-label">Title <span class="text-danger">*</span></label>
              <input type="text" id="tpl_title" class="form-control" required placeholder="e.g. Revenue (Newbiz/CCare)">
            </div>
            <div class="col-md-4">
              <label class="form-label">Type</label>
              <select id="tpl_category" class="form-select">
                <option value="KPI">KPI — scored</option>
                <option value="KRA">KRA — heading only</option>
              </select>
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
                @foreach($kras as $k)<option value="{{ $k->id }}">{{ $k->title }}</option>@endforeach
              </select>
            </div>

            <div class="col-md-4 kpi-only">
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
  ['tpl_category','tpl_scoring_type','tpl_late_policy'].forEach(id => $(id).addEventListener('change', toggleFields));

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

  function openModal(t) {
    $('tplError').classList.add('d-none');
    $('bandRows').innerHTML = '';
    $('tpl_id').value = t ? t.id : '';
    $('tplModalTitle').textContent = t ? 'Edit KPI / KRA' : 'New KPI / KRA';

    const set = (id, v, dflt) => $(id).value = (v === null || v === undefined) ? (dflt ?? '') : v;
    set('tpl_title', t?.title);
    set('tpl_description', t?.description);
    $('tpl_category').value           = t?.category ?? 'KPI';
    set('tpl_department_id', t?.department_id);
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

  $('btnNewTemplate').addEventListener('click', () => openModal(null));
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
