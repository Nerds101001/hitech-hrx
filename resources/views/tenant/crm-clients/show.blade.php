@extends('layouts.layoutMaster')

@section('title', $mapping->crm_company_name . ' — Customer 360')

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
                        <code class="bg-white bg-opacity-25 text-white px-2 py-1 rounded">{{ $mapping->crm_company_code }}</code>
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

    <div class="row g-4">
        {{-- LEFT: Team + Contacts --}}
        <div class="col-lg-4">
            {{-- Team card --}}
            <div class="card c360-card mb-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bx bx-group me-1 text-primary"></i>Internal Team</h6>
                    @php
                        $teamRoles = [
                            ['key' => 'salesperson', 'label' => 'Salesperson', 'color' => '#7c3aed'],
                            ['key' => 'ccare',       'label' => 'CCare',       'color' => '#1d4ed8'],
                            ['key' => 'newBiz',      'label' => 'New Biz',     'color' => '#a16207'],
                            ['key' => 'billing',     'label' => 'Billing',     'color' => '#15803d'],
                            ['key' => 'finance',     'label' => 'Finance',     'color' => '#be185d'],
                        ];
                    @endphp
                    @foreach($teamRoles as $role)
                        @php $person = $mapping->{$role['key']}; @endphp
                        <div class="team-slot">
                            @if($person)
                                <div class="team-avatar" style="background:{{ $role['color'] }};">{{ substr($person->first_name,0,1) }}{{ substr($person->last_name,0,1) }}</div>
                                <div class="flex-grow-1" style="min-width:0;">
                                    <div class="fw-semibold text-truncate" style="font-size:0.85rem;">{{ $person->first_name }} {{ $person->last_name }}</div>
                                    <div class="text-muted" style="font-size:0.7rem;">{{ $role['label'] }}</div>
                                </div>
                            @else
                                <div class="team-avatar" style="background:#e2e8f0;color:#94a3b8;"><i class="bx bx-user"></i></div>
                                <div class="flex-grow-1">
                                    <div class="text-muted" style="font-size:0.8rem;">Not assigned</div>
                                    <div class="text-muted" style="font-size:0.7rem;">{{ $role['label'] }}</div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                    <a href="{{ route('crm-clients.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill mt-3 w-100">
                        <i class="bx bx-edit-alt me-1"></i>Edit Team (Client Mappings)
                    </a>
                </div>
            </div>

            {{-- Contacts card --}}
            <div class="card c360-card">
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
};

function openContactModal(contact = null) {
    document.getElementById('contactModalTitle').textContent = contact ? 'Edit Contact' : 'Add Contact';
    document.getElementById('contact_id').value = contact ? contact.id : '';
    document.getElementById('contact_name').value = contact ? contact.name : '';
    // role is stored as a backed enum -- @json() serializes it as {"value":"purchase",...}, not a plain string.
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
