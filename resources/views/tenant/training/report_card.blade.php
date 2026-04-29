@extends('layouts.layoutMaster')

@section('title', 'Training Report Card - ' . $user->name)

@section('page-style')
<style>
    .report-card {
        background: white;
        padding: 60px;
        border-radius: 20px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.05);
        position: relative;
        overflow: hidden;
    }

    .report-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 10px;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    }

    .report-header {
        border-bottom: 2px solid #f0f2f8;
        padding-bottom: 30px;
        margin-bottom: 40px;
    }

    .stat-box {
        background: #f8fafc;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        border: 1px solid #edf2f7;
    }

    .stat-value {
        font-size: 1.5rem;
        font-weight: 800;
        color: #4a5568;
    }

    .phase-section {
        margin-bottom: 30px;
    }

    .module-row {
        display: flex;
        justify-content: space-between;
        padding: 15px 0;
        border-bottom: 1px solid #edf2f7;
    }

    .score-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .score-high { background: #e6fffa; color: #234e52; }
    .score-med { background: #fffaf0; color: #744210; }

    @media print {
        .no-print { display: none; }
        .report-card { box-shadow: none; padding: 20px; }
    }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h4 class="fw-bold mb-0">Training Performance Review</h4>
        <div>
            <button class="btn btn-label-secondary me-2" onclick="window.print()">
                <i class="ti ti-printer me-1"></i> Print Report
            </button>
            @if($user->training_status !== 'completed')
                <button class="btn btn-success" id="approve-btn">
                    <i class="ti ti-check me-1"></i> Approve & Finalize Training
                </button>
            @else
                <span class="badge bg-success">Training Finalized</span>
            @endif
        </div>
    </div>

    <div class="report-card">
        <div class="report-header text-center">
            <h2 class="fw-bold text-primary mb-1">Official Training Transcript</h2>
            <p class="text-muted">Hitech HRX - Onboarding Training Module</p>
            
            <div class="row mt-5 text-start">
                <div class="col-md-6">
                    <div class="mb-2"><strong>Employee Name:</strong> {{ $user->name }}</div>
                    <div class="mb-2"><strong>Employee ID:</strong> {{ $user->code ?? 'Pending' }}</div>
                    <div class="mb-2"><strong>Department:</strong> {{ $user->department->name ?? 'N/A' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="mb-2"><strong>Status:</strong> {{ strtoupper($user->training_status) }}</div>
                    <div class="mb-2"><strong>Completion Date:</strong> {{ $user->training_completed_at ? $user->training_completed_at->format('d M, Y') : 'In Progress' }}</div>
                </div>
            </div>
        </div>

        <div class="row mb-5">
            <div class="col-md-4">
                <div class="stat-box">
                    <div class="text-muted small mb-1">Modules Completed</div>
                    <div class="stat-value">{{ $summary['completed_modules'] }} / {{ $summary['total_modules'] }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-box">
                    <div class="text-muted small mb-1">Average Score</div>
                    <div class="stat-value">{{ number_format($summary['average_score'], 1) }}%</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-box">
                    <div class="text-muted small mb-1">Result</div>
                    <div class="stat-value {{ $summary['average_score'] >= 80 ? 'text-success' : 'text-warning' }}">
                        {{ $summary['average_score'] >= 80 ? 'PASSED' : 'INCOMPLETE' }}
                    </div>
                </div>
            </div>
        </div>

        <h5 class="fw-bold mb-4">Detailed Performance Breakdown</h5>

        @foreach($phases as $phase)
            <div class="phase-section">
                <h6 class="text-primary fw-bold mb-3">Phase {{ $phase->order }}: {{ $phase->title }}</h6>
                @foreach($phase->modules as $module)
                    @php $progress = $module->userProgress->first(); @endphp
                    <div class="module-row">
                        <div>
                            <div class="fw-bold">{{ $module->title }}</div>
                            <div class="text-muted small">{{ strtoupper($module->content_type) }}</div>
                        </div>
                        <div class="text-end">
                            @if($progress)
                                <span class="score-badge {{ $progress->assessment_score >= 90 ? 'score-high' : 'score-med' }}">
                                    Score: {{ $progress->assessment_score }}%
                                </span>
                                <div class="text-muted small mt-1">
                                    {{ $progress->completed_at ? $progress->completed_at->format('d M, Y') : 'N/A' }}
                                </div>
                            @else
                                <span class="badge bg-label-secondary">Not Started</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach

        <div class="mt-5 pt-4 text-center border-top">
            <p class="text-muted small">This is a system-generated report. Approval by HR/Manager is required for final dashboard activation.</p>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
    document.getElementById('approve-btn')?.addEventListener('click', function() {
        if (confirm('Are you sure you want to approve and finalize this employee\'s training? This will grant them access to the main dashboard.')) {
            fetch('{{ route("training.approve", $user->id) }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                }
            });
        }
    });
</script>
@endsection
