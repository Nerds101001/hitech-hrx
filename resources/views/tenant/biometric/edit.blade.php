@extends('layouts.layoutMaster')

@section('title', 'Edit Biometric Device')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
    'resources/assets/vendor/libs/select2/select2.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
  <style>
    .emp-field-box {
        background-color: #fff;
        border: 1px solid #E2E8F0;
        border-radius: 12px;
        padding: 0.5rem 1rem;
        transition: all 0.2s ease;
        margin-bottom: 0px;
        position: relative;
    }
    .emp-field-box:focus-within { 
        border-color: #127464; 
        box-shadow: 0 0 0 3px rgba(18, 116, 100, 0.1);
    }
    .form-control-hitech, .form-select-hitech {
        border: none !important;
        background: transparent !important;
        padding: 0.5rem 0 !important;
        font-size: 0.95rem !important;
        width: 100% !important;
        outline: none !important;
        color: #1e293b !important;
        box-shadow: none !important;
        display: block !important;
    }
    .form-label-hitech {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        color: #94A3B8;
        margin-bottom: 0px;
        display: block;
    }
    .btn-hitech-primary {
        background: linear-gradient(135deg, #127464 0%, #0E5A4E 100%);
        border: none;
        color: white;
        padding: 0.75rem 2rem;
        border-radius: 50px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    /* Select2 Skin Fix */
    .select2-container--default .select2-selection--single {
        border: none !important;
        background: transparent !important;
        height: auto !important;
        padding: 0 !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        padding: 0.5rem 0 !important;
        line-height: normal !important;
        color: #1e293b !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 100% !important;
        top: 0 !important;
    }
  </style>
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.js',
    'resources/assets/vendor/libs/select2/select2.js'
  ])
@endsection

@section('page-script')
<script>
window.addEventListener('load', function() {
    if (typeof jQuery !== 'undefined' && typeof $.fn.select2 !== 'undefined') {
        $('select[name="site_id"]').select2({
            width: '100%',
            dropdownParent: $('#deviceForm')
        });
    }

    $('#testConnectionBtn').on('click', function() {
        var ip = $('input[name="ip_address"]').val();
        var port = $('input[name="port"]').val();
        
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> Testing...');
        
        $.ajax({
            url: '{{ route("biometric.test-connection") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                ip_address: ip,
                port: port
            },
            success: function(response) {
                btn.prop('disabled', false).html('<i class="bx bx-broadcast me-1"></i> Test Connection');
                if(response.status == 'success') {
                    Swal.fire('Success', 'Port is open! Connection successful.', 'success');
                } else {
                    Swal.fire('Failed', response.message, 'error');
                }
            },
            error: function() {
                btn.prop('disabled', false).html('<i class="bx bx-broadcast me-1"></i> Test Connection');
                Swal.fire('Error', 'Server connection failed', 'error');
            }
        });
    });
});
</script>
@endsection

@section('content')
<div class="animate__animated animate__fadeIn">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">Edit Biometric Machine</h4>
            <span class="text-muted" style="font-size: 0.85rem;">Update configuration for <b>{{ $biometric->name }}</b>.</span>
        </div>
        <a href="{{ route('biometric.index') }}" class="btn btn-outline-secondary rounded-pill btn-sm d-flex align-items-center" style="font-size: 0.8rem; font-weight: 500;">
            <i class="bx bx-arrow-back me-1"></i> Back to List
        </a>
    </div>

    <form id="deviceForm" action="{{ route('biometric.update', $biometric->id) }}" method="post" class="needs-validation">
    @csrf
    @method('PUT')
    <div class="row">
        <!-- Sidebar Controls -->
        <div class="col-xl-4 col-lg-4 col-md-12 mb-4">
            <div class="card hitech-card-white border-0 shadow-sm" style="border-radius: 16px;">
                <div class="card-header py-4 px-4 border-bottom d-flex align-items-center" style="background: rgba(18, 116, 100, 0.02);">
                    <i class="bx bx-broadcast me-2 fs-4 text-teal"></i>
                    <h6 class="mb-0 fw-bold">Connectivity Status</h6>
                </div>
                <div class="card-body p-4">
                    <div class="mb-4 text-center">
                        <span class="d-block small fw-bold text-uppercase text-muted mb-2 text-start">Last Reported Status</span>
                        @if($biometric->status == 'online')
                            <div class="p-3 rounded-pill bg-label-success d-flex align-items-center justify-content-center">
                                <i class="bx bx-check-circle me-2"></i> <b>ONLINE</b>
                            </div>
                        @else
                            <div class="p-3 rounded-pill bg-label-danger d-flex align-items-center justify-content-center">
                                <i class="bx bx-x-circle me-2"></i> <b>OFFLINE</b>
                            </div>
                        @endif
                        <small class="text-muted mt-3 d-block">Synced: {{ $biometric->last_sync_at ?: 'Never' }}</small>
                    </div>

                    <button type="button" id="testConnectionBtn" class="btn btn-outline-primary w-100 rounded-pill py-2">
                        <i class="bx bx-broadcast me-1"></i> Test Connection
                    </button>
                    
                    @if($biometric->last_error)
                        <div class="mt-4 p-3 rounded-3 bg-label-danger border-0">
                            <span class="d-block small fw-bold text-uppercase mb-1">Last Sync Error</span>
                            <p class="mb-0 small" style="font-size: 0.75rem;">{{ $biometric->last_error }}</p>
                        </div>
                    @endif
                </div>
            </div>
            
            <div class="d-grid gap-2 mt-4">
                <button type="submit" class="btn btn-hitech-primary shadow-sm py-3">
                    <i class="bx bx-save me-1"></i> Update Machine
                </button>
                <a href="{{ route('biometric.index') }}" class="btn btn-label-secondary py-3">Cancel</a>
            </div>
        </div>

        <!-- Main Form Area -->
        <div class="col-xl-8 col-lg-8 col-md-12">
            <div class="card hitech-card-white border-0 shadow-sm" style="border-radius: 16px;">
                <div class="card-header py-4 px-4 border-bottom d-flex align-items-center bg-white">
                    <i class="bx bx-cog me-2 fs-4 text-teal"></i>
                    <h6 class="mb-0 fw-bold">Modify Settings</h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-12">
                            <div class="emp-field-box">
                                <label class="form-label-hitech">Machine Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control-hitech" required placeholder="e.g. Main Office Entrance" value="{{ old('name', $biometric->name) }}">
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="emp-field-box">
                                <label class="form-label-hitech">Public IP / WAN Address <span class="text-danger">*</span></label>
                                <input type="text" name="ip_address" class="form-control-hitech" required placeholder="e.g. 182.xx.xx.xx or local IP" value="{{ old('ip_address', $biometric->ip_address) }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="emp-field-box">
                                <label class="form-label-hitech">Port Number <span class="text-danger">*</span></label>
                                <input type="number" name="port" class="form-control-hitech" required placeholder="4370" value="{{ old('port', $biometric->port) }}">
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="emp-field-box">
                                <label class="form-label-hitech">Assign to Branch / Site <span class="text-danger">*</span></label>
                                <select name="site_id" class="form-select-hitech" required>
                                    <option value="">Select Branch</option>
                                    @foreach($sites as $id => $title)
                                        <option value="{{ $id }}" {{ $biometric->site_id == $id ? 'selected' : '' }}>{{ $title }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="emp-field-box d-flex align-items-center justify-content-between py-3">
                                <div>
                                    <h6 class="mb-0 fw-bold" style="font-size: 0.9rem;">Auto-Sync Status</h6>
                                    <p class="text-muted small mb-0">Allow system to pull logs from this machine automatically.</p>
                                </div>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="is_active" {{ $biometric->is_active ? 'checked' : '' }} style="width: 2.5em; height: 1.25em; cursor: pointer;">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </form>
</div>
@endsection
