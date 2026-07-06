<?php

namespace App\Services;

use App\Models\User;
use App\Models\Attendance;
use App\Models\Payslip;
use App\Models\PayrollRecord;
use App\Models\PayrollAdjustment;
use App\Models\LoanRequest;
use App\Models\PayrollCycle;
use App\Models\Settings;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PayrollService
{
    /**
     * Process payroll for all eligible employees for a specific month/year.
     */
    public function processBulk($month, $year, $status = 'pending')
    {
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();
        $periodName = $startDate->format('F Y');
        $daysInMonth = $startDate->daysInMonth;

        // Ensure a Payroll Cycle exists for this period
        $cycle = PayrollCycle::firstOrCreate(
            ['code' => 'CYCLE-' . $startDate->format('Y-m')],
            [
                'name' => $periodName . ' Payroll',
                'frequency' => 'monthly',
                'pay_period_start' => $startDate,
                'pay_period_end' => $endDate,
                'pay_date' => $endDate->copy()->addDays(5), // Default pay date
                'status' => 'pending'
            ]
        );

        $users = User::where('status', 'active')->get();
        $count = 0;

        foreach ($users as $user) {
            // Check if a payroll already exists for this user + period
            $existing = PayrollRecord::where('user_id', $user->id)
                ->where('period', $periodName)
                ->first();

            if ($existing) {
                // Never overwrite an approved or paid payroll — it is locked
                if (in_array($existing->status, ['approved', 'paid'])) {
                    continue;
                }
                // Draft ('generated') → delete old records so we regenerate fresh
                Payslip::where('payroll_record_id', $existing->id)->forceDelete();
                $existing->forceDelete();
            }

            $this->processForUser($user, $startDate, $endDate, $periodName, $daysInMonth, $status, $cycle->id);
            $count++;
        }

        return $count;
    }

    /**
     * Process payroll for a single user.
     */
    public function processForUser($user, $startDate, $endDate, $periodName, $daysInMonth, $status = 'pending', $cycleId = null)
    {
        return DB::transaction(function () use ($user, $startDate, $endDate, $periodName, $daysInMonth, $status, $cycleId) {
            $baseSalary = $user->base_salary ?? 0;
            
            // Auto-offset Sunday working against absent/unpaid leaves in the target month
            try {
                \App\Services\AttendanceAdjustmentService::autoOffsetSundayWorking($user, $startDate->month, $startDate->year);
            } catch (\Exception $e) {
                \Log::error("Failed to run auto-offset for user payroll: " . $e->getMessage());
            }
            
            // If cycleId is not provided, try to find or create one
            if (!$cycleId) {
                $cycle = PayrollCycle::firstOrCreate(
                    ['code' => 'CYCLE-' . $startDate->format('Y-m')],
                    [
                        'name' => $periodName . ' Payroll',
                        'frequency' => 'monthly',
                        'pay_period_start' => $startDate,
                        'pay_period_end' => $endDate,
                        'pay_date' => $endDate->copy()->addDays(5),
                        'status' => 'pending'
                    ]
                );
                $cycleId = $cycle->id;
            }
            
            // 1. Calculate Attendance Stats
            $attendanceData = $this->calculateAttendanceStats($user, $startDate, $endDate);
            $workedDays     = $attendanceData['worked_days'];
            $hasAttendance  = $attendanceData['has_attendance']; // true if ANY record exists
            
            // If NO attendance records exist for this month, employee is assumed to be
            // working the full month (manual/non-biometric staff). Full salary paid.
            if (!$hasAttendance) {
                $paidDays = $daysInMonth; // full month
            } else {
                // We consider Worked Days + Holidays + Weekends as "Paid Days"
                $paidDays = $workedDays + $attendanceData['holidays'] + $attendanceData['weekends'];
                if ($paidDays > $daysInMonth) $paidDays = $daysInMonth;
            }

            // Retrieve Salary Policy
            $policy = $user->salaryPolicy;
            if (!$policy && $user->site_id) {
                $policy = \App\Models\SalaryPolicy::where('site_id', $user->site_id)->first();
            }
            if (!$policy) {
                $policy = \App\Models\SalaryPolicy::first();
            }

            // Month calculation mode & dynamic divisor / paid days
            $mode = $policy ? $policy->month_calculation_mode : 'actual_calendar_days';
            $divisor = $daysInMonth;
            $effectivePaidDays = $paidDays;

            if ($mode === 'fixed_30_days') {
                $divisor = 30;
                $absentDays = $daysInMonth - $paidDays;
                $effectivePaidDays = max(0, 30 - $absentDays);
            }

            // Calculate Full Month values for components
            $fullBasic = $user->custom_basic !== null ? $user->custom_basic : ($policy ? ($baseSalary * ($policy->basic_percentage / 100)) : ($baseSalary * 0.50));
            $fullHra = $user->custom_hra !== null ? $user->custom_hra : ($policy ? ($baseSalary * ($policy->hra_percentage / 100)) : ($baseSalary * 0.25));
            $fullCa = $user->custom_ca !== null ? $user->custom_ca : ($policy ? $policy->ca_fixed : 0.00);
            $fullMedical = $user->custom_medical !== null ? $user->custom_medical : ($policy ? $policy->medical_fixed : 0.00);
            $fullEdu = $user->custom_edu !== null ? $user->custom_edu : ($policy ? $policy->edu_fixed : 0.00);
            
            // Special Allowance is the balancing figure for the full month
            $fullSpecialAllowance = $user->custom_special_allowance !== null ? $user->custom_special_allowance : max(0.00, $baseSalary - ($fullBasic + $fullHra + $fullCa + $fullMedical + $fullEdu));

            // Pro-rata ratio based on attendance
            $ratio = $divisor > 0 ? ($effectivePaidDays / $divisor) : 0;

            // Actual Payable Components
            $payableBasic = round($fullBasic * $ratio, 2);
            $payableHra = round($fullHra * $ratio, 2);
            $payableCa = round($fullCa * $ratio, 2);
            $payableMedical = round($fullMedical * $ratio, 2);
            $payableEdu = round($fullEdu * $ratio, 2);
            $payableSpecialAllowance = round($fullSpecialAllowance * $ratio, 2);

            // Actual Gross Pay from components
            $actualGross = $payableBasic + $payableHra + $payableCa + $payableMedical + $payableEdu + $payableSpecialAllowance;

            // Calculate Professional Tax (PT) - Fixed, not pro-rated
            $ptThreshold = $policy ? $policy->pt_threshold : 20833.00;
            $ptAmount    = $policy ? $policy->pt_amount    : 200.00;
            
            $isPtApplicable = false;
            if ($user->custom_pt !== null) {
                $isPtApplicable = true;
            } elseif ($policy && $policy->is_pt_applicable) {
                $isPtApplicable = true;
            } elseif ($user->site) {
                $siteName = strtolower($user->site->name);
                if (str_contains($siteName, 'ramgarh') || str_contains($siteName, 'rmagarh')) {
                    $isPtApplicable = true;
                }
            }

            $actualPt = 0.00;
            if ($isPtApplicable) {
                if ($user->custom_pt !== null) {
                    // Custom PT is a fixed monthly amount — apply fully only if any salary is paid
                    $actualPt = ($ratio > 0) ? $user->custom_pt : 0.00;
                } else {
                    if ($fullBasic < $ptThreshold) {
                        $actualPt = $ptAmount;
                    }
                }
            }

            // Calculate EPF - custom_epf is the fixed monthly amount from import
            // Policy-based EPF is calculated on payable (pro-rated) basic
            $actualEpf = 0.00;
            if ($user->custom_epf !== null) {
                // Fixed monthly amount from salary import — apply fully only if salary is paid this month
                $actualEpf = ($ratio > 0) ? $user->custom_epf : 0.00;
            } elseif ($policy && $policy->is_epf_applicable) {
                $epfRate   = $policy->epf_rate ?? 12.00;
                $actualEpf = round(($payableBasic * $epfRate) / 100, 2);
            }

            // Calculate ESIC - custom_esic is the fixed monthly amount from import
            $actualEsic = 0.00;
            if ($user->custom_esic !== null) {
                // Fixed monthly amount from salary import — apply fully only if salary is paid
                $actualEsic = ($ratio > 0) ? $user->custom_esic : 0.00;
            } elseif ($policy && $policy->is_esic_applicable) {
                $esicThreshold = $policy->esic_threshold ?? 21000.00;
                $esicRate      = $policy->esic_rate      ?? 0.75;
                if ($actualGross <= $esicThreshold) {
                    $actualEsic = round(($actualGross * $esicRate) / 100, 2);
                }
            }

            // 2. Fetch Adjustments (Benefits & Deductions)
            $adjustments = $this->getAdjustments($user, $baseSalary);
            $totalBenefits = $adjustments['benefits_total'];
            $totalDeductions = $adjustments['deductions_total'];

            // Add Professional Tax, EPF, and ESIC to deductions
            $totalDeductions += $actualPt + $actualEpf + $actualEsic;

            // 3. Auto Loan Deduction
            $loanDeduction = $this->calculateLoanDeduction($user);
            $totalDeductions += $loanDeduction;

            // Final Gross and Net Salary
            $grossSalary = $actualGross + $totalBenefits;
            $netSalary = $grossSalary - $totalDeductions;

            // 4. Create Payroll Record
            $record = PayrollRecord::create([
                'user_id' => $user->id,
                'payroll_cycle_id' => $cycleId,
                'period' => $periodName,
                'basic_salary' => $baseSalary,
                'gross_salary' => $grossSalary,
                'net_salary' => $netSalary,
                'status' => $status,
                'tenant_id' => $user->tenant_id,
                'created_by_id' => auth()->id() ?? $user->id,
                'hra' => $payableHra,
                'ca' => $payableCa,
                'medical' => $payableMedical,
                'edu' => $payableEdu,
                'special_allowance' => $payableSpecialAllowance,
                'pt' => $actualPt,
                'epf' => $actualEpf,
                'esic' => $actualEsic,
                'month_calculation_mode' => $mode,
            ]);

            // 5. Create Payslip
            Payslip::create([
                'user_id' => $user->id,
                'payroll_record_id' => $record->id,
                'code' => 'PS-' . strtoupper(Str::random(10)),
                'total_deductions' => $totalDeductions,
                'total_benefits' => $totalBenefits,
                'status' => $status,
                'total_worked_days' => $workedDays,
                'total_absent_days' => $attendanceData['absents'],
                'total_working_days' => $daysInMonth,
                'total_holidays' => $attendanceData['holidays'],
                'total_weekends' => $attendanceData['weekends'],
                'tenant_id' => $user->tenant_id,
                'created_by_id' => auth()->id() ?? $user->id,
                'notes' => $loanDeduction > 0 ? "Includes Loan Deduction: " . $loanDeduction : null,
                'hra' => $payableHra,
                'ca' => $payableCa,
                'medical' => $payableMedical,
                'edu' => $payableEdu,
                'special_allowance' => $payableSpecialAllowance,
                'pt' => $actualPt,
                'epf' => $actualEpf,
                'esic' => $actualEsic,
                'month_calculation_mode' => $mode,
            ]);

            return $record;
        });
    }

    /**
     * Calculate attendance stats for a user in a date range.
     */
    private function calculateAttendanceStats($user, $startDate, $endDate)
    {
        $attendances = Attendance::where('user_id', $user->id)
            ->whereBetween('check_in_time', [$startDate, $endDate])
            ->with('leaveRequest')
            ->get();

        $hasAttendance = $attendances->count() > 0;
        $workedDays    = 0.0;
        $absents       = 0.0;

        foreach ($attendances as $att) {
            $status = strtolower($att->status ?? '');
            
            // Normalize status based on working hours if it's check_in/check_out/empty
            if (empty($att->admin_reason) && $att->check_in_time && $att->check_out_time) {
                $mins = Carbon::parse($att->check_in_time)->diffInMinutes(Carbon::parse($att->check_out_time));
                $requiredMins = 465;
                if ($att->leaveRequest && $att->leaveRequest->is_short_leave) {
                    $requiredMins -= ($att->leaveRequest->duration_hours * 60);
                }
                if ($mins >= $requiredMins) {
                    $status = 'present';
                } elseif ($mins < $requiredMins && in_array($status, ['present', 'late', 'checked_out', 'checked_in', ''])) {
                    $status = 'half-day';
                }
            } elseif ($status === 'checked_out' && $att->check_in_time && $att->check_out_time) {
                $status = 'present';
            }
            
            if ($status === 'present') {
                if ($att->leaveRequest && $att->leaveRequest->is_half_day) {
                    $workedDays += 0.5;
                    if (!$att->leaveRequest->is_lwp) {
                        $workedDays += 0.5;
                    } else {
                        $absents += 0.5;
                    }
                } else {
                    $workedDays += 1.0;
                }
            } elseif ($status === 'half-day') {
                $workedDays += 0.5;
                $absents += 0.5;
            } elseif ($status === 'leave' || $status === 'paid_leave' || str_contains($status, 'paid')) {
                // If Paid Leave, it counts as worked day equivalent for payroll
                if ($att->leaveRequest && $att->leaveRequest->is_half_day) {
                    $workedDays += 0.5;
                    $absents += 0.5;
                } else {
                    $workedDays += 1.0;
                }
            } elseif ($status === 'unpaid_leave' || str_contains($status, 'unpaid')) {
                if ($att->leaveRequest && $att->leaveRequest->is_half_day) {
                    $absents += 0.5;
                } else {
                    $absents += 1.0;
                }
            } elseif ($status === 'wfh' || str_contains($status, 'work_from_home')) {
                $workedDays += 1.0;
            } elseif ($status === 'absent') {
                $absents += 1.0;
            }
        }

        // Count weekends in the period
        $weekends = 0;
        $holidays = 0;
        $tempDate = clone $startDate;
        
        // Fetch holidays from DB (assuming App\Models\Holiday exists)
        $holidayDates = \App\Models\Holiday::whereBetween('date', [$startDate, $endDate])->pluck('date')->map(function($date) {
            return \Carbon\Carbon::parse($date)->toDateString();
        })->toArray();

        while ($tempDate->lte($endDate)) {
            $dateString = $tempDate->toDateString();
            if (in_array($dateString, $holidayDates)) {
                $holidays++;
            } elseif ($tempDate->isWeekend()) {
                $weekends++;
            }
            $tempDate->addDay();
        }

        $totalDaysInPeriod = $startDate->diffInDays($endDate) + 1;
        $expectedWorkingDays = $totalDaysInPeriod - $holidays - $weekends;
        
        // Absents should be any expected working days that were not worked.
        // But since we already tracked $absents from explicit records (like half days or unpaid leaves),
        // we can just ensure total recorded (worked + absents) matches expected working days.
        $unrecordedDays = $expectedWorkingDays - ($workedDays + $absents);
        
        if ($unrecordedDays > 0) {
            $absents += $unrecordedDays;
        }

        return [
            'worked_days'    => $workedDays,
            'has_attendance' => $hasAttendance,
            'holidays'       => $holidays,
            'weekends'       => $weekends,
            'absents'        => $absents,
        ];
    }

    /**
     * Get benefits and deductions for the user.
     */
    private function getAdjustments($user, $baseSalary)
    {
        $benefitsTotal = 0;
        $deductionsTotal = 0;

        // Fetch Global and User-specific adjustments
        $adjustments = PayrollAdjustment::where(function($q) use ($user) {
            $q->whereNull('user_id')->orWhere('user_id', $user->id);
        })->get();

        foreach ($adjustments as $adj) {
            $amount = $adj->amount;
            if ($adj->percentage > 0) {
                $amount = ($baseSalary * $adj->percentage) / 100;
            }

            if ($adj->type === 'benefit') {
                $benefitsTotal += $amount;
            } else {
                $deductionsTotal += $amount;
            }

            // Consume one-time employee adjustments so they don't apply next month
            if ($adj->user_id !== null && $adj->applicability === 'employee') {
                $adj->delete();
            }
        }

        return [
            'benefits_total' => $benefitsTotal,
            'deductions_total' => $deductionsTotal
        ];
    }

    /**
     * Calculate loan deductions for the user.
     */
    private function calculateLoanDeduction($user)
    {
        // Simple logic: Deduct any approved loan that hasn't been fully paid
        // In a real system, you'd have installments. 
        // For this version, we'll deduct the 'approved_amount' once and mark the model as processed?
        // Actually, let's just look for 'approved' loans.
        
        $loans = LoanRequest::where('user_id', $user->id)
            ->where('status', 'approved')
            ->get();

        $totalLoanDeduction = 0;
        foreach ($loans as $loan) {
            $totalLoanDeduction += $loan->approved_amount;
            
            // Mark loan as 'deducted' or 'closed' so it's not deducted again next month
            // This is a simple implementation.
            $loan->status = 'repaid'; 
            $loan->save();
        }

        return $totalLoanDeduction;
    }
}
