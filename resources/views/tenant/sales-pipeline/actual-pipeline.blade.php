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
            <div class="d-flex gap-2 align-items-center flex-wrap">
                @include('tenant.partials.manager-view-switch', ['mvRoute' => 'sales-visits.actual-pipeline.index', 'mvOnDark' => true, 'mvMemberParam' => 'sp'])
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
                    <a href="{{ route('sales-visits.pipeline.import', ['target' => 'pipeline']) }}" class="btn btn-sm btn-outline-light rounded-pill fw-bold shadow-sm d-flex align-items-center me-2" title="Import Sales Pipeline Data">
                        <i class="bx bx-upload me-1"></i> Import Sales Pipeline Data
                    </a>
                    @if($isAdmin)
                    <a href="{{ route('sales-visits.sales-targets.import') }}" class="btn btn-sm btn-warning text-dark rounded-pill fw-bold shadow-sm d-flex align-items-center" title="Import Kingo & Bingo Targets">
                        <i class="bx bx-upload me-1"></i> Import Sales Targets (Kingo & Bingo)
                    </a>
                    @endif
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
            <span class="stat-value text-primary" id="totalCustomersCard">{{ $totalNewClients }}</span>
        </div>
    </div>

    {{-- ─── FILTER BAR (server-side; submits a GET request) ──────────────── --}}
    <style>
        .sales-filter .form-select,
        .sales-filter .form-control,
        .sales-filter .btn { height:36px; border-radius:9px; font-size:0.8rem; }
        .sales-filter .btn { display:inline-flex; align-items:center; gap:4px; }

        /* This form has no phone breakpoint at all -- every field keeps its
           hardcoded style="width:Npx" (170-200px) and just wraps at that
           fixed width instead of stacking full-width. */
        @media (max-width: 480px) {
            .sales-filter .form-select,
            .sales-filter .form-control {
                width: 100% !important;
            }
        }
    </style>
    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body py-2 px-3">
            <form method="GET" action="{{ route('sales-visits.actual-pipeline.index') }}" class="sales-filter d-flex flex-wrap align-items-center gap-2">
                <input type="hidden" name="fy" value="{{ $selectedFy }}">
                {{-- Keep the Team context across a filter, or a manager applying
                     one is thrown back to "My Data" with an empty table. --}}
                @if(request()->boolean('team'))<input type="hidden" name="team" value="1">@endif
                <span class="fw-bold text-muted" style="font-size:0.8rem">FILTER:</span>
                <input type="text" class="form-control" name="party" value="{{ $fParty }}"
                       placeholder="Search customer…" style="width:200px;">
                <select class="form-select" name="stage" style="width:170px;" onchange="this.form.submit()">
                    <option value="">All Stages</option>
                    @foreach($statuses as $st)
                        <option value="{{ $st }}" {{ strtolower($fStage) === strtolower($st) ? 'selected' : '' }}>{{ $st }}</option>
                    @endforeach
                </select>
                @php $showBothFilters = $isAdmin || $user->hasRole(['hr','HR']); @endphp
                {{-- Salesperson filter: admin/HR and CCare/New Biz users --}}
                @if($showBothFilters || $isCcare || $isNewBiz)
                    <select class="form-select" name="sp" style="width:190px;" onchange="this.form.submit()">
                        <option value="">All Salespersons ({{ $spFilterCounts->sum() }})</option>
                        @foreach($salespersonFilterUsers as $au)
                            <option value="{{ $au->id }}" {{ (string)$fSp === (string)$au->id ? 'selected' : '' }}>{{ $au->name }} ({{ $spFilterCounts[$au->id] ?? 0 }})</option>
                        @endforeach
                    </select>
                @endif
                {{-- New Biz filter: admin/HR and salespersons --}}
                @if($showBothFilters || $isSalesperson)
                    <select class="form-select" name="newbiz" style="width:190px;" onchange="this.form.submit()">
                        <option value="">All New Biz ({{ $nbFilterCounts->sum() }})</option>
                        @foreach($newBizFilterUsers as $au)
                            <option value="{{ $au->id }}" {{ (string)$fNewBiz === (string)$au->id ? 'selected' : '' }}>{{ $au->name }} ({{ $nbFilterCounts[$au->id] ?? 0 }})</option>
                        @endforeach
                    </select>
                @endif
                {{-- Admin/HR only: appears when the selected Salesperson/New Biz is
                     themselves a manager with direct reports. See
                     SalesPipelineController::applyUnrestrictedPersonOrTeamFilter(). --}}
                @if($selectedPersonHasTeam ?? false)
                    @if($showTeamActive ?? false)
                        <a href="{{ route('sales-visits.actual-pipeline.index', collect(request()->except(['page', 'team']))->all()) }}"
                           class="btn btn-success" title="Showing this person's whole team — click to show just them">
                            <i class="bx bx-group"></i> Team Sales: ON
                        </a>
                    @else
                        <a href="{{ route('sales-visits.actual-pipeline.index', array_merge(request()->except('page'), ['team' => 1])) }}"
                           class="btn btn-outline-primary" title="Show sales for this person's whole team, not just them">
                            <i class="bx bx-group"></i> Show Team Sales
                        </a>
                    @endif
                @endif
                <button type="submit" class="btn btn-primary"><i class="bx bx-search"></i> Search</button>
                <a href="{{ route('sales-visits.actual-pipeline.index', ['fy' => $selectedFy]) }}" class="btn btn-outline-secondary">
                    <i class="bx bx-x"></i> Clear
                </a>
                <span class="text-muted ms-auto text-nowrap" style="font-size:0.8rem">
                    Showing <strong>{{ $pipelines->firstItem() ?? 0 }}–{{ $pipelines->lastItem() ?? 0 }}</strong> of <strong>{{ $pipelines->total() }}</strong>
                </span>
            </form>
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
                            {{-- Both columns for everyone. A salesperson used to get a
                                 single "Representative" column and a CCare/New Biz login a
                                 single "Salesperson" column, so nobody but an admin could
                                 see the full ownership of a customer. Who may CHANGE the
                                 assignment is unchanged — that is still admin-only below. --}}
                            <th class="align-middle">Salesperson</th>
                            <th class="align-middle">New Biz</th>
                            <th class="text-end align-middle">Potential (L) / Month</th>
                            <th class="align-middle">Stage</th>
                            <th class="align-middle" style="min-width:250px;">Status Remarks</th>
                            <th class="text-center align-middle" style="width:60px;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="pipelineBody">
                        @forelse($pipelines as $index => $row)
                            @php
                                // Both names drive the search filter for every role, because
                                // both are now on screen for every role. Keying it off the old
                                // per-role display meant a search matched a column the user
                                // could not see, or missed one they could.
                                $repStr = implode(' | ', array_filter([
                                    $row->salesperson->name ?? '',
                                    $row->newBiz->name      ?? '',
                                ]));
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
                                data-salesperson="{{ strtolower($row->salesperson->name ?? '') }}"
                                data-months='@json($rowMonthSales)'
                                class="{{ $row->converted_from_newbiz ? 'converted-row' : '' }}">

                                <td class="text-center">{{ $index + 1 }}</td>
                                <td class="text-muted">{{ $row->created_at->format('d M Y') }}</td>
                                <td class="col-party fw-semibold text-dark text-truncate p-0 position-relative editable-cell"
                                    title="{{ $row->party_name }}">
                                    <input type="text" class="excel-input row-field pe-4"
                                           data-field="party_name"
                                           value="{{ $row->party_name }}">
                                    <span class="edit-pencil-icon position-absolute end-0 top-50 translate-middle-y me-2 text-muted" style="pointer-events: none;">
                                        <i class="bx bx-pencil" style="font-size: 0.75rem;"></i>
                                    </span>
                                </td>
                                <td class="p-0 align-middle position-relative editable-cell">
                                    <input type="text" class="excel-input row-field pe-4"
                                           data-field="product"
                                           value="{{ $row->product }}">
                                    <span class="edit-pencil-icon position-absolute end-0 top-50 translate-middle-y me-2 text-muted" style="pointer-events: none;">
                                        <i class="bx bx-pencil" style="font-size: 0.75rem;"></i>
                                    </span>
                                </td>

                                {{-- Was @if($isAdmin): only a literal 'admin' could reassign, so a
                                     CCare / New Biz rep had no way to correct the rep on their own
                                     prospect. Now anyone who may edit the book can, which matches
                                     what update() already enforces server-side. --}}
                                @if($canEditBook)
                                    {{-- Moving a prospect to another salesperson is a transfer that
                                         update() only allows for real book editors ($canDeleteCustomer
                                         is that strict flag) — a plain salesperson keeps editing
                                         their own prospect but sees who owns it as text. --}}
                                    @if($canDeleteCustomer)
                                    <td class="p-0 align-middle">
                                        <select class="excel-select row-field" data-field="salesperson_id">
                                            <option value="">-SP-</option>
                                            @foreach($salespersons as $u)
                                                <option value="{{ $u->id }}" {{ $row->salesperson_id == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    @else
                                    <td class="align-middle">{{ $row->salesperson->name ?? '-' }}</td>
                                    @endif
                                    <td class="p-0 align-middle">
                                        <select class="excel-select row-field" data-field="new_biz_id">
                                            <option value="">-NB-</option>
                                            @foreach($newBizUsers as $u)
                                                <option value="{{ $u->id }}" {{ $row->new_biz_id == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                @else
                                    {{-- Read-only text for roles that may not edit the book at all
                                         (plain salespeople). Everyone who CAN edit gets the selects
                                         above — the assignable lists are built from the full
                                         directory, so they always contain the row's current holder. --}}
                                    <td class="align-middle">{{ $row->salesperson->name ?? '-' }}</td>
                                    <td class="align-middle">{{ $row->newBiz->name ?? '-' }}</td>
                                @endif

                                <td class="p-0 align-middle position-relative editable-cell">
                                    <input type="number" step="0.01" class="excel-input row-field text-end fw-bold font-monospace ps-4"
                                           style="color:#0f766e;"
                                           data-field="total_business_potential"
                                           value="{{ $row->total_business_potential }}">
                                    <span class="edit-pencil-icon position-absolute start-0 top-50 translate-middle-y ms-2 text-muted" style="pointer-events: none;">
                                        <i class="bx bx-pencil" style="font-size: 0.75rem;"></i>
                                    </span>
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

                                <td class="p-0 position-relative editable-cell">
                                    <input type="text" class="excel-input row-field pe-4"
                                           data-field="status_remarks"
                                           value="{{ $row->status_remarks }}"
                                           placeholder="Type status remarks…">
                                    <span class="edit-pencil-icon position-absolute end-0 top-50 translate-middle-y me-2 text-muted" style="pointer-events: none;">
                                        <i class="bx bx-pencil" style="font-size: 0.75rem;"></i>
                                    </span>
                                </td>
                                <td class="text-center align-middle">
                                    @if($canDeleteCustomer)
                                        <form action="{{ route('sales-visits.pipeline.destroy', $row->id) }}" method="POST"
                                              onsubmit="return confirm('Delete customer '{{ addslashes($row->party_name) }}' and all of its monthly figures? This cannot be undone.');"
                                              style="display:inline-block;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-icon btn-text-danger" style="padding:0;background:none;border:none;" title="Delete customer">
                                                <i class="bx bx-trash text-danger" style="font-size:1.1rem;"></i>
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-muted" title="Only an administrator can delete a customer">&mdash;</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr id="emptyRow">
                                <td colspan="20" class="text-center py-4 text-muted">
                                    No pipeline data found. Click "+ Add New Entry" below to start.
                                </td>
                            </tr>
                        @endforelse

                        {{-- ── Add New Entry — full-width form, works for every role ── --}}
                        <tr id="addNewRow" style="display:none;background:#f0fdf4;">
                            <td colspan="20" class="p-3">
                                <div class="d-flex flex-wrap align-items-end gap-3">
                                    <div style="min-width:180px;">
                                        <label class="form-label small fw-bold mb-1 text-success">Customer Name *</label>
                                        <input type="text" id="new_party_name" class="form-control form-control-sm" placeholder="Customer Name">
                                    </div>
                                    <div>
                                        <label class="form-label small fw-bold mb-1">Product</label>
                                        <input type="text" id="new_product" class="form-control form-control-sm" style="min-width:130px;" placeholder="Product">
                                    </div>
                                    <div>
                                        <label class="form-label small fw-bold mb-1">Type</label>
                                        <select id="new_type" class="form-select form-select-sm" style="min-width:120px;">
                                            <option value="">-Type-</option>
                                            <option value="Customer">Customer</option>
                                            <option value="Distributor">Distributor</option>
                                            <option value="Dealer">Dealer</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label small fw-bold mb-1">Salesperson</label>
                                        <select id="new_salesperson_id" class="form-select form-select-sm" style="min-width:150px;">
                                            <option value="">-Salesperson-</option>
                                            @foreach($salespersons as $u)
                                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label small fw-bold mb-1">New Biz</label>
                                        <select id="new_new_biz_id" class="form-select form-select-sm" style="min-width:140px;">
                                            <option value="">-New Biz-</option>
                                            @foreach($newBizUsers as $u)
                                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label small fw-bold mb-1">CCare</label>
                                        <select id="new_ccare_id" class="form-select form-select-sm" style="min-width:140px;">
                                            <option value="">-CCare-</option>
                                            @foreach(($ccareUsers ?? $assignedUsers) as $u)
                                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label small fw-bold mb-1">Potential (L)</label>
                                        <input type="number" id="new_potential" step="0.01" class="form-control form-control-sm text-end" style="width:110px;" placeholder="0.00">
                                    </div>
                                    <div>
                                        <label class="form-label small fw-bold mb-1">Stage</label>
                                        <select id="new_status_stage" class="form-select form-select-sm" style="min-width:130px;">
                                            <option value="Prospect">Prospect</option>
                                            @foreach($statuses as $st)
                                                <option value="{{ $st }}">{{ $st }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div style="min-width:180px;">
                                        <label class="form-label small fw-bold mb-1">Remarks</label>
                                        <input type="text" id="new_remarks" class="form-control form-control-sm" placeholder="Status remarks">
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-sm btn-success px-3" onclick="saveNewPipeline()">
                                            <i class="bx bx-save me-1"></i>Save
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="cancelNewPipeline()">
                                            <i class="bx bx-x me-1"></i>Cancel
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- ─── PAGINATION (server-side, 50 per page) ────────────────── --}}
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 py-3 px-2">
                <span class="text-muted small">
                    Showing {{ $pipelines->firstItem() ?? 0 }}–{{ $pipelines->lastItem() ?? 0 }} of {{ $pipelines->total() }}
                </span>
                <div>{{ $pipelines->onEachSide(1)->links() }}</div>
            </div>

            {{-- Add New Entry trigger — hidden for read-only roles (plain salespeople). --}}
            <div class="d-flex justify-content-center py-2 border-top @unless($canEditBook) d-none @endunless" style="background:#f8fafc;">
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

    /* Hide the number spinners; the arrow keys are handled in JS so they
       move between cells instead of changing the figure. */
    .excel-input[type=number]::-webkit-outer-spin-button,
    .excel-input[type=number]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    .excel-input[type=number] { -moz-appearance: textfield; appearance: textfield; }
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
    .editable-cell { position: relative; }
    .editable-cell .edit-pencil-icon {
        color: #94a3b8;
        opacity: 0.4;
        transition: opacity 0.15s ease-in-out, color 0.15s ease-in-out;
    }
    .editable-cell:hover .edit-pencil-icon,
    .editable-cell:focus-within .edit-pencil-icon {
        opacity: 1;
        color: #0d9488;
    }
</style>

<script>
const activeMonthKeys = @json(array_column($months, 'key'));
// ANNUAL_MAP: salesperson name -> yoy figures. ANNUAL_GRAND: scoped totals.
const ANNUAL_MAP  = @json($annualMap);
const ANNUAL_GRAND = @json($annualGrand);

document.addEventListener('DOMContentLoaded', function () {

    {{-- Inline editing is attached only for roles that may change the book;
         plain salespeople get a read-only grid. Enforced server-side too. --}}
    @if($canEditBook)
    // ── Grid keyboard nav ─────────────────────────────────────────────
    // On a number input the arrow keys SPIN THE VALUE. The spinner buttons
    // were hidden in CSS, which stopped the clicks but not the keys, so a
    // stray Up/Down silently edited a sales figure. Arrows now move between
    // cells like a spreadsheet, and never change what is in them.
    (function excelGridNav() {
        const EDITABLE = 'input.excel-input:not([type=hidden]):not([disabled])';

        function move(from, dir) {
            const td = from.closest('td'), tr = from.closest('tr');
            if (!td || !tr) return false;
            const col = Array.prototype.indexOf.call(tr.children, td);
            let row = dir > 0 ? tr.nextElementSibling : tr.previousElementSibling;
            while (row) {
                const cell = row.children[col];
                const target = cell && cell.querySelector(EDITABLE);
                if (target && target.offsetParent !== null) {
                    target.focus();
                    if (typeof target.select === 'function') target.select();
                    return true;
                }
                row = dir > 0 ? row.nextElementSibling : row.previousElementSibling;
            }
            return false;
        }

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'ArrowUp' && e.key !== 'ArrowDown' && e.key !== 'Enter') return;
            const el = e.target;
            if (!el || !el.classList || !el.classList.contains('excel-input')) return;
            if (e.altKey || e.ctrlKey || e.metaKey) return;
            // Always stop the default first: on type=number that IS the spin.
            e.preventDefault();
            move(el, e.key === 'ArrowUp' ? -1 : 1);
        });

        // Same bug by another route: scrolling the page with the cursor over a
        // focused number cell scrolls its value instead.
        document.addEventListener('wheel', function (e) {
            const el = document.activeElement;
            if (el && el.classList && el.classList.contains('excel-input') &&
                el.type === 'number' && el === e.target) {
                el.blur();
            }
        }, { passive: true });
    })();

    // ── Inline edit: row-field ─────────────────────────────────────────
    document.querySelectorAll('.row-field').forEach(field => {
        field.addEventListener('change', function () {
            if (this.hasAttribute('readonly')) return;
            const tr       = this.closest('tr');
            const id       = tr.dataset.id;
            const fieldKey = this.dataset.field;
            const val      = this.value;

            // Built from the NAMED route, not a hand-written path. This used to
            // POST to /sales-visits/pipeline/{id}, which has no PUT route — every
            // inline edit on this page 404'd and was silently thrown away, so a
            // stage change looked like it saved and came back on refresh.
            fetch(@json(route('sales-visits.pipeline.update', ['id' => '__ID__'])).replace('__ID__', id), {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                           'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ [fieldKey]: val })
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    this.style.backgroundColor = '#e8f5e9';
                    setTimeout(() => { this.style.backgroundColor = 'transparent'; }, 500);
                    // Keep data attributes in sync for filter and calculations
                    if (fieldKey === 'party_name') {
                        tr.dataset.party = val.toLowerCase();
                    } else if (fieldKey === 'status_stage') {
                        tr.dataset.stage = val.toLowerCase();
                        updateDynamicStats();
                    } else if (fieldKey === 'total_business_potential') {
                        tr.dataset.potential = val || 0;
                        updateDynamicStats();
                    } else if (fieldKey === 'salesperson_id' || fieldKey === 'new_biz_id' || fieldKey === 'ccare_id') {
                        let reps = [];
                        tr.querySelectorAll('select[data-field$="_id"], select[data-field="salesperson_id"]').forEach(sel => {
                            if (sel.selectedIndex >= 0) {
                                const optText = sel.options[sel.selectedIndex].text;
                                if (optText && !optText.startsWith('-')) {
                                    reps.push(optText);
                                }
                            }
                        });
                        tr.dataset.rep = reps.join(' | ').toLowerCase();
                    }
                }
            });
        });
    });
    @endif

    // Filtering / pagination / totals are server-side now — nothing to init here.
});

// Filtering, pagination and the stat cards are computed SERVER-SIDE now (the
// controller renders 50 rows per page and pre-computes every card over the full
// filtered set). These stubs keep old inline callers safe.
function applyFilters() {}
function updateDynamicStats() {}
function clearFilters() {
    window.location.href = @json(route('sales-visits.actual-pipeline.index')) + '?fy=' + encodeURIComponent(@json($selectedFy));
}

// ── Inline Add ────────────────────────────────────────────────────────
function showAddNewRow() {
    document.getElementById('addNewRow').style.display        = '';
    document.getElementById('addRowTriggerBtn').style.display = 'none';
    document.getElementById('new_party_name').focus();
}

function cancelNewPipeline() {
    document.getElementById('addNewRow').style.display        = 'none';
    document.getElementById('addRowTriggerBtn').style.display = '';
    ['new_party_name','new_product','new_type','new_potential',
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
        type:                     document.getElementById('new_type')?.value        || '',
        total_business_potential: document.getElementById('new_potential')?.value   || 0,
        status_stage:             document.getElementById('new_status_stage')?.value || 'Prospect',
        status_remarks:           document.getElementById('new_remarks')?.value     || '',
        salesperson_id:           document.getElementById('new_salesperson_id')?.value || '',
        new_biz_id:               document.getElementById('new_new_biz_id')?.value  || '',
        ccare_id:                 document.getElementById('new_ccare_id')?.value    || '',
        is_locked:                false,  // Pipeline (prospect) rows stay unlocked
    };

    const saveBtn = document.querySelector('#addNewRow .btn-success');
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
    .then(async r => {
        let data;
        try { data = await r.json(); } catch (e) { data = null; }
        return { ok: r.ok, data };
    })
    .then(({ ok, data }) => {
        if (ok && data && data.success) {
            window.location.reload();
        } else {
            // Surface whatever the server actually said (validation error,
            // "Unauthorized", a real save failure) instead of a one-size
            // generic message that gives the reporter nothing to go on.
            const msg = (data && data.message) || (data && data.errors && Object.values(data.errors).flat().join(' ')) || 'Could not save. Please try again.';
            alert(msg);
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
