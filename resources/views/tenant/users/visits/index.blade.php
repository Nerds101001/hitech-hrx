@extends('layouts/layoutMaster')

@section('title', 'My Visits')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row g-6">
        <div class="col-md-12">
            <div class="card mb-6">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">My Visit History</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive text-nowrap">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Client</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody class="table-border-bottom-0">
                                @forelse($visits as $visit)
                                <tr>
                                    <td>{{ $visit->created_at->format('d M, Y H:i') }}</td>
                                    <td>{{ $visit->client->name ?? 'N/A' }}</td>
                                    <td>{{ \Illuminate\Support\Str::limit($visit->address, 50) }}</td>
                                    <td>
                                        <span class="badge bg-label-{{ $visit->status == 'completed' ? 'success' : 'info' }}">
                                            {{ ucfirst($visit->status) }}
                                        </span>
                                    </td>
                                    <td>{{ \Illuminate\Support\Str::limit($visit->remarks, 30) }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center">No visits found.</td>
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
