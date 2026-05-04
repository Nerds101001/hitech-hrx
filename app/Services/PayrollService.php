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
            // Check if already processed
            $exists = PayrollRecord::where('user_id', $user->id)
                ->where('period', $periodName)
                ->exists();

            if ($exists) continue;

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
            
            // 1. Calculate Attendance Pro-rata
            $attendanceData = $this->calculateAttendanceStats($user, $startDate, $endDate);
            $workedDays = $attendanceData['worked_days'];
            
            // Salary calculation: (Base Salary / Total Days) * Worked Days
            // We'll consider Worked Days + Holidays + Weekends as "Paid Days"
            $paidDays = $workedDays + $attendanceData['holidays'] + $attendanceData['weekends'];
            if ($paidDays > $daysInMonth) $paidDays = $daysInMonth;
            
            $proRataSalary = ($daysInMonth > 0) ? ($baseSalary / $daysInMonth) * $paidDays : 0;

            // 2. Fetch Adjustments (Benefits & Deductions)
            $adjustments = $this->getAdjustments($user, $baseSalary);
            $totalBenefits = $adjustments['benefits_total'];
            $totalDeductions = $adjustments['deductions_total'];

            // 3. Auto Loan Deduction
            $loanDeduction = $this->calculateLoanDeduction($user);
            $totalDeductions += $loanDeduction;

            $grossSalary = $proRataSalary + $totalBenefits;
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
            ]);

            // 5. Create Payslip
            Payslip::create([
                'user_id' => $user->id,
                'payroll_record_id' => $record->id,
                'code' => 'PS-' . strtoupper(Str::random(10)),
                'basic_salary' => $proRataSalary, // Store pro-rata as the effective basic for that month
                'total_deductions' => $totalDeductions,
                'total_benefits' => $totalBenefits,
                'net_salary' => $netSalary,
                'status' => $status,
                'total_worked_days' => $workedDays,
                'total_working_days' => $daysInMonth,
                'total_holidays' => $attendanceData['holidays'],
                'total_weekends' => $attendanceData['weekends'],
                'tenant_id' => $user->tenant_id,
                'created_by_id' => auth()->id() ?? $user->id,
                'notes' => $loanDeduction > 0 ? "Includes Loan Deduction: " . $loanDeduction : null,
            ]);

            return $record;
        });
    }

    /**
     * Calculate attendance stats for a user in a date range.
     */
    private function calculateAttendanceStats($user, $startDate, $endDate)
    {
        $workedDays = Attendance::where('user_id', $user->id)
            ->whereBetween('check_in_time', [$startDate, $endDate])
            ->whereIn('status', ['present', 'paid_leave'])
            ->count();

        // For now, simplify Holidays and Weekends
        // In a real system, you'd check the Holiday table and user's Shift schedule
        // Here we'll default to a standard 5-day week for calculation if no shift data is complex
        $weekends = 0;
        $tempDate = clone $startDate;
        while ($tempDate->lte($endDate)) {
            if ($tempDate->isWeekend()) {
                $weekends++;
            }
            $tempDate->addDay();
        }

        return [
            'worked_days' => $workedDays,
            'holidays' => 0, // Placeholder
            'weekends' => $weekends
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
