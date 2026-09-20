@extends('layouts.layoutMaster')

@section('title', $mapping->crm_company_name . ' — Customer Profile')

@section('vendor-style')
    @vite(['resources/assets/vendor/libs/sweetalert2/sweetalert2.scss', 'resources/assets/vendor/libs/apex-charts/apex-charts.scss'])
@endsection
@section('vendor-script')
    @vite(['resources/assets/vendor/libs/sweetalert2/sweetalert2.js', 'resources/assets/vendor/libs/apex-charts/apex-charts.min.js'])
@endsection

@section('page-style')
<style>
    :root {
        --cp-shadow: 0 1px 2px rgba(15,23,42,.04), 0 4px 14px rgba(15,23,42,.05);
    }
    .cp-hero {
        background: #fff; border-radius: 18px; margin-bottom: 1.1rem; position: relative;
        overflow: hidden; box-shadow: var(--cp-shadow); display: flex; min-height: 148px;
    }
    .cp-hero-left { padding: 1.6rem 1.9rem; flex: 1; display: flex; flex-direction: column; justify-content: center; }
    .cp-hero-right {
        width: 34%; min-width: 220px; position: relative;
        background: linear-gradient(135deg, #0f4c75 0%, #1b6ca8 55%, #127464 100%);
        display: flex; align-items: flex-end; padding: 1.5rem;
    }
    .cp-hero-right::before {
        content: ''; position: absolute; inset: 0; opacity: .18;
        background-image: radial-gradient(circle at 20% 20%, #fff 0, transparent 35%), radial-gradient(circle at 80% 60%, #fff 0, transparent 40%);
    }
    .cp-hero-quote { position: relative; color: #fff; font-weight: 700; font-size: .95rem; font-style: italic; line-height: 1.35; text-shadow: 0 1px 6px rgba(0,0,0,.25); }
    .cp-hero-logo {
        width: 58px; height: 58px; border-radius: 15px; background: #dbeafe; color: #1d4ed8;
        display: flex; align-items: center; justify-content: center; font-size: 1.4rem; font-weight: 800; flex-shrink: 0;
        box-shadow: inset 0 0 0 1px rgba(29,78,216,.12);
    }
    .cp-pill {
        display: inline-flex; align-items: center; gap: 5px; font-size: .74rem; font-weight: 600;
        padding: 4px 11px; border-radius: 20px; background: #f1f5f9; color: #475569;
    }
    .cp-pill.cp-pill-active  { background: #dcfce7; color: #15803d; }
    .cp-pill.cp-pill-active::before { content:''; width:6px; height:6px; border-radius:50%; background:#22c55e; }
    .cp-pill.cp-pill-dropped { background: #fee2e2; color: #b91c1c; }
    .cp-pill.cp-pill-dropped::before { content:''; width:6px; height:6px; border-radius:50%; background:#ef4444; }
    .cp-stat-card {
        border: none; border-radius: 14px; padding: 1rem 1.15rem; background: #fff; height: 100%;
        display: flex; align-items: flex-start; gap: .8rem; box-shadow: var(--cp-shadow);
    }
    .cp-stat-icon {
        width: 42px; height: 42px; border-radius: 12px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center; font-size: 1.2rem;
    }
    .cp-stat-label { font-size: .68rem; text-transform: uppercase; letter-spacing: .04em; color: #94a3b8; font-weight: 700; }
    .cp-stat-value { font-size: 1.3rem; font-weight: 800; color: #1e293b; line-height: 1.3; display: inline-block; }
    .cp-stat-trend { font-size: .72rem; font-weight: 700; margin-left: 6px; }
    .cp-stat-trend.up   { color: #16a34a; }
    .cp-stat-trend.down { color: #dc2626; }
    .cp-tabs { border-bottom: 1px solid #e2e8f0; flex-wrap: nowrap; overflow-x: auto; }
    .cp-tabs .nav-item { flex-shrink: 0; }
    .cp-tabs .nav-link { font-weight: 600; font-size: .85rem; border: none; border-bottom: 2px solid transparent; color: #64748b; padding: .65rem .95rem; white-space: nowrap; }
    .cp-tabs .nav-link.active { color: #127464; border-bottom-color: #127464; background: transparent; }
    .cp-card { border: none; border-radius: 16px; box-shadow: var(--cp-shadow); }
    .cp-health-ring { width: 108px; height: 108px; }
    .cp-quick-btn {
        display: flex; flex-direction: column; align-items: center; gap: 4px; flex: 1;
        padding: .65rem .4rem; border-radius: 12px; font-size: .72rem; font-weight: 700; text-decoration: none;
        border: 1px solid #e2e8f0; color: #334155; background: #fff;
    }
    .cp-quick-btn:hover { background: #f8fafc; color: #334155; }
    .cp-quick-btn i { font-size: 1.25rem; }
    .cp-quick-btn.cp-quick-call  { background: #127464; color: #fff; border-color: #127464; }
    .cp-quick-btn.cp-quick-call:hover { background: #0d5c4f; color: #fff; }
    .cp-quick-btn.cp-quick-whatsapp i { color: #22c55e; }
    .cp-quick-btn.cp-quick-email i { color: #1d4ed8; }
    .cp-followup-empty { color: #94a3b8; font-size: .8rem; text-align: center; padding: 1.2rem 0; }
    .cp-contact-row, .cp-doc-row { border: 1px solid #f1f5f9; border-radius: 12px; padding: 11px 13px; margin-bottom: 8px; }
    .cp-team-slot { display: flex; align-items: center; gap: 9px; padding: 8px 0; border-bottom: 1px dashed #f1f5f9; }
    .cp-team-slot:last-child { border-bottom: 0; }
    .cp-team-avatar { width: 30px; height: 30px; border-radius: 50%; color: #fff; font-size: .64rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .cp-icon-btn {
        width: 28px; height: 28px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
        flex-shrink: 0; border: none; background: #f1f5f9; color: #475569; font-size: .72rem;
    }
    .cp-icon-btn:hover { background: #e2e8f0; }
    .cp-icon-btn.cp-icon-call { background: #dcfce7; color: #15803d; }
    .cp-call-list-row { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
    .cp-call-list-row:last-child { border-bottom: 0; }
    .cp-call-icon { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: .95rem; }
    .cp-status-chip { font-size: .68rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
    .cp-status-logged { background: #dcfce7; color: #15803d; }
    .cp-status-pending { background: #fef3c7; color: #92400e; }
    .cp-quote-strip {
        border-radius: 16px; padding: 1.25rem 1.5rem; background: linear-gradient(135deg, #f0fdfa, #eff6ff);
        display: flex; align-items: center; justify-content: center; text-align: center;
        font-style: italic; font-weight: 700; color: #0f766e; font-size: .95rem; height: 100%;
    }
    .activity-item { display: flex; gap: 10px; padding: 12px 0; border-bottom: 1px solid #f1f5f9; }
    .activity-item:last-child { border-bottom: 0; }
    .activity-icon { width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 0.85rem; }
    .activity-icon.type-call    { background: #1b6ca8; }
    .activity-icon.type-comment { background: #127464; }
    .activity-icon.type-system  { background: #94a3b8; }
    .follow-up-chip { font-size: 0.72rem; padding: 2px 8px; border-radius: 20px; background: #fef3c7; color: #92400e; font-weight: 600; }
    .role-purchase { background: #dbeafe !important; color: #1d4ed8; }
    .role-finance  { background: #fce7f3 !important; color: #be185d; }
    .role-accounts { background: #dcfce7 !important; color: #15803d; }
    .role-admin    { background: #ede9fe !important; color: #7c3aed; }
    .role-other    { background: #f1f5f9 !important; color: #64748b; }
</style>
@endsection

@section('content')
<div class="animate__animated animate__fadeIn">

    <a href="{{ route('crm-clients.profile', $mapping->id) }}" onclick="event.preventDefault(); history.back();" class="text-decoration-none small text-muted mb-2 d-inline-block">
        <i class="bx bx-arrow-back"></i> Back to Client Mappings
    </a>

    @php
        $__isDropped = $mapping->crm_stage && stripos($mapping->crm_stage, 'drop') !== false;
    @endphp

    {{-- ── Hero ─────────────────────────────────────────────────────────── --}}
    <div class="cp-hero">
        <div class="cp-hero-left">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="cp-hero-logo">{{ strtoupper(substr($mapping->crm_company_name, 0, 2)) }}</div>
                    <div>
                        <h4 class="fw-extrabold mb-2" style="color:#1e293b;">{{ $mapping->crm_company_name }}</h4>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="cp-pill {{ $__isDropped ? 'cp-pill-dropped' : 'cp-pill-active' }}">{{ $__isDropped ? 'Dropped' : 'Active' }}</span>
                            <span class="cp-pill"><i class="bx bx-hash"></i> {{ $mapping->crm_company_code }}</span>
                            @if($mapping->crm_city)<span class="cp-pill"><i class="bx bx-map-pin"></i> {{ $mapping->crm_city }}{{ $mapping->crm_state ? ', '.$mapping->crm_state : '' }}</span>@endif
                            <span class="cp-pill"><i class="bx bx-calendar"></i> Customer Since {{ $mapping->created_at->format('M Y') }}</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('crm-clients.show', $mapping->id) }}" class="btn btn-sm btn-outline-secondary rounded-pill fw-bold">
                        <i class="bx bx-grid-alt me-1"></i> Classic View
                    </a>
                    <button class="btn btn-sm rounded-pill fw-bold text-white" style="background:#127464;" onclick="LogCall.open()">
                        <i class="bx bx-plus me-1"></i> Add Activity
                    </button>
                </div>
            </div>
        </div>
        <div class="cp-hero-right d-none d-lg-flex">
            <div class="cp-hero-quote">"Every call logged<br>is a relationship built."</div>
        </div>
    </div>

    {{-- ── Stat cards ───────────────────────────────────────────────────── --}}
    <div class="row g-3 mb-3">
        <div class="col-6" style="flex:1 1 19%;">
            <div class="cp-stat-card">
                <div class="cp-stat-icon" style="background:#dbeafe;color:#1d4ed8;"><i class="bx bx-bar-chart-alt-2"></i></div>
                <div>
                    <div class="cp-stat-label">Total Sales (FY)</div>
                    <div class="cp-stat-value">₹{{ number_format($totalSalesFy, 2) }}L</div>
                    @if($salesTrendPct !== null)
                        <span class="cp-stat-trend {{ $salesTrendPct >= 0 ? 'up' : 'down' }}">
                            <i class="bx {{ $salesTrendPct >= 0 ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt' }}"></i>{{ abs($salesTrendPct) }}%
                        </span>
                        <div class="small text-muted">vs same period last FY</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-6" style="flex:1 1 19%;">
            <div class="cp-stat-card">
                <div class="cp-stat-icon" style="background:#ede9fe;color:#7c3aed;"><i class="bx bx-target-lock"></i></div>
                <div>
                    <div class="cp-stat-label">Open Opportunities</div>
                    <div class="cp-stat-value">{{ $openOpportunityCount }}</div>
                    <div class="small text-muted">₹{{ number_format($openOpportunityValue, 2) }}L forecast</div>
                </div>
            </div>
        </div>
        <div class="col-6" style="flex:1 1 19%;">
            <div class="cp-stat-card">
                <div class="cp-stat-icon" style="background:#dcfce7;color:#15803d;"><i class="bx bx-group"></i></div>
                <div>
                    <div class="cp-stat-label">Total Contacts</div>
                    <div class="cp-stat-value">{{ $contacts->count() }}</div>
                </div>
            </div>
        </div>
        <div class="col-6" style="flex:1 1 19%;">
            <div class="cp-stat-card">
                <div class="cp-stat-icon" style="background:#cffafe;color:#0e7490;"><i class="bx bx-phone-call"></i></div>
                <div>
                    <div class="cp-stat-label">Calls (This Month)</div>
                    <div class="cp-stat-value">{{ $callsThisMonthTotal }}</div>
                    <div class="small text-muted">via Smart Agent</div>
                </div>
            </div>
        </div>
        <div class="col-6" style="flex:1 1 19%;">
            <div class="cp-stat-card">
                <div class="cp-stat-icon" style="background:#ffedd5;color:#c2410c;"><i class="bx bx-time-five"></i></div>
                <div>
                    <div class="cp-stat-label">Last Activity</div>
                    <div class="cp-stat-value" style="font-size:1rem;">{{ $lastActivity ? $lastActivity->created_at->diffForHumans() : 'None yet' }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Tabs ─────────────────────────────────────────────────────────── --}}
    <ul class="nav nav-tabs cp-tabs mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#cpOverview" type="button"><i class="bx bx-home-alt me-1"></i>Overview</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cpContacts" type="button"><i class="bx bx-id-card me-1"></i>Contacts</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cpSales" type="button"><i class="bx bx-line-chart me-1"></i>Sales &amp; Revenue</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cpCalls" type="button"><i class="bx bx-phone-call me-1"></i>Calls</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cpTasks" type="button"><i class="bx bx-task me-1"></i>Tasks @if($openFollowUps->count())<span class="badge bg-label-warning rounded-pill">{{ $openFollowUps->count() }}</span>@endif</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cpActivities" type="button"><i class="bx bx-time-five me-1"></i>Activities</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cpNotes" type="button"><i class="bx bx-note me-1"></i>Notes</button></li>
    </ul>

    <div class="tab-content">
        {{-- ══════════════════════════════ OVERVIEW ══════════════════════════════ --}}
        <div class="tab-pane fade show active" id="cpOverview">
            @php
                $__primaryContact = $contacts->firstWhere('is_primary', true) ?? $contacts->first();
                $__quickPhone = $__primaryContact->phone ?? $mapping->phone;
                $__quickEmail = $__primaryContact->email ?? $mapping->email;
                $__quickWhatsapp = $__quickPhone ? preg_replace('/\D+/', '', $__quickPhone) : null;
                $__nextFollowUp = $openFollowUps->first();
                $__ctcAgentForCalls = app(\App\Services\ClickToCallService::class)->agentFor(auth()->user());
            @endphp

            {{-- Three independent columns, each its own stack of cards --
                 deliberately NOT relying on Bootstrap's equal-height column
                 stretching (align-items-start turns that off): with cards of
                 genuinely different content lengths, forced equal height
                 just leaves blank void under the shorter ones instead of
                 fixing anything. Columns simply end where their own content
                 ends, like the reference layout. --}}
            <div class="row g-4 align-items-start">
                <div class="col-lg-4">
                    <div class="card cp-card mb-4">
                        <div class="card-body">
                            <h6 class="fw-bold mb-3"><i class="bx bx-building-house me-1 text-primary"></i>Customer Information</h6>
                            <table class="table table-sm table-borderless mb-0">
                                <tr><td class="text-muted" style="width:36px;"><i class="bx bx-buildings"></i></td><td class="text-muted">Customer Name</td><td class="fw-semibold text-end">{{ $mapping->crm_company_name }}</td></tr>
                                <tr><td class="text-muted"><i class="bx bx-map-pin"></i></td><td class="text-muted">Location</td><td class="text-end">{{ $mapping->crm_city ?: '—' }}{{ $mapping->crm_state ? ', '.$mapping->crm_state : '' }}</td></tr>
                                <tr><td class="text-muted"><i class="bx bx-flag"></i></td><td class="text-muted">Stage</td><td class="text-end">{{ $mapping->crm_stage ?: '—' }}</td></tr>
                                <tr><td class="text-muted"><i class="bx bx-envelope"></i></td><td class="text-muted">Email</td><td class="text-end">{{ $mapping->email ?: '—' }}</td></tr>
                                <tr><td class="text-muted"><i class="bx bx-phone"></i></td><td class="text-muted">Phone</td><td class="text-end">{{ $mapping->phone ?: '—' }}</td></tr>
                                <tr><td class="text-muted"><i class="bx bx-user"></i></td><td class="text-muted">Primary Contact</td><td class="text-end">{{ $mapping->contact_person ?: '—' }}</td></tr>
                            </table>
                        </div>
                    </div>
                    <div class="card cp-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0"><i class="bx bx-line-chart me-1 text-primary"></i>Sales Trend</h6>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-secondary cp-range-btn" data-range="6M">6M</button>
                                    <button type="button" class="btn btn-primary cp-range-btn" data-range="1Y">1Y</button>
                                    <button type="button" class="btn btn-outline-secondary cp-range-btn" data-range="2Y">2Y</button>
                                </div>
                            </div>
                            <div id="cpSalesTrendChart"></div>
                        </div>
                    </div>

                    <div class="card cp-card mt-4">
                        <div class="card-body">
                            <h6 class="fw-bold mb-2"><i class="bx bx-heart me-1 text-danger"></i>Customer Health</h6>
                            <div class="d-flex align-items-center gap-3">
                                <div id="cpHealthRing" class="cp-health-ring"></div>
                                <div>
                                    <div class="fw-bold" style="font-size:1rem;">{{ $health['label'] }}</div>
                                    @foreach($health['reasons'] as $reason)
                                        <div class="small text-muted"><i class="bx bx-check text-success"></i> {{ $reason }}</div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card cp-card mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0"><i class="bx bx-id-card me-1 text-success"></i>Key Contacts</h6>
                                <button class="btn btn-sm btn-primary rounded-pill" onclick="openContactModal()"><i class="bx bx-plus"></i></button>
                            </div>
                            <div id="cpContactsList">
                                @include('tenant.crm-clients.partials.profile-contacts-list', ['contacts' => $contacts])
                            </div>
                        </div>
                    </div>
                    <div class="card cp-card mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0"><i class="bx bx-task me-1 text-warning"></i>Open Tasks</h6>
                                <a href="javascript:void(0)" class="small fw-semibold" style="color:#127464;" onclick="document.querySelector('[data-bs-target=\'#cpTasks\']').click()">View All</a>
                            </div>
                            @forelse($openFollowUps->take(4) as $fu)
                                <div class="d-flex justify-content-between align-items-start gap-2 py-2 border-bottom">
                                    <div style="min-width:0;">
                                        <div class="small text-truncate" style="max-width:220px;">{{ \Illuminate\Support\Str::limit($fu->body, 60) }}</div>
                                        <div class="text-muted small">{{ $fu->follow_up_due_at ? 'Due '.$fu->follow_up_due_at->format('d M') : 'No date' }}</div>
                                    </div>
                                    <button class="btn btn-sm btn-outline-success rounded-pill flex-shrink-0" onclick="completeFollowUp({{ $fu->id }}, this)"><i class="bx bx-check"></i></button>
                                </div>
                            @empty
                                <p class="text-muted small text-center py-3 mb-0">No open tasks.</p>
                            @endforelse
                        </div>
                    </div>
                    <div class="card cp-card">
                        <div class="card-body">
                            <h6 class="fw-bold mb-2"><i class="bx bx-bolt me-1 text-warning"></i>Quick Actions</h6>
                            <div class="d-flex gap-2">
                                @if($__quickPhone)
                                    <button type="button" class="cp-quick-btn cp-quick-call js-ctc-call" data-phone="{{ $__quickPhone }}" onclick="callContact('{{ $__quickPhone }}')">
                                        <i class="bx bx-phone-call"></i> Call
                                    </button>
                                @endif
                                @if($__quickEmail)
                                    <a href="mailto:{{ $__quickEmail }}" class="cp-quick-btn cp-quick-email"><i class="bx bx-envelope"></i> Email</a>
                                @endif
                                @if($__quickWhatsapp)
                                    <a href="https://wa.me/{{ $__quickWhatsapp }}" target="_blank" class="cp-quick-btn cp-quick-whatsapp"><i class="bx bxl-whatsapp"></i> WhatsApp</a>
                                @endif
                                <button type="button" class="cp-quick-btn" onclick="LogCall.open()"><i class="bx bx-dots-horizontal-rounded"></i> More</button>
                            </div>
                            @if(!$__quickPhone && !$__quickEmail)
                                <p class="text-muted small mb-0 mt-2">Add a contact with a phone or email to enable Call/Email/WhatsApp.</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card cp-card mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0"><i class="bx bx-group me-1 text-primary"></i>Our Team (HRX)</h6>
                                <button class="btn btn-sm btn-primary rounded-pill" onclick="TeamCard.openAdd()"><i class="bx bx-plus"></i></button>
                            </div>
                            @include('tenant.crm-clients.partials.profile-team-list', ['teamByRole' => $teamByRole, 'teamPickable' => $teamPickable])
                        </div>
                    </div>

                    {{-- Real telephony_calls rows (Smart Agent dialer only —
                         see the Calls tab note about no inbound tracking).
                         "Logged" = dispositioned, "Follow-up Due" = the call
                         itself is still open (no disposition/end yet). --}}
                    <div class="card cp-card mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0"><i class="bx bx-phone-call me-1 text-primary"></i>Recent Calls</h6>
                                <a href="javascript:void(0)" class="small fw-semibold" style="color:#127464;" onclick="document.querySelector('[data-bs-target=\'#cpCalls\']').click()">View All</a>
                            </div>
                            @forelse($recentCalls as $call)
                                <div class="cp-call-list-row">
                                    <div class="cp-call-icon" style="background:{{ $call->status?->value === 'ended' ? '#dcfce7' : '#fee2e2' }};color:{{ $call->status?->value === 'ended' ? '#15803d' : '#b91c1c' }};">
                                        <i class="bx {{ $call->status?->value === 'ended' ? 'bx-phone-incoming' : 'bx-phone-outgoing' }}"></i>
                                    </div>
                                    <div class="flex-grow-1" style="min-width:0;">
                                        <div class="fw-semibold small">{{ optional($call->agent)->getFullName() ?: 'Unknown agent' }} <span class="text-muted">→ {{ $call->to_number }}</span></div>
                                        <div class="text-muted small">{{ $call->created_at->format('d M Y, h:i A') }}{{ $call->duration ? ' · '.gmdate('i:s', $call->duration) : '' }}</div>
                                    </div>
                                    <span class="cp-status-chip {{ $call->disposition_code ? 'cp-status-logged' : 'cp-status-pending' }}">
                                        {{ $call->disposition_code ? 'Logged' : 'Follow-up Due' }}
                                    </span>
                                </div>
                            @empty
                                <p class="text-muted small text-center py-3 mb-0">No calls placed through Smart Agent for this account yet.</p>
                            @endforelse
                            @if($__ctcAgentForCalls)
                                <button type="button" class="btn btn-sm rounded-pill fw-bold text-white w-100 mt-2 js-ctc-call" style="background:#127464;" data-phone="{{ $__quickPhone ?? $mapping->phone }}">
                                    <i class="bx bx-phone-call"></i> Make a Call
                                </button>
                            @endif
                        </div>
                    </div>
                    <div class="card cp-card">
                        <div class="card-body">
                            <h6 class="fw-bold mb-2"><i class="bx bx-calendar-check me-1 text-primary"></i>Upcoming Follow-up</h6>
                            @if($__nextFollowUp)
                                <div class="fw-bold">{{ $__nextFollowUp->follow_up_due_at ? $__nextFollowUp->follow_up_due_at->format('d M Y') : 'No date set' }}</div>
                                <div class="text-muted small mb-2">{{ \Illuminate\Support\Str::limit($__nextFollowUp->body, 70) }}</div>
                                <a href="javascript:void(0)" class="small fw-semibold" style="color:#127464;" onclick="document.querySelector('[data-bs-target=\'#cpTasks\']').click()">View all tasks</a>
                            @else
                                <p class="cp-followup-empty mb-0">No upcoming follow-ups.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

                    {{-- Every activity (comment or call) lands in the SAME
                         feed -- shown right here too, not just tucked away
                         in the Calls/Activities tabs, since a call is the
                         most common thing that happens on this page. --}}
                    <div class="card cp-card mt-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0"><i class="bx bx-time-five me-1 text-primary"></i>Activity &amp; Calls</h6>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm btn-outline-success rounded-pill" onclick="LogCall.open()"><i class="bx bx-phone-call"></i> Log Call</button>
                                    <a href="javascript:void(0)" class="btn btn-sm btn-outline-secondary rounded-pill" onclick="document.querySelector('[data-bs-target=\'#cpActivities\']').click()">View All</a>
                                </div>
                            </div>
                            <div class="mb-3 p-3 rounded-3" style="background:#f8fafc;">
                                <textarea id="cpOverviewCommentBody" class="form-control mb-2" rows="2" placeholder="Add a note about this customer..."></textarea>
                                <div class="text-end"><button class="btn btn-sm btn-primary rounded-pill" id="cpOverviewPostBtn" onclick="postOverviewComment()"><i class="bx bx-send"></i> Post</button></div>
                            </div>
                            <div id="cpOverviewActivityFeed">
                                @forelse($activities->take(5) as $activity)
                                    @include('tenant.crm-clients.partials.profile-activity-row', ['activity' => $activity])
                                @empty
                                    <p class="text-muted small text-center py-3 mb-0">No activity yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
        </div>

        {{-- ══════════════════════════════ CONTACTS ══════════════════════════════ --}}
        <div class="tab-pane fade" id="cpContacts">
            <div class="card cp-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0">All Contacts</h6>
                        <button class="btn btn-sm btn-primary rounded-pill" onclick="openContactModal()"><i class="bx bx-plus"></i> Add Contact</button>
                    </div>
                    <div id="cpContactsListFull">
                        @include('tenant.crm-clients.partials.profile-contacts-list', ['contacts' => $contacts])
                    </div>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════ SALES & REVENUE ══════════════════════════════ --}}
        <div class="tab-pane fade" id="cpSales">
            <div class="card cp-card mb-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-2">Sales Trend</h6>
                    <div id="cpSalesTrendChartBig"></div>
                </div>
            </div>
            <div class="card cp-card">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Linked Sales Report Rows</h6>
                    @if($pipelines->isEmpty())
                        <p class="text-muted small mb-0">No Sales Report rows are reconciled to this CRM account yet.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead><tr><th>Party Name</th><th>Salesperson</th><th class="text-end">This Month Sale</th><th class="text-end">Total on File</th></tr></thead>
                                <tbody>
                                    @foreach($pipelines as $p)
                                        @php $thisMonth = $p->months->firstWhere('month_year', now()->format('Y-m')); @endphp
                                        <tr>
                                            <td>{{ $p->party_name }}</td>
                                            <td>{{ optional($p->salesperson)->getFullName() ?: '—' }}</td>
                                            <td class="text-end">₹{{ number_format($thisMonth->sale_amount ?? 0, 2) }}L</td>
                                            <td class="text-end">₹{{ number_format($p->months->sum('sale_amount'), 2) }}L</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════ CALLS ══════════════════════════════ --}}
        <div class="tab-pane fade" id="cpCalls">
            <div class="card cp-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0">Calls</h6>
                        <button class="btn btn-sm btn-primary rounded-pill" onclick="LogCall.open()"><i class="bx bx-plus"></i> Log Call</button>
                    </div>
                    @forelse($recentCalls as $call)
                        <div class="cp-call-row d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold small">{{ optional($call->agent)->getFullName() ?: 'Unknown agent' }} <span class="text-muted">→ {{ $call->to_number }}</span></div>
                                <div class="text-muted small">{{ $call->created_at->format('d M Y, h:i A') }} · {{ $call->duration ? gmdate('i:s', $call->duration) : '—' }}</div>
                            </div>
                            <span class="badge bg-label-{{ $call->status?->value === 'ended' ? 'success' : 'secondary' }}">{{ $call->vendor_disposition ?: $call->status?->value }}</span>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">No calls placed through Smart Agent for this account yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════ TASKS ══════════════════════════════ --}}
        <div class="tab-pane fade" id="cpTasks">
            <div class="card cp-card">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Open Follow-ups</h6>
                    @forelse($openFollowUps as $fu)
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <div>
                                <div class="small">{{ \Illuminate\Support\Str::limit($fu->body, 100) }}</div>
                                <div class="text-muted small">
                                    @if($fu->follow_up_due_at)Due {{ $fu->follow_up_due_at->format('d M Y') }}@endif
                                    @if($fu->followUpAssignedTo) · {{ $fu->followUpAssignedTo->first_name }}@endif
                                </div>
                            </div>
                            <button class="btn btn-sm btn-outline-success rounded-pill" onclick="completeFollowUp({{ $fu->id }}, this)"><i class="bx bx-check"></i> Done</button>
                        </div>
                    @empty
                        <p class="text-muted small mb-0">No open follow-ups.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════ ACTIVITIES ══════════════════════════════ --}}
        <div class="tab-pane fade" id="cpActivities">
            <div class="card cp-card">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Activity Feed</h6>
                    <div class="mb-4 p-3 rounded-3" style="background:#f8fafc;">
                        <textarea id="cpNewCommentBody" class="form-control mb-2" rows="2" placeholder="Add a note about this customer..."></textarea>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="cpNewCommentFollowUp">
                                <label class="form-check-label small" for="cpNewCommentFollowUp">Needs follow-up</label>
                                <input type="date" id="cpNewCommentDueAt" class="form-control form-control-sm d-inline-block ms-2 d-none" style="width:150px;">
                            </div>
                            <button class="btn btn-sm btn-primary rounded-pill" id="cpPostCommentBtn" onclick="postComment()"><i class="bx bx-send"></i> Post</button>
                        </div>
                    </div>
                    <div id="cpActivityFeed">
                        @forelse($activities as $activity)
                            @include('tenant.crm-clients.partials.profile-activity-row', ['activity' => $activity])
                        @empty
                            <p class="text-muted small text-center py-3">No activity yet.</p>
                        @endforelse
                    </div>
                    {{ $activities->links() }}
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════ NOTES ══════════════════════════════ --}}
        <div class="tab-pane fade" id="cpNotes">
            <div class="card cp-card">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Notes</h6>
                    <div class="mb-4 p-3 rounded-3" style="background:#f8fafc;">
                        <textarea id="cpNewNoteBody" class="form-control mb-2" rows="2" placeholder="Write a note..."></textarea>
                        <div class="text-end"><button class="btn btn-sm btn-primary rounded-pill" onclick="postNote()"><i class="bx bx-send"></i> Save Note</button></div>
                    </div>
                    <div id="cpNotesList">
                        @forelse($activities->filter(fn($a) => $a->type?->value === 'comment') as $note)
                            @include('tenant.crm-clients.partials.profile-activity-row', ['activity' => $note])
                        @empty
                            <p class="text-muted small text-center py-3">No notes yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Modals (contact, log-call) ──────────────────────────────────────── --}}
<div class="modal fade" id="cpContactModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h6 class="modal-title" id="cpContactModalTitle">Add Contact</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" id="cp_contact_id">
                <div class="mb-3"><label class="form-label fw-bold">Name *</label><input type="text" id="cp_contact_name" class="form-control"></div>
                <div class="mb-3"><label class="form-label fw-bold">Role *</label>
                    <select id="cp_contact_role" class="form-select">
                        <option value="purchase">Purchase</option><option value="finance">Finance</option>
                        <option value="accounts">Accounts</option><option value="admin">Admin</option><option value="other">Other</option>
                    </select>
                </div>
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="cp_contact_is_primary"><label class="form-check-label">Primary contact</label></div>
                <div class="mb-3"><label class="form-label fw-bold">Email</label><input type="email" id="cp_contact_email" class="form-control"></div>
                <div class="mb-3"><label class="form-label fw-bold">Phone</label><input type="text" id="cp_contact_phone" class="form-control"></div>
                <div class="mb-1"><label class="form-label fw-bold">Notes</label><textarea id="cp_contact_notes" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary rounded-pill px-4" onclick="saveContact()" id="cpSaveContactBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="cpLogCallModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h6 class="modal-title"><i class="bx bx-phone-call me-1"></i>Log Call &amp; Add Notes</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-2 mb-2">
                    <div class="col-6"><label class="form-label fw-bold small">Call Outcome *</label>
                        <select id="cpCallOutcome" class="form-select form-select-sm">
                            <option>Discussion</option><option>Order Placed</option><option>Follow-up Needed</option>
                            <option>No Response</option><option>Complaint</option><option>Other</option>
                        </select>
                    </div>
                    <div class="col-6"><label class="form-label fw-bold small">Call Type *</label>
                        <select id="cpCallType" class="form-select form-select-sm">
                            <option>Outbound</option><option>Inbound</option>
                        </select>
                    </div>
                </div>
                <div class="mb-2"><label class="form-label fw-bold small">Call Notes *</label><textarea id="cpCallNotes" class="form-control" rows="3"></textarea></div>
                <div class="row g-2 mb-2">
                    <div class="col-6"><label class="form-label fw-bold small">Next Follow-up</label><input type="date" id="cpCallNextFollowUp" class="form-control form-control-sm"></div>
                    <div class="col-6"><label class="form-label fw-bold small">Follow-up Type</label>
                        <select id="cpCallFollowUpType" class="form-select form-select-sm">
                            <option value="">— None —</option><option>Call</option><option>Meeting</option><option>Email</option>
                        </select>
                    </div>
                </div>
                <div class="form-check"><input class="form-check-input" type="checkbox" id="cpCallSetReminder" checked><label class="form-check-label small">Set Reminder</label></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary rounded-pill px-4" id="cpSaveCallLogBtn" onclick="LogCall.save()">Save Call Log</button>
            </div>
        </div>
    </div>
</div>

<div class="d-none">
    @include('tenant.users.dashboard._click-to-call')
</div>
@endsection

@section('page-script')
<script>
const CP_MAPPING_ID = {{ $mapping->id }};
const CP_CSRF = '{{ csrf_token() }}';
const CP_ROUTES = {
    storeContact:     '{{ route('crm-clients.contacts.store', $mapping->id) }}',
    updateContact:    (id) => `{{ url('crm-clients/contacts') }}/${id}`,
    destroyContact:   (id) => `{{ url('crm-clients/contacts') }}/${id}`,
    storeActivity:    '{{ route('crm-clients.activities.store', $mapping->id) }}',
    completeFollowUp: (id) => `{{ url('crm-clients/activities') }}/${id}/complete-follow-up`,
    addTeamMember:    '{{ route('crm-clients.team.store', $mapping->id) }}',
    removeTeamMember: (userId, role) => `{{ url('crm-clients') }}/{{ $mapping->id }}/team/${userId}/${role}`,
    salesTrend:       '{{ route('crm-clients.profile.sales-trend', $mapping->id) }}',
    logCall:          '{{ route('crm-clients.profile.log-call', $mapping->id) }}',
};

function swalOk(m) { if (window.showSuccessSwal) window.showSuccessSwal(m); else if (window.Swal) Swal.fire({icon:'success', title:'Done', text:m}); }
function swalErr(m) { if (window.showErrorSwal) window.showErrorSwal(m); else if (window.Swal) Swal.fire({icon:'error', title:'Error', text:m}); else alert(m); }

// ── Contacts (shares the same backend routes as the classic Customer 360) ──
function openContactModal(contact = null) {
    document.getElementById('cpContactModalTitle').textContent = contact ? 'Edit Contact' : 'Add Contact';
    document.getElementById('cp_contact_id').value = contact ? contact.id : '';
    document.getElementById('cp_contact_name').value = contact ? contact.name : '';
    document.getElementById('cp_contact_role').value = contact ? (contact.role.value || contact.role) : 'purchase';
    document.getElementById('cp_contact_email').value = contact ? (contact.email || '') : '';
    document.getElementById('cp_contact_phone').value = contact ? (contact.phone || '') : '';
    document.getElementById('cp_contact_notes').value = contact ? (contact.notes || '') : '';
    document.getElementById('cp_contact_is_primary').checked = !!(contact && contact.is_primary);
    new bootstrap.Modal(document.getElementById('cpContactModal')).show();
}
function saveContact() {
    const id = document.getElementById('cp_contact_id').value;
    const payload = {
        name: document.getElementById('cp_contact_name').value,
        role: document.getElementById('cp_contact_role').value,
        email: document.getElementById('cp_contact_email').value,
        phone: document.getElementById('cp_contact_phone').value,
        notes: document.getElementById('cp_contact_notes').value,
        is_primary: document.getElementById('cp_contact_is_primary').checked,
    };
    if (!payload.name) { alert('Name is required.'); return; }
    const btn = document.getElementById('cpSaveContactBtn');
    btn.disabled = true;
    fetch(id ? CP_ROUTES.updateContact(id) : CP_ROUTES.storeContact, {
        method: id ? 'PUT' : 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CP_CSRF, 'Accept': 'application/json' },
        body: JSON.stringify(payload),
    }).then(r => r.json()).then(data => {
        if (data.success) { location.reload(); } else { alert(data.message || 'Save failed.'); }
    }).finally(() => { btn.disabled = false; });
}
function deleteContact(id) {
    if (!confirm('Remove this contact?')) return;
    fetch(CP_ROUTES.destroyContact(id), { method: 'DELETE', headers: { 'X-CSRF-TOKEN': CP_CSRF, 'Accept': 'application/json' } })
        .then(r => r.json()).then(data => { if (data.success) location.reload(); });
}
function callContact(phone) {
    const modalEl = document.getElementById('clickToCallModal');
    if (!modalEl) return;
    document.getElementById('ctcPhone').value = phone;
    document.getElementById('ctcResult').classList.add('d-none');
    document.getElementById('ctcLive').classList.add('d-none');
    new bootstrap.Modal(modalEl).show();
}
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.js-ctc-call');
    if (btn) callContact(btn.dataset.phone);
});

// ── Team (shares the same backend routes) ──
const TeamCard = {
    openAdd() { document.getElementById('cpTeamAddRow').classList.remove('d-none'); },
    closeAdd() { document.getElementById('cpTeamAddRow').classList.add('d-none'); },
    add() {
        const userId = document.getElementById('cpTeamAddUser').value;
        const role = document.getElementById('cpTeamAddRole').value;
        if (!userId) { swalErr('Pick a person first'); return; }
        fetch(CP_ROUTES.addTeamMember, {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CP_CSRF },
            body: JSON.stringify({ user_id: userId, role: role }),
        }).then(r => r.json()).then(d => { if (!d.success) { swalErr('Could not add'); return; } location.reload(); });
    },
    remove(userId, role) {
        if (!confirm('Remove this person from the team?')) return;
        fetch(CP_ROUTES.removeTeamMember(userId, role), { method: 'DELETE', headers: { 'X-CSRF-TOKEN': CP_CSRF } })
            .then(r => r.json()).then(d => { if (!d.success) { swalErr('Could not remove'); return; } location.reload(); });
    },
};

// ── Activities / Notes ──
function postComment() {
    const body = document.getElementById('cpNewCommentBody').value.trim();
    if (!body) return;
    const needsFollowUp = document.getElementById('cpNewCommentFollowUp').checked;
    const btn = document.getElementById('cpPostCommentBtn');
    btn.disabled = true;
    fetch(CP_ROUTES.storeActivity, {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CP_CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ body, needs_follow_up: needsFollowUp, follow_up_due_at: needsFollowUp ? document.getElementById('cpNewCommentDueAt').value : null }),
    }).then(r => r.json()).then(data => { if (data.success) location.reload(); else alert(data.message || 'Could not post.'); })
      .finally(() => { btn.disabled = false; });
}
function postNote() {
    const body = document.getElementById('cpNewNoteBody').value.trim();
    if (!body) return;
    fetch(CP_ROUTES.storeActivity, {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CP_CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ body, needs_follow_up: false }),
    }).then(r => r.json()).then(data => { if (data.success) location.reload(); else alert(data.message || 'Could not post.'); });
}
function postOverviewComment() {
    const body = document.getElementById('cpOverviewCommentBody').value.trim();
    if (!body) return;
    const btn = document.getElementById('cpOverviewPostBtn');
    btn.disabled = true;
    fetch(CP_ROUTES.storeActivity, {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CP_CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ body, needs_follow_up: false }),
    }).then(r => r.json()).then(data => { if (data.success) location.reload(); else alert(data.message || 'Could not post.'); })
      .finally(() => { btn.disabled = false; });
}
document.getElementById('cpNewCommentFollowUp')?.addEventListener('change', function () {
    document.getElementById('cpNewCommentDueAt').classList.toggle('d-none', !this.checked);
});
function completeFollowUp(id, btn) {
    fetch(CP_ROUTES.completeFollowUp(id), { method: 'POST', headers: { 'X-CSRF-TOKEN': CP_CSRF, 'Accept': 'application/json' } })
        .then(r => r.json()).then(d => { if (d.success) { btn.closest('div.d-flex').remove(); } });
}

// ── Manual Call Log ──
const LogCall = {
    open() { new bootstrap.Modal(document.getElementById('cpLogCallModal')).show(); },
    save() {
        const notes = document.getElementById('cpCallNotes').value.trim();
        if (!notes) { swalErr('Call notes are required.'); return; }
        const btn = document.getElementById('cpSaveCallLogBtn');
        btn.disabled = true;
        fetch(CP_ROUTES.logCall, {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CP_CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({
                call_outcome: document.getElementById('cpCallOutcome').value,
                call_type: document.getElementById('cpCallType').value,
                notes: notes,
                next_follow_up: document.getElementById('cpCallNextFollowUp').value || null,
                follow_up_type: document.getElementById('cpCallFollowUpType').value || null,
                set_reminder: document.getElementById('cpCallSetReminder').checked,
            }),
        }).then(r => r.json()).then(d => {
            if (!d.success) { swalErr(d.message || 'Could not save.'); return; }
            swalOk('Call logged.');
            location.reload();
        }).finally(() => { btn.disabled = false; });
    },
};

// ── Sales trend chart (ApexCharts, deferred-loaded like the rest of HRX) ──
(async function () {
    for (let i = 0; i < 200 && !window.ApexCharts; i++) { await new Promise(r => setTimeout(r, 25)); }
    if (!window.ApexCharts) return;

    let chart = null, chartBig = null;
    function render(labels, values) {
        const opts = {
            chart: { type: 'area', height: 220, toolbar: { show: false }, fontFamily: 'inherit' },
            series: [{ name: 'Sales (₹L)', data: values }],
            xaxis: { categories: labels, axisBorder: { show: false }, axisTicks: { show: false } },
            yaxis: { labels: { formatter: (v) => '₹' + v + 'L' } },
            grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
            colors: ['#127464'], stroke: { curve: 'smooth', width: 3 },
            markers: { size: 4, colors: ['#127464'], strokeColors: '#fff', strokeWidth: 2 },
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.02, stops: [0, 90] } },
            dataLabels: { enabled: false },
            tooltip: { y: { formatter: (v) => '₹' + v + 'L' } },
        };
        if (chart) chart.destroy();
        chart = new ApexCharts(document.querySelector('#cpSalesTrendChart'), opts);
        chart.render();
        if (chartBig) chartBig.destroy();
        chartBig = new ApexCharts(document.querySelector('#cpSalesTrendChartBig'), { ...opts, chart: { ...opts.chart, height: 300 } });
        chartBig.render();
    }
    function load(range) {
        fetch(CP_ROUTES.salesTrend + '?range=' + range).then(r => r.json()).then(d => render(d.labels, d.values));
    }
    document.querySelectorAll('.cp-range-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.cp-range-btn').forEach(b => b.classList.replace('btn-primary', 'btn-outline-secondary'));
            this.classList.replace('btn-outline-secondary', 'btn-primary');
            load(this.dataset.range);
        });
    });
    load('1Y');

    // Health score radial gauge
    const healthChart = new ApexCharts(document.querySelector('#cpHealthRing'), {
        chart: { type: 'radialBar', height: 96, width: 96, sparkline: { enabled: true } },
        series: [{{ $health['score'] }}],
        plotOptions: { radialBar: { hollow: { size: '55%' }, dataLabels: { name: { show: false }, value: { offsetY: 6, fontSize: '16px', fontWeight: 700, formatter: (v) => v + '%' } } } },
        colors: ['{{ $health['score'] >= 75 ? '#22c55e' : ($health['score'] >= 50 ? '#f59e0b' : '#ef4444') }}'],
    });
    healthChart.render();
})();
</script>
@endsection
