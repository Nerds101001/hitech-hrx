@extends('layouts/layoutMaster')

@section('title', 'My Payroll')

@section('vendor-style')
@vite(['resources/assets/vendor/libs/animate-css/animate.scss'])
@vite(['resources/assets/vendor/scss/pages/hitech-portal.scss'])
<style>
    .payslip-row {
        transition: all 0.3s ease;
    }
    .payslip-row:hover {
        transform: translateX(5px);
        background-color: rgba(var(--bs-primary-rgb), 0.02) !important;
    }
    .btn-hitech-view {
        background: linear-gradient(135deg, #696cff 0%, #3f42ff 100%);
        color: white !important;
        border: none;
        padding: 6px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        box-shadow: 0 4px 12px rgba(105, 108, 255, 0.25);
        transition: all 0.2s ease;
    }
    .btn-hitech-view:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(105, 108, 255, 0.4);
    }
</style>
@endsection

@section('content')

@php
    $latestPayslip = $payslips->first();
    $totalNetPaid = $payslips->where('status', 'paid')->sum('net_salary');
    // Robust SVG for Rupee Symbol
    $rupeeSvg = '<span class="rupee-svg" style="display:inline-flex;align-items:center;line-height:1;"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-currency-rupee" viewBox="0 0 16 16"><path d="M4 3.06h2.726c1.22 0 2.12.575 2.325 1.724H4v1.051h5.051C8.855 7.001 8 7.558 6.788 7.558H4v1.317L8.437 14h2.11L6.095 8.884h.855c2.316-.018 3.465-1.476 3.688-3.049H12V4.784h-1.345c-.08-.778-.357-1.335-.793-1.732H12V2H4v1.06Z"/></svg></span>';
@endphp

<div class="row g-6 px-4">
    
    {{-- HERO SECTION --}}
    <div class="col-12">
        <div class="payroll-hero animate__animated animate__fadeIn">
            <div class="payroll-hero-text">
                <div class="greeting">Payroll & Earnings</div>
                <div class="sub-text">View your salary history and download your official payslips.</div>
            </div>
            <div>
                <div class="text-white text-end">
                    <i class="bx bxs-badge-dollar" style="font-size:3rem; opacity:0.15; position:absolute; top:10px; right:10px;"></i>
                    <div style="font-size:0.75rem; font-weight:700; opacity:0.7; text-transform:uppercase; letter-spacing:0.05em;">Currency</div>
                    <div style="font-size:1.5rem; font-weight:800;">{!! $rupeeSvg !!} ({{ $settings->currency ?? 'INR' }})</div>
                </div>
            </div>
        </div>
    </div>


    {{-- STATS SECTION --}}
    <div class="col-12 mt-4">
        <div class="row g-4">
            <div class="col-sm-6 col-lg-4 animate__animated animate__fadeInUp" style="animation-delay: 0.05s">
                <div class="hitech-stat-card">
                    <div class="stat-icon-wrap icon-teal"><i class="bx bx-star"></i></div>
                    <div class="stat-label">Last Net Salary</div>
                    <div class="stat-value text-heading">{!! $rupeeSvg !!}{{ $latestPayslip ? number_format($latestPayslip->net_salary, 2) : '0.00' }}</div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4 animate__animated animate__fadeInUp" style="animation-delay: 0.1s">
                <div class="hitech-stat-card">
                    <div class="stat-icon-wrap icon-blue"><i class="bx bx-layer"></i></div>
                    <div class="stat-label">Total Earnings (YTD)</div>
                    <div class="stat-value text-heading">{!! $rupeeSvg !!}{{ number_format($totalNetPaid, 2) }}</div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4 animate__animated animate__fadeInUp" style="animation-delay: 0.15s">
                <div class="hitech-stat-card">
                    <div class="stat-icon-wrap icon-amber"><i class="bx bx-file-blank"></i></div>
                    <div class="stat-label">Available Slips</div>
                    <div class="stat-value text-heading">{{ $payslips->count() }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- TABLE SECTION --}}
    <div class="col-12 mt-6">
        <div class="hitech-card animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
            <div class="hitech-card-header border-bottom">
                <h5 class="title mb-0">Payslip History</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive text-nowrap">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Payslip ID</th>
                                <th>Period</th>
                                <th>Net Salary</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($payslips as $payslip)
                            <tr class="payslip-row">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="stat-icon-wrap icon-teal me-3 mb-0" style="width:32px; height:32px; font-size:0.9rem;">
                                            <i class="bx bx-spreadsheet"></i>
                                        </div>
                                        <span class="fw-bold text-heading">{{ $payslip->code }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-heading fw-semibold">
                                        {{ $payslip->created_at->format('F, Y') }}
                                    </div>
                                    <small class="text-muted">Generated on {{ $payslip->created_at->format('d M') }}</small>
                                </td>
                                <td>
                                    <div class="text-success fw-bold fs-5">
                                        {!! $rupeeSvg !!}{{ number_format($payslip->net_salary, 2) }}
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-hitech bg-label-{{ $payslip->status === 'paid' ? 'success' : 'warning' }}">
                                        <i class="bx bxs-circle me-1" style="font-size:0.5rem;"></i>
                                        {{ ucfirst($payslip->status) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <button 
                                            class="btn-hitech-view" 
                                            data-ajax-popup="true" 
                                            data-title="Payslip Details - {{ $payslip->code }}" 
                                            data-url="{{ route('user.payroll.show_ajax', $payslip->id) }}"
                                            data-size="xl"
                                        >
                                            <i class="bx bx-show-alt"></i> View
                                        </button>
                                        <a href="{{ route('user.payroll.download', $payslip->id) }}" class="btn-hitech-sm" target="_blank">
                                            <i class="bx bx-download"></i> PDF
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bx bx-info-circle fs-2 d-block mb-2 opacity-50"></i>
                                    No salary slips found.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

