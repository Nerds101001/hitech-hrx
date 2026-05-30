@extends('layouts.layoutMaster')

@section('title', 'Visit Details')

@push('page-style')
<style>
    /* ── Hero ──────────────────────────────────────────────── */
    .sv-hero {
        background: linear-gradient(135deg, #0d9488 0%, #0f766e 50%, #134e4a 100%);
        border-radius: 1rem;
        padding: 2.5rem 2rem;
        color: #fff;
        margin-bottom: 2rem;
        box-shadow: 0 8px 32px rgba(13, 148, 136, .35);
        position: relative;
        overflow: hidden;
    }
    .sv-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    }
    .sv-hero .visit-type-badge {
        display: inline-block;
        background: rgba(255,255,255,.18);
        border: 1px solid rgba(255,255,255,.35);
        color: #fff;
        border-radius: 2rem;
        padding: .25rem .9rem;
        font-size: .78rem;
        font-weight: 600;
        letter-spacing: .05em;
        text-transform: uppercase;
        margin-bottom: .75rem;
    }
    .sv-hero h1 {
        font-size: 1.9rem;
        font-weight: 700;
        margin-bottom: .35rem;
    }
    .sv-hero .scheduled-date {
        opacity: .85;
        font-size: .95rem;
    }

    /* ── Status badge ──────────────────────────────────────── */
    .status-pill {
        border-radius: 2rem;
        padding: .28rem 1rem;
        font-size: .8rem;
        font-weight: 700;
        display: inline-block;
        letter-spacing: .04em;
    }
    .status-pending    { background: #fef3c7; color: #92400e; }
    .status-confirmed  { background: #dbeafe; color: #1e40af; }
    .status-completed  { background: #dcfce7; color: #166534; }
    .status-cancelled  { background: #fee2e2; color: #991b1b; }

    /* ── Detail card ───────────────────────────────────────── */
    .sv-detail-card {
        background: #fff;
        border-radius: 1rem;
        box-shadow: 0 4px 20px rgba(0,0,0,.07);
        padding: 1.75rem;
        height: 100%;
    }
    .sv-detail-card .section-label {
        font-size: .7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: #6b7280;
        margin-bottom: .25rem;
    }
    .sv-detail-card .section-value {
        font-size: 1rem;
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 1.1rem;
    }
    .sv-detail-card .divider {
        border-top: 1px solid #f3f4f6;
        margin: 1rem 0;
    }
    .sv-notes-box {
        background: #f8fafc;
        border-left: 3px solid #0d9488;
        border-radius: .5rem;
        padding: .85rem 1rem;
        color: #374151;
        font-size: .95rem;
        white-space: pre-wrap;
    }

    /* ── Timeline ──────────────────────────────────────────── */
    .sv-timeline-card {
        background: #fff;
        border-radius: 1rem;
        box-shadow: 0 4px 20px rgba(0,0,0,.07);
        padding: 1.75rem;
        margin-bottom: 1.25rem;
    }
    .sv-timeline-card h6 {
        font-weight: 700;
        color: #0d9488;
        margin-bottom: 1rem;
    }
    .timeline-steps {
        list-style: none;
        padding: 0;
        margin: 0;
        position: relative;
    }
    .timeline-steps::before {
        content: '';
        position: absolute;
        left: 1rem;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #e5e7eb;
    }
    .timeline-steps li {
        display: flex;
        gap: .9rem;
        padding-left: 2.5rem;
        position: relative;
        margin-bottom: 1.1rem;
    }
    .timeline-steps li .dot {
        position: absolute;
        left: .55rem;
        top: .25rem;
        width: 1rem;
        height: 1rem;
        border-radius: 50%;
        border: 2px solid #fff;
        box-shadow: 0 0 0 2px #0d9488;
        background: #0d9488;
        flex-shrink: 0;
    }
    .timeline-steps li .dot.inactive {
        background: #e5e7eb;
        box-shadow: 0 0 0 2px #d1d5db;
    }
    .timeline-steps li .tl-label {
        font-size: .82rem;
        font-weight: 600;
        color: #1f2937;
    }
    .timeline-steps li .tl-sub {
        font-size: .76rem;
        color: #9ca3af;
    }

    /* ── GPS / Map card ────────────────────────────────────── */
    .sv-map-card {
        background: #fff;
        border-radius: 1rem;
        box-shadow: 0 4px 20px rgba(0,0,0,.07);
        overflow: hidden;
    }
    .sv-map-card .map-header {
        padding: 1rem 1.25rem .75rem;
        font-weight: 700;
        color: #0d9488;
        font-size: .95rem;
    }
    .sv-map-card img.map-img {
        width: 100%;
        height: 200px;
        object-fit: cover;
        display: block;
    }
    .sv-map-card .coords-box {
        background: #f0fdfa;
        border: 1px solid #99f6e4;
        border-radius: .75rem;
        margin: 1rem;
        padding: .75rem 1rem;
        font-family: monospace;
        font-size: .85rem;
        color: #0f766e;
    }

    /* ── Proof photo ───────────────────────────────────────── */
    .proof-photo-thumb {
        width: 100%;
        max-height: 220px;
        object-fit: cover;
        border-radius: .75rem;
        border: 2px solid #99f6e4;
        margin-top: .75rem;
    }

    /* ── Action buttons ────────────────────────────────────── */
    .btn-complete {
        background: linear-gradient(135deg, #10b981, #059669);
        border: none;
        color: #fff;
        border-radius: 2rem;
        padding: .5rem 1.4rem;
        font-weight: 600;
        transition: transform .18s, box-shadow .18s;
    }
    .btn-complete:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(16,185,129,.4);
        color: #fff;
    }
    .btn-cancel-visit {
        border-radius: 2rem;
        padding: .5rem 1.4rem;
        font-weight: 600;
    }

    /* ── Modal styling ─────────────────────────────────────── */
    .modal-content {
        border-radius: 1rem;
        border: none;
        box-shadow: 0 20px 60px rgba(0,0,0,.18);
    }
    .modal-header {
        background: linear-gradient(135deg, #0d9488, #0f766e);
        color: #fff;
        border-radius: 1rem 1rem 0 0;
    }
    .modal-header .btn-close { filter: invert(1); }
    .gps-capture-box {
        background: #f0fdfa;
        border: 2px dashed #0d9488;
        border-radius: .75rem;
        padding: 1.25rem;
        text-align: center;
    }
    .gps-status {
        font-size: .85rem;
        margin-top: .5rem;
        font-weight: 600;
    }
    .gps-status.success { color: #059669; }
    .gps-status.error   { color: #dc2626; }
</style>
@endpush

@section('content')
<div class="container-fluid py-4">

    {{-- ── Hero Header ───────────────────────────────────────────── --}}
    <div class="sv-hero">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <div class="visit-type-badge">
                    {{ ucwords(str_replace('_', ' ', $visit->visit_type)) }}
                </div>
                <h1>{{ $visit->client_name }}</h1>
                <div class="scheduled-date">
                    <i class="ti ti-calendar me-1"></i>
                    Scheduled: {{ \Carbon\Carbon::parse($visit->scheduled_at)->format('D, d M Y \a\t h:i A') }}
                </div>
            </div>
            <div class="d-flex flex-column align-items-end gap-2 mt-1">
                @php
                    $statusMap = [
                        'pending'   => 'status-pending',
                        'confirmed' => 'status-confirmed',
                        'completed' => 'status-completed',
                        'cancelled' => 'status-cancelled',
                    ];
                    $cls = $statusMap[$visit->status] ?? 'status-pending';
                @endphp
                <span class="status-pill {{ $cls }}">{{ ucfirst($visit->status) }}</span>

                {{-- Mark Complete --}}
                @if(auth()->id() == $visit->salesperson_id && $visit->status == 'pending')
                    <button class="btn-complete" data-bs-toggle="modal" data-bs-target="#completeModal">
                        <i class="ti ti-check me-1"></i> Mark Complete
                    </button>
                @endif

                {{-- Cancel Visit --}}
                @if($visit->status == 'pending' && (auth()->user()->hasRole('admin') || auth()->id() == $visit->cc_agent_id))
                    <form method="POST" action="{{ route('sales-visits.cancel', $visit->id) }}"
                          onsubmit="return confirm('Cancel this visit?')">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-outline-danger btn-cancel-visit">
                            <i class="ti ti-x me-1"></i> Cancel Visit
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Flash messages ────────────────────────────────────────── --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 mb-4" role="alert">
            <i class="ti ti-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4" role="alert">
            <i class="ti ti-alert-circle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- ── Two-column layout ─────────────────────────────────────── --}}
    <div class="row g-4">

        {{-- LEFT: Detail card --}}
        <div class="col-lg-7">
            <div class="sv-detail-card">
                <h6 class="fw-bold mb-4" style="color:#0d9488;">
                    <i class="ti ti-info-circle me-2"></i>Visit Details
                </h6>

                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="section-label">Client Name</div>
                        <div class="section-value">{{ $visit->client_name }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="section-label">Client Phone</div>
                        <div class="section-value">
                            @if($visit->client_phone)
                                <a href="tel:{{ $visit->client_phone }}" class="text-decoration-none text-dark">
                                    <i class="ti ti-phone me-1 text-teal"></i>{{ $visit->client_phone }}
                                </a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="section-label">Client Address</div>
                        <div class="section-value">{{ $visit->client_address ?? '—' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="section-label">Visit Type</div>
                        <div class="section-value">{{ ucwords(str_replace('_', ' ', $visit->visit_type)) }}</div>
                    </div>
                </div>

                <div class="divider"></div>

                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="section-label">Salesperson</div>
                        <div class="section-value d-flex align-items-center gap-2">
                            <span class="avatar avatar-sm rounded-circle bg-teal text-white fw-bold" style="background:#0d9488;color:#fff;width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center;font-size:.8rem;">
                                {{ strtoupper(substr($visit->salesperson->name ?? 'S', 0, 1)) }}
                            </span>
                            {{ $visit->salesperson->name ?? '—' }}
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="section-label">CC Agent</div>
                        <div class="section-value d-flex align-items-center gap-2">
                            <span class="avatar avatar-sm rounded-circle fw-bold" style="background:#e0f2fe;color:#0369a1;width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center;font-size:.8rem;">
                                {{ strtoupper(substr($visit->ccAgent->name ?? 'C', 0, 1)) }}
                            </span>
                            {{ $visit->ccAgent->name ?? '—' }}
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="section-label">Scheduled At</div>
                        <div class="section-value">
                            {{ \Carbon\Carbon::parse($visit->scheduled_at)->format('d M Y, h:i A') }}
                        </div>
                    </div>
                    @if($visit->completed_at)
                    <div class="col-sm-6">
                        <div class="section-label">Completed At</div>
                        <div class="section-value text-success">
                            <i class="ti ti-circle-check me-1"></i>
                            {{ \Carbon\Carbon::parse($visit->completed_at)->format('d M Y, h:i A') }}
                        </div>
                    </div>
                    @endif
                </div>

                @if($visit->notes)
                <div class="divider"></div>
                <div class="section-label">Salesperson Notes</div>
                <div class="sv-notes-box">{{ $visit->notes }}</div>
                @endif

                @if($visit->completion_notes)
                <div class="divider"></div>
                <div class="section-label">Completion Notes</div>
                <div class="sv-notes-box" style="border-left-color:#10b981;">{{ $visit->completion_notes }}</div>
                @endif

                @if($visit->proof_photo)
                <div class="divider"></div>
                <div class="section-label">Proof Photo</div>
                <img src="{{ asset('storage/' . $visit->proof_photo) }}"
                     alt="Proof Photo"
                     class="proof-photo-thumb"
                     data-bs-toggle="modal" data-bs-target="#photoModal"
                     style="cursor:pointer;">
                @endif
            </div>
        </div>

        {{-- RIGHT: Timeline + Map --}}
        <div class="col-lg-5 d-flex flex-column gap-4">

            {{-- Status Timeline --}}
            <div class="sv-timeline-card">
                <h6><i class="ti ti-timeline me-2"></i>Status Timeline</h6>
                @php
                    $steps = [
                        ['label' => 'Visit Booked',     'sub' => 'CC Agent created the visit',          'done' => true],
                        ['label' => 'Confirmed',         'sub' => 'Visit confirmed by salesperson',       'done' => in_array($visit->status, ['confirmed','completed'])],
                        ['label' => 'Visit Completed',  'sub' => $visit->completed_at ? \Carbon\Carbon::parse($visit->completed_at)->format('d M Y h:i A') : 'Awaiting completion', 'done' => $visit->status == 'completed'],
                    ];
                    if ($visit->status == 'cancelled') {
                        $steps[] = ['label' => 'Cancelled', 'sub' => 'Visit was cancelled', 'done' => true, 'danger' => true];
                    }
                @endphp
                <ul class="timeline-steps">
                    @foreach($steps as $step)
                    <li>
                        <span class="dot {{ $step['done'] ? '' : 'inactive' }}"
                              style="{{ ($step['done'] && isset($step['danger'])) ? 'background:#dc2626;box-shadow:0 0 0 2px #dc2626;' : '' }}">
                        </span>
                        <div>
                            <div class="tl-label">{{ $step['label'] }}</div>
                            <div class="tl-sub">{{ $step['sub'] }}</div>
                        </div>
                    </li>
                    @endforeach
                </ul>
            </div>

            {{-- Verification Panel (For CC/Manager on Completed visits) --}}
            @if($visit->status === 'completed')
            <div class="sv-timeline-card" style="background:#fffaf0; border:1px solid #fde68a;">
                <h6 style="color:#d97706;"><i class="ti ti-shield-check me-2"></i>Verification Required</h6>
                
                @if($visit->verification_status === 'approved')
                    <div class="alert alert-success mt-3 mb-0">
                        <i class="ti ti-check me-2"></i> This visit has been <strong>Approved</strong>.
                    </div>
                @elseif($visit->verification_status === 'rejected')
                    <div class="alert alert-danger mt-3 mb-0">
                        <i class="ti ti-x me-2"></i> This visit was <strong>Rejected</strong>.<br>
                        <strong>Reason:</strong> {{ $visit->verification_notes }}
                    </div>
                @else
                    <p class="text-muted" style="font-size:0.85rem; margin-top:0.5rem;">
                        Please verify the proof photo, location, and notes submitted by the salesperson.
                    </p>
                    @if(auth()->user()->hasRole(['admin', 'hr', 'manager', 'accounts']) || auth()->id() === $visit->cc_user_id)
                        <div class="d-flex gap-2 mt-3">
                            <form method="POST" action="{{ route('sales-visits.verify', $visit->id) }}" class="flex-grow-1">
                                @csrf @method('PATCH')
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-success w-100" onclick="return confirm('Approve this visit?');">
                                    <i class="ti ti-check me-1"></i> Approve
                                </button>
                            </form>
                            <button type="button" class="btn btn-danger flex-grow-1" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                <i class="ti ti-x me-1"></i> Reject
                            </button>
                        </div>
                    @endif
                @endif
            </div>
            @endif

            {{-- GPS Location --}}
            <div class="sv-map-card">
                <div class="map-header"><i class="ti ti-map-pin me-2"></i>GPS Location</div>
                @if($visit->completed_lat && $visit->completed_lng)
                    @php
                        $lat = $visit->completed_lat;
                        $lng = $visit->completed_lng;
                        $apiKey = config('services.google_maps.key', '');
                    @endphp
                    @if($apiKey)
                        <img src="https://maps.googleapis.com/maps/api/staticmap?center={{ $lat }},{{ $lng }}&zoom=15&size=400x250&markers={{ $lat }},{{ $lng }}&key={{ $apiKey }}"
                             alt="GPS Location Map"
                             class="map-img">
                    @else
                        <div class="coords-box mx-3 mb-3">
                            <div><strong>Latitude:</strong>  {{ $lat }}</div>
                            <div><strong>Longitude:</strong> {{ $lng }}</div>
                            <a href="https://maps.google.com/?q={{ $lat }},{{ $lng }}"
                               target="_blank"
                               class="btn btn-sm mt-2"
                               style="background:#0d9488;color:#fff;border-radius:1rem;">
                                <i class="ti ti-external-link me-1"></i>Open in Google Maps
                            </a>
                        </div>
                    @endif
                @else
                    <div class="d-flex flex-column align-items-center justify-content-center py-4 px-3 text-muted">
                        <i class="ti ti-map-pin-off" style="font-size:2.5rem;color:#d1d5db;"></i>
                        <div class="mt-2 fw-500" style="font-size:.9rem;">No GPS data recorded yet</div>
                        <div style="font-size:.78rem;">Location will appear after the visit is completed.</div>
                    </div>
                @endif
            </div>

        </div>
    </div>{{-- /row --}}
</div>

{{-- ── Mark Complete Modal ────────────────────────────────────────── --}}
@if(auth()->id() == $visit->salesperson_id && $visit->status == 'pending')
<div class="modal fade" id="completeModal" tabindex="-1" aria-labelledby="completeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="completeModalLabel">
                    <i class="ti ti-check-circle me-2"></i>Mark Visit as Complete
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">

                {{-- GPS Capture --}}
                <div class="gps-capture-box mb-4">
                    <i class="ti ti-map-pin" style="font-size:2rem;color:#0d9488;"></i>
                    <div class="fw-bold mt-1 mb-2">Capture Your Current Location</div>
                    <p class="text-muted mb-3" style="font-size:.85rem;">
                        Your GPS coordinates will be saved as proof of visit location.
                    </p>
                    <button type="button" class="btn btn-sm" id="captureGpsBtn"
                            style="background:#0d9488;color:#fff;border-radius:2rem;padding:.4rem 1.2rem;">
                        📍 Capture My Location
                    </button>
                    <div class="gps-status mt-2" id="gpsStatus"></div>
                </div>

                {{-- Hidden GPS fields --}}
                <input type="hidden" id="latInput"  name="lat">
                <input type="hidden" id="lngInput"  name="lng">

                {{-- Proof Photo --}}
                <div class="mb-4">
                    <label class="form-label fw-semibold">
                        <i class="ti ti-photo me-1 text-teal"></i>Proof Photo
                        <span class="text-muted fw-normal">(optional)</span>
                    </label>
                    <input type="file" class="form-control" id="proofPhotoInput"
                           name="proof_photo" accept="image/*">
                    <div class="form-text">Upload a photo as proof of the visit.</div>
                </div>

                {{-- Completion Notes --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        <i class="ti ti-notes me-1 text-teal"></i>Completion Notes
                        <span class="text-muted fw-normal">(optional)</span>
                    </label>
                    <textarea class="form-control" id="completionNotesInput" name="completion_notes"
                              rows="3" placeholder="Add any notes about how the visit went…"></textarea>
                </div>

                <div id="submitError" class="alert alert-danger d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light rounded-pill px-4"
                        data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-complete" id="submitCompleteBtn"
                        style="padding:.5rem 1.8rem;">
                    <i class="ti ti-check me-1"></i> Submit &amp; Complete
                </button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ── Proof Photo Lightbox Modal ──────────────────────────────────── --}}
@if($visit->proof_photo)
<div class="modal fade" id="photoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark border-0">
            <div class="modal-header border-0">
                <h6 class="text-white mb-0">Proof Photo</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-2">
                <img src="{{ asset('storage/' . $visit->proof_photo) }}"
                     alt="Proof Photo"
                     style="width:100%;border-radius:.5rem;">
            </div>
        </div>
    </div>
</div>
@endif


{{-- ── Reject Verification Modal ────────────────────────────────────── --}}
@if($visit->status === 'completed' && empty($visit->verification_status))
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('sales-visits.verify', $visit->id) }}" class="modal-content">
            @csrf @method('PATCH')
            <input type="hidden" name="action" value="reject">
            <div class="modal-header bg-danger">
                <h5 class="modal-title text-white"><i class="ti ti-alert-triangle me-2"></i>Reject Visit Verification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="mb-3">Are you sure you want to reject this visit? Please provide a reason so the salesperson can correct it.</p>
                <div class="mb-3">
                    <label class="form-label fw-bold">Rejection Reason <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="verification_notes" rows="3" required placeholder="e.g. Photo is unclear, wrong location..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Reject Visit</button>
            </div>
        </form>
    </div>
</div>
@endif

@push('page-script')
<script>
// ── GPS Capture ────────────────────────────────────────────────────
document.getElementById('captureGpsBtn')?.addEventListener('click', function () {
    const btn    = this;
    const status = document.getElementById('gpsStatus');

    if (!navigator.geolocation) {
        status.textContent = '❌ Geolocation not supported by your browser.';
        status.className   = 'gps-status error';
        return;
    }

    btn.disabled    = true;
    btn.textContent = '⏳ Detecting location…';
    status.textContent = '';
    status.className   = 'gps-status';

    navigator.geolocation.getCurrentPosition(
        function (pos) {
            const lat = pos.coords.latitude.toFixed(6);
            const lng = pos.coords.longitude.toFixed(6);
            document.getElementById('latInput').value = lat;
            document.getElementById('lngInput').value = lng;
            status.textContent = `✅ Location captured: ${lat}, ${lng}`;
            status.className   = 'gps-status success';
            btn.textContent    = '📍 Re-capture Location';
            btn.disabled       = false;
        },
        function (err) {
            status.textContent = '❌ Could not get location: ' + err.message;
            status.className   = 'gps-status error';
            btn.textContent    = '📍 Capture My Location';
            btn.disabled       = false;
        },
        { enableHighAccuracy: true, timeout: 15000 }
    );
});

// ── Submit Complete via Fetch ──────────────────────────────────────
document.getElementById('submitCompleteBtn')?.addEventListener('click', async function () {
    const btn       = this;
    const errorBox  = document.getElementById('submitError');
    const lat       = document.getElementById('latInput').value;
    const lng       = document.getElementById('lngInput').value;
    const photoFile = document.getElementById('proofPhotoInput').files[0];
    const notes     = document.getElementById('completionNotesInput').value;

    errorBox.classList.add('d-none');

    const formData = new FormData();
    formData.append('_token', '{{ csrf_token() }}');
    if (lat)       formData.append('lat', lat);
    if (lng)       formData.append('lng', lng);
    if (notes)     formData.append('completion_notes', notes);
    if (photoFile) formData.append('proof_photo', photoFile);

    btn.disabled    = true;
    btn.innerHTML   = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting…';

    try {
        const resp = await fetch('{{ route('sales-visits.complete', $visit->id) }}', {
            method: 'POST',
            body:   formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        const data = await resp.json();

        if (resp.ok && data.success) {
            window.location.reload();
        } else {
            errorBox.textContent = data.message ?? 'Something went wrong. Please try again.';
            errorBox.classList.remove('d-none');
            btn.disabled  = false;
            btn.innerHTML = '<i class="ti ti-check me-1"></i> Submit &amp; Complete';
        }
    } catch (e) {
        errorBox.textContent = 'Network error. Please check your connection.';
        errorBox.classList.remove('d-none');
        btn.disabled  = false;
        btn.innerHTML = '<i class="ti ti-check me-1"></i> Submit &amp; Complete';
    }
});

// Auto-open modal if mark_complete is set in URL
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('mark_complete') === '1') {
    const modalEl = document.getElementById('completeModal');
    if (modalEl) {
        const completeModal = new bootstrap.Modal(modalEl);
        completeModal.show();
    }
}
</script>
@endpush
@endsection
