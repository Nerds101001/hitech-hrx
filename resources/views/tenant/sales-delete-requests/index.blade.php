@extends('layouts/layoutMaster')

@section('title', 'Customer Deletion Requests')

@section('content')
@php
    $badge = ['duplicate' => 'bg-label-warning', 'legal_case' => 'bg-label-danger', 'closed' => 'bg-label-secondary'];
@endphp

<div class="px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h5 class="mb-0 fw-bold"><i class="bx bx-trash me-2 text-danger"></i>Customer Deletion Requests</h5>
            <small class="text-muted">Deleting a customer also removes every monthly figure attached to it and cannot be undone.</small>
        </div>
        <a href="{{ route('sales-visits.pipeline.index') }}" class="btn btn-sm btn-label-secondary">
            <i class="bx bx-arrow-back me-1"></i>Back to Sales Report
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible py-2">{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible py-2">{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="card">
        <div class="card-header border-bottom p-0">
            <ul class="nav nav-tabs mb-0" role="tablist">
                @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $k => $label)
                    <li class="nav-item">
                        <a class="nav-link {{ $tab === $k ? 'active' : '' }}"
                           href="{{ route('sales-visits.pipeline.delete-requests.index', ['tab' => $k]) }}">
                            {{ $label }}
                            <span class="badge {{ $k === 'pending' && ($counts[$k] ?? 0) ? 'bg-danger' : 'bg-label-secondary' }} ms-1">{{ $counts[$k] ?? 0 }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0" style="font-size:0.82rem;">
                <thead class="bg-light">
                    <tr>
                        <th>Customer</th>
                        <th>Book</th>
                        <th>Reason</th>
                        <th>Remarks</th>
                        <th>Requested by</th>
                        <th>Requested</th>
                        @if($tab === 'pending')
                            <th class="text-end" style="min-width:190px;">Action</th>
                        @else
                            <th>Decided by</th>
                            <th>Decided</th>
                            @if($tab === 'rejected')<th>Note</th>@endif
                        @endif
                    </tr>
                </thead>
                <tbody>
                @forelse($requests as $r)
                    <tr>
                        <td class="fw-bold">{{ $r->party_name }}</td>
                        <td><span class="badge bg-label-info">{{ $r->book_label }}</span></td>
                        <td><span class="badge {{ $badge[$r->reason] ?? 'bg-label-secondary' }}">{{ $r->reason_label }}</span></td>
                        <td class="text-muted" style="max-width:260px;">{{ $r->remarks ?: '—' }}</td>
                        <td>{{ $r->requester?->getFullName() ?? '#'.$r->requested_by }}</td>
                        <td class="text-muted">{{ $r->created_at?->format('d M Y, H:i') }}</td>

                        @if($tab === 'pending')
                            <td class="text-end">
                                <form action="{{ route('sales-visits.pipeline.delete-requests.approve', $r->id) }}"
                                      method="POST" class="d-inline js-approve-delete" data-party="{{ $r->party_name }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="bx bx-check me-1"></i>Approve &amp; delete
                                    </button>
                                </form>
                                <button type="button" class="btn btn-sm btn-label-secondary js-reject-delete"
                                        data-id="{{ $r->id }}" data-party="{{ $r->party_name }}">
                                    <i class="bx bx-x me-1"></i>Reject
                                </button>
                            </td>
                        @else
                            <td>{{ $r->actioner?->getFullName() ?? '—' }}</td>
                            <td class="text-muted">{{ $r->actioned_at?->format('d M Y, H:i') ?? '—' }}</td>
                            @if($tab === 'rejected')<td class="text-muted">{{ $r->decision_note ?: '—' }}</td>@endif
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center text-muted py-4">
                        No {{ $tab }} deletion requests.
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($requests->hasPages())
            <div class="card-footer">{{ $requests->links() }}</div>
        @endif
    </div>
</div>

{{-- Reject: capture a note so the requester learns why --}}
<form id="rejectForm" method="POST" style="display:none;">@csrf<input type="hidden" name="decision_note" id="rejectNote"></form>
@endsection

@section('vendor-style')
  @vite(['resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/sweetalert2/sweetalert2.js'])
@endsection

@section('page-script')
<script>
// Approving performs a real, irreversible delete — confirm it explicitly.
document.addEventListener('submit', function (e) {
    const f = e.target.closest('.js-approve-delete');
    if (!f || f.dataset.ok === '1') return;
    e.preventDefault();
    const party = f.dataset.party || 'this customer';
    const go = () => { f.dataset.ok = '1'; f.submit(); };
    if (typeof Swal === 'undefined') { if (confirm('Approve and permanently delete ' + party + '?')) go(); return; }
    Swal.fire({
        title: 'Approve and delete?',
        html: '<div style="font-size:.9rem;color:#566a7f;line-height:1.55;">'
            + '<div style="font-weight:700;color:#2f3944;margin-bottom:8px;">' + party.replace(/</g,'&lt;') + '</div>'
            + 'This deletes the customer and <b>every monthly figure</b> attached to it.'
            + '<div style="margin-top:10px;color:#c0392b;font-weight:700;">This cannot be reverted.</div></div>',
        showCancelButton: true, reverseButtons: true, focusCancel: true, buttonsStyling: false,
        confirmButtonText: 'Yes, delete it', cancelButtonText: 'Cancel',
        customClass: { confirmButton: 'btn btn-danger me-2', cancelButton: 'btn btn-label-secondary' }
    }).then(r => { if (r.isConfirmed) go(); });
});

// Rejecting keeps the customer; ask for a short reason.
document.addEventListener('click', function (e) {
    const b = e.target.closest('.js-reject-delete');
    if (!b) return;
    const form = document.getElementById('rejectForm');
    form.action = "{{ url('sales-visits/delete-requests') }}/" + b.dataset.id + "/reject";
    const submit = (note) => { document.getElementById('rejectNote').value = note || ''; form.submit(); };
    if (typeof Swal === 'undefined') { submit(prompt('Reason for rejecting?') || ''); return; }
    Swal.fire({
        title: 'Reject this request?',
        input: 'textarea',
        inputLabel: 'Why is it being kept? (optional)',
        inputPlaceholder: 'e.g. not a duplicate — different unit',
        html: '<div style="font-size:.88rem;color:#566a7f;">' + (b.dataset.party || '').replace(/</g,'&lt;') + ' will be kept.</div>',
        showCancelButton: true, reverseButtons: true, buttonsStyling: false,
        confirmButtonText: 'Reject request', cancelButtonText: 'Cancel',
        customClass: { confirmButton: 'btn btn-primary me-2', cancelButton: 'btn btn-label-secondary' }
    }).then(r => { if (r.isConfirmed) submit(r.value); });
});
</script>
@endsection
