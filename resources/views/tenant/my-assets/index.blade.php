@extends('layouts/layoutMaster')

@section('title', 'My Assets | Hitech HR')

@section('vendor-style')
@vite([
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
])
@endsection

@section('page-style')
<style>
    .asset-glass-card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(0, 128, 128, 0.1);
        border-radius: 20px;
        transition: all 0.3s ease;
    }
    .asset-glass-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0, 77, 84, 0.1);
    }
    .asset-icon-box {
        width: 50px;
        height: 50px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
</style>
@endsection

@section('content')
<div class="px-4 animate__animated animate__fadeIn">
    
    <!-- Hero Banner -->
    <div class="row mb-6">
        <div class="col-12">
            <x-hero-banner 
                title="Resource Management" 
                subtitle="Manage and maintain your assigned company assets and digital inventory."
                icon="bx-briefcase-alt-2"
                gradient="teal"
            />
        </div>
    </div>

    <!-- Quick Stats Hub -->
    <div class="row g-4 mb-6">
        <div class="col-lg-3 col-sm-6">
            <div class="asset-glass-card p-4 d-flex align-items-center gap-3">
                <div class="asset-icon-box bg-label-teal"><i class="bx bx-cube"></i></div>
                <div>
                    <h4 class="mb-0 fw-bold text-dark">{{ $stats['total'] }}</h4>
                    <small class="text-muted fw-bold">TOTAL INVENTORY</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="asset-glass-card p-4 d-flex align-items-center gap-3">
                <div class="asset-icon-box bg-label-success"><i class="bx bx-check-shield"></i></div>
                <div>
                    <h4 class="mb-0 fw-bold text-dark">{{ $stats['assigned'] }}</h4>
                    <small class="text-muted fw-bold">ACTIVE ASSIGNED</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="asset-glass-card p-4 d-flex align-items-center gap-3">
                <div class="asset-icon-box bg-label-warning"><i class="bx bx-wrench"></i></div>
                <div>
                    <h4 class="mb-0 fw-bold text-dark">{{ $stats['maintenance'] }}</h4>
                    <small class="text-muted fw-bold">UNDER SERVICE</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="asset-glass-card p-4 d-flex align-items-center gap-3">
                <div class="asset-icon-box bg-label-info"><i class="bx bx-shopping-bag"></i></div>
                <div>
                    <h4 class="mb-0 fw-bold text-dark">{{ $stats['available'] }}</h4>
                    <small class="text-muted fw-bold">READY TO CLAIM</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Inventory Asset Data -->
    <div class="row mb-6">
        <div class="col-12">
            <div class="hitech-card animate__animated animate__fadeInUp">
                <div class="hitech-card-header border-bottom d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <h5 class="title mb-0">My Active Inventory</h5>
                        <span class="badge badge-hitech-primary">{{ $stats['assigned'] }} Assets Found</span>
                    </div>
                </div>
                
                <div class="card-datatable table-responsive p-0">
                    <table class="table table-hover mb-0" id="myAssetsTable">
                        <thead class="bg-light-soft">
                            <tr>
                                <th class="text-uppercase small fw-bold">Asset ID</th>
                                <th class="text-uppercase small fw-bold">Inventory Name</th>
                                <th class="text-uppercase small fw-bold">Category</th>
                                <th class="text-uppercase small fw-bold">Current Status</th>
                                <th class="text-uppercase small fw-bold">Acquired Date</th>
                                <th class="text-uppercase small fw-bold">Estimated Value</th>
                                <th class="text-uppercase small fw-bold text-end">Actions</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

{{-- MODALS / SCRIPTS --}}
@endsection

@section('vendor-script')
@vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
])
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Premium DataTable Initialization
    $('#myAssetsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("myAssets.getListAjax") }}',
        columns: [
            { data: 'asset_code' },
            { 
               data: 'name',
               render: function(data, type, row) {
                   return `<div class="fw-bold text-dark">${data}</div><small class="text-muted">${row.category_name}</small>`;
               }
            },
            { data: 'category_name', visible: false },
            { 
               data: 'status_badge',
               render: function(data) {
                   return `<div class="hitech-badge-wrap">${data}</div>`;
               }
            },
            { data: 'assigned_date' },
            { data: 'formatted_current_value' },
            { 
                data: 'action', 
                orderable: false,
                className: 'text-end'
            }
        ],
        dom: '<"card-header p-4 border-bottom"<"d-flex justify-content-between align-items-center"fB>>t<"card-footer p-4 border-top"<"d-flex justify-content-between"ip>>',
        language: {
            sSearch: '',
            searchPlaceholder: 'Search Inventory...'
        },
        order: [[0, 'desc']]
    });
});

function viewAssetDetails(id) {
    window.location.href = '{{ route("myAssets.show", "") }}/' + id;
}

function requestMaintenance(id) {
    if (window.showInfoSwal) {
        window.showInfoSwal('Are you sure you want to request maintenance for this asset?').then((result) => {
            if (result.isConfirmed) {
                submitMaintenance(id);
            }
        });
    } else {
        if (confirm('Request maintenance for this asset?')) submitMaintenance(id);
    }
}

function submitMaintenance(id) {
    $.ajax({
        url: '{{ route("myAssets.requestMaintenance", "") }}/' + id,
        type: 'POST',
        data: { _token: '{{ csrf_token() }}' },
        success: function(response) {
            if (response.status === 'success') {
                if (window.showSuccessSwal) {
                    window.showSuccessSwal('Maintenance request submitted successfully!');
                } else {
                    alert('Success!');
                }
                $('#myAssetsTable').DataTable().ajax.reload();
            } else {
                if (window.showErrorSwal) window.showErrorSwal('Failed to submit request.');
            }
        }
    });
}
</script>
@endpush
