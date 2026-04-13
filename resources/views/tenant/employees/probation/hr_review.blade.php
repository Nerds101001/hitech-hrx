@extends('layouts/layoutMaster')

@section('title', 'HR Review - Probation')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-6">
                <div class="card-header bg-teal text-white p-4 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-white fw-bold">Manager's Evaluation Review</h5>
                    <span class="badge bg-white text-teal fw-bold">Submitted: {{ \Carbon\Carbon::parse($evaluation->submitted_at)->format('d M, Y') }}</span>
                </div>
                <div class="card-body p-5">
                    <div class="row mb-5">
                        <div class="col-md-6">
                            <label class="text-muted small text-uppercase fw-bold">Employee</label>
                            <div class="h5 fw-bold">{{ $evaluation->user->name }} ({{ $evaluation->user->code }})</div>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small text-uppercase fw-bold">Reporting Manager</label>
                            <div class="h5 fw-bold">{{ $evaluation->manager->name }}</div>
                        </div>
                    </div>

                    <div class="table-responsive mb-5">
                        <table class="table table-bordered">
                            <thead class="bg-light">
                                <tr>
                                    <th>Criteria</th>
                                    <th>Rating</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Job Knowledge & Skills</td>
                                    <td class="fw-bold">{{ $evaluation->job_knowledge }}</td>
                                </tr>
                                <tr>
                                    <td>Quality of Work</td>
                                    <td class="fw-bold">{{ $evaluation->quality_of_work }}</td>
                                </tr>
                                <tr>
                                    <td>Attendance & Punctuality</td>
                                    <td class="fw-bold">{{ $evaluation->attendance_punctuality }}</td>
                                </tr>
                                <tr>
                                    <td>Initiative & Reliability</td>
                                    <td class="fw-bold">{{ $evaluation->initiative_reliability }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mb-5">
                        <label class="text-muted small text-uppercase fw-bold mb-2 d-block">Overall Performance Summary</label>
                        <div class="p-3 bg-light rounded text-dark">{{ $evaluation->overall_performance }}</div>
                    </div>

                    <div class="mb-5">
                        <label class="text-muted small text-uppercase fw-bold mb-2 d-block">Manager recommendation</label>
                        <div class="badge bg-label-primary fs-6 p-2 px-3 fw-bold">
                            @if($evaluation->recommendation === 'confirm') Confirm Employment
                            @elseif($evaluation->recommendation === 'extend') Extend Probation ({{ $evaluation->extension_months }} months)
                            @else Terminate Employment @endif
                        </div>
                    </div>

                    @if($evaluation->areas_for_improvement)
                    <div class="mb-5">
                        <label class="text-muted small text-uppercase fw-bold mb-2 d-block">Areas for Improvement</label>
                        <div class="p-3 border-start border-4 border-warning bg-light rounded text-dark">{{ $evaluation->areas_for_improvement }}</div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- HR DECISION FORM -->
             <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                <div class="card-header bg-dark text-white p-4">
                    <h5 class="mb-0 text-white fw-bold">HR Final Decision</h5>
                </div>
                <div class="card-body p-5">
                    <form action="{{ route('probation.finalize', $evaluation->id) }}" method="POST">
                        @csrf
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Confirmation Choice</label>
                                <select name="hr_decision" class="form-select" required>
                                    <option value="confirm" {{ $evaluation->recommendation === 'confirm' ? 'selected' : '' }}>Confirm Employment</option>
                                    <option value="extend" {{ $evaluation->recommendation === 'extend' ? 'selected' : '' }}>Extend Probation</option>
                                    <option value="terminate" {{ $evaluation->recommendation === 'terminate' ? 'selected' : '' }}>Fail Probation / Terminate</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Effective Date</label>
                                <input type="date" name="effective_date" class="form-control" value="{{ $evaluation->user->probation_end_date ?? now()->format('Y-m-d') }}" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold">HR Remarks / internal Notes</label>
                                <textarea name="hr_remarks" class="form-control" rows="3" placeholder="Enter any notes relating to the final decision..."></textarea>
                            </div>
                        </div>

                        <div class="d-flex gap-3 justify-content-end mt-6">
                            <a href="{{ route('probation.index') }}" class="btn btn-outline-secondary rounded-pill">Cancel</a>
                            <button type="submit" class="btn btn-teal rounded-pill px-5 fw-bold">Finalize & Update Employee Record</button>
                        </div>
                    </form>
                </div>
             </div>
        </div>
    </div>
</div>

<style>
    .bg-teal { background-color: #008080 !important; }
    .text-teal { color: #008080 !important; }
    .btn-teal { background-color: #008080; border-color: #008080; color: white; }
    .btn-teal:hover { background-color: #005a5a; border-color: #005a5a; color: white; }
</style>
@endsection
