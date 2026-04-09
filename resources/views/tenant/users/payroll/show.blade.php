@extends('layouts/layoutMaster')

@section('title', 'Payslip Details')

@section('content')
@php
    // DEBUG: Remove after verification
    dd(['user' => $user, 'file' => __FILE__, 'time' => now()->toDateTimeString()]);
@endphp
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="salary-slip-container animate__animated animate__fadeIn" style="max-width: 900px; margin: 0 auto; padding: 40px; background: #fff; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); font-family: 'Inter', sans-serif; color: #1e293b; border: 1px solid #e2e8f0;">
        @php
            $user = $payslip->user;
            $ctcAnnum = $user?->ctc_offered ?? ($payslip->net_salary * 12); // Fallback if CTC not set
            $ctcMonth = $ctcAnnum / 12;
            
            // Breakdown Logic (Matching view.blade.php)
            $basicMonth = $ctcMonth * 0.5;
            $hraMonth = $ctcMonth * 0.25;
            $medicalMonth = 2500;
            $eduMonth = 200;
            $ltaMonth = 2500;
            
            $sumA = $basicMonth + $hraMonth + $medicalMonth + $eduMonth + $ltaMonth;
            $specialAllowance = max(0, $ctcMonth - $sumA);
            
            $profTax = 200;
            $pfAmount = 1800; // Standard PF
            $deductions = $profTax + $pfAmount;
            
            $netSalary = $ctcMonth - $deductions;
            
            // Helper for currency
            $symbol = $settings->currency_symbol;
        @endphp

        <!-- Header -->
        <div class="header" style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #f1f5f9; padding-bottom: 25px; margin-bottom: 30px;">
            <div class="company-logo">
                <h2 style="margin: 0; color: #127464; font-weight: 800; letter-spacing: -1px;">HI TECH HRX</h2>
                <p style="margin: 5px 0 0; font-size: 13px; color: #64748b;">Global Business Park, Tower B, Gurgaon, India</p>
            </div>
            <div class="slip-title text-end">
                <h3 style="margin: 0; font-weight: 700; color: #1e293b;">PAYSLIP</h3>
                <p style="margin: 5px 0 0; font-size: 14px; font-weight: 600; color: #127464; background: #e0f2f1; padding: 4px 12px; border-radius: 50px; display: inline-block;">
                    {{ strtoupper($payslip->created_at->format('F Y')) }}
                </p>
            </div>
        </div>

        <!-- Employee Information Grid -->
        <div class="info-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 40px; margin-bottom: 40px;">
            <table style="width: 100%; font-size: 13px; border-collapse: collapse;">
                <tr style="border-bottom: 1px solid #f8fafc;"><td style="padding: 8px 0; color: #64748b;">Employee Name</td><td style="padding: 8px 0; text-align: right; font-weight: 600;">{{ $user ? strtoupper($user->getFullName()) : 'N/A' }}</td></tr>
                <tr style="border-bottom: 1px solid #f8fafc;"><td style="padding: 8px 0; color: #64748b;">Employee ID</td><td style="padding: 8px 0; text-align: right; font-weight: 600;">{{ $user?->code ?? 'N/A' }}</td></tr>
                <tr style="border-bottom: 1px solid #f8fafc;"><td style="padding: 8px 0; color: #64748b;">Designation</td><td style="padding: 8px 0; text-align: right; font-weight: 600;">{{ $user->designation?->name ?? 'N/A' }}</td></tr>
                <tr style="border-bottom: 1px solid #f8fafc;"><td style="padding: 8px 0; color: #64748b;">Department</td><td style="padding: 8px 0; text-align: right; font-weight: 600;">{{ $user->team?->name ?? 'N/A' }}</td></tr>
            </table>
            <table style="width: 100%; font-size: 13px; border-collapse: collapse;">
                @php $bank = $user->bankAccount ?: $user->bank_account; @endphp
                <tr style="border-bottom: 1px solid #f8fafc;"><td style="padding: 8px 0; color: #64748b;">PAN Number</td><td style="padding: 8px 0; text-align: right; font-weight: 600;">{{ $user?->pan_no ?? 'N/A' }}</td></tr>
                <tr style="border-bottom: 1px solid #f8fafc;"><td style="padding: 8px 0; color: #64748b;">PF Number</td><td style="padding: 8px 0; text-align: right; font-weight: 600;">{{ $user?->pf_no ?? 'N/A' }}</td></tr>
                <tr style="border-bottom: 1px solid #f8fafc;"><td style="padding: 8px 0; color: #64748b;">Bank A/c No</td><td style="padding: 8px 0; text-align: right; font-weight: 600;">{{ $bank?->account_number ?? 'N/A' }}</td></tr>
                <tr style="border-bottom: 1px solid #f8fafc;"><td style="padding: 8px 0; color: #64748b;">Joining Date</td><td style="padding: 8px 0; text-align: right; font-weight: 600;">{{ $user?->joining_date ?? 'N/A' }}</td></tr>
            </table>
        </div>

        <!-- Attendance Info Summary -->
        <div style="background: #f8fafc; border-radius: 12px; padding: 15px 30px; display: flex; justify-content: space-around; margin-bottom: 40px; border: 1px solid #f1f5f9;">
            <div style="text-align: center;"><p style="margin: 0; font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 700;">Working Days</p><h4 style="margin: 5px 0 0; color: #1e293b;">{{ $payslip->total_working_days ?? 30 }}</h4></div>
            <div style="text-align: center;"><p style="margin: 0; font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 700;">Attendance</p><h4 style="margin: 5px 0 0; color: #127464;">{{ $payslip->total_worked_days ?? 30 }}</h4></div>
            <div style="text-align: center;"><p style="margin: 0; font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 700;">LOP Days</p><h4 style="margin: 5px 0 0; color: #e11d48;">{{ $payslip->total_absent_days ?? 0 }}</h4></div>
        </div>

        <!-- Earnings & Deductions Tables -->
        <div class="salary-tables" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
            <!-- Earnings -->
            <div style="border-right: 1px solid #e2e8f0;">
                <div style="background: #f8fafc; padding: 12px 20px; font-weight: 800; font-size: 12px; color: #127464; letter-spacing: 1px; border-bottom: 1px solid #e2e8f0;">EARNINGS</div>
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 12px 20px; color: #1e293b; font-weight: 500;">Basic Salary</td><td style="padding: 12px 20px; text-align: right; font-weight: 700;">{{ number_format($basicMonth, 2) }}</td></tr>
                    <tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 12px 20px; color: #1e293b; font-weight: 500;">HRA</td><td style="padding: 12px 20px; text-align: right; font-weight: 700;">{{ number_format($hraMonth, 2) }}</td></tr>
                    <tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 12px 20px; color: #1e293b; font-weight: 500;">Medical Allowance</td><td style="padding: 12px 20px; text-align: right; font-weight: 700;">{{ number_format($medicalMonth, 2) }}</td></tr>
                    <tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 12px 20px; color: #1e293b; font-weight: 500;">Educational Allowance</td><td style="padding: 12px 20px; text-align: right; font-weight: 700;">{{ number_format($eduMonth, 2) }}</td></tr>
                    <tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 12px 20px; color: #1e293b; font-weight: 500;">Special Allowance</td><td style="padding: 12px 20px; text-align: right; font-weight: 700;">{{ number_format($specialAllowance, 2) }}</td></tr>
                    <tr style="background: #fff;"><td style="padding: 15px 20px; font-weight: 800; color: #1e293b;">Gross Earnings</td><td style="padding: 15px 20px; text-align: right; font-weight: 800; color: #127464;">{{ number_format($ctcMonth, 2) }}</td></tr>
                </table>
            </div>
            <!-- Deductions -->
            <div>
                <div style="background: #f8fafc; padding: 12px 20px; font-weight: 800; font-size: 12px; color: #e11d48; letter-spacing: 1px; border-bottom: 1px solid #e2e8f0;">DEDUCTIONS</div>
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 12px 20px; color: #1e293b; font-weight: 500;">Provident Fund (PF)</td><td style="padding: 12px 20px; text-align: right; font-weight: 700;">{{ number_format($pfAmount, 2) }}</td></tr>
                    <tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 12px 20px; color: #1e293b; font-weight: 500;">Professional Tax (PT)</td><td style="padding: 12px 20px; text-align: right; font-weight: 700;">{{ number_format($profTax, 2) }}</td></tr>
                    <tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 12px 20px; color: #1e293b; font-weight: 500;">Income Tax (TDS)</td><td style="padding: 12px 20px; text-align: right; font-weight: 700;">0.00</td></tr>
                    <tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 12px 20px; color: #1e293b; font-weight: 500;">ESI</td><td style="padding: 12px 20px; text-align: right; font-weight: 700;">0.00</td></tr>
                    <tr style="border-bottom: 1px solid #f1f5f9;"><td style="padding: 12px 20px; color: #1e293b; font-weight: 500;">&nbsp;</td><td style="padding: 12px 20px; text-align: right; font-weight: 700;">&nbsp;</td></tr>
                    <tr style="background: #fff;"><td style="padding: 15px 20px; font-weight: 800; color: #1e293b;">Total Deductions</td><td style="padding: 15px 20px; text-align: right; font-weight: 800; color: #e11d48;">{{ number_format($deductions, 2) }}</td></tr>
                </table>
            </div>
        </div>

        <!-- Final Net Pay Footer -->
        <div class="net-pay" style="margin-top: 40px; display: flex; justify-content: space-between; align-items: center; background: #1e293b; color: #fff; padding: 25px 40px; border-radius: 12px;">
            <div class="words">
                <p style="margin: 0; font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; margin-bottom: 5px;">Payment Method</p>
                <h5 style="margin: 0; font-weight: 600;">Bank Transfer</h5>
            </div>
            <div class="amount text-end">
                <p style="margin: 0; font-size: 11px; text-transform: uppercase; color: #94a3b8; font-weight: 700; margin-bottom: 5px;">Take Home Salary</p>
                <h2 style="margin: 0; color: #4ade80; font-weight: 800;">{{ $symbol }}{{ number_format($netSalary, 2) }}</h2>
            </div>
        </div>

        <!-- Print Actions (Hidden in Print) -->
        <div class="d-print-none text-center mt-5">
            <button onclick="window.print()" class="btn btn-primary btn-lg rounded-pill px-5 me-3"><i class='bx bx-printer me-2'></i> Print Payslip</button>
            <a href="{{ route('user.payroll.index') }}" class="btn btn-outline-secondary btn-lg rounded-pill px-5">Back to List</a>
        </div>

        <!-- Note & Signature -->
        <div style="margin-top: 30px; display: flex; justify-content: space-between; align-items: flex-end;">
            <div style="font-size: 11px; color: #64748b; max-width: 60%;">
                <p style="margin: 0; font-style: italic;">* This is a computer-generated payslip and does not require a physical signature.</p>
            </div>
            <div style="text-align: center; border-top: 1px solid #e2e8f0; padding-top: 10px; width: 200px;">
                <p style="margin: 0; font-size: 12px; font-weight: 700; color: #1e293b;">Authorized Signatory</p>
            </div>
        </div>
    </div>
</div>
@endsection
