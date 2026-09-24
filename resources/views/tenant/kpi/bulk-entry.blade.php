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

    table.bk { width:100%; border-collapse:collapse; font-size:13px; }
    table.bk thead th { background:#0f2848; color:#fff; font-weight:600; font-size:11.5px; text-align:left;
        padding:9px 12px; position:sticky; top:0; z-index:2; }
    table.bk tbody td { border-bottom:1px solid #eef2f6; padding:7px 12px; vertical-align:middle; }
    table.bk tbody tr:hover { background:#f8fafc; }
    .bk-emp { font-weight:600; color:#1e3a5f; }
    .bk-dept { font-size:11.5px; color:#64748b; }
    .bk-val input { width:110px; text-align:center; font-weight:600; }
    .bk-note-in input { width:100%; }
    .bk-filled { background:#f0fdf4 !important; }

    .bk-empty { padding:36px; text-align:center; color:#94a3b8; }
    .owner-row td { font-size:12.5px; }
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
            <div class="alert alert-{{ $cls }} alert-dismissible" role="alert">
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

    {{-- Pick the KPI and the month --}}
    <div class="bk-card">
        <div class="bk-card-head">
            <h6>Choose what to fill</h6>
        </div>
        <div class="p-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small mb-1">KPI</label>
                    <select name="template" class="form-select form-select-sm" onchange="this.form.submit()">
                        @foreach($templates as $t)
                            <option value="{{ $t->id }}" {{ $template && $template->id == $t->id ? 'selected' : '' }}>
                                {{ $t->title }}@if($t->target_value) — target {{ rtrim(rtrim(number_format((float)$t->target_value, 2), '0'), '.') }}@endif
                            </option>
                        @endforeach
                    </select>
                </div>
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
                <div class="col-md-4 d-flex gap-2">
                    <a class="btn btn-sm btn-outline-success rounded-pill px-3"
                       href="{{ route('tenant.kpi.bulk-entry.template', ['template' => $template->id ?? 0, 'month' => $month->format('Y-m')]) }}">
                        <i class="bx bx-download me-1"></i> Excel
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3"
                            data-bs-toggle="modal" data-bs-target="#importModal">
                        <i class="bx bx-upload me-1"></i> Upload filled
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- The grid --}}
    <div class="bk-card">
        <div class="bk-card-head">
            <h6>{{ $template->title ?? 'KPI' }} — {{ $month->format('F Y') }}</h6>
            <span class="badge bg-label-secondary">{{ $rows->count() }} {{ \Illuminate\Support\Str::plural('person', $rows->count()) }}</span>
        </div>
        <div class="bk-note">
            Whatever you enter here counts immediately — <strong>no approval step</strong>. You are recorded as the
            source. Leave a box empty to clear a figure rather than storing a zero, so "not reported" stays
            distinguishable from "reported as none".
        </div>

        <form method="POST" action="{{ route('tenant.kpi.bulk-entry.store') }}">
            @csrf
            <input type="hidden" name="template" value="{{ $template->id ?? '' }}">
            <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">

            <div style="max-height:62vh; overflow:auto;">
                <table class="bk">
                    <thead>
                        <tr>
                            <th style="width:46px">#</th>
                            <th>Employee</th>
                            <th style="width:170px">Department</th>
                            <th style="width:140px">Value</th>
                            <th>Note (optional)</th>
                            <th style="width:110px">Current</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $i => $r)
                            <tr class="{{ $r->value !== null ? 'bk-filled' : '' }}">
                                <td class="text-muted">{{ $i + 1 }}</td>
                                <td>
                                    <div class="bk-emp">{{ trim($r->employee->first_name . ' ' . $r->employee->last_name) }}</div>
                                </td>
                                <td class="bk-dept">{{ $r->department }}</td>
                                <td class="bk-val">
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                           name="values[{{ $r->assignment->id }}]"
                                           value="{{ $r->value !== null ? rtrim(rtrim(number_format((float)$r->value, 2, '.', ''), '0'), '.') : '' }}"
                                           placeholder="—">
                                </td>
                                <td class="bk-note-in">
                                    <input type="text" class="form-control form-control-sm"
                                           name="notes[{{ $r->assignment->id }}]"
                                           value="{{ $r->note }}" placeholder="optional">
                                </td>
                                <td>
                                    @if($r->status)
                                        <span class="badge bg-label-{{ $r->status === 'approved' ? 'success' : ($r->status === 'rejected' ? 'danger' : 'warning') }}">
                                            {{ ucfirst($r->status) }}
                                        </span>
                                    @else
                                        <span class="text-muted small">not reported</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="bk-empty">Nobody is assigned this KPI.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($rows->isNotEmpty())
                <div class="p-3 border-top d-flex justify-content-end gap-2">
                    <button class="btn btn-hitech-primary rounded-pill px-4">
                        <i class="bx bx-save me-1"></i> Save all
                    </button>
                </div>
            @endif
        </form>
    </div>

    {{-- Who owns what --}}
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
                        @foreach($allManualTemplates as $t)
                            <option value="{{ $t->id }}">{{ $t->title }}</option>
                        @endforeach
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
                        @foreach($departments as $d)
                            <option value="{{ $d->id }}">{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-sm btn-hitech-primary w-100 rounded-pill">
                        <i class="bx bx-plus"></i> Add
                    </button>
                </div>
            </form>

            <table class="bk">
                <thead>
                    <tr><th>KPI</th><th>Owner</th><th>Scope</th><th style="width:90px"></th></tr>
                </thead>
                <tbody>
                    @forelse($owners as $o)
                        <tr class="owner-row">
                            <td>{{ $o->template->title ?? '—' }}</td>
                            <td class="bk-emp">{{ trim(($o->user->first_name ?? '') . ' ' . ($o->user->last_name ?? '')) }}</td>
                            <td>{{ $o->department->name ?? 'All departments' }}</td>
                            <td>
                                <form method="POST" action="{{ route('tenant.kpi.bulk-entry.owner.remove', $o->id) }}"
                                      onsubmit="return confirm('Remove this data owner?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger rounded-pill px-2">
                                        <i class="bx bx-trash"></i>
                                    </button>
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

{{-- Upload a filled sheet --}}
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST" enctype="multipart/form-data"
              action="{{ route('tenant.kpi.bulk-entry.import') }}">
            @csrf
            <input type="hidden" name="template" value="{{ $template->id ?? '' }}">
            <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
            <div class="modal-header">
                <h5 class="modal-title">Upload filled sheet</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">
                    Download the Excel for <strong>{{ $template->title ?? 'this KPI' }}</strong>,
                    fill the <strong>VALUE</strong> column and upload it back. The ID column is what rows are
                    matched on — don't edit it. Anything outside what you may fill is skipped and reported.
                </p>
                <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-hitech-primary"><i class="bx bx-upload me-1"></i> Import</button>
            </div>
        </form>
    </div>
</div>
@endsection
