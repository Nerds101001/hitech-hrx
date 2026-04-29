@if(isset($globalPendingProbations) && $globalPendingProbations->isNotEmpty())
    @php
        $isEvaluationPage = request()->routeIs('probation.evaluate');
    @endphp

    @if(!$isEvaluationPage)
    <div class="modal fade" id="probationAlertModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg overflow-hidden" style="border-radius: 20px;">
                <!-- Header with Gradient -->
                <div class="modal-header border-0 py-4 px-4 d-flex flex-column align-items-center text-center" style="background: linear-gradient(135deg, #127464 0%, #0E5A4E 100%);">
                    <div class="rounded-circle bg-white bg-opacity-25 d-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px;">
                        <i class="bx bx-time-five text-white" style="font-size: 2.5rem;"></i>
                    </div>
                    <h4 class="modal-title text-white fw-bold mb-1">Probation Evaluations Due!</h4>
                    <p class="text-white text-opacity-75 small mb-0">Action required for your team members.</p>
                </div>

                <div class="modal-body p-4">
                    <div class="alert alert-warning border-0 bg-label-warning rounded-3 mb-4 d-flex align-items-start">
                        <i class="bx bx-error fs-4 me-2 mt-1"></i>
                        <div class="small">The following employees have completed their probation period. Please submit your evaluation to finalize their employment status.</div>
                    </div>

                    <div class="employee-list custom-scrollbar" style="max-height: 300px; overflow-y: auto;">
                        @foreach($globalPendingProbations as $employee)
                        <div class="d-flex align-items-center p-3 rounded-3 mb-2 border border-light hover-bg-light transition-all">
                            <div class="avatar avatar-md me-3">
                                @if($employee->profile_picture)
                                    <img src="{{ $employee->getProfilePicture() }}" alt="Avatar" class="rounded-circle border">
                                @else
                                    <span class="avatar-initial rounded-circle bg-label-primary">{{ $employee->getInitials() }}</span>
                                @endif
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0 fw-bold">{{ $employee->getFullName() }}</h6>
                                <small class="text-muted d-block">{{ $employee->designation->name ?? 'Employee' }}</small>
                                <span class="badge bg-label-danger rounded-pill mt-1" style="font-size: 0.65rem;">
                                    Ended: {{ \Carbon\Carbon::parse($employee->probation_end_date)->format('d M Y') }}
                                </span>
                            </div>
                            <a href="{{ route('probation.evaluate', $employee->id) }}" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
                                Evaluate <i class="bx bx-right-arrow-alt ms-1"></i>
                            </a>
                        </div>
                        @endforeach
                    </div>
                </div>

                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-label-secondary w-100 rounded-pill py-2" data-bs-dismiss="modal">I'll do it later</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Delay a bit to ensure everything is loaded and wow the user
            setTimeout(() => {
                const myModal = new bootstrap.Modal(document.getElementById('probationAlertModal'));
                myModal.show();
            }, 1000);
        });
    </script>

    <style>
        .hover-bg-light:hover {
            background-color: #f8fafc;
            transform: translateX(5px);
        }
        .transition-all {
            transition: all 0.3s ease;
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 5px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #127464;
            border-radius: 10px;
        }
    </style>
    @endif
@endif
