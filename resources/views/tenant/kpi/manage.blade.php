@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')
@section('title', 'KPI / KRA Management')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0 fw-bold"><i class="bx bx-target-lock me-1"></i> KPI / KRA Management</h5>
        <span class="text-muted small">Sales-department employees are excluded (target-based KRA handled separately).</span>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    {{-- Employee picker --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('tenant.kpi.manage') }}" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label">Select Employee</label>
                    <select name="user_id" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Choose employee --</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}" {{ (optional($selected)->id == $emp->id) ? 'selected' : '' }}>
                                {{ $emp->first_name }} {{ $emp->last_name }} ({{ $emp->code }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>
    </div>

    @if($selected)
    <div class="row">
        {{-- DWM routines available to convert --}}
        <div class="col-lg-5 mb-4">
            <div class="card h-100">
                <div class="card-header"><h6 class="mb-0">DWM Routines — {{ $selected->first_name }}</h6></div>
                <div class="card-body">
                    @forelse($routines as $routine)
                        <div class="d-flex align-items-center justify-content-between border rounded p-2 mb-2">
                            <div>
                                <span class="fw-semibold">{{ $routine->title }}</span>
                                <span class="badge bg-label-info ms-1 text-capitalize">{{ $routine->frequency }}</span>
                                <small class="text-muted d-block">{{ $routine->duration_minutes }} min</small>
                            </div>
                            <button class="btn btn-sm btn-primary add-from-routine"
                                data-id="{{ $routine->id }}" data-title="{{ $routine->title }}" data-frequency="{{ $routine->frequency }}">
                                <i class="bx bx-plus"></i> To KPI/KRA
                            </button>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No DWM routines found for this employee.</p>
                    @endforelse
                    <hr>
                    <button class="btn btn-outline-primary btn-sm w-100 add-from-routine" data-id="" data-title="" data-frequency="daily">
                        <i class="bx bx-plus"></i> Add custom KPI/KRA (not from DWM)
                    </button>
                </div>
            </div>
        </div>

        {{-- Existing assignments --}}
        <div class="col-lg-7 mb-4">
            <div class="card h-100">
                <div class="card-header"><h6 class="mb-0">Assigned KPI / KRA</h6></div>
                <div class="card-body table-responsive">
                    <table class="table table-sm">
                        <thead><tr>
                            <th>Title</th><th>Type</th><th>Freq</th><th>Scoring</th><th>Marks</th><th>Target</th><th>Status</th><th></th>
                        </tr></thead>
                        <tbody>
                        @forelse($assignments as $a)
                            <tr class="{{ $a->is_active ? '' : 'opacity-50' }}">
                                <td class="fw-semibold">{{ $a->title }}</td>
                                <td><span class="badge bg-label-{{ $a->category === 'KRA' ? 'warning' : 'primary' }}">{{ $a->category }}</span></td>
                                <td class="text-capitalize">{{ $a->frequency }}</td>
                                <td class="text-capitalize">{{ $a->scoring_type }}</td>
                                <td>{{ rtrim(rtrim(number_format($a->max_marks,2),'0'),'.') }}</td>
                                <td>
                                    @if($a->scoring_type === 'target')
                                        {{ rtrim(rtrim(number_format($a->target_value,2),'0'),'.') }}
                                        @php $tplTarget = optional($a->template)->target_value; @endphp
                                        @if($tplTarget !== null && abs((float)$tplTarget - (float)$a->target_value) > 0.001)
                                            {{-- Their own target, not the department's. Editing the template
                                                 no longer drags this back to the shared number. --}}
                                            <span class="badge badge-hitech badge-brand-soft ms-1"
                                                  title="Set for this person. Department default is {{ rtrim(rtrim(number_format($tplTarget,2),'0'),'.') }}.">own</span>
                                        @endif
                                    @else — @endif
                                </td>
                                <td>{!! $a->is_active ? '<span class="badge bg-label-success">Active</span>' : '<span class="badge bg-label-secondary">Inactive</span>' !!}</td>
                                <td class="text-end">
                                    @if($a->scoring_type === 'target' && $a->is_active)
                                        <button class="btn btn-icon btn-sm text-success evaluate-kpi" data-id="{{ $a->id }}" data-title="{{ $a->title }}" title="Record achievement"><i class="bx bx-check-square"></i></button>
                                    @endif
                                    <button class="btn btn-icon btn-sm text-primary edit-kpi"
                                        data-id="{{ $a->id }}" data-title="{{ $a->title }}" data-category="{{ $a->category }}"
                                        data-frequency="{{ $a->frequency }}" data-scoring="{{ $a->scoring_type }}"
                                        data-marks="{{ $a->max_marks }}" data-late="{{ $a->late_score }}"
                                        data-target="{{ $a->target_value }}" data-day="{{ $a->check_day }}" data-month="{{ $a->check_month }}"
                                        title="Edit"><i class="bx bx-edit"></i></button>
                                    @if($a->is_active)
                                        <button class="btn btn-icon btn-sm text-danger deactivate-kpi" data-id="{{ $a->id }}" title="Deactivate"><i class="bx bx-x-circle"></i></button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-3">No KPI/KRA assigned yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

{{-- Add / Edit modal --}}
<div class="modal fade" id="kpiModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="kpiForm">
        <div class="modal-header"><h5 class="modal-title" id="kpiModalTitle">Add KPI / KRA</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <input type="hidden" id="kpi_id">
          <input type="hidden" id="kpi_routine_id">
          <input type="hidden" id="kpi_user_id" value="{{ optional($selected)->id }}">
          <div class="mb-2"><label class="form-label">Title</label>
            <input type="text" id="kpi_title" class="form-control" required></div>
          <div class="row g-2">
            <div class="col-6 mb-2"><label class="form-label">Category</label>
              <select id="kpi_category" class="form-select"><option value="KPI">KPI</option><option value="KRA">KRA</option></select></div>
            <div class="col-6 mb-2"><label class="form-label">Frequency</label>
              <select id="kpi_frequency" class="form-select">
                <option value="daily">Daily</option><option value="weekly">Weekly</option>
                <option value="fortnightly">Fortnightly</option><option value="monthly">Monthly</option>
                <option value="quarterly">Quarterly</option><option value="yearly">Yearly</option></select></div>
          </div>
          <div class="mb-2"><label class="form-label">Scoring type</label>
            <select id="kpi_scoring" class="form-select">
              <option value="timeline">Timeline (on-time = full marks, late = late score)</option>
              <option value="target">Target (score = achieved ÷ target × marks)</option></select></div>
          <div class="row g-2">
            <div class="col-6 mb-2"><label class="form-label">Max marks</label>
              <input type="number" step="0.5" id="kpi_marks" class="form-control" value="5" required></div>
            <div class="col-6 mb-2" id="lateWrap"><label class="form-label">Late score</label>
              <input type="number" step="0.5" id="kpi_late" class="form-control" value="0"></div>
          </div>
          <div class="mb-2 d-none" id="targetWrap"><label class="form-label">Target value</label>
            <input type="number" step="0.01" id="kpi_target" class="form-control" placeholder="e.g. 10 hirings"></div>
          <div class="row g-2">
            <div class="col-6 mb-2 d-none" id="dayWrap"><label class="form-label" id="dayLabel">Check day</label>
              <input type="number" id="kpi_day" class="form-control" min="1" max="31"></div>
            <div class="col-6 mb-2 d-none" id="monthWrap"><label class="form-label" id="monthLabel">Check month</label>
              <input type="number" id="kpi_month" class="form-control" min="1" max="12"></div>
          </div>
          <p class="text-muted small mb-0" id="freqHint"></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Evaluate (record achievement) modal --}}
<div class="modal fade" id="evalModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="evalForm">
        <div class="modal-header"><h5 class="modal-title">Record Achievement — <span id="evalTitle"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <input type="hidden" id="eval_id">
          <div class="mb-2"><label class="form-label">Achieved value (this period)</label>
            <input type="number" step="0.01" id="eval_value" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Remarks (optional)</label>
            <input type="text" id="eval_remarks" class="form-control"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Save score</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
const CSRF = '{{ csrf_token() }}';
const kpiModal = new bootstrap.Modal(document.getElementById('kpiModal'));
const evalModal = new bootstrap.Modal(document.getElementById('evalModal'));

function toggleScoringFields() {
    const s = document.getElementById('kpi_scoring').value;
    document.getElementById('targetWrap').classList.toggle('d-none', s !== 'target');
    document.getElementById('lateWrap').classList.toggle('d-none', s === 'target');
    toggleFreqFields();
}
function toggleFreqFields() {
    const f = document.getElementById('kpi_frequency').value;
    const dayWrap = document.getElementById('dayWrap'), monthWrap = document.getElementById('monthWrap');
    const dayLabel = document.getElementById('dayLabel'), monthLabel = document.getElementById('monthLabel');
    const hint = document.getElementById('freqHint');
    dayWrap.classList.add('d-none'); monthWrap.classList.add('d-none');
    const hints = {
        daily: 'Checked every day — due the same day.',
        weekly: 'Checked each week — set which weekday it is due (1=Mon … 7=Sun).',
        fortnightly: 'Two periods a month (1–15, 16–end); due the last day of each half.',
        monthly: 'Checked each month — set the day of month it is due.',
        quarterly: 'Fiscal quarters (Apr-Jun, …). Set month-in-quarter (1–3) and day.',
        yearly: 'Fiscal year Apr–Mar. Set the calendar month (1–12) and day it is due.'
    };
    hint.textContent = hints[f] || '';
    if (f === 'weekly') { dayWrap.classList.remove('d-none'); dayLabel.textContent = 'Due weekday (1=Mon..7=Sun)'; }
    else if (f === 'monthly') { dayWrap.classList.remove('d-none'); dayLabel.textContent = 'Due day of month'; }
    else if (f === 'quarterly') { dayWrap.classList.remove('d-none'); monthWrap.classList.remove('d-none'); dayLabel.textContent='Due day'; monthLabel.textContent='Month in quarter (1-3)'; }
    else if (f === 'yearly') { dayWrap.classList.remove('d-none'); monthWrap.classList.remove('d-none'); dayLabel.textContent='Due day'; monthLabel.textContent='Calendar month (1-12)'; }
}
document.getElementById('kpi_scoring').addEventListener('change', toggleScoringFields);
document.getElementById('kpi_frequency').addEventListener('change', toggleFreqFields);

document.querySelectorAll('.add-from-routine').forEach(b => b.addEventListener('click', function() {
    document.getElementById('kpiModalTitle').textContent = 'Add KPI / KRA';
    document.getElementById('kpi_id').value = '';
    document.getElementById('kpi_routine_id').value = this.dataset.id || '';
    document.getElementById('kpi_title').value = this.dataset.title || '';
    document.getElementById('kpi_frequency').value = this.dataset.frequency || 'daily';
    document.getElementById('kpi_category').value = 'KPI';
    document.getElementById('kpi_scoring').value = 'timeline';
    document.getElementById('kpi_marks').value = 5;
    document.getElementById('kpi_late').value = 0;
    document.getElementById('kpi_target').value = '';
    document.getElementById('kpi_day').value = '';
    document.getElementById('kpi_month').value = '';
    toggleScoringFields();
    kpiModal.show();
}));

document.querySelectorAll('.edit-kpi').forEach(b => b.addEventListener('click', function() {
    const d = this.dataset;
    document.getElementById('kpiModalTitle').textContent = 'Edit KPI / KRA';
    document.getElementById('kpi_id').value = d.id;
    document.getElementById('kpi_routine_id').value = '';
    document.getElementById('kpi_title').value = d.title;
    document.getElementById('kpi_category').value = d.category;
    document.getElementById('kpi_frequency').value = d.frequency;
    document.getElementById('kpi_scoring').value = d.scoring;
    document.getElementById('kpi_marks').value = d.marks;
    document.getElementById('kpi_late').value = d.late;
    document.getElementById('kpi_target').value = d.target || '';
    document.getElementById('kpi_day').value = d.day || '';
    document.getElementById('kpi_month').value = d.month || '';
    toggleScoringFields();
    kpiModal.show();
}));

document.getElementById('kpiForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const id = document.getElementById('kpi_id').value;
    const payload = {
        user_id: document.getElementById('kpi_user_id').value,
        work_control_routine_id: document.getElementById('kpi_routine_id').value || null,
        title: document.getElementById('kpi_title').value,
        category: document.getElementById('kpi_category').value,
        frequency: document.getElementById('kpi_frequency').value,
        scoring_type: document.getElementById('kpi_scoring').value,
        max_marks: document.getElementById('kpi_marks').value,
        late_score: document.getElementById('kpi_late').value || 0,
        target_value: document.getElementById('kpi_target').value || null,
        check_day: document.getElementById('kpi_day').value || null,
        check_month: document.getElementById('kpi_month').value || null,
        _token: CSRF
    };
    const url = id ? `/kpi/${id}` : '/kpi/store';
    const method = id ? 'PUT' : 'POST';
    fetch(url, { method, headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'}, body: JSON.stringify(payload) })
      .then(r => r.json()).then(res => {
        if (res.status === 'success') { location.reload(); }
        else { alert(res.message || 'Error'); }
      }).catch(() => alert('Request failed.'));
});

document.querySelectorAll('.deactivate-kpi').forEach(b => b.addEventListener('click', function() {
    if (!confirm('Deactivate this KPI/KRA?')) return;
    fetch(`/kpi/${this.dataset.id}`, { method: 'DELETE', headers: {'X-CSRF-TOKEN':CSRF,'Accept':'application/json'} })
      .then(r => r.json()).then(() => location.reload());
}));

document.querySelectorAll('.evaluate-kpi').forEach(b => b.addEventListener('click', function() {
    document.getElementById('eval_id').value = this.dataset.id;
    document.getElementById('evalTitle').textContent = this.dataset.title;
    document.getElementById('eval_value').value = '';
    document.getElementById('eval_remarks').value = '';
    evalModal.show();
}));
document.getElementById('evalForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const id = document.getElementById('eval_id').value;
    fetch(`/kpi/${id}/evaluate`, { method: 'POST', headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'Accept':'application/json'},
        body: JSON.stringify({ achieved_value: document.getElementById('eval_value').value, remarks: document.getElementById('eval_remarks').value }) })
      .then(r => r.json()).then(res => {
        if (res.status === 'success') { alert('Score recorded: ' + res.score); location.reload(); }
        else { alert(res.message || 'Error'); }
      });
});
</script>
@endsection
