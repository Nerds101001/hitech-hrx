<?php

namespace App\Services;

use App\Models\User;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Enums\LeaveRequestStatus;
use Carbon\Carbon;

class AttendanceAdjustmentService
{
    /**
     * Automatically offsets absent/unpaid leave days in a month with worked Sundays.
     *
     * @param User $user
     * @param int $month
     * @param int $year
     * @return void
     */
    public static function autoOffsetSundayWorking(User $user, $month, $year)
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();
        
        $today = Carbon::today();
        $limitDate = $endDate->gt($today) ? $today : $endDate;

        // 1. Get all attendance records in the month
        $attendances = Attendance::where('user_id', $user->id)
            ->where(function($q) use ($startDate, $endDate) {
                $q->whereBetween('check_in_time', [$startDate, $endDate])
                  ->orWhereBetween('created_at', [$startDate, $endDate]);
            })
            ->get();

        // 2. Find Worked Sundays (not yet used to offset)
        $workedSundays = [];
        foreach ($attendances as $att) {
            $checkIn = $att->check_in_time ? Carbon::parse($att->check_in_time) : null;
            if (!$checkIn || !$checkIn->isSunday()) {
                continue;
            }

            // Check if it's worked: either status is 'Sunday working', or check_out exists and hours >= 4
            $isWorked = false;
            $status = strtolower($att->status ?? '');
            if ($status === 'sunday working' || $status === 'sunday_working') {
                $isWorked = true;
            } elseif ($att->check_out_time) {
                $checkOut = Carbon::parse($att->check_out_time);
                $mins = $checkIn->diffInMinutes($checkOut);
                if ($mins >= 240) { // 4 hours
                    $isWorked = true;
                }
            }

            // Check if already used
            $alreadyUsed = $att->admin_reason && str_contains($att->admin_reason, 'Used to offset absence');

            if ($isWorked && !$alreadyUsed) {
                $workedSundays[] = $att;
            }
        }

        if (empty($workedSundays)) {
            return;
        }

        // 3. Get all approved leaves in the month
        $leaves = LeaveRequest::where('user_id', $user->id)
            ->where('status', LeaveRequestStatus::APPROVED)
            ->where(function($q) use ($startDate, $endDate) {
                $q->whereBetween('from_date', [$startDate, $endDate])
                  ->orWhereBetween('to_date', [$startDate, $endDate])
                  ->orWhere(function($q2) use ($startDate, $endDate) {
                      $q2->where('from_date', '<=', $startDate)
                         ->where('to_date', '>=', $endDate);
                  });
            })
            ->with('leaveType')
            ->get();

        // 4. Find all eligible absent or unpaid leave days
        $eligibleDays = [];
        
        // Loop through each day up to the limit date
        for ($date = $startDate->copy(); $date->lte($limitDate); $date->addDay()) {
            $dateStr = $date->toDateString();
            
            // Check if it is Sunday (handled as worker)
            if ($date->isSunday()) {
                continue;
            }

            // Must be a working day
            if (!LeavePolicyService::isWorkingDay($user, $date)) {
                continue;
            }

            // Find attendance record for this day
            $dayAtt = $attendances->first(function($a) use ($dateStr) {
                return ($a->check_in_time && Carbon::parse($a->check_in_time)->toDateString() === $dateStr) ||
                       (!$a->check_in_time && Carbon::parse($a->created_at)->toDateString() === $dateStr);
            });

            if ($dayAtt) {
                $status = strtolower($dayAtt->status ?? '');
                $isAbsentOrUnpaid = in_array($status, ['absent', 'unpaid_leave', 'unpaid-leave', 'unpaid']);
                $alreadyAdjusted = $dayAtt->admin_reason && str_contains($dayAtt->admin_reason, 'Auto-adjusted against Sunday Working');

                if ($isAbsentOrUnpaid && !$alreadyAdjusted) {
                    $eligibleDays[] = [
                        'date' => $dateStr,
                        'attendance' => $dayAtt
                    ];
                }
            } else {
                // No attendance record. Check leave
                $leaveOnDate = $leaves->first(function($l) use ($dateStr) {
                    return Carbon::parse($l->from_date)->toDateString() <= $dateStr &&
                           Carbon::parse($l->to_date)->toDateString() >= $dateStr;
                });

                if ($leaveOnDate) {
                    if ($leaveOnDate->leaveType && !$leaveOnDate->leaveType->is_paid) {
                        $eligibleDays[] = [
                            'date' => $dateStr,
                            'attendance' => null
                        ];
                    }
                } else {
                    // No attendance, no leave: it's a dynamic absent day
                    $eligibleDays[] = [
                        'date' => $dateStr,
                        'attendance' => null
                    ];
                }
            }
        }

        if (empty($eligibleDays)) {
            return;
        }

        // 5. Retrieve Comp-off (ALP/PL) leave type
        $compOffType = LeaveType::where('code', 'ALP')
            ->orWhere('name', 'like', '%All Purpose%')
            ->orWhere('code', 'PL')
            ->first();

        // 6. Perform the pairing/offset
        $offsetCount = min(count($workedSundays), count($eligibleDays));
        
        for ($i = 0; $i < $offsetCount; $i++) {
            $sundayAtt = $workedSundays[$i];
            $sundayDate = Carbon::parse($sundayAtt->check_in_time ?: $sundayAtt->created_at);
            
            $target = $eligibleDays[$i];
            $targetDateStr = $target['date'];
            $targetAtt = $target['attendance'];

            // A. Update or create the attendance record for the absent day to make it Paid Leave
            $checkIn = Carbon::parse($targetDateStr)->setTime(9, 30);
            $checkOut = Carbon::parse($targetDateStr)->setTime(18, 30);

            if ($targetAtt) {
                $targetAtt->status = 'paid_leave';
                $targetAtt->admin_reason = "Auto-adjusted against Sunday Working on " . $sundayDate->format('d-m-Y');
                $targetAtt->save();
            } else {
                Attendance::create([
                    'user_id' => $user->id,
                    'tenant_id' => $user->tenant_id,
                    'shift_id' => $user->shift_id,
                    'check_in_time' => $checkIn,
                    'check_out_time' => $checkOut,
                    'status' => 'paid_leave',
                    'admin_reason' => "Auto-adjusted against Sunday Working on " . $sundayDate->format('d-m-Y'),
                    'created_by_id' => $user->id
                ]);
            }

            // B. Increment the used balance for ALP/PL leave type to consume the credit
            if ($compOffType) {
                $balance = LeaveBalance::firstOrCreate(
                    ['user_id' => $user->id, 'leave_type_id' => $compOffType->id],
                    ['tenant_id' => $user->tenant_id, 'balance' => 0, 'used' => 0, 'accrued_this_year' => 0]
                );
                
                LeaveBalance::$auditReason = "Auto-adjusted against Earned Leave (Sunday)";
                $balance->increment('used', 1);
            }

            // C. Mark the worked Sunday record as used for offset
            $sundayAtt->admin_reason = "Used to offset absence on " . Carbon::parse($targetDateStr)->format('d-m-Y');
            $sundayAtt->save();
        }
    }
}
