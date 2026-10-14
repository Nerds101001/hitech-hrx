@extends('layouts.layoutMaster')
@section('title', 'Bulk KPI Entry')

@section('content')
<style>
    .bk-page { padding: 8px 4px 24px; }
    .bk-head { display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:18px; }
    .bk-title { font-size:1.25rem; font-weight:800; color:#1e3a5f; margin:0; flex:1; }

    .bk-card { background:#fff; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,.08); overflow:hidden; }
    .bk-card + .bk-card { margin-top:20px; }
    .bk-card-head { padding:14px 20px; border-bottom:1px solid #eef2f6; display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .bk-card-head h6 { margin:0; font-weight:700; color:#1e3a5f; flex:1; }

    .bk-note { font-size:12px; color:#64748b; padding:10px 20px; background:#f8fafc; border-bottom:1px solid #eef2f6; }
    .bk-note strong { color:#0f766e; }

    /* KPI chooser */
    .kpi-pick { display:flex; flex-wrap:wrap; gap:8px; }
    .kpi-chip { position:relative; }
    .kpi-chip input { position:absolute; opacity:0; pointer-events:none; }
    .kpi-chip span {
        display:inline-block; padding:7px 14px; border-radius:999px; cursor:pointer;
        border:1.5px solid #cbd5e1; background:#fff; font-size:12.5px; font-weight:600; color:#475569;
        transition:all .12s;
    }
    .kpi-chip input:checked + span { background:#0f766e; border-color:#0f766e; color:#fff; }
    .kpi-chip span:hover { border-color:#0f766e; }

    /* width:100% forced every KPI column to squeeze rather than scroll, which
       is what caused elements to run past the right edge once there were
       more than a couple of KPI columns selected -- min-content lets the
       table grow to its real width and the wrapper below scrolls it. */
    table.bk { width:100%; min-width:max-content; border-collapse:collapse; font-size:13px; }
    table.bk thead th { background:#0f2848; color:#fff; font-weight:600; font-size:11.5px; text-align:left;
        padding:9px 12px; position:sticky; top:0; z-index:2; white-space:nowrap; }
    table.bk thead th.kpi-col { text-align:center; background:#0f766e; }
    table.bk tbody td { border-bottom:1px solid #eef2f6; padding:6px 12px; vertical-align:middle; }
    table.bk tbody tr:hover { background:#f8fafc; }
    .bk-emp { font-weight:600; color:#1e3a5f; }
    .bk-dept { font-size:11.5px; color:#64748b; }
    .bk-cell { text-align:center; }
    .bk-cell input { width:96px; text-align:center; font-weight:600; }
    .bk-cell.filled input { background:#f0fdf4; border-color:#86efac; }
    .bk-na { color:#cbd5e1; font-size:11px; }
    .bk-empty { padding:36px; text-align:center; color:#94a3b8; }

    /* Employee column stays visible while scrolling through KPI columns,
       matching the sticky-column convention already used on /attendance's
       Monthly Registry table. */
    table.bk thead th:nth-child(2),
    table.bk tbody td:nth-child(2) {
        position: sticky; left: 0; z-index: 1; background: #fff;
    }
    table.bk thead th:nth-child(2) { z-index: 3; background: #0f2848; }

    @media (max-width: 480px) {
        /* The 33 reported tiny inputs were a fixed 96px width with the
           Bootstrap form-control-sm density baked in at every breakpoint --
           no min-height/font-size/padding override existed for touch. */
        .bk-cell input {
            min-height: 48px;
            font-size: 1rem;
            padding: 6px 8px;
        }
    }
</style>

<div class="bk-page">

    <div class="bk-head">
        <h1 class="bk-title"><i class="bx bx-spreadsheet me-2" style="color:#0f766e"></i>Bulk KPI Entry</h1>
        <a href="{{ route('tenant.kpi.my') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
            <i class="bx bx-user me-1"></i> My KPIs
        </a>
    </div>

    @foreach (['success' => 'success', 'warning' => 'warning', 'error' => 'danger'] as $key => $cls)
        @if(session($key))
            <div class="alert alert-{{ $cls }} alert-dismissible">
                {{ session($key) }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
    @endforeach

    @if($templates->isEmpty())
        <div class="bk-card"><div class="bk-empty">
            <i class="bx bx-lock-alt d-block mb-2" style="font-size:2rem"></i>
            You are not set as the data owner for any KPI.
        </div></div>
    @else

    {{-- Choose KPIs + month --}}
    <div class="bk-card">
        <div class="bk-card-head"><h6>Choose what to fill</h6></div>
        <div class="bk-note">
            Tick as many KPIs as you report together — the grid and the spreadsheet both get one column per KPI.
        </div>
        <div class="p-3">
            <form method="GET" id="pickForm">
                <div class="kpi-pick mb-3">
                    @foreach($templates as $t)
                        <label class="kpi-chip">
                            <input type="checkbox" name="templates[]" value="{{ $t->id }}"
                                   {{ in_array($t->id, $selectedIds) ? 'checked' : '' }}
                                   onchange="document.getElementById('pickForm').submit()">
                            <span>{{ $labels[$t->id] ?? $t->title }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Month</label>
                        <select name="month" class="form-select form-select-sm" onchange="this.form.submit()">
                            @foreach($months as $m)
                                <option value="{{ $m->format('Y-m') }}" {{ $month->format('Y-m') === $m->format('Y-m') ? 'selected' : '' }}>
                                    {{ $m->format('F Y') }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-9 d-flex gap-2">
                        <a class="btn btn-sm btn-outline-success rounded-pill px-3"
                           href="{{ route('tenant.kpi.bulk-entry.template', array_merge(['month' => $month->format('Y-m')], ['templates' => $selectedIds])) }}">
                            <i class="bx bx-download me-1"></i> Download Excel ({{ count($selectedIds) }} {{ \Illuminate\Support\Str::plural('KPI', count($selectedIds)) }})
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3"
                                data-bs-toggle="modal" data-bs-target="#importModal">
                            <i class="bx bx-upload me-1"></i> Upload filled
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- The matrix --}}
    <div class="bk-card">
        <div class="bk-card-head">
            <h6>{{ $month->format('F Y') }}</h6>
            <span class="badge bg-label-secondary">{{ $rows->count() }} {{ \Illuminate\Support\Str::plural('person', $rows->count()) }}</span>
        </div>
        <div class="bk-note">
            Whatever you enter counts immediately — <strong>no approval step</strong>. You are recorded as the source.
            Leave a box empty to clear a figure rather than storing a zero, so "not reported" stays distinguishable
            from "reported as none". A dash means that person does not carry that KPI.
        </div>

        <form method="POST" action="{{ route('tenant.kpi.bulk-entry.store') }}">
            @csrf
            <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
            @foreach($selectedIds as $tid)
                <input type="hidden" name="templates[]" value="{{ $tid }}">
            @endforeach

            <div style="max-height:62vh; overflow:auto;" class="table-responsive">
                <table class="bk">
                    <thead>
                        <tr>
                            <th style="width:44px">#</th>
                            <th>Employee</th>
                            <th style="width:160px">Department</th>
                            @foreach($selected as $t)
                                <th class="kpi-col" title="{{ $t->description }}">
                                    {{ $labels[$t->id] ?? $t->title }}
                                    @php
                                        $hint = array_filter([
                                            $t->target_value ? 'target ' . rtrim(rtrim(number_format((float) $t->target_value, 2), '0'), '.') : null,
                                            $t->trend === 'decrease' ? 'lower is better' : null,
                                        ]);
                                    @endphp
                                    @if($hint)
                                        <div style="font-weight:500;opacity:.8;font-size:10px">{{ implode(' · ', $hint) }}</div>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $i => $r)
                            <tr>
                                <td class="text-muted">{{ $i + 1 }}</td>
                                <td><div class="bk-emp">{{ $r->name }}</div></td>
                                <td class="bk-dept">{{ $r->department }}</td>
                                @foreach($selected as $t)
                                    @php $c = $r->cells[$t->id] ?? null; @endphp
                                    <td class="bk-cell {{ $c && $c->value !== null ? 'filled' : '' }}">
                                        @if($c && $c->assignment_id)
                                            <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                                   name="values[{{ $c->assignment_id }}]"
                                                   value="{{ $c->value !== null ? rtrim(rtrim(number_format((float)$c->value, 2, '.', ''), '0'), '.') : '' }}"
                                                   placeholder="—">
                                        @else
                                            <span class="bk-na">—</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ 3 + $selected->count() }}" class="bk-empty">
                                Nobody is assigned the {{ \Illuminate\Support\Str::plural('KPI', $selected->count()) }} you chose.
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($rows->isNotEmpty())
                <div class="p-3 border-top d-flex justify-content-end">
                    <button class="btn btn-hitech-primary rounded-pill px-4"><i class="bx bx-save me-1"></i> Save all</button>
                </div>
            @endif
        </form>
    </div>

    {{-- Ownership --}}
    @if($isKpiAdmin)
    <div class="bk-card">
        <div class="bk-card-head">
            <h6>Who may fill which KPI</h6>
            <span class="badge bg-label-secondary">{{ $owners->count() }} assigned</span>
        </div>
        <div class="bk-note">
            An owner fills that KPI for everyone, without approval. Leave the department blank to cover every team,
            or set it so one person owns Calls for New Biz while somebody else owns it for Sales.
        </div>
        <div class="p-3">
            <form method="POST" action="{{ route('tenant.kpi.bulk-entry.owner.add') }}" class="row g-2 align-items-end mb-3">
                @csrf
                <div class="col-md-4">
                    <label class="form-label small mb-1">KPI</label>
                    <select name="kpi_template_id" class="form-select form-select-sm" required>
                        @foreach($templates as $t)<option value="{{ $t->id }}">{{ $labels[$t->id] ?? $t->title }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Owner</label>
                    <select name="user_id" class="form-select form-select-sm" required>
                        @foreach($ownerCandidates as $c)
                            <option value="{{ $c->id }}">{{ trim($c->first_name . ' ' . $c->last_name) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Department (optional)</label>
                    <select name="department_id" class="form-select form-select-sm">
                        <option value="">All departments</option>
                        @foreach($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-sm btn-hitech-primary w-100 rounded-pill"><i class="bx bx-plus"></i> Add</button>
                </div>
            </form>

            <table class="bk">
                <thead><tr><th>KPI</th><th>Owner</th><th>Scope</th><th style="width:80px"></th></tr></thead>
                <tbody>
                    @forelse($owners as $o)
                        <tr>
                            <td>{{ $o->template ? ($labels[$o->template->id] ?? $o->template->title) : '—' }}</td>
                            <td class="bk-emp">{{ trim(($o->user->first_name ?? '') . ' ' . ($o->user->last_name ?? '')) }}</td>
                            <td>{{ $o->department->name ?? 'All departments' }}</td>
                            <td>
                                <form method="POST" action="{{ route('tenant.kpi.bulk-entry.owner.remove', $o->id) }}"
                                      onsubmit="return confirm('Remove this data owner?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger rounded-pill px-2"><i class="bx bx-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="bk-empty">Nobody is assigned yet — everything still relies on each person reporting their own.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @endif
</div>

<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST" enctype="multipart/form-data"
              action="{{ route('tenant.kpi.bulk-entry.import') }}">
            @csrf
            <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
            @foreach($selectedIds as $tid)<input type="hidden" name="templates[]" value="{{ $tid }}">@endforeach
            <div class="modal-header">
                <h5 class="modal-title">Upload filled sheet</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2">
                    You'll see a preview of every change before anything is saved.
                </p>
                <p class="text-muted small">
                    Columns are matched by their <strong>header text</strong> and rows by the <strong>User ID</strong>
                    in column A — so don't edit those. Anything outside what you may fill is listed and skipped.
                </p>
                <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-hitech-primary"><i class="bx bx-search-alt me-1"></i> Preview</button>
            </div>
        </form>
    </div>
</div>
@endsection
