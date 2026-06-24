@extends('layouts.layoutMaster')

@section('title', 'Sales Targets Import')

@section('vendor-style')
@endsection

@section('page-style')
<style>
.bg-teal-soft { background-color: rgba(18, 116, 100, 0.08) !important; }
.text-teal { color: #127464 !important; }
.border-dashed { border-style: dashed !important; border-width: 2px !important; border-color: #cbd5e1 !important; }
.hover-bg-teal-soft:hover { background-color: rgba(18, 116, 100, 0.04) !important; border-color: #127464 !important; }
.cursor-pointer { cursor: pointer; }
.btn-hitech {
    background: linear-gradient(135deg, #127464, #1b6ca8);
    color: white !important;
    border: none;
}
.btn-hitech:hover {
    box-shadow: 0 4px 12px rgba(18, 116, 100, 0.3);
    transform: translateY(-1px);
}
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm border-0" style="border-radius: 16px;">
                <div class="card-header bg-white border-bottom py-4" style="border-radius: 16px 16px 0 0;">
                    <div class="d-flex align-items-center">
                        <div class="bg-teal-soft p-3 rounded-circle me-3">
                            <i class="bx bx-upload fs-3 text-teal"></i>
                        </div>
                        <div>
                            <h4 class="mb-1 fw-bold text-dark">Bulk Import Sales Targets</h4>
                            <p class="mb-0 text-muted small">Upload historical or future sales targets via CSV/Excel</p>
                        </div>
                    </div>
                </div>

                <div class="card-body p-5">
                    @if(session('error'))
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="bx bx-error-circle me-2"></i>
                        <div>{{ session('error') }}</div>
                    </div>
                    @endif

                    @if(session('success'))
                    <div class="alert alert-success d-flex align-items-center" role="alert">
                        <i class="bx bx-check-circle me-2"></i>
                        <div>{{ session('success') }}</div>
                    </div>
                    @endif
                    
                    @if(session('warning'))
                    <div class="alert alert-warning d-flex align-items-center" role="alert">
                        <i class="bx bx-info-circle me-2"></i>
                        <div>{{ session('warning') }}</div>
                    </div>
                    @endif

                    @if(session('import_errors'))
                    <div class="alert alert-danger" style="max-height: 200px; overflow-y: auto;">
                        <ul class="mb-0 ps-3">
                            @foreach(session('import_errors') as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    <form action="{{ route('sales-visits.sales-targets.import.preview') }}" method="POST" enctype="multipart/form-data" id="uploadForm">
                        @csrf
                        
                        <div class="mb-4 text-start">
                            <label class="form-label fw-bold text-teal">1. Select Salesperson <span class="text-danger">*</span></label>
                            <select name="salesperson_id" id="salesperson_id" class="form-select border-teal" required>
                                <option value="">-- Choose Salesperson --</option>
                                @foreach($salespersons as $sp)
                                    <option value="{{ $sp->id }}">{{ $sp->name }} ({{ $sp->code }})</option>
                                @endforeach
                            </select>
                            <small class="text-muted">All targets in the uploaded file will be assigned to this salesperson.</small>
                        </div>
                        
                        <div class="mb-3 text-start">
                            <label class="form-label fw-bold text-teal">2. Upload Matrix File <span class="text-danger">*</span></label>
                        </div>

                        <div class="text-center p-5 border-dashed rounded-3 bg-light cursor-pointer hover-bg-teal-soft transition-all" onclick="document.getElementById('fileInput').click()">
                            <i class="bx bxs-file-export fs-1 text-muted mb-3 opacity-50"></i>
                            <h5 class="fw-bold mb-1">Click to Upload File</h5>
                            <p class="text-muted small mb-0">.csv, .xls, .xlsx supported (Max 10MB)</p>
                            <div id="fileNameDisplay" class="mt-3 fw-bold text-teal d-none"></div>
                        </div>
                        <input type="file" id="fileInput" name="file" class="d-none" accept=".csv, .xlsx, .xls" onchange="showFileName(this)">

                        <div class="mt-4 text-center">
                            <button type="submit" class="btn btn-hitech rounded-pill px-5 py-2 shadow-sm" id="submitBtn" disabled>
                                <i class="bx bx-right-arrow-alt me-1"></i> Proceed to Mapping
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card shadow-sm border-0 mt-4" style="border-radius: 16px;">
                <div class="card-body bg-light" style="border-radius: 16px;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold mb-0"><i class="bx bx-info-circle me-1"></i> Smart AI Column Mapping</h6>
                        <a href="{{ asset('templates/sales_target_template.csv') }}" class="btn btn-xs btn-outline-primary rounded-pill fw-bold" download>
                            <i class="bx bx-download me-1"></i> Download CSV Template
                        </a>
                    </div>
                    <p class="small text-muted mb-0">
                        Our system will automatically scan your file and guess which column is the Employee Code, which is the Month, and which are the targets. 
                        You'll have a chance to review and fix the mappings on the next screen before any data is saved.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
    function showFileName(input) {
        const display = document.getElementById('fileNameDisplay');
        const submitBtn = document.getElementById('submitBtn');
        if (input.files && input.files[0]) {
            display.textContent = 'Selected: ' + input.files[0].name;
            display.classList.remove('d-none');
            submitBtn.disabled = false;
        } else {
            display.classList.add('d-none');
            submitBtn.disabled = true;
        }
    }
</script>
@endsection
