@extends('layouts/layoutMaster')

@section('title', 'My SOS Alerts')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row g-6">
        <div class="col-md-12">
            <div class="card mb-6">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">My SOS History</h5>
                    <button type="button" class="btn btn-danger" disabled>
                        Send SOS (App only)
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive text-nowrap">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Location (Lat/Long)</th>
                                    <th>Address</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody class="table-border-bottom-0">
                                @forelse($sosLogs as $log)
                                <tr>
                                    <td>{{ $log->created_at->format('d M, Y H:i') }}</td>
                                    <td>{{ $log->latitude }}, {{ $log->longitude }}</td>
                                    <td>{{ \Illuminate\Support\Str::limit($log->address, 50) }}</td>
                                    <td>
                                        <span class="badge bg-label-{{ $log->status == 'pending' ? 'warning' : 'success' }}">
                                            {{ ucfirst($log->status) }}
                                        </span>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center">No SOS alerts found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
