<div class="hitech-modal-payslip hitech-premium-theme animate__animated animate__fadeIn">
    {{-- Header --}}
    <div class="modal-payslip-header mb-4">
        <div class="d-flex justify-content-between align-items-start">
            <div class="brand-side">
                <div class="d-flex align-items-center mb-2">
                    @if(!empty($company['logoBase64']))
                        <img src="{{ $company['logoBase64'] }}" alt="Logo" style="height: 45px;" class="me-3">
                    @endif
                    <div>
                        <h3 class="brand-title mb-0">HI Tech Group</h3>
                        <div class="text-muted extra-small fw-bold letter-spacing-1 text-uppercase">Next-Gen HR Management</div>
                    </div>
                </div>
                <div class="company-address-block">
                    <p class="text-muted small mb-0"><i class="bx bx-map-pin me-1"></i> {{ $company['address'] }}</p>
                    <p class="text-muted extra-small mb-0"><i class="bx bx-envelope me-1"></i> {{ $company['email'] }} | <i class="bx bx-phone me-1"></i> {{ $company['phone'] }}</p>
                </div>
            </div>
            <div class="payslip-id-side text-end">
                <div class="premium-badge mb-2">PAYSLIP {{ $payslip->created_at->format('F Y') }}</div>
                <h5 class="fw-bold text-dark mb-0">#{{ $payslip->code }}</h5>
                <div class="mt-2">
                    <span class="badge bg-label-success rounded-pill px-3">
                        <i class="bx bxs-check-shield me-1"></i> {{ strtoupper($payslip->status) }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Details Section - Premium Cards (Merged with Compliance) --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="info-card p-3 h-100">
                <label class="info-label">EMPLOYEE PROFILE</label>
                <div class="d-flex align-items-center mt-2">
                    <div class="avatar avatar-md me-3">
                        <span class="avatar-initial rounded-circle bg-teal text-white">{{ $user->getInitials() }}</span>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0 text-dark">{{ $user->full_name }}</h6>
                        <div class="text-muted extra-small mt-1">ID: {{ $compliance['computer_id'] }}</div>
                        <div class="text-muted extra-small">Joined: {{ $compliance['joining_date'] }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="info-card p-3 h-100">
                <label class="info-label">IDENTITY & COMPLIANCE</label>
                <div class="mt-2">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="extra-small text-muted">Father's Name:</span>
                        <span class="extra-small fw-bold text-dark">{{ $compliance['father_name'] }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="extra-small text-muted">Aadhaar:</span>
                        <span class="extra-small fw-bold text-dark">{{ $compliance['aadhar'] }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="extra-small text-muted">PAN:</span>
                        <span class="extra-small fw-bold text-dark">{{ $compliance['pan'] }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="info-card p-3 h-100">
                <label class="info-label">BANK & PF DETAILS</label>
                <div class="mt-2 text-nowrap">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="extra-small text-muted">A/C:</span>
                        <span class="extra-small fw-bold text-dark">{{ substr($compliance['bank_ac'], -4) }} (Locked)</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="extra-small text-muted">PF No:</span>
                        <span class="extra-small fw-bold text-dark">{{ $compliance['pf_no'] }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="extra-small text-muted">UAN:</span>
                        <span class="extra-small fw-bold text-dark">{{ $compliance['uan_no'] }} | ESI: {{ $compliance['esi_no'] }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Attendance Bar - Premium Style --}}
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="info-card p-2 px-4 d-flex justify-content-between align-items-center">
                <div class="text-center">
                    <span class="extra-small text-uppercase text-muted d-block">Worked</span>
                    <span class="fw-bold text-dark">{{ $attendance['worked'] }}</span>
                </div>
                <div class="text-center">
                    <span class="extra-small text-uppercase text-muted d-block">Off</span>
                    <span class="fw-bold text-dark">{{ $attendance['off'] }}</span>
                </div>
                <div class="text-center">
                    <span class="extra-small text-uppercase text-muted d-block">Leaves</span>
                    <span class="fw-bold text-dark">{{ $attendance['leave'] }}</span>
                </div>
                <div class="text-center">
                    <span class="extra-small text-uppercase text-muted d-block">Holidays</span>
                    <span class="fw-bold text-dark">{{ $attendance['holidays'] }}</span>
                </div>
                <div class="text-center">
                    <span class="extra-small text-uppercase text-muted d-block">Total Period</span>
                    <span class="fw-bold text-teal">{{ $attendance['total'] }} Days</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Financial Summary Bar --}}
    <div class="premium-summary-bar mb-4">
        <div class="row g-0 align-items-center py-3">
            <div class="col-5 ps-4">
                <span class="summary-label">STANDARD MONTHLY GROSS</span>
                <div class="summary-value">{{ $currencySymbol }}{{ number_format($fixedMonthlyCTC, 2) }}</div>
            </div>
            <div class="col-2 text-center">
                <div class="summary-arrow"><i class="bx bx-right-arrow-alt"></i></div>
            </div>
            <div class="col-5 pe-4 text-end">
                <span class="summary-label">EARNED GROSS INCOME</span>
                <div class="summary-value">{{ $currencySymbol }}{{ number_format($netEarned, 2) }}</div>
            </div>
        </div>
    </div>

    {{-- Detailed Tables --}}
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="hitech-data-card h-100">
                <div class="card-header-premium earnings">
                    <i class="bx bx-trending-up me-2"></i> EARNINGS BREAKDOWN
                </div>
                <div class="card-body-premium">
                    <div class="data-row">
                        <span class="row-label">Basic Salary <small>(50% of Gross)</small></span>
                        <span class="row-value">{{ $currencySymbol }}{{ number_format($basicMonth, 2) }}</span>
                    </div>
                    <div class="data-row">
                        <span class="row-label">HRA <small>(25% of Gross)</small></span>
                        <span class="row-value">{{ $currencySymbol }}{{ number_format($hraMonth, 2) }}</span>
                    </div>
                    <div class="data-row">
                        <span class="row-label">Medical Allowance</span>
                        <span class="row-value">{{ $currencySymbol }}{{ number_format($medicalMonth, 2) }}</span>
                    </div>
                    <div class="data-row">
                        <span class="row-label">LTA & Education</span>
                        <span class="row-value">{{ $currencySymbol }}{{ number_format($ltaMonth + $eduMonth, 2) }}</span>
                    </div>
                    <div class="data-row">
                        <span class="row-label">Special Allowance</span>
                        <span class="row-value">{{ $currencySymbol }}{{ number_format($specialAllowance, 2) }}</span>
                    </div>
                    <div class="data-footer mt-3">
                        <span class="footer-label">GROSS PAYABLE</span>
                        <span class="footer-value text-teal">{{ $currencySymbol }}{{ number_format($netEarned, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="hitech-data-card h-100">
                <div class="card-header-premium deductions">
                    <i class="bx bx-trending-down me-2"></i> DEDUCTIONS
                </div>
                <div class="card-body-premium">
                    <div class="data-row">
                        <span class="row-label">Provident Fund (EPF)</span>
                        <span class="row-value text-danger">-{{ $currencySymbol }}{{ number_format($pfAmount, 2) }}</span>
                    </div>
                    <div class="data-row">
                        <span class="row-label">Professional Tax (PT)</span>
                        <span class="row-value text-danger">-{{ $currencySymbol }}{{ number_format($profTax, 2) }}</span>
                    </div>
                    <div class="data-row opacity-25">
                        <span class="row-label">ESI Contribution</span>
                        <span class="row-value">₹0.00</span>
                    </div>
                    <div class="data-row opacity-25">
                        <span class="row-label">Income Tax / TDS</span>
                        <span class="row-value">₹0.00</span>
                    </div>
                    <div class="data-row">&nbsp;</div>
                    <div class="data-footer mt-3">
                        <span class="footer-label">TOTAL DEDUCTIONS</span>
                        <span class="footer-value text-danger">{{ $currencySymbol }}{{ number_format($profTax + $pfAmount, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Net Salary - Ultimate Highlight --}}
    <div class="hitech-net-footer shadow-lg">
        <div class="d-flex justify-content-between align-items-center px-5 py-4">
            <div>
                <span class="net-label-text">TOTAL NET DISBURSED</span>
                <p class="text-white-50 small mb-0 mt-1">Under Payment of Wages Act. Securely credited to Bank Ac.</p>
            </div>
            <div class="text-end">
                <h1 class="net-amount mb-0">{{ $currencySymbol }}{{ number_format($netSalary, 2) }}</h1>
            </div>
        </div>
    </div>

    {{-- Actions --}}
    <div class="mt-4 d-flex justify-content-center gap-3 no-print">
        <a href="{{ route('user.payroll.download', $payslip->id) }}" class="btn btn-premium-teal px-5 py-2" target="_blank">
            <i class="bx bx-download me-2"></i> DOWNLOAD OFFICIAL PDF
        </a>
        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">CLOSE</button>
    </div>
</div>

<style>
    .hitech-modal-payslip {
        font-family: 'Public Sans', system-ui, -apple-system, sans-serif;
        padding: 10px;
        background: #fff;
    }
    
    .modal-dialog.modal-xl {
        max-width: 1100px !important;
    }

    .brand-title {
        color: #008080;
        font-weight: 800;
        letter-spacing: -0.5px;
    }

    .info-label {
        font-size: 0.65rem;
        font-weight: 700;
        color: #008080;
        letter-spacing: 1px;
        border-bottom: 1px solid #e0f2f2;
        padding-bottom: 4px;
        display: block;
        text-transform: uppercase;
    }

    .info-card {
        background: #f8fdfd;
        border: 1px solid #e0f2f2;
        border-radius: 12px;
    }

    .premium-badge {
        background: #1e1e1a;
        color: #fff;
        font-size: 0.7rem;
        font-weight: 800;
        padding: 4px 12px;
        border-radius: 4px;
        display: inline-block;
        letter-spacing: 2px;
    }

    .premium-summary-bar {
        background: linear-gradient(90deg, #008080 0%, #00a8a8 100%);
        border-radius: 12px;
        color: white;
    }

    .summary-label {
        font-size: 0.65rem;
        font-weight: 600;
        opacity: 0.8;
        letter-spacing: 0.5px;
        display: block;
    }

    .summary-value {
        font-size: 1.5rem;
        font-weight: 800;
    }

    .summary-arrow {
        font-size: 1.5rem;
        opacity: 0.5;
    }

    .hitech-data-card {
        background: #fff;
        border: 1px solid #eee;
        border-radius: 12px;
        overflow: hidden;
    }

    .card-header-premium {
        padding: 10px 15px;
        font-weight: 700;
        font-size: 0.8rem;
        letter-spacing: 1px;
    }

    .card-header-premium.earnings { background: #f0fafa; color: #008080; border-bottom: 2px solid #008080; }
    .card-header-premium.deductions { background: #fff5f5; color: #ff4d49; border-bottom: 2px solid #ff4d49; }

    .card-body-premium { padding: 15px; }

    .data-row {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        border-bottom: 1px solid #f8f8f8;
        font-size: 0.85rem;
    }

    .row-label { color: #666; }
    .row-value { font-weight: 700; color: #333; }

    .data-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 10px;
        border-top: 2px dashed #eee;
    }

    .footer-label { font-size: 0.75rem; font-weight: 800; color: #444; }
    .footer-value { font-size: 1.2rem; font-weight: 800; }

    .hitech-net-footer {
        background: linear-gradient(135deg, #008080 0%, #00cece 100%);
        border-radius: 16px;
        color: white;
        position: relative;
        box-shadow: 0 10px 30px rgba(0, 128, 128, 0.2);
        overflow: hidden;
    }

    .hitech-net-footer::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
        pointer-events: none;
    }

    .net-label-text {
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 2px;
        opacity: 0.8;
    }

    .net-amount {
        font-size: 2.8rem;
        font-weight: 800;
        letter-spacing: -1px;
        color: #ffffff !important;
    }

    .btn-premium-teal {
        background: #008080;
        color: white;
        font-weight: 700;
        border-radius: 8px;
        transition: all 0.3s;
    }
    
    .extra-small { font-size: 0.65rem; }
    
    @media print {
        .no-print { display: none !important; }
    }
</style>
