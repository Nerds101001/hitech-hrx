@extends('layouts/layoutMaster')

@section('title', 'Edit Asset')

@section('vendor-style')
@vite(['resources/assets/vendor/libs/select2/select2.scss'])
@vite(['resources/assets/vendor/libs/animate-css/animate.scss'])
@vite(['resources/assets/vendor/scss/pages/hitech-portal.scss'])
@endsection

@section('vendor-script')
@vite(['resources/assets/vendor/libs/select2/select2.js'])
@endsection

@section('content')
<div class="content-wrapper">
    <!-- Content -->
    <div class="container-xxl flex-grow-1 container-p-y">
        
        {{-- ===== HERO BANNER ===== --}}
        <div class="row mb-6">
            <div class="col-12">
                <x-hero-banner
                    title="Edit Asset"
                    subtitle="Update specifications for {{ $asset->name }}"
                    icon="bx-edit"
                    gradient="primary"
                />
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="hitech-card animate__animated animate__fadeInUp">
                    <div class="hitech-card-header">
                        <h5 class="title mb-0">Modify Asset Information</h5>
                        <div class="card-actions">
                            <a href="{{ route('assets.index') }}" class="btn btn-hitech btn-sm px-4 rounded-pill">
                                <i class="bx bx-left-arrow-alt fs-5"></i> Back
                            </a>
                        </div>
                    </div>
                    <div class="card-body mt-4 p-5">
                        <form method="POST" action="{{ route('assets.update', $asset->id) }}" id="assetForm">
                            @csrf
                            @method('PUT')
                            <div class="row g-6">
                                <div class="col-md-6 mb-4">
                                    <label class="form-label-hitech">Asset Name *</label>
                                    <input type="text" name="name" class="form-control-hitech w-100" value="{{ old('name', $asset->name) }}" required>
                                    @error('name')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label class="form-label-hitech">Asset Code <span class="badge bg-label-secondary ms-2 small">System ID</span></label>
                                    <input type="text" name="asset_code" class="form-control-hitech w-100 bg-light" value="{{ old('asset_code', $asset->asset_code) }}" readonly style="cursor: not-allowed;">
                                    @error('asset_code')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="row g-6">
                                <div class="col-md-6 mb-4">
                                    <label class="form-label-hitech">Category *</label>
                                    <select name="category_id" class="form-select-hitech select2 w-100" required>
                                        <option value="">Select Category</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}" {{ old('category_id', $asset->category_id) == $category->id ? 'selected' : '' }}>
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('category_id')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label class="form-label-hitech">Assign To</label>
                                    <select name="assigned_to" class="form-select-hitech select2 w-100">
                                        <option value="">Select User</option>
                                        @foreach($users as $user)
                                            <option value="{{ $user->id }}" {{ old('assigned_to', $asset->assigned_to) == $user->id ? 'selected' : '' }}>
                                                {{ $user->first_name }} {{ $user->last_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('assigned_to')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="row g-6">
                                <div class="col-md-6 mb-4">
                                    <label class="form-label-hitech">Status *</label>
                                    <select name="status" class="form-select-hitech w-100" required>
                                        <option value="available" {{ old('status', $asset->status) == 'available' ? 'selected' : '' }}>Available</option>
                                        <option value="assigned" {{ old('status', $asset->status) == 'assigned' ? 'selected' : '' }}>Assigned</option>
                                        <option value="maintenance" {{ old('status', $asset->status) == 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                                        <option value="retired" {{ old('status', $asset->status) == 'retired' ? 'selected' : '' }}>Retired</option>
                                    </select>
                                    @error('status')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label class="form-label-hitech">Warranty Expiry</label>
                                    <input type="date" name="warranty_expiry" class="form-control-hitech w-100" value="{{ old('warranty_expiry', $asset->warranty_expiry ? $asset->warranty_expiry->format('Y-m-d') : '') }}">
                                    @error('warranty_expiry')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="row g-6">
                                <div class="col-md-6 mb-4">
                                    <label class="form-label-hitech">Brand</label>
                                    <input type="text" name="brand" class="form-control-hitech w-100" value="{{ old('brand', $asset->brand) }}">
                                    @error('brand')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label class="form-label-hitech">Model</label>
                                    <input type="text" name="model" class="form-control-hitech w-100" value="{{ old('model', $asset->model) }}">
                                    @error('model')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="row g-6">
                                <div class="col-md-6 mb-4">
                                    <label class="form-label-hitech">Location</label>
                                    <input type="text" name="location" class="form-control-hitech w-100" value="{{ old('location', $asset->location) }}">
                                    @error('location')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label class="form-label-hitech">Serial Number</label>
                                    <input type="text" name="serial_number" class="form-control-hitech w-100" value="{{ old('serial_number', $asset->serial_number) }}">
                                    @error('serial_number')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="row g-6">
                                <div class="col-md-12 mb-4">
                                    <label class="form-label-hitech">Description</label>
                                    <textarea name="description" class="form-control-hitech w-100" rows="4">{{ old('description', $asset->description) }}</textarea>
                                    @error('description')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="row pt-2">
                                <div class="col-12 text-end">
                                    <button type="submit" class="btn btn-primary px-5" style="background: linear-gradient(135deg, #005a5a 0%, #008a8a 100%); border:none; border-radius:10px; padding: 0.75rem 2.5rem; font-weight:700; color: #ffffff;">
                                        <i class="bx bx-save me-2"></i> Update Asset
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%'
    });
});
</script>
@endsection
