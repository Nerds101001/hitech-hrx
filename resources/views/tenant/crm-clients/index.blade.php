@extends('layouts.layoutMaster')

@section('title', 'CRM Client Mapping')

@section('vendor-style')
    @vite(['resources/assets/vendor/libs/select2/select2.scss'])
@endsection

@section('page-style')
<style>
    .crm-header-gradient {
        background: linear-gradient(135deg, #0f4c75 0%, #1b6ca8 50%, #127464 100%);
        border-radius: 16px;
        padding: 2rem;
        color: #fff;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
    }
    .crm-header-gradient::before {
        content: '';
        position: absolute;
        top: -40px; right: -40px;
        width: 160px; height: 160px;
        background: rgba(255,255,255,0.06);
        border-radius: 50%;
    }
    .crm-header-gradient::after {
        content: '';
        position: absolute;
        bottom: -60px; left: -20px;
        width: 200px; height: 200px;
        background: rgba(255,255,255,0.04);
        border-radius: 50%;
    }
    .stage-badge {
        font-size: 0.65rem;
        font-weight: 700;
        padding: 3px 10px;
        border-radius: 20px;
        letter-spacing: 0.5px;
        text-transform: uppercase;
    }
    .stage-ACTIVE       { background: #dcfce7; color: #16a34a; }
    .stage-DROPPED      { background: #fee2e2; color: #dc2626; }
    .stage-PROSPECT     { background: #fef3c7; color: #d97706; }
    .stage-INTRODUCTION { background: #dbeafe; color: #2563eb; }
    .stage-ORDER        { background: #ede9fe; color: #7c3aed; }
    .stage-GIVENTOCC    { background: #cffafe; color: #0891b2; }
    .stage-default      { background: #f1f5f9; color: #64748b; }
    .role-pill {
        font-size: 0.62rem;
        padding: 2px 8px;
        border-radius: 20px;
        font-weight: 700;
        display: inline-block;
        margin-bottom: 2px;
    }
    .role-sales   { background: #ede9fe; color: #7c3aed; }
    .role-ccare   { background: #dbeafe; color: #1d4ed8; }
    .role-newbiz  { background: #fef9c3; color: #a16207; }
    .role-billing { background: #dcfce7; color: #15803d; }
    .role-finance { background: #fce7f3; color: #be185d; }
    .mapping-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        transition: box-shadow 0.2s, transform 0.2s;
    }
    .mapping-card:hover {
        box-shadow: 0 8px 25px rgba(18,116,100,0.1);
        transform: translateY(-2px);
    }
    .emp-avatar {
        width: 26px; height: 26px;
        border-radius: 50%;
        font-size: 0.62rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: #fff;
    }
    .search-crm-input {
        border-radius: 50px;
        border: 2px solid #e2e8f0;
        padding: 0.5rem 1rem;
        font-size: 0.87rem;
        transition: border-color 0.2s;
    }
    .search-crm-input:focus {
        border-color: #127464;
        box-shadow: 0 0 0 3px rgba(18,116,100,0.1);
        outline: none;
    }

    /* Sync Progress UI */
    #syncProgressContainer {
        display: none;
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 12px;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.5rem;
    }
    .sync-progress-bar-wrap {
        background: #e2e8f0;
        border-radius: 50px;
        height: 10px;
        overflow: hidden;
        margin: 0.5rem 0;
    }
    .sync-progress-bar {
        background: linear-gradient(90deg, #127464, #1b6ca8);
        height: 100%;
        border-radius: 50px;
        transition: width 0.4s ease;
        width: 0%;
    }
    .sync-log {
        font-size: 0.8rem;
        color: #475569;
        max-height: 80px;
        overflow-y: auto;
        margin-top: 0.5rem;
    }
    .sync-log p { margin-bottom: 2px; }
</style>
@endsection

@section('vendor-script')
    @vite(['resources/assets/vendor/libs/select2/select2.js'])
@endsection

@section('content')
<div class="animate__animated animate__fadeIn">

    {{-- Header --}}
    <div class="crm-header-gradient mb-4">
        <div class="position-relative" style="z-index:1;">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h4 class="fw-extrabold mb-1 text-white">
                        <i class="bx bx-building-house me-2"></i>CRM Client Mapping
                    </h4>
                    <p class="mb-0 text-white opacity-75 small">
                        Sync clients from Rustx CRM (api-crm.rustx.net) — name, email, phone, address, and all 5 team roles mapped to HRX staff.
                    </p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    {{-- Test: fetch latest 20 only --}}
                    <button class="btn btn-warning fw-bold rounded-pill px-3 shadow-sm" id="testSyncBtn" onclick="startSync('full', 20)" title="Fetch only 20 companies for testing">
                        <i class="bx bx-test-tube me-1"></i> Test 20
                    </button>
                    {{-- Incremental sync: only NEW companies not yet in DB --}}
                    <button class="btn btn-light fw-bold rounded-pill px-4 shadow-sm" id="syncNowBtn" onclick="startSync('incremental')">
                        <i class="bx bx-sync me-1"></i> Sync New
                    </button>
                    {{-- Full sync: re-sync every company and refresh team names --}}
                    <button class="btn btn-outline-light fw-bold rounded-pill px-3 shadow-sm" id="fullSyncBtn" onclick="startSync('full')">
                        <i class="bx bx-refresh me-1"></i> Full Refresh
                    </button>
                    <button class="btn btn-white fw-bold rounded-pill px-4 shadow-sm border" data-bs-toggle="modal" data-bs-target="#addMappingModal">
                        <i class="bx bx-plus me-1"></i> Add Manual
                    </button>
                </div>
            </div>
            <div class="row mt-3 g-2">
                <div class="col-auto">
                    <span class="badge bg-white text-dark fw-bold px-3 py-2" style="border-radius:20px;">
                        <i class="bx bx-buildings me-1 text-primary"></i>
                        {{ $mappings->total() }} Clients Synced
                    </span>
                </div>
                <div class="col-auto">
                    <span class="badge bg-white text-dark fw-bold px-3 py-2" style="border-radius:20px;">
                        <i class="bx bx-group me-1 text-success"></i>
                        Sales · CCare · New Biz · Billing · Finance
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Sync Progress Panel (hidden until sync starts) --}}
    <div id="syncProgressContainer">
        <div class="d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-success"><i class="bx bx-sync bx-spin me-1"></i> <span id="syncStatusText">Starting sync...</span></h6>
            <button class="btn btn-sm btn-outline-danger rounded-pill" onclick="stopSync()" id="stopSyncBtn">
                <i class="bx bx-stop me-1"></i>Stop
            </button>
        </div>
        <div class="sync-progress-bar-wrap mt-2">
            <div class="sync-progress-bar" id="syncProgressBar"></div>
        </div>
        <div class="d-flex justify-content-between">
            <small class="text-muted" id="syncCountText">0 / 0 clients</small>
            <small class="text-muted" id="syncPageText">Page 0 of 0</small>
        </div>
        <div class="sync-log" id="syncLog"></div>
    </div>

    {{-- Mappings Table --}}
    <div class="card border-0 shadow-sm" style="border-radius:16px; overflow:hidden;">
        <div class="card-header bg-white py-3 px-4 d-flex align-items-center justify-content-between border-bottom flex-wrap gap-2">
            <h6 class="fw-bold mb-0 text-dark"><i class="bx bx-link-alt me-2 text-primary"></i>Client → Team Assignments</h6>
            <input type="text" id="tableSearchInput" class="search-crm-input" placeholder="Search clients..." style="width:240px;">
        </div>
        <div class="card-body p-0">
            @if($mappings->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle" id="mappingsTable" style="font-size:0.84rem; min-width:900px;">
                    <thead style="background:#f8fafc; border-bottom:2px solid #e2e8f0;">
                        <tr>
                            <th class="ps-4 py-3 text-uppercase fw-bold text-muted" style="font-size:0.7rem;letter-spacing:0.5px;width:22%;">Client Name</th>
                            <th class="py-3 text-uppercase fw-bold text-muted text-center" style="font-size:0.7rem;width:8%;">CRM Code</th>
                            <th class="py-3 text-uppercase fw-bold text-muted" style="font-size:0.7rem;width:16%;">Contact</th>
                            <th class="py-3 text-uppercase fw-bold text-muted text-center" style="font-size:0.7rem;width:10%;">Stage</th>
                            <th class="py-3 text-uppercase fw-bold text-muted" style="font-size:0.7rem;width:36%;">Team Members</th>
                            <th class="py-3 text-uppercase fw-bold text-muted text-center pe-4" style="font-size:0.7rem;width:8%;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($mappings as $map)
                        @php
                            $stage = strtoupper(str_replace([' ', '_'], '', $map->crm_stage ?? ''));
                            $stageClass = match($stage) {
                                'ACTIVE'       => 'stage-ACTIVE',
                                'DROPPED'      => 'stage-DROPPED',
                                'PROSPECT'     => 'stage-PROSPECT',
                                'INTRODUCTION' => 'stage-INTRODUCTION',
                                'ORDER'        => 'stage-ORDER',
                                'GIVENTOCC'    => 'stage-GIVENTOCC',
                                default        => 'stage-default'
                            };
                        @endphp
                        <tr class="mapping-row">

                            {{-- Client Name --}}
                            <td class="ps-4 py-2">
                                <div class="fw-semibold text-dark" style="font-size:0.88rem;">{{ $map->crm_company_name }}</div>
                                @if($map->crm_city)
                                <small class="text-muted d-block mt-1" style="font-size:0.75rem;">
                                    <i class="bx bx-map-pin"></i> {{ $map->crm_city }}{{ $map->crm_state ? ', '.$map->crm_state : '' }}
                                </small>
                                @endif
                            </td>

                            {{-- CRM Code --}}
                            <td class="py-2 text-center">
                                <code class="bg-light px-2 py-1 rounded" style="font-size:0.78rem; color:#0f4c75;">{{ $map->crm_company_code }}</code>
                            </td>

                            {{-- Contact --}}
                            <td class="py-2">
                                @if($map->phone)
                                <div class="d-flex align-items-center gap-1 mb-1">
                                    <i class="bx bx-phone text-muted" style="font-size:0.8rem;"></i>
                                    <small>{{ $map->phone }}</small>
                                </div>
                                @endif
                                @if($map->email)
                                <div class="d-flex align-items-center gap-1 mb-1">
                                    <i class="bx bx-envelope text-muted" style="font-size:0.8rem;"></i>
                                    <small>{{ \Illuminate\Support\Str::limit($map->email, 26) }}</small>
                                </div>
                                @endif
                                @if($map->contact_person)
                                <div class="d-flex align-items-center gap-1">
                                    <i class="bx bx-user text-muted" style="font-size:0.8rem;"></i>
                                    <small class="text-muted">{{ $map->contact_person }}</small>
                                </div>
                                @endif
                                @if(!$map->phone && !$map->email && !$map->contact_person)
                                <small class="text-muted">—</small>
                                @endif
                            </td>

                            {{-- Stage --}}
                            <td class="py-2 text-center">
                                @if($map->crm_stage)
                                    <span class="stage-badge {{ $stageClass }}">{{ $map->crm_stage }}</span>
                                @else
                                    <small class="text-muted">—</small>
                                @endif
                            </td>

                            {{-- Team Members: 5 roles in a clean 2×3 grid --}}
                            <td class="py-2">
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:4px 12px;">

                                    @php
                                    $teamRoles = [
                                        ['key'=>'salesperson', 'label'=>'SP', 'color'=>'#7c3aed', 'bg'=>'#ede9fe'],
                                        ['key'=>'ccare',       'label'=>'CC', 'color'=>'#1d4ed8', 'bg'=>'#dbeafe'],
                                        ['key'=>'newBiz',      'label'=>'NB', 'color'=>'#a16207', 'bg'=>'#fef9c3'],
                                        ['key'=>'billing',     'label'=>'BL', 'color'=>'#15803d', 'bg'=>'#dcfce7'],
                                        ['key'=>'finance',     'label'=>'FN', 'color'=>'#be185d', 'bg'=>'#fce7f3'],
                                    ];
                                    @endphp

                                    @foreach($teamRoles as $role)
                                    @php $person = $map->{$role['key']}; @endphp
                                    <div class="d-flex align-items-center gap-1" style="min-width:0; overflow:hidden;">
                                        <span style="font-size:0.6rem; font-weight:700; padding:1px 5px; border-radius:20px; background:{{ $role['bg'] }}; color:{{ $role['color'] }}; flex-shrink:0;">{{ $role['label'] }}</span>
                                        @if($person)
                                            <div style="width:20px; height:20px; border-radius:50%; background:{{ $role['color'] }}; color:#fff; font-size:0.55rem; font-weight:700; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;">{{ substr($person->first_name,0,1) }}{{ substr($person->last_name,0,1) }}</div>
                                            <span style="font-size:0.78rem; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $person->first_name }} {{ $person->last_name }}</span>
                                        @else
                                            <span class="text-muted" style="font-size:0.75rem;">Not assigned</span>
                                        @endif
                                    </div>
                                    @endforeach

                                </div>
                            </td>

                            {{-- Actions --}}
                            <td class="py-2 text-center pe-4">
                                <div class="d-flex gap-1 justify-content-center">
                                    <button class="btn btn-sm btn-icon btn-outline-primary rounded-circle"
                                        title="Edit"
                                        onclick="openEditModal({{ $map->id }},{{ $map->crm_company_code }},'{{ addslashes($map->crm_company_name) }}','{{ addslashes($map->crm_city ?? '') }}','{{ addslashes($map->crm_state ?? '') }}','{{ addslashes($map->crm_stage ?? '') }}',{{ $map->salesperson_id ?? 'null' }},{{ $map->ccare_id ?? 'null' }},{{ $map->new_biz_id ?? 'null' }},{{ $map->billing_id ?? 'null' }},{{ $map->finance_id ?? 'null' }},'{{ addslashes($map->notes ?? '') }}')">
                                        <i class="bx bx-edit-alt"></i>
                                    </button>
                                    <button class="btn btn-sm btn-icon btn-outline-danger rounded-circle"
                                        title="Delete"
                                        onclick="deleteMapping({{ $map->id }})">
                                        <i class="bx bx-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-top">
                {{ $mappings->links() }}
            </div>
            @else
            <div class="text-center py-5">
                <div style="width:80px;height:80px;background:#f0fdf4;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
                    <i class="bx bx-buildings fs-1 text-success"></i>
                </div>
                <h6 class="fw-bold text-dark">No CRM Clients Synced Yet</h6>
                <p class="text-muted small mb-3">Click "Sync from CRM" to import all clients with their team assignments automatically.</p>
                <button class="btn btn-primary rounded-pill px-4" onclick="startSync()">
                    <i class="bx bx-sync me-1"></i>Sync from CRM Now
                </button>
            </div>
            @endif
        </div>
    </div>
</div>

{{-- Add Manual Mapping Modal --}}
<div class="modal fade" id="addMappingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius:16px; overflow:hidden; border:0;">
            <div class="modal-header" style="background:linear-gradient(135deg,#0f4c75,#127464); color:#fff;">
                <h5 class="modal-title fw-bold"><i class="bx bx-plus-circle me-2"></i>Add Manual Client Mapping</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-4">
                    <label class="form-label fw-bold">Search CRM Client <span class="text-danger">*</span></label>
                    <select id="crmClientSelect" class="form-select" style="width:100%;"></select>
                    <div id="clientPreview" class="mt-2 p-2 rounded" style="background:#f8fafc; display:none;">
                        <small class="text-muted" id="clientPreviewText"></small>
                    </div>
                </div>
                <div class="row g-3">
                    @foreach([
                        ['id' => 'add_salesperson_id', 'label' => 'Salesperson',  'icon' => 'bx-user',     'color' => '#7c3aed', 'class' => 'role-sales'],
                        ['id' => 'add_ccare_id',       'label' => 'CCare',        'icon' => 'bx-headphone','color' => '#1d4ed8', 'class' => 'role-ccare'],
                        ['id' => 'add_new_biz_id',     'label' => 'New Biz',      'icon' => 'bx-rocket',   'color' => '#a16207', 'class' => 'role-newbiz'],
                        ['id' => 'add_billing_id',     'label' => 'Billing',      'icon' => 'bx-receipt',  'color' => '#15803d', 'class' => 'role-billing'],
                        ['id' => 'add_finance_id',     'label' => 'Finance',      'icon' => 'bx-coin',     'color' => '#be185d', 'class' => 'role-finance'],
                    ] as $role)
                    <div class="col-md-4">
                        <label class="form-label fw-bold"><i class="bx {{ $role['icon'] }} me-1" style="color:{{ $role['color'] }};"></i>{{ $role['label'] }}</label>
                        <select id="{{ $role['id'] }}" class="form-select employee-select" style="width:100%;">
                            <option value="">— Not Assigned —</option>
                            @foreach($employees as $emp)
                            <option value="{{ $emp['id'] }}">{{ $emp['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endforeach
                </div>
                <div class="mt-3">
                    <label class="form-label fw-bold">Notes</label>
                    <textarea id="add_notes" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary rounded-pill px-4" onclick="saveMapping()" id="saveMappingBtn">
                    <i class="bx bx-save me-1"></i>Save Mapping
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Edit Mapping Modal --}}
<div class="modal fade" id="editMappingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius:16px; overflow:hidden; border:0;">
            <div class="modal-header" style="background:linear-gradient(135deg,#127464,#0f4c75); color:#fff;">
                <h5 class="modal-title fw-bold"><i class="bx bx-edit me-2"></i>Edit — <span id="editModalClientName"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="edit_mapping_id">
                <div class="p-3 rounded-3 mb-4" style="background:#f0fdf4; border:1px solid #bbf7d0;">
                    <div class="d-flex justify-content-between">
                        <span class="fw-bold text-dark" id="edit_company_name_display"></span>
                        <span class="text-muted small" id="edit_location_display"></span>
                    </div>
                    <span class="stage-badge stage-default mt-1 d-inline-block" id="edit_stage_display"></span>
                </div>
                <div class="row g-3">
                    @foreach([
                        ['id' => 'edit_salesperson_id', 'label' => 'Salesperson', 'icon' => 'bx-user',     'color' => '#7c3aed'],
                        ['id' => 'edit_ccare_id',       'label' => 'CCare',       'icon' => 'bx-headphone','color' => '#1d4ed8'],
                        ['id' => 'edit_new_biz_id',     'label' => 'New Biz',     'icon' => 'bx-rocket',   'color' => '#a16207'],
                        ['id' => 'edit_billing_id',     'label' => 'Billing',     'icon' => 'bx-receipt',  'color' => '#15803d'],
                        ['id' => 'edit_finance_id',     'label' => 'Finance',     'icon' => 'bx-coin',     'color' => '#be185d'],
                    ] as $role)
                    <div class="col-md-4">
                        <label class="form-label fw-bold"><i class="bx {{ $role['icon'] }} me-1" style="color:{{ $role['color'] }};"></i>{{ $role['label'] }}</label>
                        <select id="{{ $role['id'] }}" class="form-select employee-select" style="width:100%;">
                            <option value="">— Not Assigned —</option>
                            @foreach($employees as $emp)
                            <option value="{{ $emp['id'] }}">{{ $emp['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endforeach
                </div>
                <div class="mt-3">
                    <label class="form-label fw-bold">Notes</label>
                    <textarea id="edit_notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary rounded-pill px-4" onclick="updateMapping()">
                    <i class="bx bx-check me-1"></i>Update Mapping
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('page-script')
<script>
const ROUTES = {
    store:      '{{ route('crm-clients.store') }}',
    search:     '{{ route('crm-clients.search') }}',
    syncStatus: '{{ route('crm-clients.sync-status') }}',
    syncPage:   '{{ route('crm-clients.sync-page') }}',
    update:     (id) => `{{ url('crm-clients') }}/${id}`,
    destroy:    (id) => `{{ url('crm-clients') }}/${id}`,
};
const CSRF = '{{ csrf_token() }}';

// ======================================================
// SYNC LOGIC
// ======================================================
let syncRunning  = false;
let syncStopped  = false;
let syncTotal    = 0;
let syncSynced   = 0;
let syncTotalPages = 0;

async function startSync(mode = 'incremental', testLimit = 0) {
    if (syncRunning) return;
    syncRunning = true;
    syncStopped = false;
    syncSynced  = 0;
    syncTotal   = 0;

    const isTest        = testLimit > 0;
    const perPage       = 5;          // always 5/batch → ~30s per request
    const maxPages      = isTest ? Math.ceil(testLimit / perPage) : 2000;
    const modeLabel     = isTest
        ? `Test Sync (${testLimit} companies)`
        : (mode === 'incremental' ? 'Incremental Sync (new only)' : 'Full Refresh');

    // Disable all buttons
    ['syncNowBtn','fullSyncBtn','testSyncBtn'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.disabled = true;
    });
    document.getElementById('syncNowBtn').innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Syncing...';
    document.getElementById('syncProgressContainer').style.display = 'block';
    document.getElementById('syncLog').innerHTML = '';
    updateSyncUI(1, 0, isTest ? testLimit : '...', `Starting ${modeLabel}...`);

    try {
        addLog(`🚀 ${modeLabel} — fetching ${perPage} companies per batch...`);

        let page = 1;
        while (page <= maxPages) {
            if (syncStopped) { addLog('⛔ Stopped by user.'); break; }

            const res = await fetch(ROUTES.syncPage, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body:    JSON.stringify({ page, mode }),
            });

            if (!res.ok) {
                addLog(`❌ HTTP ${res.status} on page ${page} — retrying in 3s...`);
                await new Promise(r => setTimeout(r, 3000));
                continue;
            }

            const data = await res.json();
            if (!data.success) { addLog(`❌ ${data.message || 'Error'}`); break; }

            syncSynced += (data.synced || 0);
            if (data.total && data.total > syncTotal) syncTotal = data.total;

            const cap   = isTest ? testLimit : syncTotal;
            const pct   = cap > 0 ? Math.min(Math.round((syncSynced / cap) * 100), 99) : Math.min(page * 5, 80);
            updateSyncUI(pct, syncSynced, cap || '?',
                `Page ${page} — ${data.synced} synced, ${data.skipped} skipped`);
            addLog(`✅ Page ${page}: +${data.synced} new | ${data.skipped} skipped`);

            if (data.done || (isTest && syncSynced >= testLimit)) break;
            page++;
        }

        if (!syncStopped) {
            updateSyncUI(100, syncSynced, syncSynced, `✅ ${modeLabel} done! ${syncSynced} clients.`);
            addLog(`🎉 Complete! ${syncSynced} clients synced. Refreshing in 2s...`);
            setTimeout(() => location.reload(), 2000);
        }
    } catch (e) {
        addLog(`❌ Error: ${e.message}`);
        document.getElementById('syncStatusText').textContent = 'Error — see log';
    } finally {
        syncRunning = false;
        ['syncNowBtn','fullSyncBtn','testSyncBtn'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.disabled = false;
        });
        document.getElementById('syncNowBtn').innerHTML = '<i class="bx bx-sync me-1"></i> Sync New';
    }
}

function stopSync() {
    syncStopped = true;
    syncRunning = false;
    document.getElementById('syncStatusText').textContent = 'Stopping...';
    document.getElementById('stopSyncBtn').disabled = true;
}

function updateSyncUI(pct, synced, total, statusText) {
    document.getElementById('syncProgressBar').style.width   = pct + '%';
    document.getElementById('syncStatusText').textContent    = statusText;
    document.getElementById('syncCountText').textContent     = `${synced} / ${total} clients`;
    // Animate progress bar width
    const bar = document.getElementById('syncProgressBar');
    if (bar) bar.style.width = Math.max(pct, pct > 0 ? 2 : 0) + '%';
}

function addLog(msg) {
    const log = document.getElementById('syncLog');
    const p   = document.createElement('p');
    p.textContent = msg;
    log.prepend(p);
}

// ======================================================
// TABLE SEARCH
// ======================================================
$(document).ready(function() {
    // Init all employee dropdowns with Select2
    $('.employee-select').select2({ dropdownParent: $('.modal'), width: '100%', allowClear: true, placeholder: '— Not Assigned —' });

    // CRM live search for add modal
    $('#crmClientSelect').select2({
        dropdownParent: $('#addMappingModal'),
        width: '100%',
        placeholder: 'Type company name to search CRM...',
        minimumInputLength: 2,
        allowClear: true,
        ajax: {
            url: ROUTES.search,
            dataType: 'json',
            delay: 400,
            data: (params) => ({ q: params.term }),
            processResults: (data) => ({
                results: data.map(c => ({ id: c.id, text: c.text, city: c.city, state: c.state, stage: c.stage }))
            }),
            cache: true,
        },
    });

    $('#crmClientSelect').on('select2:select', function(e) {
        const d = e.params.data;
        $('#clientPreview').show();
        $('#clientPreviewText').html(`<b>${d.text}</b> &nbsp;|&nbsp; ${d.city || ''}${d.city && d.state ? ', ' : ''}${d.state || ''} &nbsp;|&nbsp; Stage: <b>${d.stage || 'N/A'}</b>`);
    });

    // Table filter
    $('#tableSearchInput').on('input', function() {
        const q = $(this).val().toLowerCase();
        $('.mapping-row').each(function() {
            $(this).toggle($(this).text().toLowerCase().includes(q));
        });
    });
});

// ======================================================
// SAVE (manual add)
// ======================================================
function saveMapping() {
    const selectedOption = $('#crmClientSelect').select2('data')[0];
    if (!selectedOption || !selectedOption.id) {
        Swal && Swal.fire('Error', 'Please select a CRM client first.', 'error');
        return;
    }
    const btn = document.getElementById('saveMappingBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

    fetch(ROUTES.store, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({
            crm_company_code: selectedOption.id,
            crm_company_name: selectedOption.text,
            crm_city:         selectedOption.city  || '',
            crm_state:        selectedOption.state || '',
            crm_stage:        selectedOption.stage || '',
            salesperson_id:   $('#add_salesperson_id').val() || null,
            ccare_id:         $('#add_ccare_id').val()       || null,
            new_biz_id:       $('#add_new_biz_id').val()     || null,
            billing_id:       $('#add_billing_id').val()     || null,
            finance_id:       $('#add_finance_id').val()     || null,
            notes:            $('#add_notes').val(),
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('addMappingModal')).hide();
            location.reload();
        } else {
            alert(data.message || 'Save failed.');
        }
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bx bx-save me-1"></i>Save Mapping';
    });
}

// ======================================================
// EDIT MODAL
// ======================================================
function openEditModal(id, compCode, compName, city, state, stage, salesId, ccareId, newBizId, billingId, financeId, notes) {
    document.getElementById('edit_mapping_id').value              = id;
    document.getElementById('editModalClientName').textContent    = compName;
    document.getElementById('edit_company_name_display').textContent = compName;
    document.getElementById('edit_location_display').textContent  = [city, state].filter(Boolean).join(', ');
    document.getElementById('edit_stage_display').textContent     = stage || '';

    const setVal = (selector, val) => $(selector).val(val || '').trigger('change');
    setVal('#edit_salesperson_id', salesId);
    setVal('#edit_ccare_id',       ccareId);
    setVal('#edit_new_biz_id',     newBizId);
    setVal('#edit_billing_id',     billingId);
    setVal('#edit_finance_id',     financeId);
    document.getElementById('edit_notes').value = notes || '';

    new bootstrap.Modal(document.getElementById('editMappingModal')).show();
}

function updateMapping() {
    const id = document.getElementById('edit_mapping_id').value;
    fetch(ROUTES.update(id), {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({
            salesperson_id: $('#edit_salesperson_id').val() || null,
            ccare_id:       $('#edit_ccare_id').val()       || null,
            new_biz_id:     $('#edit_new_biz_id').val()     || null,
            billing_id:     $('#edit_billing_id').val()     || null,
            finance_id:     $('#edit_finance_id').val()     || null,
            notes:          document.getElementById('edit_notes').value,
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) { location.reload(); }
        else { alert(data.message || 'Update failed.'); }
    });
}

// ======================================================
// DELETE
// ======================================================
function deleteMapping(id) {
    if (!confirm('Remove this CRM client mapping?')) return;
    fetch(ROUTES.destroy(id), {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(data => { if (data.success) location.reload(); });
}
</script>
@endsection
