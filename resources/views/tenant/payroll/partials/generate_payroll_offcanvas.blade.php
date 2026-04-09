<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasGeneratePayroll" aria-labelledby="offcanvasGeneratePayrollLabel">
    <div class="offcanvas-header bg-label-primary py-3 border-bottom shadow-sm">
        <h5 id="offcanvasGeneratePayrollLabel" class="offcanvas-title fw-bold text-primary">Generate Monthly Payroll</h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body mx-0 flex-grow-0 p-5">
        <div class="alert alert-info border-info d-flex align-items-center mb-5" role="alert">
            <i class="bx bx-info-circle me-3 fs-3"></i>
            <div>
                <p class="mb-0 fw-medium">Automatic Processing</p>
                <small class="opacity-75">Payroll will be calculated based on attendance, active loans, and global/user adjustments.</small>
            </div>
        </div>

        <form action="{{ route('payroll.generate') }}" method="POST" id="generatePayrollForm">
            @csrf
            
            <div class="mb-5">
                <label class="form-label-hitech" for="payrollMonth">Select Month</label>
                <select id="payrollMonth" name="month" class="form-select form-select-hitech" required>
                    @for ($i = 1; $i <= 12; $i++)
                        <option value="{{ $i }}" {{ date('n') == $i ? 'selected' : '' }}>
                            {{ date('F', mktime(0, 0, 0, $i, 1)) }}
                        </option>
                    @endfor
                </select>
            </div>

            <div class="mb-5">
                <label class="form-label-hitech" for="payrollYear">Select Year</label>
                <select id="payrollYear" name="year" class="form-select form-select-hitech" required>
                    @for ($i = date('Y') - 1; $i <= date('Y') + 1; $i++)
                        <option value="{{ $i }}" {{ date('Y') == $i ? 'selected' : '' }}>
                            {{ $i }}
                        </option>
                    @endfor
                </select>
            </div>

            <div class="mt-6 pt-2">
                <button type="submit" class="btn btn-hitech w-100 py-3 rounded-pill shadow-lg">
                    <i class="bx bx-rocket me-2 scaleX-n1-rtl"></i> <span>Generate Payroll Records</span>
                </button>
                <button type="button" class="btn btn-label-secondary w-100 mt-3 rounded-pill" data-bs-dismiss="offcanvas">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>
