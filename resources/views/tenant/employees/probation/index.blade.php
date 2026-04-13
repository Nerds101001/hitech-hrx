@extends('layouts/layoutMaster')

@section('title', 'Probation Evaluations')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold py-3 mb-0">Probation Evaluations</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('tenant.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item active">Probation</li>
            </ol>
        </nav>
    </div>

    <div class="card hitech-card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="card-header bg-teal text-white p-4">
            <div class="d-flex align-items-center justify-content-between">
                <h5 class="mb-0 text-white fw-bold">Pending & Recent Evaluations</h5>
                <i class="bx bx-file fs-4"></i>
            </div>
        </div>
        <div class="table-responsive text-nowrap">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Employee</th>
                        <th>Manager</th>
                        <th>Recommendation</th>
                        <th>Status</th>
                        <th>Submitted At</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($evaluations as $eval)
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <div class="avatar avatar-sm me-3">
                                    <img src="{{ $eval->user->getProfilePicture() }}" alt="Avatar" class="rounded-circle">
                                </div>
                                <div>
                                    <div class="fw-bold text-dark">{{ $eval->user->name }}</div>
                                    <small class="text-muted">{{ $eval->user->code }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="fw-semibold text-muted small">{{ $eval->manager->name }}</div>
                        </td>
                        <td>
                            @if($eval->recommendation === 'confirm')
                                <span class="badge bg-label-success rounded-pill px-3">Confirm</span>
                            @elseif($eval->recommendation === 'extend')
                                <span class="badge bg-label-warning rounded-pill px-3">Extend ({{ $eval->extension_months }}m)</span>
                            @else
                                <span class="badge bg-label-danger rounded-pill px-3">Terminate</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $eval->hr_status === 'approved' ? 'bg-success' : 'bg-warning' }} dot-badge">
                                {{ ucfirst($eval->hr_status) }}
                            </span>
                        </td>
                        <td>
                            <div class="small text-muted">{{ $eval->submitted_at ? \Carbon\Carbon::parse($eval->submitted_at)->format('d M, Y') : 'N/A' }}</div>
                        </td>
                        <td class="text-center">
                            <a href="{{ route('probation.review', $eval->id) }}" class="btn btn-sm btn-outline-primary rounded-pill">
                                <i class="bx bx-show me-1"></i> Review
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <div class="text-muted">No probation evaluations found.</div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .bg-teal { background-color: #008080 !important; }
    .dot-badge { padding: 5px 10px; border-radius: 5px; font-size: 0.75rem; }
</style>
@endsection
