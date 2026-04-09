@extends('layouts.layoutMaster')

@section('title', 'Biometric Import Preview')

@section('content')
<div class="px-4 py-4">
    <div class="hitech-card animate__animated animate__fadeInUp">
        <div class="hitech-card-header border-bottom d-flex justify-content-between align-items-center">
            <div>
                <h5 class="title mb-1">Import Preview</h5>
                <p class="text-muted small mb-0">Verify employee details auto-fetched via Biometric ID</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('attendance.index') }}" class="btn btn-label-secondary">Cancel</a>
                <form action="{{ route('attendance.biometric-import.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="records" value="{{ json_encode($previewData) }}">
                    <button type="submit" class="btn btn-teal shadow-sm">
                        <i class="bx bx-check-circle me-1"></i> Confirm & Sync to Database
                    </button>
                </form>
            </div>
        </div>
        
        <div class="card-body p-0">
            @include('tenant.attendance.preview_table', ['previewData' => $previewData])
        </div>
    </div>
</div>

<style>
.bg-success-soft { background-color: rgba(40, 199, 111, 0.12) !important; }
.bg-danger-soft { background-color: rgba(255, 77, 73, 0.12) !important; }
.btn-teal { background-color: #005a5a; color: white; border: none; }
.btn-teal:hover { background-color: #004d4d; color: white; }
</style>
@endsection
