@extends('layouts/layoutMaster')

@section('title', 'My Assets')

@section('vendor-style')
@vite(['resources/assets/vendor/libs/animate-css/animate.scss'])
@endsection

@section('vendor-script')
@vite(['resources/assets/vendor/libs/select2/select2.full.min.js'])
@vite(['resources/assets/vendor/js/bootstrap.js'])
@endsection

@section('content')
<div class="content-wrapper">
    <!-- Content -->
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="row">
            <div class="col-md-12">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">My Assets</h5>
                        <div class="card-actions">
                            <a href="{{ route('assets.index') }}" class="btn btn-secondary">
                                <i class="bx bx-archive"></i> All Assets
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- My Assets Statistics -->
                        <div class="row mb-4">
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="card bg-label-primary">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar me-3">
                                                <div class="avatar-initial bg-primary">
                                                    <i class="bx bx-package"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">{{ $stats['total'] }}</h6>
                                                <p class="mb-0">Total Assets</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="card bg-label-success">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar me-3">
                                                <div class="avatar-initial bg-success">
                                                    <i class="bx bx-check-circle"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">{{ $stats['available'] }}</h6>
                                                <p class="mb-0">Available</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="card bg-label-info">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar me-3">
                                                <div class="avatar-initial bg-info">
                                                    <i class="bx bx-user"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">{{ $stats['assigned'] }}</h6>
                                                <p class="mb-0">Assigned to Me</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="card bg-label-warning">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar me-3">
                                                <div class="avatar-initial bg-warning">
                                                    <i class="bx bx-tools"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">{{ $stats['maintenance'] }}</h6>
                                                <p class="mb-0">Under Maintenance</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="card bg-label-secondary">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar me-3">
                                                <div class="avatar-initial bg-secondary">
                                                    <i class="bx bx-x-circle"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">{{ $stats['retired'] }}</h6>
                                                <p class="mb-0">Retired</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- My Assets Table -->
                        <div class="table-responsive">
                            <table class="table table-hover" id="myAssetsTable">
                                <thead>
                                    <tr>
                                        <th>Asset Code</th>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th>Assigned Date</th>
                                        <th>Current Value</th>
                                        <th>Warranty</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable
    $('#myAssetsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("myAssets.getListAjax") }}',
        columns: [
            { data: 'asset_code' },
            { data: 'name' },
            { data: 'category_name' },
            { data: 'status_badge' },
            { data: 'assigned_date' },
            { data: 'formatted_current_value' },
            { data: 'warranty_status' },
            { data: 'action', orderable: false }
        ],
        order: [[0, 'desc']]
    });
});

function viewAssetDetails(id) {
    window.location.href = '{{ route("myAssets.show", "") }}/' + id;
}

function requestMaintenance(id) {
    if (confirm('Do you want to request maintenance for this asset?')) {
        $.ajax({
            url: '{{ route("myAssets.requestMaintenance", "") }}/' + id,
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    alert('Maintenance request submitted successfully!');
                } else {
                    alert('Failed to submit maintenance request.');
                }
            },
            error: function() {
                alert('An error occurred while submitting the request.');
            }
        });
    }
}
</script>
@endpush
