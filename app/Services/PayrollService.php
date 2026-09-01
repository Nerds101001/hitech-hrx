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
            
            // Clamp the period to the days this person was actually employed.
            // date_of_joining is set for 227 of 231 staff but payroll never read
            // it, so a joiner mid-month — and a leaver — were both treated as
            // present for the whole month.
            $periodStart = $startDate->copy();
            $periodEnd   = $endDate->copy();
            if ($user->date_of_joining) {
                $joined = Carbon::parse($user->date_of_joining);
                if ($joined->gt($periodStart)) $periodStart = $joined->copy();
            }
            $exit = $user->last_working_day ?: $user->exit_date;
            if ($exit) {
                $left = Carbon::parse($exit);
                if ($left->lt($periodEnd)) $periodEnd = $left->copy();
            }
            // Employed for none of this period (joined later, or already left).
            // Compare whole days: the period ends at 23:59:59, so diffInDays on
            // the raw timestamps returns a fraction (1.99…) and the +1 pushed a
            // 2-day joiner to 2.99 paid days.
            $employedDays = $periodStart->gt($periodEnd)
                ? 0
                : (int) $periodStart->copy()->startOfDay()->diffInDays($periodEnd->copy()->startOfDay()) + 1;

            // Not employed for any part of this period — someone who joins next
            // month, or left before it started, has no payroll here at all. They
            // were previously given a record whose only content was the global
            // adjustments, producing a bonus and a tax on a salary of nothing.
            if ($employedDays <= 0) {
                return null;
            }

            // 1. Calculate Attendance Stats
            $attendanceData = $this->calculateAttendanceStats($user, $periodStart, $periodEnd);
            $workedDays     = $attendanceData['worked_days'];
            $hasAttendance  = $attendanceData['has_attendance']; // true if ANY record exists
            
            // If NO attendance records exist for this month, employee is assumed to be
            // working the full month (manual/non-biometric staff). Full salary paid.
            // Pay the full month, less the days actually recorded as unpaid.
            //
            // This used to be worked + holidays + weekends, which made pay depend
            // on attendance records EXISTING. 13% of working days have no record,
            // so people who plainly worked were losing days; and the fallback
            // "no records at all = full month" created a cliff where one stray
            // record cost someone most of their salary. Holidays and weekly offs
            // are paid, and only an explicit absence or unpaid leave reduces pay.
            $paidDays = $employedDays - $attendanceData['unpaid_days'];
            if ($paidDays < 0) $paidDays = 0;
            if ($paidDays > $daysInMonth) $paidDays = $daysInMonth;

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
            $adjustments = $this->getAdjustments($user, $baseSalary, $actualGross);
            $totalBenefits = $adjustments['benefits_total'];
            $totalDeductions = $adjustments['deductions_total'];

            // Add Professional Tax, EPF, and ESIC to deductions
            $totalDeductions += $actualPt + $actualEpf + $actualEsic;

            // 3. Auto Loan Deduction
            $loanDeduction = $this->calculateLoanDeduction($user);
            $totalDeductions += $loanDeduction;

            // Final Gross and Net Salary
            $grossSalary = $actualGross + $totalBenefits;
            $netSalary   = $grossSalary - $totalDeductions;
            // Deductions cannot exceed what is being paid; the shortfall stays
            // visible in the deduction figures rather than turning pay negative.
            if ($netSalary < 0) {
                $netSalary = 0.00;
            }

            // 4. Create Payroll Record
            $record = PayrollRecord::create([
                'user_id' => $user->id,
                'payroll_cycle_id' => $cycleId,
                'period' => $periodName,
                // The pro-rated basic actually being paid. This stored the full
                // monthly base salary, so every payslip showed a basic that did
                // not match its own gross.
                'basic_salary' => $payableBasic,
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
                'employed_days' => $employedDays,
                'paid_days' => $paidDays,
                'total_worked_days' => $workedDays,
                'total_absent_days' => $attendanceData['absents'],
                'total_working_days' => $attendanceData['expected_days'],
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
            ->with('leaveRequest.leaveType')
            ->get();

        $hasAttendance = $attendances->count() > 0;
        $workedDays    = 0.0;
        $absents       = 0.0;

        // Day-status classification now goes through the same
        // AttendanceDayStatus resolver the Monthly Registry, Attendance Logs,
        // and My Attendance pages already share, instead of this method's own
        // flat 465-minute check. That flat check had no idea a worked
        // designated off-Saturday (or Sunday, or holiday) only needs 240
        // minutes to earn full credit - a partial shift on one of those days
        // was silently docked half a day's pay, the same day the attendance
        // pages already correctly showed as fully credited (SP/HP). It also
        // had no holiday-concession awareness (a part-day concession lowers
        // the bar below 465 too), and no idea what a miss-punch even was: a
        // real check-in with no check-out and no admin correction was paid
        // as a full present day, though every attendance page already treats
        // it as absent until HR corrects it. Below applies this service's own
        // pay-consequence rules on top of the resolver's classification,
        // rather than re-deriving the classification itself.
        $holidayMapsByMonth = [];
        foreach ($attendances as $att) {
            if (!$att->check_in_time) {
                continue;
            }
            $date = $att->check_in_time->copy();
            $monthKey = $date->year . '-' . $date->month;
            if (!isset($holidayMapsByMonth[$monthKey])) {
                $holidayMapsByMonth[$monthKey] = AttendanceDayStatus::holidayMapsForMonth($date->month, $date->year);
            }
            $holiday = AttendanceDayStatus::holidayForUserOnDate($holidayMapsByMonth[$monthKey], $user, $user->site_id, $date->toDateString());
            $r = AttendanceDayStatus::resolve($user, $date, $att, null, $holiday);

            switch ($r['code']) {
                case 'sp':
                case 'hp':
                    // A worked day off, credited in full - never a pay
                    // deduction, whether or not it cleared the ordinary
                    // full-day bar. Not part of expectedWorkingDays below
                    // either way, so this only affects worked-days reporting.
                    $workedDays += 1.0;
                    break;
                case 'present':
                case 'short-leave-present':
                case 'checked_out': // manual admin_reason passthrough, preserving the old fallback for this one raw status
                    $workedDays += 1.0;
                    break;
                case 'half-day-paid':
                    // Half worked, half covered by a paid leave the balance
                    // could cover - a full paid day.
                    $workedDays += 1.0;
                    break;
                case 'half-day':
                    $workedDays += 0.5;
                    $absents += 0.5;
                    break;
                case 'work_from_home':
                case 'wfh':
                    // WFH always pays exactly half a day, with no leave-
                    // balance condition at all (owner confirmed 2026-08-13).
                    $workedDays += 0.5;
                    $absents += 0.5;
                    break;
                case 'paid_leave':
                    $workedDays += 1.0;
                    break;
                case 'unpaid_leave':
                    $absents += 1.0;
                    break;
                case 'miss-punch':
                    // Counted absent until HR corrects the punch with
                    // remarks - the same rule the attendance pages already
                    // enforce. The resolver only reaches this code when no
                    // leave is linked at all (any linked leave routes
                    // through its own branch instead), so this always takes
                    // the unexplained-absence rate below, same as 'absent'.
                case 'absent':
                    // No leave applied for this day: it costs 1.5 days of
                    // salary, not one. Apply a back-dated leave and get it
                    // approved before payroll runs for the month, and the
                    // approval links this day to a leave record so the
                    // resolver scores it under paid_leave/unpaid_leave
                    // instead - the penalty only applies with no leave at all.
                    $absents += $r['leaveRequest'] ? 1.0 : 1.5;
                    break;
                case 'weekly_off':
                case 'holiday':
                case 'scheduled':
                case 'today':
                    // An ordinary non-working day (or one that hasn't
                    // happened yet) - already excluded from
                    // expectedWorkingDays below, so no consequence here.
                    break;
                default:
                    // A manually-set raw status this resolver doesn't
                    // recognise - leave both counters untouched, same as
                    // before: it falls into unrecorded_days, reporting-only
                    // and never deducted.
                    break;
            }
        }

        // Count weekends in the period
        $weekends = 0;
        $holidays = 0;
        $tempDate = clone $startDate;
        
        // Holidays that apply to THIS employee's location (plus company-wide
        // ones). Unscoped, a Punjab-only holiday was counted as a paid holiday
        // for every employee in the company, inflating their paid days.
        $holidayDates = array_keys(\App\Models\Holiday::mapForUser($user, $startDate, $endDate));

        while ($tempDate->lte($endDate)) {
            $dateString = $tempDate->toDateString();
            if (in_array($dateString, $holidayDates)) {
                $holidays++;
            } elseif (!\App\Services\LeavePolicyService::isWorkingDay($user, $tempDate->copy())) {
                // Carbon's isWeekend() was used here, treating Saturday as off for
                // everyone. Most staff work every Saturday, and those who don't
                // follow per-profile rules (alternate / all Saturdays). The leave
                // module, registry and dashboards already use isWorkingDay(); this
                // brings payroll onto the same definition.
                $weekends++;
            }
            $tempDate->addDay();
        }

        // Whole days only — diffInDays on timestamps ending at 23:59:59 returns
        // a fraction, which leaked into the reported expected-days figure.
        $totalDaysInPeriod = (int) $startDate->copy()->startOfDay()
            ->diffInDays($endDate->copy()->startOfDay()) + 1;
        $expectedWorkingDays = $totalDaysInPeriod - $holidays - $weekends;

        // A missing attendance record is a gap in the data, not proof of absence,
        // so it no longer becomes one. Previously every unrecorded working day was
        // added to $absents, which is why staff with sparse biometric data looked
        // absent for most of the month. $absents now means what it says: days
        // explicitly recorded as absent or unpaid leave.
        $unrecordedDays = max(0, $expectedWorkingDays - ($workedDays + $absents));

        return [
            'worked_days'     => $workedDays,
            'has_attendance'  => $hasAttendance,
            'holidays'        => $holidays,
            'weekends'        => $weekends,
            'absents'         => $absents,
            'unpaid_days'     => $absents,   // what actually reduces pay
            'expected_days'   => $expectedWorkingDays,
            'unrecorded_days' => $unrecordedDays,   // reporting only — never deducted
        ];
    }

    /**
     * Get benefits and deductions for the user.
     */
    private function getAdjustments($user, $baseSalary, $payableGross = null)
    {
        // A percentage adjustment used to be taken on the FULL monthly salary
        // even when only part of the month was payable, so a 10% deduction on a
        // 2-day salary took 21,000 out of 14,100 and produced a negative net.
        // Percentages now follow what is actually being paid.
        $percentBase = $payableGross !== null ? $payableGross : $baseSalary;
        $benefitsTotal = 0;
        $deductionsTotal = 0;

        // Fetch Global and User-specific adjustments
        $adjustments = PayrollAdjustment::where(function($q) use ($user) {
            $q->whereNull('user_id')->orWhere('user_id', $user->id);
        })->get();

        foreach ($adjustments as $adj) {
            $amount = $adj->amount;
            if ($adj->percentage > 0) {
                $amount = ($percentBase * $adj->percentage) / 100;
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
