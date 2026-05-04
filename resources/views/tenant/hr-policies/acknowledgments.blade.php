@extends('layouts/layoutMaster')

@section('title', 'Policy Acknowledgments')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">HR Policies /</span> Acknowledgments
</h4>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">{{ $policy->title }}</h5>
        <a href="{{ route('hr-policies.index') }}" class="btn btn-secondary">
            <i class="bx bx-arrow-back me-1"></i> Back to Policies
        </a>
    </div>
    <div class="card-body">
        <div class="alert alert-info d-flex align-items-center" role="alert">
            <span class="badge badge-center rounded-pill bg-info me-3"><i class="bx bx-info-circle"></i></span>
            <div>
                Total Acknowledgments: <strong>{{ $acknowledgments->count() }}</strong>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-striped" id="acknowledgmentsTable">
                <thead>
                    <tr>
                        <th>Employee Name</th>
                        <th>Employee ID</th>
                        <th>IP Address</th>
                        <th>Acknowledged At</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($acknowledgments as $ack)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar avatar-sm me-3">
                                    <span class="avatar-initial rounded-circle bg-label-primary">{{ strtoupper(substr($ack->user->first_name, 0, 1)) }}</span>
                                </div>
                                <div>
                                    <h6 class="mb-0">{{ $ack->user->first_name }} {{ $ack->user->last_name }}</h6>
                                    <small class="text-muted">{{ $ack->user->email }}</small>
                                </div>
                            </div>
                        </td>
                        <td><span class="fw-bold">{{ $ack->user->employee_code ?? 'N/A' }}</span></td>
                        <td><code>{{ $ack->ip_address }}</code></td>
                        <td>
                            <span class="text-nowrap">{{ $ack->acknowledged_at->format('d M Y') }}</span><br>
                            <small class="text-muted">{{ $ack->acknowledged_at->format('h:i A') }}</small>
                        </td>
                        <td>
                            @if(is_array($ack->signature_data))
                                <small class="text-muted">Verified by: {{ $ack->signature_data['name'] ?? 'System' }}</small>
                            @else
                                <span class="badge bg-label-success">Standard Ack</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-5">
                            <i class="bx bx-user-x fs-1 text-muted"></i>
                            <p class="mt-2">No one has acknowledged this policy yet.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
