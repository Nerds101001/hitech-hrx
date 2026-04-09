@extends('layouts/layoutMaster')

@section('title', 'My Asset Details')

@section('vendor-style')
@vite(['resources/assets/vendor/libs/animate-css/animate.scss'])
@endsection

@section('content')
<div class="content-wrapper">
    <!-- Content -->
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">My Asset Details</h5>
                        <div class="card-actions">
                            <a href="{{ route('myAssets.index') }}" class="btn btn-secondary">
                                <i class="bx bx-arrow-back"></i> Back to My Assets
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted mb-3">Asset Information</h6>
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Asset Code:</strong></td>
                                        <td>{{ $asset->asset_code }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Name:</strong></td>
                                        <td>{{ $asset->name }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Category:</strong></td>
                                        <td>{{ $asset->category->name ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Status:</strong></td>
                                        <td><span class="badge bg-label-{{ $asset->status === 'available' ? 'success' : ($asset->status === 'assigned' ? 'primary' : ($asset->status === 'maintenance' ? 'warning' : 'secondary')) }}">{{ ucfirst($asset->status) }}</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Assigned Date:</strong></td>
                                        <td>{{ $asset->updated_at ? $asset->updated_at->format('M d, Y') : 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Current Value:</strong></td>
                                        <td>{{ $asset->current_value ? number_format($asset->current_value, 2) : 'N/A' }}</td>
                                    </tr>
                                    @if($asset->description)
                                        <tr>
                                            <td><strong>Description:</strong></td>
                                            <td>{{ $asset->description }}</td>
                                        </tr>
                                    @endif
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-muted mb-3">Assignment Details</h6>
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Assigned To:</strong></td>
                                        <td>
                                            @if($asset->assignedUser)
                                                {{ $asset->assignedUser->first_name }} {{ $asset->assignedUser->last_name }} (You)
                                            @else
                                                <span class="text-muted">Unassigned</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @if($asset->serial_number)
                                        <tr>
                                            <td><strong>Serial Number:</strong></td>
                                            <td>{{ $asset->serial_number }}</td>
                                        </tr>
                                    @endif
                                    @if($asset->brand)
                                        <tr>
                                            <td><strong>Brand:</strong></td>
                                            <td>{{ $asset->brand }}</td>
                                        </tr>
                                    @endif
                                    @if($asset->model)
                                        <tr>
                                            <td><strong>Model:</strong></td>
                                            <td>{{ $asset->model }}</td>
                                        </tr>
                                    @endif
                                    @if($asset->warranty_expiry)
                                        <tr>
                                            <td><strong>Warranty Expiry:</strong></td>
                                            <td>
                                                {{ $asset->warranty_expiry->format('M d, Y') }}
                                                @if($asset->warranty_expiry->lt(now()->addDays(30)))
                                                    <span class="badge bg-label-warning ms-2">Expiring Soon</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endif
                                </table>
                            </div>
                        </div>
                        
                        @if($asset->maintenance_records && $asset->maintenance_records->count() > 0)
                            <div class="col-md-12 mt-4">
                                <h6 class="text-muted mb-3">Maintenance History</h6>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Type</th>
                                                <th>Cost</th>
                                                <th>Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($asset->maintenance_records as $maintenance)
                                                <tr>
                                                    <td>{{ $maintenance->created_at->format('M d, Y') }}</td>
                                                    <td>{{ $maintenance->maintenance_type ?? 'N/A' }}</td>
                                                    <td>${{ number_format($maintenance->cost, 2) }}</td>
                                                    <td>{{ $maintenance->notes ?? 'N/A' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
