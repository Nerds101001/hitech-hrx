@extends('layouts.layoutMaster')

@section('title', 'Map Sales Targets Columns')

@section('page-style')
<style>
.bg-teal-soft { background-color: rgba(18, 116, 100, 0.08) !important; }
.text-teal { color: #127464 !important; }
.btn-hitech {
    background: linear-gradient(135deg, #127464, #1b6ca8);
    color: white !important;
    border: none;
}
.btn-hitech:hover {
    box-shadow: 0 4px 12px rgba(18, 116, 100, 0.3);
    transform: translateY(-1px);
}
.table-preview th {
    background: #f8fafc !important;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    color: #475569;
}
.mapping-box {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.5rem;
    background: #fff;
    margin-bottom: 1.5rem;
    transition: all 0.2s;
}
.mapping-box:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 12px rgba(0,0,0,0.02);
}
.required-star { color: #ef4444; }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="bx bx-shuffle text-teal me-2"></i>Map Columns</h4>
            <p class="text-muted small mb-0">Our AI has attempted to guess the columns. Please review and confirm.</p>
        </div>
        <a href="{{ route('sales-visits.sales-targets.import') }}" class="btn btn-outline-secondary rounded-pill btn-sm shadow-sm">
            <i class="bx bx-arrow-back me-1"></i> Start Over
        </a>
    </div>

    <form action="{{ route('sales-visits.sales-targets.import.store') }}" method="POST">
        @csrf
        <input type="hidden" name="file_path" value="{{ $filePath }}">
        <input type="hidden" name="salesperson_id" value="{{ $salesperson_id }}">

        <div class="row">

            <div class="col-md-4">
                <div class="mapping-box">
                    <h6 class="fw-bold"><i class="bx bx-calendar me-1 text-warning"></i>Month / Year <span class="required-star">*</span></h6>
                    <p class="small text-muted mb-2">The period for the target (e.g. 2026-01).</p>
                    <select name="mapping[month_year]" class="form-select" required>
                        <option value="">-- Select Column --</option>
                        @foreach($headers as $idx => $header)
                            <option value="{{ $idx }}" {{ (string)$suggestedMappings['month_year'] === (string)$idx ? 'selected' : '' }}>{{ $header }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="mapping-box">
                    <h6 class="fw-bold"><i class="bx bx-target-lock me-1 text-success"></i>Kingo Target</h6>
                    <p class="small text-muted mb-2">Optional target value.</p>
                    <select name="mapping[kingo_target]" class="form-select">
                        <option value="">-- Do Not Import --</option>
                        @foreach($headers as $idx => $header)
                            <option value="{{ $idx }}" {{ (string)$suggestedMappings['kingo_target'] === (string)$idx ? 'selected' : '' }}>{{ $header }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="col-md-4">
                <div class="mapping-box">
                    <h6 class="fw-bold"><i class="bx bx-target-lock me-1 text-info"></i>Bingo Target</h6>
                    <p class="small text-muted mb-2">Optional target value.</p>
                    <select name="mapping[bingo_target]" class="form-select">
                        <option value="">-- Do Not Import --</option>
                        @foreach($headers as $idx => $header)
                            <option value="{{ $idx }}" {{ (string)$suggestedMappings['bingo_target'] === (string)$idx ? 'selected' : '' }}>{{ $header }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4" style="border-radius: 16px;">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 fw-bold">Data Preview (First 5 Rows)</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-preview mb-0">
                    <thead>
                        <tr>
                            @foreach($headers as $header)
                                <th>{{ $header }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($previewData as $row)
                        <tr>
                            @foreach($row as $cell)
                                <td>{{ \Illuminate\Support\Str::limit($cell, 50) }}</td>
                            @endforeach
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="text-end">
            <button type="submit" class="btn btn-hitech px-5 py-2 shadow-sm rounded-pill">
                <i class="bx bx-check-circle me-1"></i> Confirm & Import Data
            </button>
        </div>
    </form>
</div>
@endsection
