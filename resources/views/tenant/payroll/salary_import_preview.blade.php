@extends('layouts.layoutMaster')

@section('title', 'Salary Breakup Import Preview')

@section('content')
<div class="px-4 py-4">
    <div class="hitech-card animate__animated animate__fadeInUp">
        <div class="hitech-card-header border-bottom d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h5 class="title mb-1"><i class="bx bx-show me-2 text-teal"></i>Salary Import Preview</h5>
                <p class="text-muted small mb-0">Verify the loaded salary breakups and overrides before saving them to the system.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('payroll.index') }}" class="btn btn-label-secondary">Cancel</a>
                <form action="{{ route('payroll.salary-import.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="records" value="{!! htmlspecialchars($recordsJson, ENT_QUOTES, 'UTF-8') !!}">
                    <button type="submit" class="btn btn-teal shadow-sm">
                        <i class="bx bx-check-circle me-1"></i> Confirm & Import all
                    </button>
                </form>
            </div>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Identifier</th>
                            <th>Employee Name</th>
                            <th>Base Salary</th>
                            <th>Policy</th>
                            <th>Basic (Custom)</th>
                            <th>HRA (Custom)</th>
                            <th>CA (Custom)</th>
                            <th>Medical (Custom)</th>
                            <th>Edu (Custom)</th>
                            <th>Special (Custom)</th>
                            <th>PT (Custom)</th>
                            <th>EPF (Custom)</th>
                            <th>ESIC (Custom)</th>
                            <th class="pe-4 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($previewData as $row)
                        <tr class="{{ $row['status'] === 'Error' ? 'bg-danger-light' : '' }}">
                            <td class="ps-4 font-monospace small text-primary">{{ $row['identifier'] }}</td>
                            <td>
                                @if($row['user_id'])
                                    <span class="fw-bold text-dark">{{ $row['employee_name'] }}</span>
                                @else
                                    <span class="text-danger small"><i class="bx bx-error-circle me-1"></i>Match Not Found</span>
                                @endif
                            </td>
                            <td>
                                @if($row['status'] !== 'Error')
                                    @if($row['old_base_salary'] != $row['base_salary'])
                                        <del class="text-muted small">₹{{ number_format($row['old_base_salary']) }}</del> 
                                        <i class="bx bx-right-arrow-alt text-teal mx-1"></i>
                                        <span class="fw-bold text-success">₹{{ number_format($row['base_salary']) }}</span>
                                    @else
                                        <span class="text-dark">₹{{ number_format($row['base_salary']) }}</span>
                                    @endif
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="small">{{ $row['salary_policy_name'] }}</td>
                            <td class="small font-monospace">{{ $row['custom_basic'] !== null ? '₹' . number_format($row['custom_basic']) : '-' }}</td>
                            <td class="small font-monospace">{{ $row['custom_hra'] !== null ? '₹' . number_format($row['custom_hra']) : '-' }}</td>
                            <td class="small font-monospace">{{ $row['custom_ca'] !== null ? '₹' . number_format($row['custom_ca']) : '-' }}</td>
                            <td class="small font-monospace">{{ $row['custom_medical'] !== null ? '₹' . number_format($row['custom_medical']) : '-' }}</td>
                            <td class="small font-monospace">{{ $row['custom_edu'] !== null ? '₹' . number_format($row['custom_edu']) : '-' }}</td>
                            <td class="small font-monospace">{{ $row['custom_special_allowance'] !== null ? '₹' . number_format($row['custom_special_allowance']) : '-' }}</td>
                            <td class="small font-monospace text-danger">{{ $row['custom_pt'] !== null ? '₹' . number_format($row['custom_pt']) : '-' }}</td>
                            <td class="small font-monospace text-danger">{{ $row['custom_epf'] !== null ? '₹' . number_format($row['custom_epf']) : '-' }}</td>
                            <td class="small font-monospace text-danger">{{ $row['custom_esic'] !== null ? '₹' . number_format($row['custom_esic']) : '-' }}</td>
                            <td class="pe-4 text-center">
                                @if($row['status'] == 'Ready')
                                    <span class="badge bg-success-soft text-success rounded-pill"><i class="bx bx-check me-1"></i>Ready</span>
                                @else
                                    <span class="badge bg-danger-soft text-danger rounded-pill" title="{{ $row['error_message'] }}"><i class="bx bx-x me-1"></i>Skip</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-top bg-light">
                <div class="d-flex align-items-center">
                    <i class="bx bx-info-circle fs-4 me-3 text-teal"></i>
                    <p class="mb-0 small text-muted">
                        <b>Total uploaded rows:</b> {{ count($previewData) }}. 
                        @php 
                            $errorCount = collect($previewData)->where('status', 'Error')->count();
                            $readyCount = collect($previewData)->where('status', 'Ready')->count();
                        @endphp
                        <span class="text-teal fw-bold">{{ $readyCount }} records are ready to import.</span>
                        @if($errorCount > 0)
                            <span class="text-danger fw-bold ms-2">{{ $errorCount }} records have errors and will be skipped.</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.bg-success-soft { background-color: rgba(40, 199, 111, 0.12) !important; }
.bg-danger-soft { background-color: rgba(255, 77, 73, 0.12) !important; }
.bg-danger-light { background-color: rgba(255, 77, 73, 0.03) !important; }
.btn-teal { background-color: #005a5a; color: white; border: none; }
.btn-teal:hover { background-color: #004d4d; color: white; }
</style>
@endsection
