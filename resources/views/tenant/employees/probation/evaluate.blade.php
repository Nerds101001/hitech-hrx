@extends('layouts/layoutMaster')

@section('title', 'Probation Evaluation - ' . $employee->name)

@section('vendor-style')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" />
<style>
    :root {
        --hitech-teal: #008080;
        --hitech-teal-dark: #005a5a;
        --hitech-teal-light: #e6f2f2;
    }
    .evaluation-card {
        border: none;
        border-radius: 20px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.08);
        overflow: hidden;
    }
    .evaluation-header {
        background: linear-gradient(135deg, var(--hitech-teal-dark) 0%, var(--hitech-teal) 100%);
        padding: 40px;
        color: white;
    }
    .evaluation-body {
        padding: 40px;
        background: #fff;
    }
    .employee-info-glass {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 15px;
        padding: 20px;
        margin-top: 20px;
    }
    .criteria-group {
        margin-bottom: 35px;
        padding-bottom: 25px;
        border-bottom: 1px solid #f0f4f8;
    }
    .criteria-title {
        font-weight: 700;
        font-size: 1.1rem;
        color: #1e293b;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .rating-options {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }
    .rating-chip {
        cursor: pointer;
    }
    .rating-chip input {
        display: none;
    }
    .rating-chip span {
        display: inline-block;
        padding: 10px 20px;
        border-radius: 50px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        font-size: 0.9rem;
        font-weight: 600;
        color: #64748b;
        transition: all 0.3s ease;
    }
    .rating-chip input:checked + span {
        background: var(--hitech-teal);
        color: white;
        border-color: var(--hitech-teal);
        box-shadow: 0 5px 15px rgba(0, 128, 128, 0.2);
    }
    .form-label-premium {
        font-weight: 700;
        font-size: 0.85rem;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 10px;
    }
    .recommendation-card {
        background: #f0fdfb;
        border: 1px solid #b2e0e0;
        border-radius: 15px;
        padding: 25px;
    }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card evaluation-card">
                <div class="evaluation-header">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h3 class="text-white mb-1 fw-bold">Employee Probation Confirmation Form</h3>
                            <p class="text-white opacity-75 mb-0">Performance Evaluation after 6 Months</p>
                        </div>
                        <div class="text-end d-none d-sm-block">
                            <img src="{{ asset('assets/img/logo-white.png') }}" height="40" alt="Logo">
                        </div>
                    </div>

                    <div class="employee-info-glass">
                        <div class="row g-4">
                            <div class="col-md-3">
                                <small class="d-block opacity-75">Employee Name</small>
                                <div class="fw-bold">{{ $employee->name }}</div>
                            </div>
                            <div class="col-md-3">
                                <small class="d-block opacity-75">Designation</small>
                                <div class="fw-bold">{{ $employee->designation?->name ?? 'N/A' }}</div>
                            </div>
                            <div class="col-md-3">
                                <small class="d-block opacity-75">Joining Date</small>
                                <div class="fw-bold">{{ \Carbon\Carbon::parse($employee->date_of_joining)->format('d M, Y') }}</div>
                            </div>
                            <div class="col-md-3">
                                <small class="d-block opacity-75">Review Date</small>
                                <div class="fw-bold text-warning">{{ now()->format('d M, Y') }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="evaluation-body">
                    <form action="{{ route('probation.store', $employee->id) }}" method="POST">
                        @csrf
                        
                        <h5 class="mb-5 fw-bold text-primary">I. Performance Evaluation</h5>

                        <!-- 1. Job Knowledge -->
                        <div class="criteria-group">
                            <div class="criteria-title">
                                <i class="bx bx-cog text-teal"></i> 1. Job Knowledge & Skills
                            </div>
                            <p class="text-muted small mb-4">(Technical competence, understanding of role requirements)</p>
                            <div class="rating-options">
                                @foreach(['Excellent', 'Good', 'Satisfactory', 'Needs Improvement'] as $rating)
                                <label class="rating-chip">
                                    <input type="radio" name="job_knowledge" value="{{ $rating }}" required>
                                    <span>{{ $rating }}</span>
                                </label>
                                @endforeach
                            </div>
                        </div>

                        <!-- 2. Quality of Work -->
                        <div class="criteria-group">
                            <div class="criteria-title">
                                <i class="bx bx-check-double text-teal"></i> 2. Quality of Work
                            </div>
                            <p class="text-muted small mb-4">(Accuracy, attention to detail, consistency)</p>
                            <div class="rating-options">
                                @foreach(['Excellent', 'Good', 'Satisfactory', 'Needs Improvement'] as $rating)
                                <label class="rating-chip">
                                    <input type="radio" name="quality_of_work" value="{{ $rating }}" required>
                                    <span>{{ $rating }}</span>
                                </label>
                                @endforeach
                            </div>
                        </div>

                        <!-- 3. Attendance & Punctuality -->
                        <div class="criteria-group">
                            <div class="criteria-title">
                                <i class="bx bx-time text-teal"></i> 3. Attendance & Punctuality
                            </div>
                            <p class="text-muted small mb-4">(Reliability in reporting to work on time)</p>
                            <div class="rating-options">
                                @foreach(['Excellent', 'Good', 'Satisfactory', 'Needs Improvement'] as $rating)
                                <label class="rating-chip">
                                    <input type="radio" name="attendance_punctuality" value="{{ $rating }}" required>
                                    <span>{{ $rating }}</span>
                                </label>
                                @endforeach
                            </div>
                        </div>

                        <!-- 4. Initiative & Reliability -->
                        <div class="criteria-group">
                            <div class="criteria-title">
                                <i class="bx bx-bolt-circle text-teal"></i> 4. Initiative & Reliability
                            </div>
                            <p class="text-muted small mb-4">(Willingness to take responsibility, working under minimal supervision)</p>
                            <div class="rating-options">
                                @foreach(['Excellent', 'Good', 'Satisfactory', 'Needs Improvement'] as $rating)
                                <label class="rating-chip">
                                    <input type="radio" name="initiative_reliability" value="{{ $rating }}" required>
                                    <span>{{ $rating }}</span>
                                </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="mb-6">
                            <label class="form-label-premium">Overall Performance Summary</label>
                            <textarea name="overall_performance" class="form-control" rows="4" placeholder="Summarize the employee's performance during the probation period..." required></textarea>
                        </div>

                        <div class="recommendation-card mb-6">
                            <h5 class="fw-bold mb-4">II. Final Recommendation</h5>
                            
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="mb-4">
                                        <label class="form-label fw-bold">Select Outcome</label>
                                        <select name="recommendation" id="recommendation" class="form-select" required onchange="toggleExtension()">
                                            <option value="confirm">Confirm Employment</option>
                                            <option value="extend">Extend Probation</option>
                                            <option value="terminate">Terminate Employment</option>
                                        </select>
                                    </div>
                                    
                                    <div id="extension_div" style="display: none;">
                                        <label class="form-label fw-bold">Extension Period (Months)</label>
                                        <input type="number" name="extension_months" class="form-control" min="1" max="12" value="3">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-premium">Specific Areas for Improvement</label>
                                    <textarea name="areas_for_improvement" class="form-control" rows="3" placeholder="If extending or terminated, please specify reasons..."></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="mb-6">
                            <label class="form-label-premium">Manager's General Remarks</label>
                            <textarea name="manager_remarks" class="form-control" rows="3" placeholder="Any additional comments?"></textarea>
                        </div>

                        <div class="alert alert-info d-flex align-items-center mb-6" role="alert">
                            <i class="bx bx-info-circle me-2"></i>
                            <div>
                                This evaluation will be shared with HR. The final decision rests with the HR department.
                            </div>
                        </div>

                        <div class="d-grid pt-2">
                            <button type="submit" class="btn btn-primary btn-lg rounded-pill fw-bold">Submit Evaluation to HR</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
    function toggleExtension() {
        const recommendation = document.getElementById('recommendation').value;
        const extensionDiv = document.getElementById('extension_div');
        extensionDiv.style.display = recommendation === 'extend' ? 'block' : 'none';
    }
</script>
@endsection
