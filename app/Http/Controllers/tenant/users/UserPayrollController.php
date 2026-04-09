<?php

namespace App\Http\Controllers\tenant\users;

use App\Http\Controllers\Controller;
use App\Models\Payslip;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class UserPayrollController extends Controller
{
    public function index()
    {
        $payslips = Payslip::where('user_id', auth()->id())
            ->whereIn('status', ['approved', 'paid'])
            ->orderBy('created_at', 'desc')
            ->get();

        $settings = \App\Models\Settings::first();

        return view('tenant.users.payroll.index', compact('payslips', 'settings'));
    }

    public function showAjax($id)
    {
        $data = $this->preparePayslipData($id);
        return view('tenant.users.payroll.show_ajax', $data);
    }

    public function download($id)
    {
        try {
            $data = $this->preparePayslipData($id);
            $pdf = Pdf::loadView('payslip.pdf', $data);
            
            $filename = 'payslip-' . str_replace(['/', '\\'], '-', $data['payslip']->code) . '.pdf';
            
            return $pdf->download($filename);
        } catch (\Exception $e) {
            \Log::error('PDF Generation Error: ' . $e->getMessage());
            return back()->with('error', 'Could not generate PDF. Please contact support.');
        }
    }

    private function preparePayslipData($id)
    {
        $payslip = Payslip::with(['user.designation', 'user.team', 'user.bankAccount'])
            ->where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $user = $payslip->user;
        $settings = \App\Models\Settings::first();
        
        // Prepare Logo
        $logoBase64 = '';
        if ($settings && $settings->company_logo) {
            $logoPath = storage_path('app/public/' . $settings->company_logo);
            if (file_exists($logoPath)) {
                $type = pathinfo($logoPath, PATHINFO_EXTENSION);
                $logoData = file_get_contents($logoPath);
                $logoBase64 = 'data:image/' . $type . ';base64,' . base64_encode($logoData);
            }
        }
        
        // If still empty, try fallback to default logo
        if (empty($logoBase64)) {
            $fallbackLogo = public_path('assets/img/branding/logo.png');
            if (file_exists($fallbackLogo)) {
                $type = pathinfo($fallbackLogo, PATHINFO_EXTENSION);
                $logoData = file_get_contents($fallbackLogo);
                $logoBase64 = 'data:image/' . $type . ';base64,' . base64_encode($logoData);
            }
        }

        // Attendance Data
        $totalDays = $payslip->total_working_days ?: 31;
        $workedDays = $payslip->total_worked_days ?: 0;
        $offDays = $payslip->total_weekends ?: 0;
        $holidays = $payslip->total_holidays ?: 0;
        $leaveDays = $payslip->total_leave_days ?: 0;
        
        $totalDaysInMonth = 31; // Fallback or dynamic
        
        // Financials (Dynamic Attendance Based)
        $ctcAnnum = $user?->ctc_offered ?? ($payslip->net_salary * 12);
        $fixedMonthlyCTC = $ctcAnnum / 12;
        
        // Calculate Earned Gross based on Attendance
        $earnedGross = ($fixedMonthlyCTC / $totalDays) * $workedDays;
        
        $symbol = $settings->currency_symbol ?? '₹';

        // Synchronized Breakdown Logic (Compliance Friendly) - Applied to EARNED GROSS
        $basicMonth = $earnedGross * 0.5;
        $hraMonth = $earnedGross * 0.25;
        $medicalMonth = 2500; 
        $eduMonth = 200;
        $ltaMonth = 2500;
        
        $sumA = $basicMonth + $hraMonth + $medicalMonth + $eduMonth + $ltaMonth;
        $specialAllowance = max(0, $earnedGross - $sumA);
        
        $profTax = 200; 
        
        $pfAmount = min($basicMonth * 0.12, 1800); 
        
        $deductions = $profTax + $pfAmount;
        $netSalary = $earnedGross - $deductions;

        return [
            'payslip' => $payslip,
            'user' => $user,
            'settings' => $settings,
            'fixedMonthlyCTC' => $fixedMonthlyCTC,
            'basicMonth' => $basicMonth,
            'hraMonth' => $hraMonth,
            'medicalMonth' => $medicalMonth,
            'eduMonth' => $eduMonth,
            'ltaMonth' => $ltaMonth,
            'specialAllowance' => $specialAllowance,
            'profTax' => $profTax,
            'pfAmount' => $pfAmount,
            'netEarned' => $earnedGross,
            'netSalary' => $netSalary,
            'currencySymbol' => $symbol,
            'attendance' => [
                'worked' => $workedDays,
                'off' => $offDays,
                'holidays' => $holidays,
                'leave' => $leaveDays,
                'total' => $totalDays,
            ],
            'compliance' => [
                'father_name' => $user->father_name ?? 'N/A',
                'pan' => $user->pan_no ?? 'N/A',
                'aadhar' => $user->aadhaar_no ?? 'N/A',
                'pf_no' => $user->pf_no ?? 'N/A',
                'esi_no' => $user->esi_no ?? 'N/A',
                'uan_no' => $user->uan_no ?? 'N/A',
                'bank_ac' => $user->bankAccount->account_number ?? 'N/A',
                'ifsc' => $user->bankAccount->bank_code ?? 'N/A',
                'joining_date' => $user->date_of_joining ? \Carbon\Carbon::parse($user->date_of_joining)->format('d/m/Y') : 'N/A',
                'computer_id' => $user->code ?? 'N/A',
                'dept' => $user->team->name ?? 'N/A',
            ],
            'company' => [
                'name' => $settings->company_name ?? 'HI Tech Group',
                'address' => $settings->company_address ?? 'plot 18 Sector 6 IMT Manesar Gurugram Haryana',
                'phone' => $settings->company_phone ?? '+91-9814215000',
                'email' => $settings->company_email ?? 'info@hitechgroup.in',
                'logoBase64' => $logoBase64
            ]
        ];
    }
}
