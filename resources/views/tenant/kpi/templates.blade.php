@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')

@section('title', 'KPI / KRA Templates')

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">

  {{-- Header — same shape as the Employee Directory. --}}
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6 mx-4">
    <div>
      <h3 class="fw-extrabold mb-1 text-dark">KPI / KRA Templates</h3>
      <p class="text-muted mb-0 small">
        Define a scorecard once for a department — every active employee in it receives the KPI.
        A <strong>KRA</strong> is a heading that groups KPIs and carries no marks of its own.
      </p>
    </div>
    <div class="d-flex gap-3 flex-wrap">
      <a href="{{ route('tenant.kpi.manage') }}" class="btn btn-hitech-cancel shadow-sm rounded-pill px-4 d-flex align-items-center gap-2">
        <i class="bx bx-user-detail fs-5"></i><span class="fw-bold">Per-employee view</span>
      </a>
      <button type="button" id="btnNewKra" class="btn btn-hitech-cancel shadow-sm rounded-pill px-4 d-flex align-items-center gap-2">
        <i class="bx bx-folder-plus fs-5"></i><span class="fw-bold">New KRA</span>
      </button>
      <button type="button" id="btnNewKpi" class="btn-hitech shadow-sm rounded-pill px-5 d-flex align-items-center gap-2">
        <i class="bx bx-plus-circle fs-5"></i><span class="fw-bold">New KPI</span>
      </button>
    </div>
  </div>

  @php
    $allKrasTop = $templates->where('category', 'KRA');
    $allKpisTop = $templates->where('category', '!=', 'KRA');
    $deptsCovered = $templates->pluck('department_id')->unique()->filter()->count();
    // Trim only the zeros AFTER a decimal point. Stripping them off the raw
    // string turned 150 into 15 and 100 into 1 wherever a sum came back as a
    // float rather than the DB's "150.00".
    $num = fn($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
  @endphp

  {{-- Stats bar, matching the directory's cards. --}}
  <div class="row g-6 mb-6 mx-0 px-4">
    <div class="col-xl-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay:.05s">
      <div class="hitech-stat-card dashboard-variant card-teal uniform-card">
        <div class="stat-card-header mb-2">
          <div class="stat-icon-wrap icon-teal" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-target-lock"></i></div>
        </div>
        <div><h3 class="stat-value mb-0">{{ $allKpisTop->count() }}</h3><span class="stat-label">Scored KPIs</span></div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay:.1s">
      <div class="hitech-stat-card dashboard-variant card-blue uniform-card">
        <div class="stat-card-header mb-2">
          <div class="stat-icon-wrap icon-blue" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-folder"></i></div>
        </div>
        <div><h3 class="stat-value mb-0">{{ $allKrasTop->count() }}</h3><span class="stat-label">KRA headings</span></div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay:.15s">
      <div class="hitech-stat-card dashboard-variant card-amber uniform-card">
        <div class="stat-card-header mb-2">
          <div class="stat-icon-wrap icon-amber" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-buildings"></i></div>
        </div>
        <div><h3 class="stat-value mb-0">{{ $deptsCovered }}</h3><span class="stat-label">Departments covered</span></div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6 animate__animated animate__fadeInUp" style="animation-delay:.2s">
      <div class="hitech-stat-card dashboard-variant card-red uniform-card">
        <div class="stat-card-header mb-2">
          <div class="stat-icon-wrap icon-red" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-medal"></i></div>
        </div>
        <div><h3 class="stat-value mb-0">{{ $num($allKpisTop->sum('max_marks')) }}</h3><span class="stat-label">Marks defined</span></div>
      </div>
    </div>
  </div>

  <div class="px-4" id="filter-section">
    <div class="hitech-card-white mb-6 overflow-hidden">
      <div class="card-body p-sm-5 p-4">
        <div class="row align-items-end g-4">
          <div class="col-lg-4">
            <label class="form-label fw-bold small text-muted text-uppercase mb-2">Search</label>
            <div class="search-wrapper-hitech mw-100">
              <i class="bx bx-search text-muted ms-3"></i>
              <input type="text" class="form-control" placeholder="Search KPI or KRA..." id="tplSearch">
              <button class="btn-search shadow-sm" type="button" id="tplSearchBtn">
                <i class="bx bx-search fs-5"></i><span>Search</span>
              </button>
            </div>
          </div>
          <div class="col-lg-2 col-md-4">
            <label class="form-label fw-bold small text-muted text-uppercase mb-2">Department</label>
            <select class="form-select" id="fDept">
              <option value="">All Departments</option>
              <option value="__none__">Company-wide</option>
              @foreach($departments as $d)
                <option value="{{ $d->id }}" {{ (string)$departmentId === (string)$d->id ? 'selected' : '' }}>{{ $d->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-lg-2 col-md-4">
            <label class="form-label fw-bold small text-muted text-uppercase mb-2">Interval</label>
            <select class="form-select" id="fFreq">
              <option value="">All Intervals</option>
              @foreach(['daily','weekly','fortnightly','monthly','quarterly','yearly'] as $f)
                <option value="{{ $f }}">{{ ucfirst($f) }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-lg-2 col-md-4">
            <label class="form-label fw-bold small text-muted text-uppercase mb-2">Scoring</label>
            <select class="form-select" id="fScoring">
              <option value="">All Scoring</option>
              @foreach(['target','threshold','banded','timeline'] as $sc)
                <option value="{{ $sc }}">{{ ucfirst($sc) }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-lg-2 d-flex align-items-center gap-3">
            <button class="btn btn-white shadow-sm rounded-pill px-4 border fw-semibold" type="button" id="tplClear">
              <i class="bx bx-reset me-1"></i>Clear
            </button>
            <span class="text-muted fw-semibold small text-nowrap" id="tplShowing"></span>
          </div>
        </div>
      </div>
    </div>

@php
  // Split the flat list into headings and scored KPIs, then group each by
  // department so the page reads department-by-department instead of one long
  // mixed table where a KRA and a KPI looked the same.
  $allKras = $allKrasTop;
  $allKpis = $allKpisTop;
  $kraById = $allKras->keyBy('id');
  $deptName = fn($t) => $t->department->name ?? 'All departments';
  $kpisByDept = $allKpis->groupBy($deptName);
  $krasByDept = $allKras->groupBy($deptName);
  $marksOf = fn($t) => $num($t->max_marks);
@endphp

<ul class="nav nav-pills hitech-tabs-sm mb-4" id="tplTabs">
  <li class="nav-item">
    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabKpis" type="button">
      <i class="bx bx-target-lock"></i> KPIs <span class="badge badge-brand-soft badge-hitech ms-1">{{ $allKpis->count() }}</span>
    </button>
  </li>
  <li class="nav-item">
    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabKras" type="button">
      <i class="bx bx-folder"></i> KRAs <span class="badge badge-muted badge-hitech ms-1">{{ $allKras->count() }}</span>
    </button>
  </li>
</ul>

<div class="tab-content p-0 border-0">

  {{-- ===================== KPIs ===================== --}}
  <div class="tab-pane fade show active" id="tabKpis">
    <div class="hitech-card">
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
              <tr class="table-secondary tpl-group" data-group-dept="">
                <td colspan="8" class="fw-semibold py-2">
                  <i class="bx bx-buildings me-1"></i>{{ $dept }}
                  <span class="text-muted fw-normal small ms-2">
                    {{ $rows->count() }} KPI{{ $rows->count() === 1 ? '' : 's' }} ·
                    {{ $num($rows->sum('max_marks')) }} marks total
                  </span>
                </td>
              </tr>
              @foreach($rows as $t)
                <tr class="tpl-row"
                    data-title="{{ strtolower($t->title . ' ' . $t->description) }}"
                    data-dept="{{ $t->department_id ?: '__none__' }}"
                    data-freq="{{ $t->frequency }}"
                    data-scoring="{{ $t->scoring_type }}"
                    data-kra="{{ $t->parent_template_id ?: '__none__' }}"
                    data-marks="{{ (float) $t->max_marks }}"
                    data-late="{{ $t->late_policy }}"
                    data-assigned="{{ optional($assignmentCounts[$t->id] ?? null)->active > 0 ? 'yes' : 'no' }}">
                  <td>
                    <div class="fw-medium">{{ $t->title }}</div>
                    @if($t->description)<small class="text-muted">{{ $t->description }}</small>@endif
                  </td>
                  <td>
                    @if($t->parent_template_id && $kraById->has($t->parent_template_id))
                      <span class="badge badge-hitech badge-brand-soft">{{ $kraById[$t->parent_template_id]->title }}</span>
                    @elseif($t->parent_template_id)
                      <span class="badge badge-hitech badge-hitech-danger" title="The KRA this points at no longer exists">orphaned</span>
                    @else
                      <span class="text-muted">— ungrouped —</span>
                    @endif
                  </td>
                  <td>
                    <span class="badge badge-hitech badge-muted">{{ $t->scoring_type }}</span>
                    @if($t->target_value !== null && ($t->target_value > 0 || ($t->trend ?? 'increase') === 'decrease'))<small class="text-muted d-block">target {{ $num($t->target_value) }}</small>@endif
                    @if(($t->trend ?? 'increase') === 'decrease')<small class="text-muted d-block">lower is better</small>@endif
                    @if(!empty($t->designation_ids))<small class="text-muted d-block">{{ count($t->designation_ids) }} designation{{ count($t->designation_ids) === 1 ? '' : 's' }} only</small>@endif
                    @if($t->result_cap_percent !== null)<small class="text-muted d-block">counts up to {{ $num($t->result_cap_percent) }}%</small>@endif
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
                    @if($c)<span class="badge badge-hitech badge-hitech-success">{{ $c->active }}</span>
                    @else<span class="text-muted">—</span>@endif
                  </td>
                  <td class="text-end text-nowrap">
                    <button class="btn btn-sm btn-icon icon-sophisticated text-success tpl-apply" data-id="{{ $t->id }}"
                            title="Apply to everyone in this department"><i class="bx bx-user-plus"></i></button>
                    <button class="btn btn-sm btn-icon icon-sophisticated tpl-edit" data-json="{{ json_encode($t) }}" title="Edit"><i class="bx bx-edit"></i></button>
                    <button class="btn btn-sm btn-icon icon-sophisticated text-danger tpl-retire" data-id="{{ $t->id }}" title="Retire"><i class="bx bx-x-circle"></i></button>
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
    <div class="hitech-card">
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
              <tr class="table-secondary tpl-group" data-group-dept="">
                <td colspan="5" class="fw-semibold py-2"><i class="bx bx-buildings me-1"></i>{{ $dept }}</td>
              </tr>
              @foreach($rows as $t)
                @php $children = $allKpis->where('parent_template_id', $t->id); @endphp
                <tr class="tpl-row"
                    data-title="{{ strtolower($t->title . ' ' . $t->description) }}"
                    data-dept="{{ $t->department_id ?: '__none__' }}"
                    data-freq="{{ $t->frequency }}"
                    data-scoring="" data-kra="" data-marks="" data-late="" data-assigned="">
                  <td>
                    <span class="badge badge-hitech badge-muted me-1">KRA</span>
                    <span class="fw-medium">{{ $t->title }}</span>
                    @if($t->description)<small class="text-muted d-block">{{ $t->description }}</small>@endif
                  </td>
                  <td>{{ ucfirst($t->frequency) }}</td>
                  <td class="text-center">
                    @if($children->count())<span class="badge badge-hitech badge-brand-soft">{{ $children->count() }}</span>
                    @else<span class="badge badge-hitech badge-muted" title="Nothing sits under this heading yet">empty</span>@endif
                  </td>
                  <td class="text-end fw-semibold">{{ $num($children->sum('max_marks')) }}</td>
                  <td class="text-end text-nowrap">
                    <button class="btn btn-sm btn-icon icon-sophisticated tpl-edit" data-json="{{ json_encode($t) }}" title="Edit"><i class="bx bx-edit"></i></button>
                    <button class="btn btn-sm btn-icon icon-sophisticated text-danger tpl-retire" data-id="{{ $t->id }}" title="Retire"><i class="bx bx-x-circle"></i></button>
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
  </div>{{-- /px-4 --}}
</div>{{-- /layout-full-width --}}

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
            <div class="col-12 kpi-only">
              <label class="form-label">Only these designations <span class="text-muted fw-normal">(optional)</span></label>
              <select id="tpl_designation_ids" class="form-select" multiple size="5">
                @foreach($designations as $des)<option value="{{ $des->id }}">{{ $des->name }}</option>@endforeach
              </select>
              <div class="form-text">
                Leave empty and everyone in the department gets it. Pick some (Ctrl/Cmd-click for several)
                and only people holding those designations do — including anyone later promoted into one.
              </div>
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
              <div class="form-text">
                What 100% looks like. Score compares achieved against this.
                This seeds each person's target when they first get the KPI — change
                it per person in the Per-employee view, and editing this will not
                overwrite what you set there.
              </div>
            </div>
            <div class="col-md-6 needs-target">
              <label class="form-label">Better when</label>
              <select id="tpl_trend" class="form-select">
                <option value="increase">Higher is better — achieved ÷ target</option>
                <option value="decrease">Lower is better — target ÷ achieved</option>
              </select>
              <div class="form-text">
                Use “lower is better” for waste, mandays or complaints. With a target of 0,
                zero achieved scores 100% and anything above it scores 0%.
              </div>
            </div>
            <div class="col-md-6 needs-target">
              <label class="form-label">Highest result that counts (%)</label>
              <input type="number" step="1" min="100" max="1000" id="tpl_result_cap_percent" class="form-control" placeholder="Blank = stop at 100%">
              <div class="form-text">
                Leave blank and the KPI can never score above its marks. Enter e.g. 200 to let
                over-achievement earn more than the marks, up to 200% of target.
              </div>
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
                <optgroup label="Someone types it in">
                  <option value="approval">Manual entry (needs HR approval)</option>
                </optgroup>
                <optgroup label="Worked out from live data">
                  @foreach($autoSources as $key => $label)
                    <option value="{{ $key }}" title="{{ $label }}">{{ $label }}</option>
                  @endforeach
                </optgroup>
              </select>
              <div class="form-text" id="tpl_source_help"></div>
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

  // Belt-and-suspenders: force the scrollable-modal layout with inline styles too, not just
  // the .modal-dialog-scrollable CSS class. Plain `el.style.x = ...` loses to any stylesheet
  // !important rule — style.setProperty(prop, value, 'important') is what actually wins that
  // fight. The REAL bug, found by inspecting computed styles directly: .modal-header/.modal-body/
  // .modal-footer here are wrapped in a <form>, a plain block box sitting between them and the
  // flex .modal-content — so .modal-body's flex:1 1 auto had no flex container to size against
  // and just rendered at full content height, silently clipped by .modal-content's overflow:
  // hidden instead of ever scrolling. The <form> needs the same flex treatment relayed through it.
  function forceScrollableModal() {
    const content = this.querySelector('.modal-content');
    const form = content ? content.querySelector(':scope > form') : null;
    const body = this.querySelector('.modal-body');
    if (content) {
      content.style.setProperty('max-height', 'calc(100vh - 3.5rem)', 'important');
      content.style.setProperty('display', 'flex', 'important');
      content.style.setProperty('flex-direction', 'column', 'important');
    }
    if (form) {
      form.style.setProperty('display', 'flex', 'important');
      form.style.setProperty('flex-direction', 'column', 'important');
      form.style.setProperty('flex', '1 1 auto', 'important');
      form.style.setProperty('min-height', '0', 'important');
      form.style.setProperty('overflow', 'hidden', 'important');
    }
    if (body) {
      body.style.setProperty('overflow-y', 'auto', 'important');
      body.style.setProperty('flex', '1 1 auto', 'important');
      body.style.setProperty('min-height', '0', 'important');
    }
  }
  $('tplModal').addEventListener('show.bs.modal', forceScrollableModal);
  $('tplModal').addEventListener('shown.bs.modal', forceScrollableModal);

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
  // What the chosen source actually measures, straight from the service.
  const SOURCE_HELP = @json($autoSources);
  function sourceHelp() {
    const v = $('tpl_data_source').value;
    const el = $('tpl_source_help');
    if (!el) return;
    el.textContent = v === 'approval' ? 'The person enters it, then HR approves before it scores.'
      : (SOURCE_HELP[v] || '');
  }
  $('tpl_data_source').addEventListener('change', sourceHelp);

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
    const wanted = (t?.designation_ids || []).map(Number);
    Array.from($('tpl_designation_ids').options).forEach(o => { o.selected = wanted.includes(Number(o.value)); });
    $('tpl_frequency').value          = t?.frequency ?? 'monthly';
    $('tpl_scoring_type').value       = t?.scoring_type ?? 'target';
    set('tpl_max_marks', t?.max_marks, 10);
    set('tpl_target_value', t?.target_value);
    set('tpl_threshold_percent', t?.threshold_percent);
    $('tpl_trend').value              = t?.trend ?? 'increase';
    set('tpl_result_cap_percent', t?.result_cap_percent);
    $('tpl_late_policy').value        = t?.late_policy ?? 'fixed';
    set('tpl_late_score', t?.late_score, 0);
    set('tpl_late_percent', t?.late_percent);
    set('tpl_grace_days', t?.grace_days, 0);
    set('tpl_per_day_penalty', t?.per_day_penalty);
    $('tpl_data_source').value        = t?.data_source ?? 'approval';
    set('tpl_approver_id', t?.approver_id);

    let bands = t?.bands;
    if (typeof bands === 'string') { try { bands = JSON.parse(bands); } catch (e) { bands = null; } }
    (bands || []).forEach(b => $('bandRows').appendChild(bandRow(b.min, b.marks)));

    toggleFields();
    sourceHelp();
    modal().show();
  }

  // ---- Filtering -------------------------------------------------------
  // Everything is already on the page, so filtering is done here rather than
  // by reloading. A department heading hides itself once all its rows are out.
  const FILTERS = ['fDept', 'fFreq', 'fScoring'];

  function applyFilters() {
    const q       = ($('tplSearch').value || '').trim().toLowerCase();
    const dept    = $('fDept').value;
    const freq    = $('fFreq').value;
    const scoring = $('fScoring').value;

    let shown = 0, total = 0;
    document.querySelectorAll('.tpl-row').forEach(function (row) {
      const d = row.dataset;
      const isKra = d.marks === '';          // KRA rows carry no scored fields
      total++;

      let ok = true;
      if (q && !(d.title || '').includes(q)) ok = false;
      if (ok && dept && d.dept !== dept) ok = false;
      if (ok && freq && d.freq !== freq) ok = false;
      // Scoring only describes a KPI, so filtering by it hides KRA headings
      // rather than pretending a heading matches a scored condition.
      if (ok && scoring) ok = !isKra && d.scoring === scoring;

      row.classList.toggle('d-none', !ok);
      if (ok) shown++;
    });

    // Hide a department heading when nothing under it survived.
    document.querySelectorAll('.tpl-group').forEach(function (head) {
      let any = false;
      for (let r = head.nextElementSibling; r && !r.classList.contains('tpl-group'); r = r.nextElementSibling) {
        if (r.classList.contains('tpl-row') && !r.classList.contains('d-none')) { any = true; break; }
      }
      head.classList.toggle('d-none', !any);
    });

    // "No matches" line per tab.
    document.querySelectorAll('.tab-pane').forEach(function (pane) {
      const body = pane.querySelector('tbody');
      if (!body) return;
      const visible = body.querySelectorAll('.tpl-row:not(.d-none)').length;
      let msg = body.querySelector('.tpl-nomatch');
      if (!visible && !msg) {
        const cols = pane.querySelectorAll('thead th').length || 8;
        const tr = document.createElement('tr');
        tr.className = 'tpl-nomatch';
        tr.innerHTML = '<td colspan="' + cols + '" class="text-center text-muted py-5">Nothing matches these filters.</td>';
        body.appendChild(tr);
      } else if (visible && msg) {
        msg.remove();
      }
    });

    $('tplShowing').textContent = shown + ' of ' + total + ' shown';
  }

  FILTERS.forEach(id => $(id).addEventListener('change', applyFilters));
  $('tplSearch').addEventListener('input', applyFilters);
  $('tplSearchBtn').addEventListener('click', applyFilters);
  $('tplClear').addEventListener('click', function () {
    FILTERS.forEach(id => $(id).value = '');
    $('tplSearch').value = '';
    applyFilters();
  });
  applyFilters();

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
      designation_ids: Array.from($('tpl_designation_ids').selectedOptions).map(o => parseInt(o.value, 10)),
      frequency: $('tpl_frequency').value,
      scoring_type: $('tpl_scoring_type').value,
      max_marks: numOrNull('tpl_max_marks') ?? 0,
      target_value: numOrNull('tpl_target_value'),
      threshold_percent: numOrNull('tpl_threshold_percent'),
      trend: $('tpl_trend').value,
      result_cap_percent: numOrNull('tpl_result_cap_percent'),
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
