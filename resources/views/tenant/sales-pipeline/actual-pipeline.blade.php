@extends('layouts/layoutMaster')

@section('content')
@php
    $user         = auth()->user();
    $isAdmin      = $user->hasRole(['admin', 'Admin']);
    $isSalesperson = !$isAdmin && !$isCcare && !$isNewBiz;
    $statuses = [
        'Sample','Quotation','PO AWAITED','Prospect','Introduction','Negotiation',
        'Order','Repeat Order','Dropped','Sales to Visit','TRIAL ONGOING',
        'NEED CONTACT NO.','UNDER DISCUSSION','OUR RATE NOT MATCHED',
        'NEED CUSTOMER APPROVAL','PRICE NOT MATCHED',
        'Divert to dealer due to less quantity','Trying to Upgrade','Upgraded new product',
    ];
    $statusColors = [
        'Order'=>'#059669','Repeat Order'=>'#059669','Upgraded new product'=>'#059669',
        'Dropped'=>'#dc2626','OUR RATE NOT MATCHED'=>'#dc2626','PRICE NOT MATCHED'=>'#dc2626',
        'Divert to dealer due to less quantity'=>'#dc2626',
        'Negotiation'=>'#ea580c','PO AWAITED'=>'#ea580c','UNDER DISCUSSION'=>'#ea580c',
        'NEED CUSTOMER APPROVAL'=>'#ea580c',
        'Sample'=>'#2563eb','Quotation'=>'#2563eb','Prospect'=>'#2563eb',
        'Introduction'=>'#2563eb','TRIAL ONGOING'=>'#2563eb',
        'Sales to Visit'=>'#7c3aed','NEED CONTACT NO.'=>'#7c3aed','Trying to Upgrade'=>'#7c3aed',
    ];

    $currentFyActualTotal = array_sum($monthlyActuals ?? []);
    $fyParts = explode('-', $selectedFy ?? '');
    $selectedFyLabel = (count($fyParts) === 2)
        ? substr($fyParts[0], -2) . '-' . substr($fyParts[1], -2)
        : ($selectedFy ?? 'FY');
    $totalPotential = $pipelines->sum('total_business_potential');
    $potentialPerMonth = $totalPotential;
@endphp
<div class="container-fluid px-4 py-4">

    {{-- ─── HEADER ────────────────────────────────────────────────────── --}}
    <div class="hitech-page-hero mb-4">
        <div class="d-flex justify-content-between align-items-center w-100">
            <div>
                <h4 class="greeting text-white mb-1">Sales Pipeline</h4>
                <p class="text-white-50 mb-0">Track and manage your active sales pipeline</p>
            </div>
            <div class="d-flex gap-2">
                <form action="{{ route('sales-visits.actual-pipeline.index') }}" method="GET" class="d-flex gap-2">
                    @php $startYear = 2023; $endYear = (int)date('Y') + 1; @endphp
                    <select name="fy" class="form-select border-white text-white fw-bold"
                            style="background-color:rgba(255,255,255,0.2);" onchange="this.form.submit()">
                        @for($i = $startYear; $i <= $endYear; $i++)
                            <option value="{{ $i }}-{{ $i+1 }}" style="color:#333;background:#fff;"
                                {{ $selectedFy == ($i.'-'.($i+1)) ? 'selected' : '' }}>
                                FY {{ $i }}-{{ substr($i+1,-2) }}
                            </option>
                        @endfor
                    </select>
                </form>
                @if($isCcare || $isAdmin || $isNewBiz)
                    <a href="{{ route('sales-visits.pipeline.import') }}" class="btn btn-sm btn-outline-light rounded-pill fw-bold shadow-sm d-flex align-items-center me-2" title="Import Sales & Forecast Data">
                        <i class="bx bx-upload me-1"></i> Import Sales Report (Sale & Forecast)
                    </a>
                    <a href="{{ route('sales-visits.sales-targets.import') }}" class="btn btn-sm btn-warning text-dark rounded-pill fw-bold shadow-sm d-flex align-items-center" title="Import Kingo & Bingo Targets">
                        <i class="bx bx-upload me-1"></i> Import Sales Targets (Kingo & Bingo)
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- ─── COMPACT STATS STRIP ────────────────────────────────────────── --}}
    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach(['22_23'=>'22-23','23_24'=>'23-24','24_25'=>'24-25','25_26'=>'25-26'] as $key => $label)
            <div class="stat-chip">
                <span class="stat-label">YoY {{ $label }}</span>
                <span class="stat-value" id="cardYoy{{ $key }}">₹{{ number_format($totals[$key] ?? 0, 2) }}<em>L</em></span>
            </div>
        @endforeach
        <div class="stat-chip" style="border-left-color:#10b981;">
            <span class="stat-label">YoY {{ $selectedFyLabel }}</span>
            <span class="stat-value" id="fyTotalCard">₹{{ number_format($currentFyActualTotal, 2) }}<em>L</em></span>
        </div>
        <div class="stat-chip" style="border-left-color:#f59e0b;">
            <span class="stat-label">Potential/Month</span>
            <span class="stat-value" id="potentialMonthCard">₹{{ number_format($potentialPerMonth, 2) }}<em>L</em></span>
        </div>
        <div class="stat-chip" style="border-left-color:#3b82f6;">
            <span class="stat-label">Total Customers</span>
            <span class="stat-value text-primary" id="totalCustomersCard">{{ $pipelines->count() }}</span>
        </div>
    </div>

    {{-- ─── FILTER BAR ─────────────────────────────────────────────────── --}}
    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body py-2 px-3">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <span class="fw-bold text-muted" style="font-size:0.8rem">FILTER:</span>
                <input type="text" class="form-control form-control-sm" id="filterParty"
                       placeholder="Search customer…" style="width:200px;" oninput="applyFilters()">
                <select class="form-select form-select-sm" id="filterStage" style="width:170px;"
                        onchange="applyFilters()">
                    <option value="">All Stages</option>
                    @foreach($statuses as $st)
                        <option value="{{ strtolower($st) }}">{{ $st }}</option>
                    @endforeach
                </select>
                <select class="form-select form-select-sm" id="filterRep" style="width:210px;"
                        onchange="applyFilters()">
                    <option value="">
                        @if($isCcare || $isNewBiz) All Salespersons
                        @else All Representatives @endif
                    </option>
                    @foreach($assignedUsers as $au)
                        <option value="{{ strtolower($au->name) }}">{{ $au->name }}</option>
                    @endforeach
                </select>
                <button class="btn btn-sm btn-outline-secondary" onclick="clearFilters()">
                    <i class="bx bx-x"></i> Clear
                </button>
                <span class="text-muted ms-auto" style="font-size:0.8rem">
                    Showing: <strong id="filterCount">{{ $pipelines->count() }}</strong>
                </span>
            </div>
        </div>
    </div>

    {{-- ─── TABLE ──────────────────────────────────────────────────────── --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 70vh;">
                <table class="table table-bordered table-sm align-middle m-0"
                       id="pipelineTable" style="white-space:nowrap;font-size:0.85rem;">
                    <thead class="position-sticky top-0 text-white"
                           style="z-index:40;background-color:#0f766e;">
                        <tr>
                            <th class="text-center align-middle">S.No.</th>
                            <th class="align-middle">Start Date</th>
                            <th class="align-middle">Customer Name</th>
                            <th class="align-middle">Product</th>
                            @if($isAdmin)
                                <th class="align-middle">Salesperson</th>
                                <th class="align-middle">New Biz</th>
                                <th class="align-middle">Cust. Care</th>
                            @elseif($isSalesperson)
                                <th class="align-middle" title="CCare name if type=CCare · New Biz name if type=New Biz">Representative</th>
                            @else
                                {{-- CCare / New Biz see the salesperson --}}
                                <th class="align-middle">Salesperson</th>
                            @endif
                            <th class="text-end align-middle">Potential (L) / Month</th>
                            <th class="align-middle">Stage</th>
                            <th class="align-middle" style="min-width:250px;">Status Remarks</th>
                        </tr>
                    </thead>
                    <tbody id="pipelineBody">
                        @forelse($pipelines as $index => $row)
                            @php
                                // Determine single rep column value for salesperson view
                                if ($isSalesperson) {
                                    if ($row->type === 'CCare')     { $repDisplay = $row->ccare->name   ?? '-'; }
                                    elseif ($row->type === 'New Biz') { $repDisplay = $row->newBiz->name ?? '-'; }
                                    else                             { $repDisplay = '-'; }
                                } elseif ($isCcare || $isNewBiz) {
                                    $repDisplay = $row->salesperson->name ?? '-';
                                } else {
                                    $repDisplay = '';
                                }
                                // data-rep for filter
                                $repStr = $isAdmin
                                    ? implode(' | ', array_filter([
                                        $row->salesperson->name ?? '',
                                        $row->newBiz->name      ?? '',
                                        $row->ccare->name       ?? '',
                                      ]))
                                    : $repDisplay;
                                $rowMonthSales = $row->months->pluck('sale_amount', 'month_year')->toArray();
                            @endphp
                            <tr data-id="{{ $row->id }}"
                                data-party="{{ strtolower($row->party_name) }}"
                                data-stage="{{ strtolower($row->status_stage ?? '') }}"
                                data-rep="{{ strtolower($repStr) }}"
                                data-yoy22="{{ $row->yoy_22_23 ?? 0 }}"
                                data-yoy23="{{ $row->yoy_23_24 ?? 0 }}"
                                data-yoy24="{{ $row->yoy_24_25 ?? 0 }}"
                                data-yoy25="{{ $row->yoy_25_26 ?? 0 }}"
                                data-potential="{{ $row->total_business_potential ?? 0 }}"
                                data-months='@json($rowMonthSales)'
                                class="{{ $row->converted_from_newbiz ? 'converted-row' : '' }}">

                                <td class="text-center">{{ $index + 1 }}</td>
                                <td class="text-muted">{{ $row->created_at->format('d M Y') }}</td>
                                <td class="col-party fw-semibold text-dark text-truncate"
                                    title="{{ $row->party_name }}">{{ $row->party_name }}</td>
                                <td class="align-middle">{{ $row->product }}</td>

                                @if($isAdmin)
                                    <td class="align-middle">{{ $row->salesperson->name ?? '-' }}</td>
                                    <td class="align-middle">{{ $row->newBiz->name ?? '-' }}</td>
                                    <td class="align-middle">{{ $row->ccare->name ?? '-' }}</td>
                                @elseif($isSalesperson)
                                    <td class="align-middle">{{ $repDisplay }}</td>
                                @else
                                    <td class="align-middle">{{ $row->salesperson->name ?? '-' }}</td>
                                @endif

                                <td class="text-end align-middle fw-bold font-monospace" style="color:#0f766e;">
                                    {{ number_format($row->total_business_potential ?? 0, 2) }}
                                </td>

                                <td class="p-0 align-middle">
                                    <select class="excel-select row-field status-select text-truncate"
                                            data-field="status_stage"
                                            style="color:{{ $statusColors[$row->status_stage] ?? '#0d9488' }};"
                                            onchange="this.style.color=this.options[this.selectedIndex].style.color;">
                                        <option value="">-Select-</option>
                                        @foreach($statuses as $st)
                                            <option value="{{ $st }}"
                                                    style="color:{{ $statusColors[$st] ?? '#0d9488' }};font-weight:600;"
                                                    {{ $row->status_stage == $st ? 'selected' : '' }}>{{ $st }}</option>
                                        @endforeach
                                    </select>
                                </td>

                                <td class="p-0">
                                    <input type="text" class="excel-input row-field"
                                           data-field="status_remarks"
                                           value="{{ $row->status_remarks }}"
                                           placeholder="Type status remarks…">
                                </td>
                            </tr>
                        @empty
                            <tr id="emptyRow">
                                <td colspan="20" class="text-center py-4 text-muted">
                                    No pipeline data found. Click "+ Add New Entry" below to start.
                                </td>
                            </tr>
                        @endforelse

                        {{-- ── Inline Add New Row (hidden) ── --}}
                        <tr id="addNewRow" style="display:none;background:#f0fdf4;">
                            <td class="text-center text-success fw-bold">+</td>
                            <td class="text-muted small align-middle">Today</td>
                            <td class="p-1">
                                <input type="text" id="new_party_name"
                                       class="form-control form-control-sm" placeholder="Customer Name *">
                            </td>
                            <td class="p-1">
                                <input type="text" id="new_product"
                                       class="form-control form-control-sm" placeholder="Product">
                            </td>
                            @if($isAdmin)
                                <td class="p-1">
                                    <select id="new_salesperson_id" class="form-select form-select-sm">
                                        <option value="">-SP-</option>
                                        @foreach($assignedUsers as $u)
                                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="p-1">
                                    <select id="new_new_biz_id" class="form-select form-select-sm">
                                        <option value="">-NB-</option>
                                        @foreach($assignedUsers as $u)
                                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="p-1">
                                    <select id="new_ccare_id" class="form-select form-select-sm">
                                        <option value="">-CC-</option>
                                        @foreach($assignedUsers as $u)
                                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            @else
                                {{-- Salesperson / CCare / NewBiz: auto-assigned from auth user --}}
                                <td class="p-2 text-muted small align-middle">Auto-assigned</td>
                            @endif
                            <td class="p-1">
                                <input type="number" id="new_potential" step="0.01"
                                       class="form-control form-control-sm text-end" placeholder="Potential (L)">
                            </td>
                            <td class="p-1">
                                <select id="new_status_stage" class="form-select form-select-sm">
                                    <option value="Prospect">Prospect</option>
                                    @foreach($statuses as $st)
                                        <option value="{{ $st }}">{{ $st }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="p-1">
                                <input type="text" id="new_remarks"
                                       class="form-control form-control-sm" placeholder="Remarks">
                            </td>
                        </tr>
                        {{-- Save / Cancel --}}
                        <tr id="addNewRowActions" style="display:none;background:#f0fdf4;">
                            <td colspan="20" class="p-2 text-center">
                                <button class="btn btn-sm btn-success px-4 me-2"
                                        onclick="saveNewPipeline()">
                                    <i class="bx bx-save me-1"></i>Save
                                </button>
                                <button class="btn btn-sm btn-outline-secondary"
                                        onclick="cancelNewPipeline()">
                                    <i class="bx bx-x me-1"></i>Cancel
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Add New Entry trigger --}}
            <div class="d-flex justify-content-center py-2 border-top" style="background:#f8fafc;">
                <button class="btn btn-sm btn-outline-success rounded-pill px-4"
                        id="addRowTriggerBtn" onclick="showAddNewRow()"
                        style="border-color:#0f766e;color:#0f766e;">
                    <i class="bx bx-plus-circle me-1"></i> Add New Entry
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    /* Compact stats strip */
    .stat-chip {
        display: flex; flex-direction: column; justify-content: center;
        background: #fff; border-left: 3px solid #0f766e;
        border-radius: 5px; padding: 0.3rem 0.7rem;
        box-shadow: 0 1px 3px rgba(0,0,0,.07);
        min-width: 100px;
    }
    .stat-label { font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; line-height: 1.2; margin-bottom: 1px; }
    .stat-value { font-size: 0.88rem; font-weight: 700; color: #0f172a; line-height: 1.2; }
    .stat-value em { font-style: normal; font-size: 0.65rem; color: #94a3b8; margin-left: 1px; }

    .excel-input, .excel-select {
        width:100%; height:100%; min-height:28px;
        border:none !important; outline:none !important;
        background:transparent !important;
        padding:0.2rem 0.5rem; font-size:0.75rem;
        border-radius:0 !important; box-shadow:none !important; color:#333;
    }
    .excel-select {
        appearance:none; -webkit-appearance:none;
        background-image:url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23666%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.4-12.8z%22%2F%3E%3C%2Fsvg%3E") !important;
        background-repeat:no-repeat !important;
        background-position:right .5rem top 50% !important;
        background-size:.65em auto !important;
        padding-right:1.5rem; cursor:pointer;
    }
    .excel-input:focus, .excel-select:focus {
        background-color:#fff !important;
        box-shadow:inset 0 0 0 2px #0d9488 !important;
    }
    .status-select { font-weight:700; font-size:0.75rem; }
    #pipelineTable { background-color:#fff; }
    #pipelineTable th, #pipelineTable td {
        padding:0.35rem 0.5rem !important; font-size:0.75rem;
        vertical-align:middle; border-color:#e2e8f0;
    }
    #pipelineTable td.p-0 { padding:0 !important; }
    #pipelineTable thead th {
        background-color:#0f766e !important; color:#fff !important;
        border-color:#0b534d !important;
        white-space:normal !important; line-height:1.2;
        text-align:center !important; vertical-align:middle !important;
    }
    tr:hover td { background-color:transparent !important; }
    .converted-row td { background-color:#fdf4ff !important; border-bottom:2px solid #e879f9 !important; }
    .converted-row .col-party::after { content:' (Converted)'; color:#d946ef; font-size:0.7rem; font-weight:bold; }
    .pipeline-row-hidden { display:none !important; }
</style>

<script>
const activeMonthKeys = @json(array_column($months, 'key'));

document.addEventListener('DOMContentLoaded', function () {

    // ── Inline edit: row-field ─────────────────────────────────────────
    document.querySelectorAll('.row-field').forEach(field => {
        field.addEventListener('change', function () {
            if (this.hasAttribute('readonly')) return;
            const tr       = this.closest('tr');
            const id       = tr.dataset.id;
            const fieldKey = this.dataset.field;
            const val      = this.value;

            fetch(`{{ url('sales-visits/pipeline') }}/${id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ [fieldKey]: val })
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    this.style.backgroundColor = '#e8f5e9';
                    setTimeout(() => { this.style.backgroundColor = 'transparent'; }, 500);
                    // Keep data-stage in sync for filter
                    if (fieldKey === 'status_stage') {
                        tr.dataset.stage = val.toLowerCase();
                        updateDynamicStats();
                    }
                }
            });
        });
    });

    applyFilters();
});

// ── Filters ───────────────────────────────────────────────────────────
function applyFilters() {
    const partyVal = document.getElementById('filterParty').value.toLowerCase().trim();
    const stageVal = document.getElementById('filterStage').value.toLowerCase();
    const repVal   = document.getElementById('filterRep').value.toLowerCase().trim();

    let visible = 0;
    document.querySelectorAll('#pipelineBody tr[data-id]').forEach(tr => {
        const matchParty = !partyVal || (tr.dataset.party || '').includes(partyVal);
        const matchStage = !stageVal || (tr.dataset.stage || '').includes(stageVal);
        const matchRep   = !repVal   || (tr.dataset.rep   || '').includes(repVal);
        const show = matchParty && matchStage && matchRep;
        tr.classList.toggle('pipeline-row-hidden', !show);
        if (show) visible++;
    });
    document.getElementById('filterCount').textContent = visible;
    updateDynamicStats();
}

function updateDynamicStats() {
    let yoy22 = 0, yoy23 = 0, yoy24 = 0, yoy25 = 0, selectedFyTotal = 0, potential = 0;
    let count = 0;

    document.querySelectorAll('#pipelineBody tr[data-id]:not(.pipeline-row-hidden)').forEach(tr => {
        yoy22     += parseFloat(tr.dataset.yoy22 || 0);
        yoy23     += parseFloat(tr.dataset.yoy23 || 0);
        yoy24     += parseFloat(tr.dataset.yoy24 || 0);
        yoy25     += parseFloat(tr.dataset.yoy25 || 0);
        potential += parseFloat(tr.dataset.potential || 0);
        count++;

        try {
            const months = JSON.parse(tr.dataset.months || '{}');
            activeMonthKeys.forEach(mk => {
                selectedFyTotal += parseFloat(months[mk] || 0);
            });
        } catch(e) {}
    });

    const potentialPerMonth = potential;

    function fmtL(v) { return '₹' + parseFloat(v).toFixed(2) + '<em>L</em>'; }

    const elY22 = document.getElementById('cardYoy22_23'); if (elY22) elY22.innerHTML = fmtL(yoy22);
    const elY23 = document.getElementById('cardYoy23_24'); if (elY23) elY23.innerHTML = fmtL(yoy23);
    const elY24 = document.getElementById('cardYoy24_25'); if (elY24) elY24.innerHTML = fmtL(yoy24);
    const elY25 = document.getElementById('cardYoy25_26'); if (elY25) elY25.innerHTML = fmtL(yoy25);
    const elFy  = document.getElementById('fyTotalCard');    if (elFy)  elFy.innerHTML  = fmtL(selectedFyTotal);
    const elPot = document.getElementById('potentialMonthCard'); if (elPot) elPot.innerHTML = fmtL(potentialPerMonth);
    const elCnt = document.getElementById('totalCustomersCard');  if (elCnt) elCnt.textContent = count;
}

function clearFilters() {
    document.getElementById('filterParty').value = '';
    document.getElementById('filterStage').value = '';
    document.getElementById('filterRep').value   = '';
    applyFilters();
}

// ── Inline Add ────────────────────────────────────────────────────────
function showAddNewRow() {
    document.getElementById('addNewRow').style.display        = '';
    document.getElementById('addNewRowActions').style.display = '';
    document.getElementById('addRowTriggerBtn').style.display = 'none';
    document.getElementById('new_party_name').focus();
}

function cancelNewPipeline() {
    document.getElementById('addNewRow').style.display        = 'none';
    document.getElementById('addNewRowActions').style.display = 'none';
    document.getElementById('addRowTriggerBtn').style.display = '';
    ['new_party_name','new_product','new_potential',
     'new_status_stage','new_remarks',
     'new_salesperson_id','new_new_biz_id','new_ccare_id']
        .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
}

function saveNewPipeline() {
    const partyName = document.getElementById('new_party_name')?.value?.trim();
    if (!partyName) {
        alert('Customer Name is required.');
        document.getElementById('new_party_name')?.focus();
        return;
    }

    const payload = {
        party_name:               partyName,
        product:                  document.getElementById('new_product')?.value     || '',
        total_business_potential: document.getElementById('new_potential')?.value   || 0,
        status_stage:             document.getElementById('new_status_stage')?.value || 'Prospect',
        status_remarks:           document.getElementById('new_remarks')?.value     || '',
        salesperson_id:           document.getElementById('new_salesperson_id')?.value || '',
        new_biz_id:               document.getElementById('new_new_biz_id')?.value  || '',
        ccare_id:                 document.getElementById('new_ccare_id')?.value    || '',
    };

    const saveBtn = document.querySelector('#addNewRowActions .btn-success');
    if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Saving…'; }

    fetch('{{ route("sales-visits.pipeline.store") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept':       'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Could not save. Please try again.');
            if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = '<i class="bx bx-save me-1"></i>Save'; }
        }
    })
    .catch(() => {
        alert('Network error. Please try again.');
        if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = '<i class="bx bx-save me-1"></i>Save'; }
    });
}
</script>
@endsection
