<div class="modal fade" id="generatePayrollModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-hitech shadow-lg border-0 overflow-hidden">
            <div class="modal-header modal-header-hitech py-4">
                <div class="d-flex align-items-center">
                    <div class="modal-icon-header me-3 bg-primary bg-opacity-10 text-primary p-2 rounded-3">
                        <i class="bx bx-rocket fs-3"></i>
                    </div>
                    <div>
                        <h5 class="modal-title modal-title-hitech fw-bold mb-0">Generate Payroll</h5>
                        <small class="text-muted">Bulk process salary for all active staff</small>
                    </div>
                </div>
                <button type="button" class="btn-close-hitech shadow-none" data-bs-dismiss="modal" aria-label="Close">
                    <i class="bx bx-x"></i>
                </button>
            </div>
            
            <div class="modal-body p-5">
                <div class="alert alert-soft-primary d-flex align-items-center mb-5 border-0 rounded-4 p-4" style="background: rgba(var(--bs-primary-rgb), 0.08);">
                    <div class="flex-shrink-0 me-3">
                        <i class="bx bx-info-circle fs-2 text-primary"></i>
                    </div>
                    <div>
                        <p class="mb-0 fw-semibold text-primary">Intelligent Automation</p>
                        <p class="mb-0 small text-dark opacity-75">Systems will auto-calculate based on attendance logs, approved leaves, and loan repayments.</p>
                    </div>
                </div>

                <form action="{{ route('payroll.generate') }}" method="POST" id="generatePayrollForm">
                    @csrf
                    
                    <div class="row g-4 mb-5">
                        <div class="col-md-6">
                            <label class="form-label-hitech fs-6 fw-bold mb-2" for="payrollMonth">Select Month</label>
                            <select id="payrollMonth" name="month" class="form-select form-select-hitech py-3 rounded-3" required>
                                @for ($i = 1; $i <= 12; $i++)
                                    <option value="{{ $i }}" {{ date('n') == $i ? 'selected' : '' }}>
                                        {{ date('F', mktime(0, 0, 0, $i, 1)) }}
                                    </option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-hitech fs-6 fw-bold mb-2" for="payrollYear">Select Year</label>
                            <select id="payrollYear" name="year" class="form-select form-select-hitech py-3 rounded-3" required>
                                @for ($i = date('Y') - 1; $i <= date('Y') + 1; $i++)
                                    <option value="{{ $i }}" {{ date('Y') == $i ? 'selected' : '' }}>
                                        {{ $i }}
                                    </option>
                                @endfor
                            </select>
                        </div>
                    </div>

                    <div class="text-center mt-2">
                        <button type="submit" class="btn btn-primary w-100 py-3 rounded-pill shadow-lg fw-bold d-flex align-items-center justify-content-center gap-2">
                            <i class="bx bx-check-circle fs-4"></i> 
                            <span>Initiate Batch Generation</span>
                        </button>
                        <button type="button" class="btn btn-link text-secondary w-100 mt-3 text-decoration-none" data-bs-dismiss="modal">
                            Not now, go back
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
