@extends('layouts/layoutMaster')

@section('title', 'Asset Details')

@section('vendor-style')
@vite(['resources/assets/vendor/libs/animate-css/animate.scss'])
@vite(['resources/assets/vendor/scss/pages/hitech-portal.scss'])
@endsection

@@section('content')
<div class="content-wrapper">
    <!-- Content -->
    <div class="container-xxl flex-grow-1 container-p-y">
        
        {{-- ===== HERO BANNER ===== --}}
        <div class="row mb-6">
            <div class="col-12">
                <x-hero-banner
                    title="Asset Details"
                    subtitle="In-depth information and history for {{ $asset->name }}"
                    icon="bx-info-circle"
                    gradient="info"
                />
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="hitech-card animate__animated animate__fadeInUp">
                    <div class="hitech-card-header">
                        <h5 class="title mb-0">Asset Specification</h5>
                        <div class="card-actions">
                            <a href="{{ route('assets.index') }}" class="btn btn-hitech px-4 rounded-pill d-flex align-items-center gap-2">
                                <i class="bx bx-left-arrow-alt fs-5"></i>
                                <span>Back</span>
                            </a>
                            @if(auth()->user()->hasRole(['admin', 'hr']))
                                <a href="{{ route('assets.edit', $asset->id) }}" class="btn btn-primary btn-sm btn-hitech px-3" style="background: linear-gradient(135deg, #005a5a 0%, #008a8a 100%); border:none; border-radius:8px;">
                                    <i class="bx bx-edit me-1"></i> Edit
                                </a>
                            @endif
                        </div>
                    </div>
                    <div class="card-body mt-4 p-5">
                        <div class="row g-6">
                            <div class="col-md-6">
                                <div class="p-4 rounded-3" style="background: rgba(0, 90, 90, 0.03); border: 1px solid rgba(0, 90, 90, 0.05);">
                                    <h6 class="form-label-hitech mb-4 text-primary"><i class="bx bx-cube me-2"></i>Core Information</h6>
                                    <table class="table table-borderless table-sm">
                                        <tr>
                                            <td class="ps-0 py-2"><span class="text-muted small text-uppercase fw-bold">Asset Code</span></td>
                                            <td class="py-2"><span class="badge bg-label-secondary font-monospace">{{ $asset->asset_code }}</span></td>
                                        </tr>
                                        <tr>
                                            <td class="ps-0 py-2"><span class="text-muted small text-uppercase fw-bold">Name</span></td>
                                            <td class="py-3 fw-bold text-dark">{{ $asset->name }}</td>
                                        </tr>
                                        <tr>
                                            <td class="ps-0 py-2"><span class="text-muted small text-uppercase fw-bold">Category</span></td>
                                            <td class="py-2">
                                                <span class="badge bg-label-info badge-hitech">{{ $asset->category->name ?? 'N/A' }}</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="ps-0 py-2"><span class="text-muted small text-uppercase fw-bold">Status</span></td>
                                            <td class="py-2"><span class="badge {{ $asset->status_badge }} badge-hitech">{{ ucfirst($asset->status) }}</span></td>
                                        </tr>
                                        <tr>
                                            <td class="ps-0 py-2"><span class="text-muted small text-uppercase fw-bold">Location</span></td>
                                            <td class="py-2 text-dark">{{ $asset->location ?? 'N/A' }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-4 rounded-3" style="background: rgba(0, 90, 90, 0.03); border: 1px solid rgba(0, 90, 90, 0.05);">
                                    <h6 class="form-label-hitech mb-4 text-primary"><i class="bx bx-purchase-tag me-2"></i>Financial & Logistics</h6>
                                    <table class="table table-borderless table-sm">
                                        <tr>
                                            <td class="ps-0 py-2"><span class="text-muted small text-uppercase fw-bold">Purchase Date</span></td>
                                            <td class="py-2 text-dark">{{ $asset->purchase_date->format('M d, Y') }}</td>
                                        </tr>
                                        <tr>
                                            <td class="ps-0 py-2"><span class="text-muted small text-uppercase fw-bold">Purchase Cost</span></td>
                                            <td class="py-2 fw-bold text-success">₹{{ $asset->formatted_purchase_cost }}</td>
                                        </tr>
                                        <tr>
                                            <td class="ps-0 py-2"><span class="text-muted small text-uppercase fw-bold">Current Value</span></td>
                                            <td class="py-2 fw-bold text-teal">₹{{ $asset->formatted_current_value }}</td>
                                        </tr>
                                        @if($asset->description)
                                            <tr>
                                                <td class="ps-0 py-2"><span class="text-muted small text-uppercase fw-bold">Description</span></td>
                                                <td class="py-2 text-muted small">{{ $asset->description }}</td>
                                            </tr>
                                        @endif
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="row g-6 mt-2">
                            <div class="col-md-6">
                                <div class="p-4 rounded-3 h-100" style="background: rgba(0, 90, 90, 0.03); border: 1px solid rgba(0, 90, 90, 0.05);">
                                    <h6 class="form-label-hitech mb-4 text-primary"><i class="bx bx-user-circle me-2"></i>Assignment Details</h6>
                                    <table class="table table-borderless table-sm">
                                        <tr>
                                            <td class="ps-0 py-2"><span class="text-muted small text-uppercase fw-bold">Assigned To</span></td>
                                            <td class="py-2">
                                                @if($asset->assignedUser)
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar avatar-sm me-2">
                                                            <span class="avatar-initial rounded-circle bg-label-primary">{{ $asset->assignedUser->getInitials() }}</span>
                                                        </div>
                                                        <span class="fw-bold text-dark">{{ $asset->assignedUser->first_name }} {{ $asset->assignedUser->last_name }}</span>
                                                    </div>
                                                @else
                                                    <span class="text-muted italic">Unassigned</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @if($asset->serial_number)
                                        <tr>
                                            <td class="ps-0 py-2"><span class="text-muted small text-uppercase fw-bold">Serial No</span></td>
                                            <td class="py-2 text-dark font-monospace">{{ $asset->serial_number }}</td>
                                        </tr>
                                        @endif
                                        @if($asset->brand || $asset->model)
                                        <tr>
                                            <td class="ps-0 py-2"><span class="text-muted small text-uppercase fw-bold">Make/Model</span></td>
                                            <td class="py-2 text-dark">{{ $asset->brand }} {{ $asset->model }}</td>
                                        </tr>
                                        @endif
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-4 rounded-3 h-100" style="background: rgba(0, 90, 90, 0.03); border: 1px solid rgba(0, 90, 90, 0.05);">
                                    <h6 class="form-label-hitech mb-4 text-primary"><i class="bx bx-shield-check me-2"></i>Warranty & Docs</h6>
                                    <table class="table table-borderless table-sm">
                                        @if($asset->warranty_expiry)
                                        <tr>
                                            <td class="ps-0 py-2"><span class="text-muted small text-uppercase fw-bold">Expiry</span></td>
                                            <td class="py-2">
                                                <span class="text-dark">{{ $asset->warranty_expiry->format('M d, Y') }}</span>
                                                @if($asset->warranty_expiring_soon)
                                                    <span class="badge bg-label-warning ms-2 animate__animated animate__pulse animate__infinite">Expiring Soon</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @endif
                                        @if($asset->warranty_bill)
                                        <tr>
                                            <td class="ps-0 py-2"><span class="text-muted small text-uppercase fw-bold">Bill/Receipt</span></td>
                                            <td class="py-2">
                                                <a href="{{ $asset->warranty_bill_url }}" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                                    <i class="bx bx-file me-1"></i> View Bill
                                                </a>
                                            </td>
                                        </tr>
                                        @endif
                                    </table>
                                </div>
                            </div>
                        </div>

                        @if($asset->extra_details && count($asset->extra_details) > 0)
                        <div class="row mt-6">
                            <div class="col-12">
                                <div class="p-4 rounded-3" style="background: rgba(0, 90, 90, 0.03); border: 1px solid rgba(0, 90, 90, 0.05);">
                                    <h6 class="form-label-hitech mb-4 text-primary"><i class="bx bx-list-check me-2"></i>Category Specific Details</h6>
                                    <div class="row">
                                        @foreach($asset->extra_details as $key => $value)
                                            <div class="col-md-4 mb-3">
                                                <div class="small text-muted text-uppercase fw-bold mb-1">{{ $key }}</div>
                                                <div class="text-dark fw-medium">{{ $value ?? 'N/A' }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                        
                        @if($asset->maintenanceRecords && $asset->maintenance_records->count() > 0)
                        <div class="row mt-6">
                            <div class="col-12">
                                <h6 class="form-label-hitech mb-3"><i class="bx bx-history me-2"></i>Maintenance History</h6>
                                <div class="table-responsive border rounded-3">
                                    <table class="table table-hover mb-0">
                                        <thead class="bg-light">
                                            <tr>
                                                <th class="border-0">Date</th>
                                                <th class="border-0">Type</th>
                                                <th class="border-0">Cost</th>
                                                <th class="border-0">Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($asset->maintenance_records as $maintenance)
                                                <tr>
                                                    <td>{{ $maintenance->created_at->format('M d, Y') }}</td>
                                                    <td><span class="badge bg-label-secondary">{{ $maintenance->maintenance_type ?? 'N/A' }}</span></td>
                                                    <td class="fw-bold">₹{{ number_format($maintenance->cost, 2) }}</td>
                                                    <td class="small text-muted">{{ $maintenance->notes ?? 'N/A' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
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
