@php
    $__ctcAgent = app(\App\Services\ClickToCallService::class)->agentFor(auth()->user());
@endphp
@forelse($contacts as $contact)
    <div class="cp-contact-row" id="cp-contact-row-{{ $contact->id }}">
        @php $__parts = preg_split('/\s+/', trim($contact->name)); $__ini = strtoupper(mb_substr($__parts[0], 0, 1) . (count($__parts) > 1 ? mb_substr(end($__parts), 0, 1) : '')); @endphp
        <div class="d-flex justify-content-between align-items-start gap-2">
            <div class="cp-avatar">{{ $__ini }}</div>
            <div class="flex-grow-1" style="min-width:0;">
                <div class="fw-semibold" style="font-size:0.85rem;">
                    {{ $contact->name }}
                    @if($contact->is_primary)<i class="bx bxs-star text-warning" style="font-size:0.7rem;" title="Primary"></i>@endif
                </div>
                <span class="role-pill role-{{ $contact->role->value }}" style="font-size:.62rem;padding:2px 8px;border-radius:20px;font-weight:700;display:inline-block;background:#f1f5f9;">{{ $contact->role->label() }}</span>
                @if($contact->email)<div class="text-muted mt-1" style="font-size:0.76rem;"><i class="bx bx-envelope"></i> {{ $contact->email }}</div>@endif
                @if($contact->phone)<div class="text-muted" style="font-size:0.76rem;"><i class="bx bx-phone"></i> {{ $contact->phone }}</div>@endif
            </div>
            <div class="d-flex gap-1 flex-shrink-0">
                @if($contact->phone)
                    @if($__ctcAgent)
                        <button type="button" class="btn btn-sm btn-icon btn-outline-success rounded-circle js-ctc-call"
                            style="width:26px;height:26px;padding:0;" data-phone="{{ $contact->phone }}" title="Call {{ $contact->name }} via Smart Agent">
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
    <p class="text-muted small text-center py-3 mb-0">No contacts yet.</p>
@endforelse
