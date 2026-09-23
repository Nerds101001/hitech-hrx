@extends('layouts.layoutMaster')

@section('title', $mapping->crm_company_name . ' — Customer 360')

{{-- SweetAlert2 isn't bundled globally -- the click-to-call result (and the
     Internal Team add/remove error paths) need it for a styled popup instead
     of a native alert(). --}}
@section('vendor-style')
    @vite(['resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'])
@endsection

@section('vendor-script')
    @vite(['resources/assets/vendor/libs/sweetalert2/sweetalert2.js'])
@endsection

@section('page-style')
<style>
    .crm-header-gradient {
        background: linear-gradient(135deg, #0f4c75 0%, #1b6ca8 50%, #127464 100%);
        border-radius: 16px;
        padding: 2rem;
        color: #fff;
        margin-bottom: 1.5rem;
        position: relative;
        overflow: hidden;
    }
    .crm-header-gradient::after {
        content: '';
        position: absolute;
        bottom: -60px; left: -20px;
        width: 200px; height: 200px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
    }
    .stage-badge {
        font-size: 0.65rem; font-weight: 700; padding: 3px 10px;
        border-radius: 20px; letter-spacing: 0.5px; text-transform: uppercase;
    }
    .role-pill {
        font-size: 0.62rem; padding: 2px 8px; border-radius: 20px;
        font-weight: 700; display: inline-block;
    }
    .role-purchase { background: #dbeafe; color: #1d4ed8; }
    .role-finance  { background: #fce7f3; color: #be185d; }
    .role-accounts { background: #dcfce7; color: #15803d; }
    .role-admin    { background: #ede9fe; color: #7c3aed; }
    .role-other    { background: #f1f5f9; color: #64748b; }
    .c360-card { border: 1px solid #e2e8f0; border-radius: 14px; }
    .team-slot { display: flex; align-items: center; gap: 8px; padding: 8px 0; border-bottom: 1px dashed #eef2f7; }
    .team-slot:last-child { border-bottom: 0; }
    .team-avatar {
        width: 30px; height: 30px; border-radius: 50%; color: #fff;
        font-size: 0.65rem; font-weight: 700; display: inline-flex;
        align-items: center; justify-content: center; flex-shrink: 0;
    }
    .contact-row { border: 1px solid #eef2f7; border-radius: 10px; padding: 10px 12px; margin-bottom: 8px; }
    .activity-item { display: flex; gap: 10px; padding: 12px 0; border-bottom: 1px solid #f1f5f9; }
    .activity-item:last-child { border-bottom: 0; }
    .activity-icon {
        width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center; color: #fff; font-size: 0.85rem;
    }
    .activity-icon.type-call    { background: #1b6ca8; }
    .activity-icon.type-comment { background: #127464; }
    .activity-icon.type-system  { background: #94a3b8; }
    .follow-up-chip {
        font-size: 0.72rem; padding: 2px 8px; border-radius: 20px;
        background: #fef3c7; color: #92400e; font-weight: 600;
    }
</style>
@endsection

@section('content')
<div class="animate__animated animate__fadeIn">

    <a href="{{ route('crm-clients.index') }}" class="text-decoration-none small text-muted mb-2 d-inline-block">
        <i class="bx bx-arrow-back"></i> Back to Client Mappings
    </a>

    {{-- Header --}}
    <div class="crm-header-gradient">
        <div class="position-relative" style="z-index:1;">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <h4 class="fw-extrabold mb-1 text-white">
                        <i class="bx bx-building-house me-2"></i>{{ $mapping->crm_company_name }}
                    </h4>
                    <p class="mb-1 text-white opacity-75 small">
                        <code class="px-2 py-1 rounded fw-bold" style="background:rgba(255,255,255,.9);color:#127464;">{{ $mapping->crm_company_code }}</code>
                        @if($mapping->crm_city)
                            &nbsp;<i class="bx bx-map-pin"></i> {{ $mapping->crm_city }}{{ $mapping->crm_state ? ', '.$mapping->crm_state : '' }}
                        @endif
                    </p>
                    @if($mapping->crm_stage)
                        <span class="stage-badge" style="background:rgba(255,255,255,.9);color:#127464;">{{ $mapping->crm_stage }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @php
        // Same agent lookup the dashboard's dialpad uses (ClickToCallService::agentFor)
        // -- the logged-in user's own campaign/agent/client_id (or Convoque extension),
        // so whoever is signed in dials with their own telephony identity.
        $__ctcAgent = app(\App\Services\ClickToCallService::class)->agentFor(auth()->user());
    @endphp
    <div class="row g-4">
        {{-- LEFT: Contacts + Team --}}
        <div class="col-lg-4">
            {{-- Contacts card --}}
            <div class="card c360-card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0"><i class="bx bx-id-card me-1 text-success"></i>Customer Contacts</h6>
                        <button class="btn btn-sm btn-primary rounded-pill" onclick="openContactModal()">
                            <i class="bx bx-plus"></i>
                        </button>
                    </div>

                    <div id="contactsList">
                        @forelse($contacts as $contact)
                            <div class="contact-row" id="contact-row-{{ $contact->id }}">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div style="min-width:0;">
                                        <div class="fw-semibold" style="font-size:0.85rem;">
                                            {{ $contact->name }}
                                            @if($contact->is_primary)<i class="bx bxs-star text-warning" style="font-size:0.7rem;" title="Primary"></i>@endif
                                        </div>
                                        <span class="role-pill role-{{ $contact->role->value }}">{{ $contact->role->label() }}</span>
                                        @if($contact->email)<div class="text-muted mt-1" style="font-size:0.76rem;"><i class="bx bx-envelope"></i> {{ $contact->email }}</div>@endif
                                        @if($contact->phone)<div class="text-muted" style="font-size:0.76rem;"><i class="bx bx-phone"></i> {{ $contact->phone }}</div>@endif
                                    </div>
                                    <div class="d-flex gap-1 flex-shrink-0">
                                        @if($contact->phone)
                                            @if($__ctcAgent)
                                                {{-- Dials through Smart Agent using the SIGNED-IN user's own
                                                     telephony identity (ClickToCallService::agentFor) -- same
                                                     dialer the header's Click to Call widget uses, just aimed
                                                     straight at this contact's number instead of a typed one. --}}
                                                <button type="button" class="btn btn-sm btn-icon btn-outline-success rounded-circle js-ctc-call"
                                                    style="width:26px;height:26px;padding:0;"
                                                    data-phone="{{ $contact->phone }}" data-name="{{ $contact->name }}"
                                                    title="Call {{ $contact->name }} via Smart Agent">
                                                    <i class="bx bx-phone-call" style="font-size:0.75rem;"></i>
                                                </button>
                                            @else
                                                <a class="btn btn-sm btn-icon btn-outline-success rounded-circle" style="width:26px;height:26px;padding:0;"
                                                    href="tel:{{ $contact->phone }}" title="Call {{ $contact->name }}">
                                                    <i class="bx bx-phone-call" style="font-size:0.75rem;"></i>
                                                </a>
                                            @endif
                                        @endif
                                        <button class="btn btn-sm btn-icon btn-outline-primary rounded-circle" style="width:26px;height:26px;padding:0;"
                                            onclick='openContactModal(@json($contact))'>
                                            <i class="bx bx-edit-alt" style="font-size:0.75rem;"></i>
                                        </button>
                                        <button class="btn btn-sm btn-icon btn-outline-danger rounded-circle" style="width:26px;height:26px;padding:0;"
                                            onclick="deleteContact({{ $contact->id }})">
                                            <i class="bx bx-trash" style="font-size:0.75rem;"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted small text-center py-3 mb-0">No contacts yet — add their purchase or finance team here.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Team card --}}
            <div class="card c360-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0"><i class="bx bx-group me-1 text-primary"></i>Internal Team</h6>
                        <button class="btn btn-sm btn-primary rounded-pill" onclick="TeamCard.openAdd()">
                            <i class="bx bx-plus"></i>
                        </button>
                    </div>
                    @php
                        $teamRoleMeta = [
                            'sales_person' => ['label' => 'Sales Person', 'color' => '#7c3aed'],
                            'ccare'        => ['label' => 'CCare',       'color' => '#1d4ed8'],
                            'new_biz'      => ['label' => 'New Biz',     'color' => '#a16207'],
                            'billing'      => ['label' => 'Billing',     'color' => '#15803d'],
                            'finance'      => ['label' => 'Finance',     'color' => '#be185d'],
                            'other'        => ['label' => 'Other',       'color' => '#64748b'],
                        ];
                    @endphp
                    <div id="teamCardList">
                        @foreach($teamRoleMeta as $roleKey => $meta)
                            @php $members = $teamByRole->get($roleKey, collect()); @endphp
                            @if($members->isNotEmpty())
                                @foreach($members as $member)
                                    <div class="team-slot" data-user-id="{{ $member->user_id }}" data-role="{{ $roleKey }}">
                                        <div class="team-avatar" style="background:{{ $meta['color'] }};">{{ substr($member->user->first_name,0,1) }}{{ substr($member->user->last_name,0,1) }}</div>
                                        <div class="flex-grow-1" style="min-width:0;">
                                            <div class="fw-semibold text-truncate" style="font-size:0.85rem;">{{ $member->user->first_name }} {{ $member->user->last_name }}</div>
                                            <div class="text-muted" style="font-size:0.7rem;">{{ $meta['label'] }}</div>
                                        </div>
                                        <button type="button" class="btn btn-sm text-danger p-0 border-0" title="Remove"
                                                onclick="TeamCard.remove({{ $member->user_id }}, '{{ $roleKey }}', this)">
                                            <i class="bx bx-x fs-5"></i>
                                        </button>
                                    </div>
                                @endforeach
                            @else
                                <div class="team-slot" data-role="{{ $roleKey }}">
                                    <div class="team-avatar" style="background:#e2e8f0;color:#94a3b8;"><i class="bx bx-user"></i></div>
                                    <div class="flex-grow-1">
                                        <div class="text-muted" style="font-size:0.8rem;">Not assigned</div>
                                        <div class="text-muted" style="font-size:0.7rem;">{{ $meta['label'] }}</div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>

                    {{-- Add-member row: hidden until "+" is clicked --}}
                    <div id="teamAddRow" class="d-none mt-3 pt-3" style="border-top:1px dashed #eef2f7;">
                        <select id="teamAddUser" class="form-select form-select-sm mb-2">
                            <option value="">— choose person —</option>
                            @foreach($teamPickable as $p)
                                <option value="{{ $p->id }}">{{ $p->first_name }} {{ $p->last_name }}</option>
                            @endforeach
                        </select>
                        <select id="teamAddRole" class="form-select form-select-sm mb-2">
                            @foreach($teamRoleMeta as $roleKey => $meta)
                                <option value="{{ $roleKey }}">{{ $meta['label'] }}</option>
                            @endforeach
                        </select>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-success flex-grow-1" onclick="TeamCard.add()">Add</button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="TeamCard.closeAdd()">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- RIGHT: Activity feed --}}
        <div class="col-lg-8">
            <div class="card c360-card">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bx bx-time-five me-1 text-primary"></i>Activity &amp; Calls</h6>

                    @if($openFollowUps->isNotEmpty())
                        <div class="p-3 mb-3 rounded-3" style="background:#fffbeb;border:1px solid #fde68a;">
                            <div class="fw-bold small mb-2 text-warning-emphasis"><i class="bx bx-alarm-exclamation me-1"></i>Open Follow-ups ({{ $openFollowUps->count() }})</div>
                            @foreach($openFollowUps as $fu)
                                <div class="d-flex justify-content-between align-items-center py-1" style="font-size:0.8rem;">
                                    <span>{{ \Illuminate\Support\Str::limit($fu->body, 70) }}
                                        @if($fu->follow_up_due_at)<span class="text-muted">— due {{ $fu->follow_up_due_at->format('d M') }}</span>@endif
                                        @if($fu->followUpAssignedTo)<span class="text-muted"> ({{ $fu->followUpAssignedTo->first_name }})</span>@endif
                                    </span>
                                    <button class="btn btn-sm btn-outline-success rounded-pill py-0 px-2" style="font-size:0.72rem;" onclick="completeFollowUp({{ $fu->id }})">
                                        <i class="bx bx-check"></i> Done
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Post a comment --}}
                    <div class="mb-4 p-3 rounded-3" style="background:#f8fafc;">
                        <textarea id="newCommentBody" class="form-control mb-2" rows="2" placeholder="Add a note about this customer..."></textarea>
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" id="newCommentFollowUp" onchange="document.getElementById('newCommentDueWrap').style.display = this.checked ? 'inline-block' : 'none';">
                                    <label class="form-check-label small" for="newCommentFollowUp">Needs follow-up</label>
                                </div>
                                <span id="newCommentDueWrap" style="display:none;">
                                    <input type="date" id="newCommentDueAt" class="form-control form-control-sm" style="width:150px;display:inline-block;">
                                </span>
                            </div>
                            <button class="btn btn-primary btn-sm rounded-pill px-3" onclick="postComment()" id="postCommentBtn">
                                <i class="bx bx-send me-1"></i>Post
                            </button>
                        </div>
                    </div>

                    {{-- Feed --}}
                    <div id="activityFeed">
                        @forelse($activities as $activity)
                            <div class="activity-item">
                                <div class="activity-icon type-{{ $activity->type->value }}">
                                    <i class="bx {{ $activity->type->value === 'call' ? 'bx-phone' : ($activity->type->value === 'system' ? 'bx-cog' : 'bx-comment-detail') }}"></i>
                                </div>
                                <div class="flex-grow-1" style="min-width:0;">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-1">
                                        <span class="fw-semibold" style="font-size:0.82rem;">{{ $activity->createdBy->first_name ?? 'System' }} {{ $activity->createdBy->last_name ?? '' }}</span>
                                        <span class="text-muted" style="font-size:0.72rem;">{{ $activity->created_at->diffForHumans() }}</span>
                                    </div>
                                    <div style="font-size:0.83rem;white-space:pre-line;">{{ $activity->body }}</div>
                                    @if($activity->needs_follow_up)
                                        <span class="follow-up-chip mt-1 d-inline-block">
                                            <i class="bx bx-alarm-exclamation"></i>
                                            {{ $activity->follow_up_completed_at ? 'Follow-up done' : 'Needs follow-up' }}
                                            @if($activity->follow_up_due_at && !$activity->follow_up_completed_at) — {{ $activity->follow_up_due_at->format('d M') }}@endif
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="text-muted small text-center py-4 mb-0">No activity yet.</p>
                        @endforelse
                    </div>

                    <div class="mt-3">
                        {{ $activities->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Contact Modal --}}
<div class="modal fade" id="contactModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius:14px;border:0;">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="contactModalTitle">Add Contact</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="contact_id">
                <div class="mb-3">
                    <label class="form-label fw-bold">Name <span class="text-danger">*</span></label>
                    <input type="text" id="contact_name" class="form-control" required>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label fw-bold">Role <span class="text-danger">*</span></label>
                        <select id="contact_role" class="form-select">
                            <option value="purchase">Purchase</option>
                            <option value="finance">Finance</option>
                            <option value="accounts">Accounts</option>
                            <option value="admin">Admin</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-bold">&nbsp;</label>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="contact_is_primary">
                            <label class="form-check-label" for="contact_is_primary">Primary contact</label>
                        </div>
                    </div>
                </div>
                <div class="mb-3 mt-2">
                    <label class="form-label fw-bold">Email</label>
                    <input type="email" id="contact_email" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Phone</label>
                    <input type="text" id="contact_phone" class="form-control">
                </div>
                <div class="mb-1">
                    <label class="form-label fw-bold">Notes</label>
                    <textarea id="contact_notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary rounded-pill px-4" onclick="saveContact()" id="saveContactBtn">Save</button>
            </div>
        </div>
    </div>
</div>

{{-- Same "Click to Call" dialpad the Employee Dashboard uses -- its own
     launch button is kept out of sight here (the Customer Contacts card's
     per-contact call icon opens it instead, pre-filled), but the modal and
     its dial/hangup/dispose JS are unchanged, so the popup looks and behaves
     exactly the same as everywhere else it's used. --}}
<div class="d-none">
    @include('tenant.users.dashboard._click-to-call')
</div>

@endsection

@section('page-script')
<script>
const MAPPING_ID = {{ $mapping->id }};
const CSRF = '{{ csrf_token() }}';
const ROUTES = {
    storeContact:    '{{ route('crm-clients.contacts.store', $mapping->id) }}',
    updateContact:   (id) => `{{ url('crm-clients/contacts') }}/${id}`,
    destroyContact:  (id) => `{{ url('crm-clients/contacts') }}/${id}`,
    storeActivity:   '{{ route('crm-clients.activities.store', $mapping->id) }}',
    completeFollowUp:(id) => `{{ url('crm-clients/activities') }}/${id}/complete-follow-up`,
    addTeamMember:   '{{ route('crm-clients.team.store', $mapping->id) }}',
    removeTeamMember:(userId, role) => `{{ url('crm-clients') }}/{{ $mapping->id }}/team/${userId}/${role}`,
};

// Click-to-call from a Customer Contact row -- opens the SAME dialpad modal
// as the Employee Dashboard's "Click to Call" (hidden-but-included above),
// pre-filled with this contact's number, instead of a bare toast. The modal's
// own script (see _click-to-call.blade.php) owns dial/hangup/dispose from here.
function callContact(phone) {
    const modalEl = document.getElementById('clickToCallModal');
    if (!modalEl) return; // no dialer agent mapped to this login -- nothing to open

    document.getElementById('ctcPhone').value = phone;
    document.getElementById('ctcResult').classList.add('d-none');
    document.getElementById('ctcLive').classList.add('d-none');
    new bootstrap.Modal(modalEl).show();
}

document.addEventListener('click', function (e) {
    const btn = e.target.closest('.js-ctc-call');
    if (btn) callContact(btn.dataset.phone);
});

// Internal Team add/remove. Reloads on success rather than patching the DOM —
// the card has several per-role empty/populated states that would be easy to
// get subtly wrong by hand; a full reload is simple and always correct.
const TeamCard = {
    openAdd() { document.getElementById('teamAddRow').classList.remove('d-none'); },
    closeAdd() { document.getElementById('teamAddRow').classList.add('d-none'); },
    add() {
        const userId = document.getElementById('teamAddUser').value;
        const role = document.getElementById('teamAddRole').value;
        if (!userId) { if (window.Swal) Swal.fire({icon:'warning', title:'Pick a person first'}); return; }
        fetch(ROUTES.addTeamMember, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ user_id: userId, role: role }),
        }).then(r => r.json()).then(d => {
            if (!d.success) { if (window.Swal) Swal.fire({icon:'error', title:'Could not add'}); return; }
            location.reload();
        });
    },
    remove(userId, role, btn) {
        if (!confirm('Remove this person from the team?')) return;
        fetch(ROUTES.removeTeamMember(userId, role), {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF },
        }).then(r => r.json()).then(d => {
            if (!d.success) { if (window.Swal) Swal.fire({icon:'error', title:'Could not remove'}); return; }
            location.reload();
        });
    },
};

function openContactModal(contact = null) {
    document.getElementById('contactModalTitle').textContent = contact ? 'Edit Contact' : 'Add Contact';
    document.getElementById('contact_id').value = contact ? contact.id : '';
    document.getElementById('contact_name').value = contact ? contact.name : '';
    // role is stored as a backed enum -- the Blade json directive serializes it as {"value":"purchase",...}, not a plain string.
    document.getElementById('contact_role').value = contact ? (contact.role.value || contact.role) : 'purchase';
    document.getElementById('contact_email').value = contact ? (contact.email || '') : '';
    document.getElementById('contact_phone').value = contact ? (contact.phone || '') : '';
    document.getElementById('contact_notes').value = contact ? (contact.notes || '') : '';
    document.getElementById('contact_is_primary').checked = !!(contact && contact.is_primary);
    new bootstrap.Modal(document.getElementById('contactModal')).show();
}

function saveContact() {
    const id = document.getElementById('contact_id').value;
    const payload = {
        name: document.getElementById('contact_name').value,
        role: document.getElementById('contact_role').value,
        email: document.getElementById('contact_email').value,
        phone: document.getElementById('contact_phone').value,
        notes: document.getElementById('contact_notes').value,
        is_primary: document.getElementById('contact_is_primary').checked,
    };
    if (!payload.name) { alert('Name is required.'); return; }

    const btn = document.getElementById('saveContactBtn');
    btn.disabled = true;

    fetch(id ? ROUTES.updateContact(id) : ROUTES.storeContact, {
        method: id ? 'PUT' : 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify(payload),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Save failed.');
        }
    })
    .finally(() => { btn.disabled = false; });
}

function deleteContact(id) {
    if (!confirm('Remove this contact?')) return;
    fetch(ROUTES.destroyContact(id), {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
    })
    .then(r => r.json())
    .then(data => { if (data.success) document.getElementById('contact-row-' + id).remove(); });
}

function postComment() {
    const body = document.getElementById('newCommentBody').value.trim();
    if (!body) { return; }
    const needsFollowUp = document.getElementById('newCommentFollowUp').checked;
    const btn = document.getElementById('postCommentBtn');
    btn.disabled = true;

    fetch(ROUTES.storeActivity, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({
            body: body,
            needs_follow_up: needsFollowUp,
            follow_up_due_at: needsFollowUp ? document.getElementById('newCommentDueAt').value : null,
        }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Could not post comment.');
        }
    })
    .finally(() => { btn.disabled = false; });
}

function completeFollowUp(id) {
    fetch(ROUTES.completeFollowUp(id), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
    })
    .then(r => r.json())
    .then(data => { if (data.success) location.reload(); });
}
</script>
@endsection
