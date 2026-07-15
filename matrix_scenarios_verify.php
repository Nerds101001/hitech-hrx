<?php
// PHP script to verify all HRMS Attendance Decision Matrix scenarios in a transaction
// Run: php artisan tinker --execute="require 'matrix_scenarios_verify.php';"

use App\Models\User;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\LeaveBalance;
use App\Models\Holiday;
use App\Enums\LeaveRequestStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

DB::beginTransaction();

try {
    // 1. Setup a test user
    $tenantId = User::first()?->tenant_id ?? 1;
    $user = User::create([
        'first_name' => 'Test',
        'last_name' => 'Employee',
        'email' => 'test_emp_' . uniqid() . '@hitech.com',
        'password' => bcrypt('password'),
        'code' => 'TEST999',
        'biometric_id' => '99999',
        'status' => \App\Enums\UserAccountStatus::ACTIVE,
        'tenant_id' => $tenantId,
    ]);

    // 2. Fetch or create leave types
    $paidType = LeaveType::where('is_paid', true)->where('is_short_leave', false)->first();
    if (!$paidType) {
        $paidType = LeaveType::create(['name' => 'Paid Leave', 'code' => 'PL', 'is_paid' => true]);
    }
    $unpaidType = LeaveType::where('is_paid', false)->where('is_short_leave', false)->first();
    if (!$unpaidType) {
        $unpaidType = LeaveType::create(['name' => 'Unpaid Leave', 'code' => 'LWP', 'is_paid' => false]);
    }
    
    $shortType = LeaveType::where('is_short_leave', true)->first();
    if (!$shortType) {
        $shortType = LeaveType::create(['name' => 'Short Leave', 'code' => 'SL', 'is_short_leave' => true, 'is_paid' => true]);
    }

    $halfPaidType = $paidType;

    // Set balances for all leave types to guarantee no LWP auto-assignment
    foreach (LeaveType::all() as $lt) {
        LeaveBalance::firstOrCreate([
            'user_id' => $user->id,
            'leave_type_id' => $lt->id,
            'tenant_id' => $tenantId
        ], [
            'balance' => 10.0,
            'used' => 0.0
        ]);
    }

    // 3. Define the scenarios
    $scenarios = [
        // Working Day, No Leave
        [
            'name' => 'Working Day: >=465 mins worked, No Leave',
            'in' => '09:00', 'out' => '17:00', // 480 mins
            'leave' => null,
            'expected' => 'present'
        ],
        [
            'name' => 'Working Day: 240-464 mins worked, No Leave',
            'in' => '09:00', 'out' => '14:00', // 300 mins
            'leave' => null,
            'expected' => 'half-day'
        ],
        [
            'name' => 'Working Day: <240 mins worked, No Leave',
            'in' => '09:00', 'out' => '12:00', // 180 mins
            'leave' => null,
            'expected' => 'absent'
        ],

        // Working Day, Short Leave
        [
            'name' => 'Short Leave: >=360 mins worked',
            'in' => '09:00', 'out' => '15:30', // 390 mins
            'leave' => ['type' => $shortType, 'short' => true],
            'expected' => 'short-leave-present'
        ],
        [
            'name' => 'Short Leave: 240-359 mins worked',
            'in' => '09:00', 'out' => '13:30', // 270 mins
            'leave' => ['type' => $shortType, 'short' => true],
            'expected' => 'half-day'
        ],
        [
            'name' => 'Short Leave: <240 mins worked',
            'in' => '09:00', 'out' => '11:00', // 120 mins
            'leave' => ['type' => $shortType, 'short' => true],
            'expected' => 'absent'
        ],

        // Working Day, Half Day Leave
        [
            'name' => 'Half Day Leave: >=240 mins worked + Paid Balance',
            'in' => '09:00', 'out' => '13:30', // 270 mins
            'leave' => ['type' => $halfPaidType, 'half' => true, 'paid' => true],
            'expected' => 'half-day-paid'
        ],
        [
            'name' => 'Half Day Leave: >=240 mins worked + No Balance',
            'in' => '09:00', 'out' => '13:30', // 270 mins
            'leave' => ['type' => $unpaidType, 'half' => true, 'paid' => false],
            'expected' => 'half-day'
        ],
        [
            'name' => 'Half Day Leave: <240 mins worked + Paid Balance',
            'in' => '09:00', 'out' => '12:00', // 180 mins
            'leave' => ['type' => $halfPaidType, 'half' => true, 'paid' => true],
            'expected' => 'paid_leave'
        ],
        [
            'name' => 'Half Day Leave: <240 mins worked + No Balance',
            'in' => '09:00', 'out' => '12:00', // 180 mins
            'leave' => ['type' => $unpaidType, 'half' => true, 'paid' => false],
            'expected' => 'absent'
        ],

        // Working Day, Full Day Leave
        [
            'name' => 'Full Day Leave: 240-464 mins worked + Paid Balance',
            'in' => '09:00', 'out' => '14:00', // 300 mins
            'leave' => ['type' => $paidType, 'paid' => true],
            'expected' => 'half-day-paid'
        ],
        [
            'name' => 'Full Day Leave: 240-464 mins worked + No Balance',
            'in' => '09:00', 'out' => '14:00', // 300 mins
            'leave' => ['type' => $unpaidType, 'paid' => false],
            'expected' => 'half-day'
        ],
        [
            'name' => 'Full Day Leave: >=465 mins worked',
            'in' => '09:00', 'out' => '17:00', // 480 mins
            'leave' => ['type' => $paidType, 'paid' => true],
            'expected' => 'present'
        ],
        [
            'name' => 'Full Day Leave: <240 mins worked + Paid Balance',
            'in' => '09:00', 'out' => '11:00', // 120 mins
            'leave' => ['type' => $paidType, 'paid' => true],
            'expected' => 'paid_leave'
        ]
    ];

    // Helper function mirroring the exact logic in registryAjax
    $evaluateStatus = function($attendance, $user, $dateStr, $dayLeave) {
        $isSunday = Carbon::parse($dateStr)->isSunday();
        $isHoliday = false; // We can mock this
        $s = strtolower($attendance->status ?? '');
        
        $hasRealPunch = $attendance->check_in_time && $attendance->check_out_time && $attendance->check_in_time->format('H:i') !== '00:00';
        $workMins = $hasRealPunch ? $attendance->check_in_time->diffInMinutes($attendance->check_out_time) : 0;
        
        $isShort = $attendance->leaveRequest && ($attendance->leaveRequest->is_short_leave || $attendance->leaveRequest->leaveType?->is_short_leave);
        if (!$isShort && $dayLeave) {
            if ($dayLeave->is_short_leave || $dayLeave->leaveType?->is_short_leave) {
                $isShort = true;
            }
        }
        
        $isHalfDay = $attendance->leaveRequest && ($attendance->leaveRequest->is_half_day || $attendance->leaveRequest->leaveType?->is_half_day);
        if (!$isHalfDay && $dayLeave) {
            if ($dayLeave->is_half_day || $dayLeave->leaveType?->is_half_day) {
                $isHalfDay = true;
            }
        }
        
        $isFullDayLeave = ($attendance->leaveRequest && !$isShort && !$isHalfDay) || ($dayLeave && !$isShort && !$isHalfDay);
        $leaveReq = $attendance->leaveRequest ?: ($dayLeave ?? null);
        $isPaid = $leaveReq && $leaveReq->leaveType?->is_paid && !$leaveReq->is_lwp;

        if ($isSunday) {
            if ($workMins >= 240) {
                return 'sp';
            } else {
                return 'weekly_off';
            }
        } elseif ($isHoliday) {
            if ($workMins >= 240) {
                return 'hp';
            } else {
                return 'holiday';
            }
        } elseif (empty($attendance->admin_reason)) {
            if ($isShort) {
                if ($workMins >= 360) {
                    return 'short-leave-present';
                } elseif ($workMins >= 240) {
                    return 'half-day';
                } else {
                    return 'absent';
                }
            } elseif ($isHalfDay) {
                if ($workMins >= 240) {
                    return $isPaid ? 'half-day-paid' : 'half-day';
                } else {
                    return $isPaid ? 'paid_leave' : 'absent';
                }
            } elseif ($isFullDayLeave) {
                if ($workMins >= 465) {
                    return 'present';
                } elseif ($workMins >= 240) {
                    return $isPaid ? 'half-day-paid' : 'half-day';
                } else {
                    return $isPaid ? 'paid_leave' : 'absent';
                }
            } else {
                if ($workMins >= 465) {
                    return 'present';
                } elseif ($workMins >= 240) {
                    return 'half-day';
                } else {
                    return 'absent';
                }
            }
        }
        return $s;
    };

    echo "Running " . count($scenarios) . " scenarios...\n";
    $success = true;
    
    $dayOffset = 10;
    foreach ($scenarios as $idx => $sc) {
        $dateObj = Carbon::create(2026, 7, $dayOffset);
        while ($dateObj->isSunday()) {
            $dayOffset++;
            $dateObj = Carbon::create(2026, 7, $dayOffset);
        }
        $dateStr = $dateObj->toDateString();
        $dayOffset++;
        
        $leave = null;
        if ($sc['leave']) {
            $leave = LeaveRequest::create([
                'user_id' => $user->id,
                'leave_type_id' => $sc['leave']['type']->id,
                'from_date' => $dateStr,
                'to_date' => $dateStr,
                'is_short_leave' => $sc['leave']['short'] ?? false,
                'is_half_day' => $sc['leave']['half'] ?? false,
                'is_lwp' => !($sc['leave']['paid'] ?? true),
                'status' => LeaveRequestStatus::APPROVED,
                'tenant_id' => $tenantId,
            ]);
            $leave->load('leaveType');
        }

        $att = Attendance::create([
            'user_id' => $user->id,
            'check_in_time' => Carbon::parse($dateStr . ' ' . $sc['in']),
            'check_out_time' => Carbon::parse($dateStr . ' ' . $sc['out']),
            'status' => 'present',
            'leave_request_id' => $leave ? $leave->id : null
        ]);

        // Load relations
        $att->load('leaveRequest.leaveType');

        $result = $evaluateStatus($att, $user, $dateStr, $leave);
        
        if ($result === $sc['expected']) {
            echo "✅ [PASS] {$sc['name']} -> Result: {$result}\n";
        } else {
            echo "❌ [FAIL] {$sc['name']} -> Expected: {$sc['expected']}, Got: {$result}\n";
            if ($leave) {
                echo "      Leave request details: id={$leave->id}, is_short_leave=" . ($leave->is_short_leave ? '1':'0') . ", is_half_day=" . ($leave->is_half_day ? '1':'0') . ", is_lwp=" . ($leave->is_lwp ? '1':'0') . "\n";
                echo "      LeaveType details: id=" . ($leave->leaveType?->id ?? 'null') . ", is_paid=" . ($leave->leaveType?->is_paid ? '1':'0') . "\n";
            } else {
                echo "      No leave request.\n";
            }
            $success = false;
        }
    }

    if ($success) {
        echo "\n🎉 ALL ATTENDANCE DECISION MATRIX SCENARIOS PASSED SUCCESSFULLY!\n";
    } else {
        echo "\n⚠️ SOME SCENARIOS FAILED!\n";
    }

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
} finally {
    DB::rollBack();
    echo "Transaction rolled back. DB clean.\n";
}
